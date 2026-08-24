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

    private function checkAuth(): void
    {
        if (empty($_SESSION['user_logged_in'])) {
            redirect(base_url('admin/login'));
        }
        // Everything in this controller (dashboard-over-all-events, site-wide
        // settings, email/PDF config, user management) is SuperAdmin-only.
        // Regular users get their own area once that exists (see Phase 4).
        if (($_SESSION['user_role'] ?? '') !== 'superadmin') {
            abort(403);
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

    private function getCurrentUser(): array
    {
        global $db;
        $user = $db->fetch('SELECT * FROM users WHERE id = ?', [(int) ($_SESSION['user_id'] ?? 0)]);
        if (!$user) {
            abort(500);
        }
        return $user;
    }

    /**
     * Establishes a logged-in session (password-only, TOTP, or passkey path)
     * and rotates the session id. Shared so all three login paths stay in
     * sync. Does not redirect/respond — callers differ (HTML redirect vs.
     * JSON response for the passkey API), so they do that themselves.
     */
    private function establishSession(array $user): void
    {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id']        = $user['id'];
        $_SESSION['user_role']      = $user['role'];
        unset(
            $_SESSION['user_totp_pending'],
            $_SESSION['user_id_pending'],
            $_SESSION['totp_attempts']
        );
        session_regenerate_id(true);

        // Remove installer on first login (belt-and-suspenders in case unlink inside install.php failed)
        foreach ([BASE_DIR . '/install.php', BASE_DIR . '/install.php.done'] as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }
    }

    /** Redirect target after a successful login, based on role. */
    /**
     * Redirect target after a successful login. Prefers whatever page sent
     * the user to login in the first place (e.g. an owned list's manage
     * page, or the create-list form in restricted mode) over the generic
     * role-based default.
     */
    private function postLoginUrl(array $user): string
    {
        if (!empty($_SESSION['login_redirect'])) {
            $target = $_SESSION['login_redirect'];
            unset($_SESSION['login_redirect']);
            return $target;
        }
        return base_url($user['role'] === 'superadmin' ? 'admin/dashboard' : 'my/lists');
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
    private function verifyRecoveryCode(array $user, string $input): bool
    {
        global $db;
        $codes = json_decode($user['recovery_codes'] ?? '[]', true);
        if (!is_array($codes) || empty($codes)) {
            return false;
        }
        $input = strtoupper(trim($input));
        foreach ($codes as $idx => $hash) {
            if (password_verify($input, $hash)) {
                unset($codes[$idx]);
                $db->update('users',
                    ['recovery_codes' => json_encode(array_values($codes))],
                    ['id' => $user['id']]
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
        if (!empty($_SESSION['user_logged_in'])) {
            redirect(base_url(($_SESSION['user_role'] ?? '') === 'superadmin' ? 'admin/dashboard' : 'my/lists'));
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
        $user = $db->fetch('SELECT * FROM users WHERE email = ?', [$email]);

        // Disabled accounts are treated like a wrong password (generic error)
        // so a login attempt can't be used to probe whether an account exists
        // or is merely disabled.
        if (!$user || $user['status'] !== 'active' || !password_verify($password, (string) $user['password_hash'])) {
            rate_limit_record('admin_login');
            flash('error', __('admin.login_error'));
            render('admin/login', ['pageTitle' => __('admin.login_title')]);
            return;
        }

        $hasPasskeys = false;
        try {
            $pkStmt = $db->getPdo()->prepare('SELECT COUNT(*) FROM user_passkeys WHERE user_id = ?');
            $pkStmt->execute([(int) $user['id']]);
            $hasPasskeys = (int) $pkStmt->fetchColumn() > 0;
        } catch (Throwable) {}

        if ($user['totp_enabled'] || $hasPasskeys) {
            $_SESSION['user_totp_pending'] = true;
            $_SESSION['user_id_pending']   = $user['id'];
            session_regenerate_id(true);
            redirect(base_url('admin/totp'));
        }

        $this->establishSession($user);
        redirect($this->postLoginUrl($user));
    }

    /** GET /admin/totp */
    public function totpForm(array $params = []): void
    {
        if (empty($_SESSION['user_totp_pending'])) {
            redirect(base_url('admin/login'));
        }

        $userId      = (int) ($_SESSION['user_id_pending'] ?? 0);
        $hasTOTP     = false;
        $hasPasskeys = false;
        $hasRecovery = false;

        global $db;
        if ($userId) {
            $userRow = $db->fetch('SELECT totp_enabled, recovery_codes FROM users WHERE id = ? LIMIT 1', [$userId]);
            if ($userRow) {
                $hasTOTP     = !empty($userRow['totp_enabled']);
                $hasRecovery = !empty($userRow['recovery_codes']);
            }
            try {
                $pkStmt = $db->getPdo()->prepare('SELECT COUNT(*) FROM user_passkeys WHERE user_id = ?');
                $pkStmt->execute([$userId]);
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
        if (empty($_SESSION['user_totp_pending'])) {
            redirect(base_url('admin/login'));
        }
        if (!csrf_verify()) {
            abort(403);
        }

        $code = trim($_POST['totp_code'] ?? '');
        global $db;
        $user = $db->fetch('SELECT * FROM users WHERE id = ?',
            [$_SESSION['user_id_pending'] ?? 0]);

        if (!$user) {
            session_destroy();
            redirect(base_url('admin/login'));
        }

        $_SESSION['totp_attempts'] = ($_SESSION['totp_attempts'] ?? 0) + 1;

        $totpValid = Totp::verify($user['totp_secret'], $code);

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

        $valid = $totpValid || $this->verifyRecoveryCode($user, $code);

        if (!$valid) {
            if ($_SESSION['totp_attempts'] >= 3) {
                session_destroy();
                redirect(base_url('admin/login'));
            }
            flash('error', __('admin.totp_error'));
            render('admin/totp_verify', ['pageTitle' => __('admin.totp_title')]);
            return;
        }

        $this->establishSession($user);
        redirect($this->postLoginUrl($user));
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
            'SELECT e.*, u.email AS owner_email,
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
             LEFT JOIN users u ON u.id = e.user_id
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
    // User management (invite-only registered users; the SuperAdmin row
    // itself is never listed/editable here)
    // ==================================================================

    /** GET /admin/users */
    public function users(array $params = []): void
    {
        $this->checkAuth();
        global $db;
        $users = $db->fetchAll(
            "SELECT u.*,
                (SELECT expires_at FROM user_invites WHERE user_id = u.id ORDER BY id DESC LIMIT 1) AS latest_invite_expires
             FROM users u
             WHERE u.role = 'user'
             ORDER BY u.created_at DESC"
        );

        render('admin/users', [
            'pageTitle'        => __('admin.settings_users'),
            'currentAdminPage' => 'users',
            'containerClass'   => 'container--wide',
            'users'            => $users,
        ]);
    }

    /**
     * Generates a fresh invite token for a user and emails it, replacing any
     * previous unused invite (so an old, still-valid link can't linger
     * alongside a newly issued one). Shared by inviteUser() and resendInvite().
     */
    private function sendInvite(int $userId, string $email): bool
    {
        global $db;
        $db->delete('user_invites', ['user_id' => $userId]);

        $token = bin2hex(random_bytes(32));
        $db->insert('user_invites', [
            'user_id'    => $userId,
            'token'      => $token,
            'expires_at' => date('Y-m-d H:i:s', time() + 86400),
        ]);

        $emailSettings = $db->fetch('SELECT * FROM email_settings LIMIT 1') ?: [];
        $mailer        = new Mailer($emailSettings);
        $html          = Mailer::renderTemplate('user_invite', [
            'inviteUrl' => base_url('invite/' . $token),
        ]);
        return $mailer->send($email, 'Einladung zu ' . site_title(), $html);
    }

    /** POST /admin/users/invite */
    public function inviteUser(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        $email = trim($_POST['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Bitte eine gültige E-Mail-Adresse angeben.');
            redirect(base_url('admin/users'));
        }

        global $db;
        if ($db->fetch('SELECT id FROM users WHERE email = ?', [$email])) {
            flash('error', 'Es existiert bereits ein Account mit dieser E-Mail-Adresse.');
            redirect(base_url('admin/users'));
        }

        $userId = $db->insert('users', [
            'email'  => $email,
            'role'   => 'user',
            'status' => 'active',
        ]);

        if ($this->sendInvite($userId, $email)) {
            flash('success', 'Einladung an ' . h($email) . ' wurde versendet.');
        } else {
            flash('error', 'Benutzer wurde angelegt, aber die Einladungs-E-Mail konnte nicht versendet werden. Bitte E-Mail-Einstellungen prüfen und die Einladung erneut senden.');
        }
        redirect(base_url('admin/users'));
    }

    /** POST /admin/users/{id}/resend */
    public function resendInvite(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        global $db;
        $id   = (int) ($params['id'] ?? 0);
        $user = $db->fetch("SELECT * FROM users WHERE id = ? AND role = 'user'", [$id]);
        if (!$user) {
            flash('error', 'Benutzer nicht gefunden.');
            redirect(base_url('admin/users'));
        }
        if ($user['password_hash'] !== null) {
            flash('error', 'Dieser Account wurde bereits aktiviert.');
            redirect(base_url('admin/users'));
        }

        if ($this->sendInvite((int) $user['id'], $user['email'])) {
            flash('success', 'Einladung wurde erneut versendet.');
        } else {
            flash('error', 'Einladungs-E-Mail konnte nicht versendet werden. Bitte E-Mail-Einstellungen prüfen.');
        }
        redirect(base_url('admin/users'));
    }

    /**
     * POST /admin/users/{id}/toggle
     * Locks/unlocks login for a user without deleting their account or
     * lists. Note: an already-open session isn't killed immediately — it
     * expires via the existing 30-minute inactivity timeout, same as
     * everywhere else in this controller.
     */
    public function toggleUserStatus(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        global $db;
        $id   = (int) ($params['id'] ?? 0);
        $user = $db->fetch("SELECT * FROM users WHERE id = ? AND role = 'user'", [$id]);
        if (!$user) {
            flash('error', 'Benutzer nicht gefunden.');
            redirect(base_url('admin/users'));
        }

        $newStatus = $user['status'] === 'active' ? 'disabled' : 'active';
        $db->update('users', ['status' => $newStatus], ['id' => $id]);

        flash('success', $newStatus === 'active' ? 'Account wurde entsperrt.' : 'Account wurde gesperrt.');
        redirect(base_url('admin/users'));
    }

    /**
     * POST /admin/users/{id}/delete
     * events.user_id has ON DELETE SET NULL (see migration 002) — their
     * lists fall back to token-only access instead of being deleted.
     */
    public function deleteUser(array $params = []): void
    {
        $this->checkAuth();
        if (!csrf_verify()) {
            abort(403);
        }

        global $db;
        $id   = (int) ($params['id'] ?? 0);
        $user = $db->fetch("SELECT * FROM users WHERE id = ? AND role = 'user'", [$id]);
        if (!$user) {
            flash('error', 'Benutzer nicht gefunden.');
            redirect(base_url('admin/users'));
        }

        $db->delete('users', ['id' => $id]);

        flash('success', 'Account "' . h($user['email']) . '" wurde gelöscht.');
        redirect(base_url('admin/users'));
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
        $user     = $this->getCurrentUser();
        $to       = !empty(trim($_POST['test_email'] ?? ''))
                  ? trim($_POST['test_email'])
                  : ($user['email'] ?? '');

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
    // Security: site-wide settings (rate limits, claim delay, list
    // creation mode). Personal 2FA (TOTP/Passkeys) lives in
    // AccountSecurityController — available to any logged-in account, not
    // just the SuperAdmin, see /account/security.
    // ==================================================================

    /** GET /admin/settings/security */
    public function securitySettings(array $params = []): void
    {
        $this->checkAuth();
        $s         = $this->getSiteSettings();
        $cronToken = $s['cron_cleanup_token'] ?? '';

        render('admin/security_settings', [
            'pageTitle'        => __('admin.settings_security'),
            'currentAdminPage' => 'security',
            'containerClass'   => 'container--wide',
            's'                => $s,
            'listCreationMode' => list_creation_mode(),
            'cronToken'        => $cronToken,
            'cronUrl'          => $cronToken !== '' ? base_url('cron/cleanup?token=' . $cronToken) : '',
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

        if ($action === 'list_creation_mode') {
            $mode = ($_POST['list_creation_mode'] ?? '') === 'restricted' ? 'restricted' : 'open';
            $this->saveSiteSetting('list_creation_mode', $mode);
            flash('success', 'Einstellung gespeichert.');
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
    // Passkeys (WebAuthn) — login-time verification only. Registering,
    // confirming, listing and deleting passkeys lives in
    // AccountSecurityController (any logged-in account manages those for
    // themselves at /account/security), since that's no longer SuperAdmin-only.
    // ==================================================================

    /**
     * POST /admin/settings/2fa/passkey-auth  (JSON)
     * Handles auth_challenge and auth_complete during the login 2FA step.
     * Requires user_totp_pending session (not a full login session).
     */
    public function passkeyAuthApi(array $params = []): void
    {
        header('Content-Type: application/json');

        if (empty($_SESSION['user_totp_pending'])) {
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
        $userId   = (int) ($_SESSION['user_id_pending'] ?? 0);
        $siteName = site_title();
        $rpId     = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');

        if ($action === 'auth_challenge') {
            try {
                $stmt = $db->getPdo()->prepare('SELECT COUNT(*) FROM user_passkeys WHERE user_id = ?');
                $stmt->execute([$userId]);
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
                    'SELECT * FROM user_passkeys WHERE user_id = ? AND credential_id = ? LIMIT 1'
                );
                $stmt->execute([$userId, $credIdB64]);
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

                $db->getPdo()->prepare('UPDATE user_passkeys SET sign_counter = ? WHERE id = ?')
                   ->execute([$newCounter, $passkey['id']]);

                unset($_SESSION['webauthn_auth_challenge']);

                $user = $db->fetch('SELECT * FROM users WHERE id = ?', [$userId]);
                if (!$user) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Account nicht gefunden.']);
                    return;
                }
                $this->establishSession($user);

                echo json_encode(['ok' => true, 'redirect' => $this->postLoginUrl($user)]);
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
