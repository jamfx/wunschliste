<?php
declare(strict_types=1);

use lbuchs\WebAuthn\WebAuthn;

class AdminController
{
    // ==================================================================
    // Private helpers
    // ==================================================================

    private static function b64urlDecode(string $data): string
    {
        $data = strtr($data, '-_', '+/');
        return base64_decode($data . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }

    private function checkAuth(): void
    {
        if (empty($_SESSION['admin_logged_in'])) {
            redirect(base_url('admin/login'));
        }
        // 30-minute inactivity timeout
        if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity']) > 1800) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
            redirect(base_url('admin/login'));
        }
        $_SESSION['admin_last_activity'] = time();
    }

    private function getAdminRow(): array
    {
        global $db;
        $admin = $db->fetch('SELECT * FROM admin LIMIT 1');
        if (!$admin) {
            abort(500);
        }
        return $admin;
    }

    private function getSiteSettings(): array
    {
        global $db;
        $rows = $db->fetchAll('SELECT setting_key, setting_value FROM site_settings ORDER BY id ASC');
        $s    = [];
        foreach ($rows as $row) {
            $s[$row['setting_key']] = $row['setting_value'];
        }
        return $s;
    }

    private function saveSiteSetting(string $key, string $value): void
    {
        global $db;
        $db->query('DELETE FROM site_settings WHERE setting_key = ?', [$key]);
        $db->insert('site_settings', ['setting_key' => $key, 'setting_value' => $value]);
    }

    /**
     * Shared cleanup routine: deletes expired events and stale rate_limit rows.
     * An event's expiry (expires_at) already bakes in its own retention period
     * (auto_delete_days, chosen per event at creation/edit time), so no extra
     * global delay is applied here.
     * Used by the dashboard button, the manual "run now" action and the
     * token-based web-cron endpoint (see cronCleanup()).
     */
    private function performCleanup(): array
    {
        global $db;
        $deleted = ['events' => 0, 'rate_limit' => 0];

        $stmt = $db->getPdo()->prepare(
            'DELETE FROM events WHERE expires_at IS NOT NULL AND expires_at < ?'
        );
        $stmt->execute([date('Y-m-d H:i:s')]);
        $deleted['events'] = $stmt->rowCount();

        $cutoff = date('Y-m-d H:i:s', time() - 86400);
        $stmt   = $db->getPdo()->prepare('DELETE FROM rate_limit WHERE created_at < ?');
        $stmt->execute([$cutoff]);
        $deleted['rate_limit'] = $stmt->rowCount();

        // Also process any claims whose reconsideration window has elapsed, so
        // a real crontab entry delivers creator notifications reliably even
        // without page traffic (see bootstrap.php's per-request call for the
        // common case).
        process_due_claim_notifications();

        return $deleted;
    }

    /** Returns true and removes the used code from the DB if valid. */
    private function verifyRecoveryCode(array $admin, string $input): bool
    {
        global $db;
        $codes = json_decode($admin['recovery_codes'] ?? '[]', true);
        if (!is_array($codes) || empty($codes)) {
            return false;
        }
        $input = strtoupper(trim($input));
        foreach ($codes as $idx => $hash) {
            if (password_verify($input, $hash)) {
                unset($codes[$idx]);
                $db->update('admin',
                    ['recovery_codes' => json_encode(array_values($codes))],
                    ['id' => $admin['id']]
                );
                return true;
            }
        }
        return false;
    }

    // ==================================================================
    // Auth — login / TOTP / logout
    // ==================================================================

    /** GET /admin → redirect to login */
    public function adminRedirect(array $params = []): void
    {
        redirect(base_url('admin/login'));
    }

    /** GET /admin/login */
    public function loginForm(array $params = []): void
    {
        if (!empty($_SESSION['admin_logged_in'])) {
            redirect(base_url('admin/dashboard'));
        }
        render('admin/login', ['pageTitle' => __('admin.login_title')]);
    }

    /** POST /admin/login */
    public function login(array $params = []): void
    {
        if (!csrf_verify()) {
            abort(403);
        }

        // Brute-force: IP-based rate limiting (configurable in admin security settings)
        $s            = $this->getSiteSettings();
        $maxAttempts  = max(1, (int) ($s['rate_login_max_attempts']  ?? 5));
        $windowSecs   = max(60, (int) ($s['rate_login_window_minutes'] ?? 15) * 60);

        if (!rate_limit_check('admin_login', $maxAttempts, $windowSecs)) {
            flash('error', __('admin.brute_force', ['minutes' => max(1, (int) ceil($windowSecs / 60))]));
            render('admin/login', ['pageTitle' => __('admin.login_title')]);
            return;
        }

        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';

        global $db;
        $admin = $db->fetch('SELECT * FROM admin WHERE email = ?', [$email]);

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            rate_limit_record('admin_login');
            flash('error', __('admin.login_error'));
            render('admin/login', ['pageTitle' => __('admin.login_title')]);
            return;
        }

        $hasPasskeys = false;
        try {
            $pkStmt = $db->getPdo()->prepare('SELECT COUNT(*) FROM admin_passkeys WHERE admin_id = ?');
            $pkStmt->execute([(int) $admin['id']]);
            $hasPasskeys = (int) $pkStmt->fetchColumn() > 0;
        } catch (Throwable) {}

        if ($admin['totp_enabled'] || $hasPasskeys) {
            $_SESSION['admin_totp_pending'] = true;
            $_SESSION['admin_id_pending']   = $admin['id'];
            session_regenerate_id(true);
            redirect(base_url('admin/totp'));
        }

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']        = $admin['id'];
        session_regenerate_id(true);

        // Remove installer on first login (belt-and-suspenders in case unlink inside install.php failed)
        foreach ([BASE_DIR . '/install.php', BASE_DIR . '/install.php.done'] as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }

        redirect(base_url('admin/dashboard'));
    }

    /** GET /admin/totp */
    public function totpForm(array $params = []): void
    {
        if (empty($_SESSION['admin_totp_pending'])) {
            redirect(base_url('admin/login'));
        }

        $adminId     = (int) ($_SESSION['admin_id_pending'] ?? 0);
        $hasTOTP     = false;
        $hasPasskeys = false;
        $hasRecovery = false;

        global $db;
        if ($adminId) {
            $adminRow = $db->fetch('SELECT totp_enabled, recovery_codes FROM admin WHERE id = ? LIMIT 1', [$adminId]);
            if ($adminRow) {
                $hasTOTP     = !empty($adminRow['totp_enabled']);
                $hasRecovery = !empty($adminRow['recovery_codes']);
            }
            try {
                $pkStmt = $db->getPdo()->prepare('SELECT COUNT(*) FROM admin_passkeys WHERE admin_id = ?');
                $pkStmt->execute([$adminId]);
                $hasPasskeys = (int) $pkStmt->fetchColumn() > 0;
            } catch (Throwable) {}
        }

        render('admin/totp_verify', [
            'pageTitle'   => __('admin.totp_title'),
            'hasTOTP'     => $hasTOTP,
            'hasPasskeys' => $hasPasskeys,
            'hasRecovery' => $hasRecovery,
        ]);
    }

    /** POST /admin/totp */
    public function totpVerify(array $params = []): void
    {
        if (empty($_SESSION['admin_totp_pending'])) {
            redirect(base_url('admin/login'));
        }
        if (!csrf_verify()) {
            abort(403);
        }

        $code  = trim($_POST['totp_code'] ?? '');
        global $db;
        $admin = $db->fetch('SELECT * FROM admin WHERE id = ?',
            [$_SESSION['admin_id_pending'] ?? 0]);

        if (!$admin) {
            session_destroy();
            redirect(base_url('admin/login'));
        }

        $_SESSION['totp_attempts'] = ($_SESSION['totp_attempts'] ?? 0) + 1;

        $totpValid = Totp::verify($admin['totp_secret'], $code);

        // TOTP replay protection: reject codes from already-used time steps
        if ($totpValid) {
            $currentStep = (string) ((int) floor(time() / 30));
            $lastStep    = $this->getSiteSettings()['totp_last_used_step'] ?? '';
            if ($currentStep === $lastStep) {
                $totpValid = false;
            } else {
                $this->saveSiteSetting('totp_last_used_step', $currentStep);
            }
        }

        $valid = $totpValid || $this->verifyRecoveryCode($admin, $code);

        if (!$valid) {
            if ($_SESSION['totp_attempts'] >= 3) {
                session_destroy();
                redirect(base_url('admin/login'));
            }
            flash('error', __('admin.totp_error'));
            render('admin/totp_verify', ['pageTitle' => __('admin.totp_title')]);
            return;
        }

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']        = $admin['id'];
        unset(
            $_SESSION['admin_totp_pending'],
            $_SESSION['admin_id_pending'],
            $_SESSION['totp_attempts']
        );
        session_regenerate_id(true);
        redirect(base_url('admin/dashboard'));
    }

    /** POST /admin/logout */
    public function logout(array $params = []): void
    {
        if (csrf_verify()) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
        }
        redirect(base_url('admin/login'));
    }

    // ==================================================================
    // Dashboard
    // ==================================================================

    /** GET /admin/dashboard */
    public function dashboard(array $params = []): void
    {
        $this->checkAuth();
        global $db;

        // Weighted by quantity, not by item count: an item needing 20 and
        // having 5 claimed should move the bar, not wait until all 20 are
        // gone (see GuestController::view for the same calculation).
        $events = $db->fetchAll(
            'SELECT e.*,
                (SELECT COALESCE(SUM(quantity), 0) FROM items
                 WHERE event_id = e.id AND status = "approved") AS item_count,
                (SELECT COALESCE(SUM(
                    LEAST(i2.quantity, (SELECT COALESCE(SUM(c.quantity), 0)
                                        FROM claims c WHERE c.item_id = i2.id))
                 ), 0)
                 FROM items i2
                 WHERE i2.event_id = e.id AND i2.status = "approved"
                ) AS claimed_count
             FROM events e
             ORDER BY e.created_at DESC'
        );

        $now = time();
        foreach ($events as &$event) {
            $event['is_expired']   = $event['expires_at'] !== null
                                   && strtotime($event['expires_at']) < $now;
            $event['progress_pct'] = $event['item_count'] > 0
                ? round(($event['claimed_count'] / $event['item_count']) * 100)
                : 0;
        }
        unset($event);

        $totalEvents   = count($events);
        $expiredEvents = count(array_filter($events, fn($e) => $e['is_expired']));
        $activeEvents  = $totalEvents - $expiredEvents;

        render('admin/dashboard', [
            'pageTitle'        => __('admin.dashboard_title'),
            'currentAdminPage' => 'dashboard',
            'containerClass'   => 'container--wide',
            'events'           => $events,
            'totalEvents'      => $totalEvents,
            'activeEvents'     => $activeEvents,
            'expiredEvents'    => $expiredEvents,
        ]);
    }

    /** POST /admin/event/{id}/delete */
    public function deleteEvent(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }
        global $db;
        $id    = (int) ($params['id'] ?? 0);
        $event = $db->fetch('SELECT id, title FROM events WHERE id = ?', [$id]);
        if (!$event) {
            flash('error', 'Event nicht gefunden.');
            redirect(base_url('admin/dashboard'));
        }
        $db->delete('events', ['id' => $id]);
        flash('success', 'Event "' . $event['title'] . '" wurde gelöscht.');
        redirect(base_url('admin/dashboard'));
    }

    /** POST /admin/cleanup */
    public function cleanup(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }
        $deleted = $this->performCleanup();
        flash('success', $deleted['events'] . ' abgelaufene Event(s) und ' . $deleted['rate_limit'] . ' Rate-Limit-Eintrag/Einträge gelöscht.');
        redirect(base_url('admin/dashboard'));
    }

    // ==================================================================
    // Settings overview
    // ==================================================================

    /** GET /admin/settings */
    public function settings(array $params = []): void
    {
        $this->checkAuth();
        render('admin/settings', [
            'pageTitle'        => __('admin.settings_title'),
            'currentAdminPage' => 'settings',
            'containerClass'   => 'container--wide',
        ]);
    }

    // ==================================================================
    // Design
    // ==================================================================

    /** GET /admin/settings/design */
    public function designSettings(array $params = []): void
    {
        $this->checkAuth();
        $s = $this->getSiteSettings();
        render('admin/design_settings', [
            'pageTitle'          => __('admin.settings_design'),
            'currentAdminPage'   => 'design',
            'containerClass'     => 'container--wide',
            's'                  => $s,
            'availableLanguages' => available_languages(),
        ]);
    }

    /** POST /admin/settings/design */
    public function saveDesignSettings(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        $color           = trim($_POST['primary_color']   ?? '');
        $siteTitle       = trim($_POST['site_title']       ?? '');
        $imprint         = trim($_POST['legal_imprint']    ?? '');
        $privacy         = trim($_POST['legal_privacy']    ?? '');
        $defaultLanguage = trim($_POST['default_language'] ?? '');
        $licenseUrl      = trim($_POST['license_url']      ?? '');

        if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            $color = '#4F46E5';
        }
        if ($siteTitle === '') {
            $siteTitle = 'Mitbringliste';
        }
        $siteTitle = mb_substr($siteTitle, 0, 100);
        $imprint   = mb_substr($imprint, 0, 50000);
        $privacy   = mb_substr($privacy, 0, 50000);

        if (!array_key_exists($defaultLanguage, available_languages())) {
            $defaultLanguage = 'de';
        }

        // Only http(s) links are accepted (blocks javascript: etc.) — this URL
        // is rendered as a site-wide footer link on every page.
        $licenseUrl = mb_substr($licenseUrl, 0, 500);
        if ($licenseUrl !== '' && (!preg_match('#^https?://#i', $licenseUrl) || !filter_var($licenseUrl, FILTER_VALIDATE_URL))) {
            $licenseUrl = '';
        }

        $this->saveSiteSetting('primary_color',     $color);
        $this->saveSiteSetting('site_title',        $siteTitle);
        $this->saveSiteSetting('legal_imprint',     $imprint);
        $this->saveSiteSetting('legal_privacy',     $privacy);
        $this->saveSiteSetting('default_language',  $defaultLanguage);
        $this->saveSiteSetting('license_url',       $licenseUrl);

        flash('success', 'Design-Einstellungen gespeichert.');
        redirect(base_url('admin/settings/design'));
    }

    /** POST /admin/settings/logo */
    public function uploadLogo(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Upload fehlgeschlagen oder keine Datei ausgewählt.');
            redirect(base_url('admin/settings/design'));
        }

        $file = $_FILES['logo'];
        if ($file['size'] > 2 * 1024 * 1024) {
            flash('error', 'Datei zu groß (max. 2 MB).');
            redirect(base_url('admin/settings/design'));
        }

        // SVG is intentionally not allowed: it can carry <script>/event-handler
        // payloads that execute if someone opens the uploaded file URL directly.
        $allowedMime = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        $mime        = mime_content_type($file['tmp_name']);
        if (!isset($allowedMime[$mime])) {
            flash('error', 'Ungültiges Dateiformat (erlaubt: JPG, PNG).');
            redirect(base_url('admin/settings/design'));
        }
        $ext = $allowedMime[$mime];

        $uploadsDir = BASE_DIR . '/uploads';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        // Delete old logo
        $s       = $this->getSiteSettings();
        $oldPath = $s['logo_path'] ?? '';
        if ($oldPath !== '' && file_exists(BASE_DIR . '/' . $oldPath)) {
            @unlink(BASE_DIR . '/' . $oldPath);
        }

        $filename = 'logo_' . time() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadsDir . '/' . $filename)) {
            flash('error', 'Fehler beim Speichern der Datei.');
            redirect(base_url('admin/settings/design'));
        }

        $this->saveSiteSetting('logo_path', 'uploads/' . $filename);
        flash('success', 'Logo erfolgreich hochgeladen.');
        redirect(base_url('admin/settings/design'));
    }

    /** POST /admin/settings/logo/delete */
    public function deleteLogo(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        $s       = $this->getSiteSettings();
        $oldPath = $s['logo_path'] ?? '';
        if ($oldPath !== '' && file_exists(BASE_DIR . '/' . $oldPath)) {
            @unlink(BASE_DIR . '/' . $oldPath);
        }

        $this->saveSiteSetting('logo_path', '');
        flash('success', 'Logo wurde entfernt.');
        redirect(base_url('admin/settings/design'));
    }

    // ==================================================================
    // 2FA / TOTP
    // ==================================================================

    /** POST /admin/settings/2fa */
    public function totpEnable(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        $secret = $_SESSION['totp_setup_secret'] ?? '';
        if ($secret === '') {
            flash('error', 'Session abgelaufen. Bitte Einrichtung erneut starten.');
            redirect(base_url('admin/settings/security'));
        }

        $code = trim($_POST['totp_code'] ?? '');
        if (!Totp::verify($secret, $code)) {
            flash('error', __('admin.totp_error'));
            redirect(base_url('admin/settings/security'));
        }

        // Generate 8 recovery codes (8 uppercase hex chars each)
        $plaintextCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $plaintextCodes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        $hashedCodes = array_map(
            fn($c) => password_hash($c, PASSWORD_BCRYPT),
            $plaintextCodes
        );

        $admin = $this->getAdminRow();
        global $db;
        $db->update('admin', [
            'totp_secret'    => $secret,
            'totp_enabled'   => 1,
            'recovery_codes' => json_encode($hashedCodes),
        ], ['id' => $admin['id']]);

        unset($_SESSION['totp_setup_secret']);
        $_SESSION['recovery_codes_display'] = $plaintextCodes;

        flash('success', '2FA wurde erfolgreich aktiviert. Bewahre deine Recovery-Codes sicher auf!');
        redirect(base_url('admin/settings/security'));
    }

    /** POST /admin/settings/2fa/disable */
    public function totpDisable(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        $password = $_POST['password'] ?? '';
        $admin    = $this->getAdminRow();

        if (!password_verify($password, $admin['password_hash'])) {
            flash('error', 'Falsches Passwort. 2FA wurde nicht deaktiviert.');
            redirect(base_url('admin/settings/security'));
        }

        global $db;
        $hasPasskeys = false;
        try {
            $pkStmt = $db->getPdo()->prepare('SELECT COUNT(*) FROM admin_passkeys WHERE admin_id = ?');
            $pkStmt->execute([(int) $admin['id']]);
            $hasPasskeys = (int) $pkStmt->fetchColumn() > 0;
        } catch (Throwable) {}

        $db->update('admin', [
            'totp_secret'    => null,
            'totp_enabled'   => 0,
            'recovery_codes' => $hasPasskeys ? $admin['recovery_codes'] : null,
        ], ['id' => $admin['id']]);

        flash('success', '2FA wurde deaktiviert.');
        redirect(base_url('admin/settings/security'));
    }

    // ==================================================================
    // Email settings
    // ==================================================================

    /** GET /admin/email */
    public function emailSettings(array $params = []): void
    {
        $this->checkAuth();
        global $db;
        $settings = $db->fetch('SELECT * FROM email_settings LIMIT 1') ?: [];

        render('admin/email_settings', [
            'pageTitle'        => __('admin.settings_email'),
            'currentAdminPage' => 'email',
            'containerClass'   => 'container--wide',
            'settings'         => $settings,
        ]);
    }

    /** POST /admin/email */
    public function saveEmailSettings(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        $transport = $_POST['transport'] ?? 'smtp';
        if (!in_array($transport, ['smtp', 'brevo_api', 'sendmail'], true)) {
            $transport = 'smtp';
        }

        global $db;
        $db->getPdo()->prepare(
            'INSERT INTO email_settings
                 (id, transport, smtp_host, smtp_port, smtp_user, smtp_password,
                  api_key, from_address, from_name)
             VALUES (1, :transport, :smtp_host, :smtp_port, :smtp_user,
                     :smtp_password, :api_key, :from_address, :from_name)
             ON DUPLICATE KEY UPDATE
               transport    = VALUES(transport),
               smtp_host    = VALUES(smtp_host),
               smtp_port    = VALUES(smtp_port),
               smtp_user    = VALUES(smtp_user),
               smtp_password = VALUES(smtp_password),
               api_key      = VALUES(api_key),
               from_address = VALUES(from_address),
               from_name    = VALUES(from_name)'
        )->execute([
            'transport'     => $transport,
            'smtp_host'     => trim($_POST['smtp_host']     ?? ''),
            'smtp_port'     => (int) ($_POST['smtp_port']   ?? 587),
            'smtp_user'     => trim($_POST['smtp_user']     ?? ''),
            'smtp_password' => trim($_POST['smtp_password'] ?? ''),
            'api_key'       => trim($_POST['api_key']       ?? ''),
            'from_address'  => trim($_POST['from_address']  ?? ''),
            'from_name'     => trim($_POST['from_name']     ?? ''),
        ]);

        flash('success', 'E-Mail-Einstellungen gespeichert.');
        redirect(base_url('admin/email'));
    }

    /** POST /admin/email/test */
    public function testEmail(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }
        global $db;
        $settings = $db->fetch('SELECT * FROM email_settings LIMIT 1') ?: [];
        $admin    = $this->getAdminRow();
        $to       = !empty(trim($_POST['test_email'] ?? ''))
                  ? trim($_POST['test_email'])
                  : ($admin['email'] ?? '');

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Keine gültige Empfänger-E-Mail angegeben.');
            redirect(base_url('admin/email'));
        }

        $mailer = new Mailer($settings);
        $body   = '<p>Dies ist eine Test-E-Mail von <strong>' . htmlspecialchars(site_title(), ENT_QUOTES) . '</strong>.'
                . ' Die E-Mail-Konfiguration funktioniert korrekt.</p>';
        if ($mailer->send($to, 'Test-Mail von ' . site_title(), $body)) {
            flash('success', __('admin.email_test_success'));
        } else {
            flash('error', __('admin.email_test_error', ['error' => $mailer->lastError]));
        }
        redirect(base_url('admin/email'));
    }

    // ==================================================================
    // PDF Settings
    // ==================================================================

    /** GET /admin/pdf-settings */
    public function pdfSettings(array $params = []): void
    {
        $this->checkAuth();
        $s = $this->getSiteSettings();
        render('admin/pdf_settings', [
            'pageTitle'        => __('admin.settings_pdf'),
            'currentAdminPage' => 'pdf',
            'containerClass'   => 'container--wide',
            's'                => $s,
        ]);
    }

    /** POST /admin/pdf-settings */
    public function savePdfSettings(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        $pageSize = in_array($_POST['pdf_page_size'] ?? '', ['A4', 'A5', 'Letter'], true)
                    ? $_POST['pdf_page_size'] : 'A4';
        $orient   = in_array($_POST['pdf_orientation'] ?? '', ['portrait', 'landscape'], true)
                    ? $_POST['pdf_orientation'] : 'portrait';
        $fontSize = max(8, min(24, (int) ($_POST['pdf_font_size'] ?? 12)));
        $showLogo = !empty($_POST['pdf_show_logo']) ? '1' : '0';
        $footer   = trim($_POST['pdf_footer_text'] ?? '');

        $this->saveSiteSetting('pdf_page_size',   $pageSize);
        $this->saveSiteSetting('pdf_orientation', $orient);
        $this->saveSiteSetting('pdf_font_size',   (string) $fontSize);
        $this->saveSiteSetting('pdf_show_logo',   $showLogo);
        $this->saveSiteSetting('pdf_footer_text', $footer);

        flash('success', 'PDF-Einstellungen gespeichert.');
        redirect(base_url('admin/pdf-settings'));
    }

    // ==================================================================
    // Security: rate limits, claim delay, and 2FA (TOTP / Passkeys)
    // ==================================================================

    /** GET /admin/settings/security */
    public function securitySettings(array $params = []): void
    {
        $this->checkAuth();
        global $db;
        $s          = $this->getSiteSettings();
        $cronToken  = $s['cron_cleanup_token'] ?? '';
        $admin      = $this->getAdminRow();

        $qrSvg         = null;
        $pendingSecret = null;

        // Show recovery codes exactly once after enabling TOTP or regenerating codes
        $recoveryCodes = $_SESSION['recovery_codes_display'] ?? null;
        unset($_SESSION['recovery_codes_display']);

        // Pending passkey confirmation: passkey validated but not yet saved to DB
        $pendingPasskeyConfirm = false;
        if (!empty($_SESSION['passkey_pending'])) {
            $pendingPasskeyConfirm = true;
            $recoveryCodes = $_SESSION['passkey_pending_codes'] ?? [];
        }

        if (!$admin['totp_enabled']) {
            if (empty($_SESSION['totp_setup_secret'])) {
                $_SESSION['totp_setup_secret'] = Totp::generateSecret();
            }
            $pendingSecret = $_SESSION['totp_setup_secret'];
            $uri           = Totp::getQrCodeUri($pendingSecret, $admin['email'], site_title());
            try {
                $qrSvg = Totp::renderQrCodeSvg($uri);
            } catch (Throwable) {
                $qrSvg = null;
            }
        }

        $passkeys = [];
        try {
            $passkeys = $db->fetchAll(
                'SELECT * FROM admin_passkeys WHERE admin_id = ? ORDER BY created_at DESC',
                [(int) $admin['id']]
            );
        } catch (Throwable) {}

        $storedHashes  = json_decode($admin['recovery_codes'] ?? '[]', true);
        $recoveryCount = is_array($storedHashes) ? count($storedHashes) : 0;

        render('admin/security_settings', [
            'pageTitle'             => __('admin.settings_security'),
            'currentAdminPage'      => 'security',
            'containerClass'        => 'container--wide',
            's'                     => $s,
            'cronToken'             => $cronToken,
            'cronUrl'               => $cronToken !== '' ? base_url('cron/cleanup?token=' . $cronToken) : '',
            'admin'                 => $admin,
            'qrSvg'                 => $qrSvg,
            'pendingSecret'         => $pendingSecret,
            'recoveryCodes'         => $recoveryCodes,
            'passkeys'              => $passkeys,
            'recoveryCount'         => $recoveryCount,
            'pendingPasskeyConfirm' => $pendingPasskeyConfirm,
        ]);
    }

    /** POST /admin/settings/security */
    public function saveSecuritySettings(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'rate_limits') {
            $this->saveSiteSetting('rate_login_max_attempts',  (string) max(1, min(20,   (int) ($_POST['rate_login_max_attempts']  ?? 5))));
            $this->saveSiteSetting('rate_login_window_minutes',(string) max(1, min(60,   (int) ($_POST['rate_login_window_minutes'] ?? 15))));
            $this->saveSiteSetting('rate_event_max_count',     (string) max(1, min(100,  (int) ($_POST['rate_event_max_count']     ?? 10))));
            $this->saveSiteSetting('rate_event_window_minutes',(string) max(1, min(1440, (int) ($_POST['rate_event_window_minutes'] ?? 60))));
            flash('success', __('admin.rate_limits_saved'));
            redirect(base_url('admin/settings/security'));
        }

        if ($action === 'claim_delay') {
            $this->saveSiteSetting('claim_finalize_delay_minutes', (string) max(1, min(1440, (int) ($_POST['claim_finalize_delay_minutes'] ?? 10))));
            flash('success', __('admin.claim_delay_saved'));
            redirect(base_url('admin/settings/security'));
        }

        redirect(base_url('admin/settings/security'));
    }

    /** GET /admin/settings/cleanup */
    public function cleanupSettings(array $params = []): void
    {
        $this->checkAuth();
        $s         = $this->getSiteSettings();
        $cronToken = $s['cron_cleanup_token'] ?? '';
        render('admin/cleanup_settings', [
            'pageTitle'        => __('admin.settings_cleanup'),
            'currentAdminPage' => 'cleanup',
            'containerClass'   => 'container--wide',
            'cronToken'        => $cronToken,
            'cronUrl'          => $cronToken !== '' ? base_url('cron/cleanup?token=' . $cronToken) : '',
        ]);
    }

    /** POST /admin/settings/cleanup */
    public function saveCleanupSettings(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'cron_generate') {
            $this->saveSiteSetting('cron_cleanup_token', bin2hex(random_bytes(32)));
            flash('success', __('admin.cron_token_generated'));
            redirect(base_url('admin/settings/cleanup'));
        }

        if ($action === 'cron_disable') {
            $this->saveSiteSetting('cron_cleanup_token', '');
            flash('success', __('admin.cron_token_disabled'));
            redirect(base_url('admin/settings/cleanup'));
        }

        if ($action === 'cron_run') {
            $deleted = $this->performCleanup();
            flash('success', __('admin.cron_run_result', [
                'events'     => $deleted['events'],
                'rate_limit' => $deleted['rate_limit'],
            ]));
            redirect(base_url('admin/settings/cleanup'));
        }

        redirect(base_url('admin/settings/cleanup'));
    }

    // ==================================================================
    // Passkeys (WebAuthn)
    // ==================================================================

    /**
     * POST /admin/settings/2fa/passkey  (JSON)
     * Handles register_challenge and register_complete for passkey registration.
     * Requires admin to be logged in.
     */
    public function passkeyApi(array $params = []): void
    {
        $this->checkAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'method not allowed']);
            return;
        }

        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $input['action'] ?? '';

        if (!hash_equals($_SESSION['csrf_token'] ?? '', $input['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['error' => 'csrf']);
            return;
        }

        global $db;
        $admin    = $this->getAdminRow();
        $adminId  = (int) $admin['id'];
        $siteName = site_title();
        $rpId     = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');

        if ($action === 'register_challenge') {
            try {
                // Discard any previously unconfirmed pending passkey so it cannot
                // accumulate as an orphan credential in the authenticator.
                unset($_SESSION['passkey_pending'], $_SESSION['passkey_pending_codes']);

                $wa = new WebAuthn($siteName, $rpId, null, true);

                $excludeIds = [];
                try {
                    $stmt = $db->getPdo()->prepare('SELECT credential_id FROM admin_passkeys WHERE admin_id = ?');
                    $stmt->execute([$adminId]);
                    $excludeIds = array_map('base64_decode', $stmt->fetchAll(PDO::FETCH_COLUMN));
                } catch (Throwable) {}

                // Use a stable, deterministic user ID so that re-registration of the
                // same authenticator replaces the old credential instead of adding a
                // new orphan entry in the authenticator (e.g. Vaultwarden).
                $stableUserId = pack('N', $adminId) . "\0\0\0\0";  // 8-byte big-endian admin ID

                $args = $wa->getCreateArgs(
                    $stableUserId,
                    $admin['email'],
                    $admin['email'],
                    60,
                    true,
                    'preferred',
                    null,
                    $excludeIds
                );
                $_SESSION['webauthn_register_challenge'] = $wa->getChallenge();
                echo json_encode($args);
            } catch (Throwable $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            return;
        }

        if ($action === 'register_complete') {
            try {
                $name = mb_substr(trim($input['deviceName'] ?? ''), 0, 100);
                if ($name === '') $name = 'Passkey';

                $clientDataJSON = self::b64urlDecode($input['clientDataJSON']    ?? '');
                $attestation    = self::b64urlDecode($input['attestationObject'] ?? '');
                $challenge      = $_SESSION['webauthn_register_challenge'] ?? null;

                if (!$challenge) {
                    throw new RuntimeException('Keine Challenge in der Session.');
                }

                $wa   = new WebAuthn($siteName, $rpId, null, true);
                $data = $wa->processCreate($clientDataJSON, $attestation, $challenge, false, false);

                unset($_SESSION['webauthn_register_challenge']);

                // Passkey is NOT saved to DB yet – only after the user confirms
                // they have safely stored the recovery codes (pending-confirmation flow).
                $_SESSION['passkey_pending'] = [
                    'credential_id' => base64_encode($data->credentialId),
                    'public_key'    => $data->credentialPublicKey,
                    'sign_counter'  => (int) $data->signatureCounter,
                    'device_name'   => $name,
                ];
                $_SESSION['passkey_pending_codes'] = $this->generateRecoveryCodes();

                echo json_encode(['ok' => true, 'redirect' => base_url('admin/settings/security')]);
            } catch (Throwable $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            return;
        }

        http_response_code(400);
        echo json_encode(['error' => 'unknown action']);
    }

    /**
     * POST /admin/settings/2fa/passkey-auth  (JSON)
     * Handles auth_challenge and auth_complete during the login 2FA step.
     * Requires admin_totp_pending session (not full admin session).
     */
    public function passkeyAuthApi(array $params = []): void
    {
        header('Content-Type: application/json');

        if (empty($_SESSION['admin_totp_pending'])) {
            http_response_code(401);
            echo json_encode(['error' => 'no pending session']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'method not allowed']);
            return;
        }

        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $input['action'] ?? '';

        if (!hash_equals($_SESSION['csrf_token'] ?? '', $input['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['error' => 'csrf']);
            return;
        }

        global $db;
        $adminId  = (int) ($_SESSION['admin_id_pending'] ?? 0);
        $siteName = site_title();
        $rpId     = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');

        if ($action === 'auth_challenge') {
            try {
                $stmt = $db->getPdo()->prepare('SELECT COUNT(*) FROM admin_passkeys WHERE admin_id = ?');
                $stmt->execute([$adminId]);
                if ((int) $stmt->fetchColumn() === 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Keine Passkeys registriert.']);
                    return;
                }

                $wa   = new WebAuthn($siteName, $rpId, null, true);
                $args = $wa->getGetArgs([], 60, true, true, true, true, true, 'preferred');
                $_SESSION['webauthn_auth_challenge'] = $wa->getChallenge();
                echo json_encode($args);
            } catch (Throwable $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            return;
        }

        if ($action === 'auth_complete') {
            try {
                $credIdBin = self::b64urlDecode($input['id'] ?? '');
                $credIdB64 = base64_encode($credIdBin);

                $stmt = $db->getPdo()->prepare(
                    'SELECT * FROM admin_passkeys WHERE admin_id = ? AND credential_id = ? LIMIT 1'
                );
                $stmt->execute([$adminId, $credIdB64]);
                $passkey = $stmt->fetch() ?: null;

                if (!$passkey) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Passkey nicht gefunden.']);
                    return;
                }

                $challenge = $_SESSION['webauthn_auth_challenge'] ?? null;
                if (!$challenge) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Keine Challenge in der Session.']);
                    return;
                }

                $clientDataJSON    = self::b64urlDecode($input['clientDataJSON']    ?? '');
                $authenticatorData = self::b64urlDecode($input['authenticatorData'] ?? '');
                $signature         = self::b64urlDecode($input['signature']         ?? '');

                $wa = new WebAuthn($siteName, $rpId, null, true);
                $wa->processGet(
                    $clientDataJSON,
                    $authenticatorData,
                    $signature,
                    $passkey['public_key'],
                    $challenge,
                    (int) $passkey['sign_counter'],
                    false
                );
                $newCounter = $wa->getSignatureCounter() ?? (int) $passkey['sign_counter'];

                $db->getPdo()->prepare('UPDATE admin_passkeys SET sign_counter = ? WHERE id = ?')
                   ->execute([$newCounter, $passkey['id']]);

                unset(
                    $_SESSION['admin_totp_pending'],
                    $_SESSION['admin_id_pending'],
                    $_SESSION['totp_attempts'],
                    $_SESSION['webauthn_auth_challenge']
                );
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = $adminId;
                session_regenerate_id(true);

                echo json_encode(['ok' => true, 'redirect' => base_url('admin/dashboard')]);
            } catch (Throwable $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            return;
        }

        http_response_code(400);
        echo json_encode(['error' => 'unknown action']);
    }

    /**
     * POST /admin/settings/2fa/passkey/confirm
     * Saves a pending passkey to the DB after the user confirms recovery codes are stored.
     */
    public function confirmPasskey(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        $pending = $_SESSION['passkey_pending'] ?? null;
        $codes   = $_SESSION['passkey_pending_codes'] ?? [];

        if (!$pending || empty($pending['credential_id'])) {
            flash('error', 'Keine ausstehende Passkey-Registrierung gefunden.');
            redirect(base_url('admin/settings/security'));
        }

        global $db;
        $admin   = $this->getAdminRow();
        $adminId = (int) $admin['id'];

        try {
            $db->insert('admin_passkeys', [
                'admin_id'      => $adminId,
                'credential_id' => $pending['credential_id'],
                'public_key'    => $pending['public_key'],
                'sign_counter'  => $pending['sign_counter'],
                'device_name'   => $pending['device_name'],
            ]);
            $hashedCodes = array_map(fn($c) => password_hash($c, PASSWORD_BCRYPT), $codes);
            $db->update('admin', ['recovery_codes' => json_encode($hashedCodes)], ['id' => $adminId]);
        } catch (Throwable $e) {
            flash('error', 'Fehler beim Speichern des Passkeys: ' . $e->getMessage());
            redirect(base_url('admin/settings/security'));
        }

        unset($_SESSION['passkey_pending'], $_SESSION['passkey_pending_codes']);

        flash('success', 'Passkey "' . h($pending['device_name']) . '" wurde gespeichert.');
        redirect(base_url('admin/settings/security'));
    }

    /**
     * POST /admin/settings/2fa/passkey/cancel
     * Cancels a pending passkey registration without saving anything.
     */
    public function cancelPendingPasskey(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        unset($_SESSION['passkey_pending'], $_SESSION['passkey_pending_codes']);

        flash('info', 'Passkey-Registrierung abgebrochen.');
        redirect(base_url('admin/settings/security'));
    }

    /**
     * POST /admin/settings/2fa/passkey/delete
     * Deletes a registered passkey by ID.
     * Clears recovery codes if no 2FA method remains.
     */
    public function deletePasskey(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        global $db;
        $admin   = $this->getAdminRow();
        $pkId    = (int) ($_POST['passkey_id'] ?? 0);

        try {
            $db->getPdo()->prepare('DELETE FROM admin_passkeys WHERE id = ? AND admin_id = ?')
               ->execute([$pkId, (int) $admin['id']]);

            $stmt = $db->getPdo()->prepare('SELECT COUNT(*) FROM admin_passkeys WHERE admin_id = ?');
            $stmt->execute([(int) $admin['id']]);
            $remaining = (int) $stmt->fetchColumn();

            if ($remaining === 0 && empty($admin['totp_enabled'])) {
                $db->update('admin', ['recovery_codes' => null], ['id' => $admin['id']]);
            }
        } catch (Throwable) {}

        flash('success', __('admin.passkey_deleted'));
        redirect(base_url('admin/settings/security'));
    }

    /**
     * POST /admin/settings/2fa/recovery
     * Regenerates recovery codes (requires at least one active 2FA method).
     */
    public function regenerateRecoveryCodes(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        global $db;
        $admin = $this->getAdminRow();

        $hasTOTP     = !empty($admin['totp_enabled']);
        $hasPasskeys = false;
        try {
            $stmt = $db->getPdo()->prepare('SELECT COUNT(*) FROM admin_passkeys WHERE admin_id = ?');
            $stmt->execute([(int) $admin['id']]);
            $hasPasskeys = (int) $stmt->fetchColumn() > 0;
        } catch (Throwable) {}

        if (!$hasTOTP && !$hasPasskeys) {
            flash('error', 'Keine 2FA-Methode aktiv. Aktiviere zuerst TOTP oder einen Passkey.');
            redirect(base_url('admin/settings/security'));
        }

        $plaintextCodes = $this->generateRecoveryCodes();
        $hashedCodes    = array_map(fn($c) => password_hash($c, PASSWORD_BCRYPT), $plaintextCodes);
        $db->update('admin', ['recovery_codes' => json_encode($hashedCodes)], ['id' => $admin['id']]);

        $_SESSION['recovery_codes_display'] = $plaintextCodes;
        flash('success', __('admin.recovery_regenerated'));
        redirect(base_url('admin/settings/security'));
    }

    /**
     * GET /cron/cleanup?token=...
     * Token-protected, unauthenticated endpoint for free web-cron services
     * that can only trigger a plain GET URL (no CLI/SSH access needed).
     * Does the same work as the CLI cleanup.php script.
     */
    public function cronCleanup(array $params = []): void
    {
        header('Content-Type: text/plain; charset=utf-8');

        $storedToken   = $this->getSiteSettings()['cron_cleanup_token'] ?? '';
        $providedToken = (string) ($_GET['token'] ?? '');

        if ($storedToken === '' || $providedToken === '' || !hash_equals($storedToken, $providedToken)) {
            http_response_code(403);
            echo "Forbidden\n";
            return;
        }

        $deleted = $this->performCleanup();
        http_response_code(200);
        printf(
            "OK - %d event(s) deleted, %d rate_limit row(s) deleted\n",
            $deleted['events'],
            $deleted['rate_limit']
        );
    }

}
