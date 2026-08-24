<?php
declare(strict_types=1);

use lbuchs\WebAuthn\WebAuthn;

/**
 * Personal account security (TOTP, Passkeys, recovery codes) — available to
 * any logged-in account, regardless of role. This is the counterpart to the
 * SuperAdmin-only, site-wide settings in AdminController (rate limits,
 * claim delay, list creation mode) which stay under /admin/settings/security.
 * The SuperAdmin manages their own 2FA here too, same as any other user.
 */
class AccountSecurityController
{
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

    private function requireLogin(): void
    {
        if (empty($_SESSION['user_logged_in'])) {
            redirect(base_url('admin/login'));
        }
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

    // ==================================================================
    // View
    // ==================================================================

    /** GET /account/security */
    public function index(array $params = []): void
    {
        $this->requireLogin();
        global $db;
        $user = $this->getCurrentUser();

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

        // Pending TOTP confirmation: recovery codes staged but secret not yet
        // saved to DB — see totpStart()/totpConfirm().
        $pendingTotpConfirm = false;
        $totpPendingCodes   = [];
        if (!empty($_SESSION['totp_pending']['codes'])) {
            $pendingTotpConfirm = true;
            $totpPendingCodes   = $_SESSION['totp_pending']['codes'];
        }

        if (!$user['totp_enabled'] && !$pendingTotpConfirm) {
            if (empty($_SESSION['totp_setup_secret'])) {
                $_SESSION['totp_setup_secret'] = Totp::generateSecret();
            }
            $pendingSecret = $_SESSION['totp_setup_secret'];
            $uri           = Totp::getQrCodeUri($pendingSecret, $user['email'], site_title());
            try {
                $qrSvg = Totp::renderQrCodeSvg($uri);
            } catch (Throwable) {
                $qrSvg = null;
            }
        }

        $passkeys = [];
        try {
            $passkeys = $db->fetchAll(
                'SELECT * FROM user_passkeys WHERE user_id = ? ORDER BY created_at DESC',
                [(int) $user['id']]
            );
        } catch (Throwable) {}

        $storedHashes  = json_decode($user['recovery_codes'] ?? '[]', true);
        $recoveryCount = is_array($storedHashes) ? count($storedHashes) : 0;

        render('account/security', [
            'pageTitle'             => 'Meine Sicherheit',
            'admin'                 => $user,
            'qrSvg'                 => $qrSvg,
            'pendingSecret'         => $pendingSecret,
            'pendingTotpConfirm'    => $pendingTotpConfirm,
            'totpPendingCodes'      => $totpPendingCodes,
            'recoveryCodes'         => $recoveryCodes,
            'passkeys'              => $passkeys,
            'recoveryCount'         => $recoveryCount,
            'pendingPasskeyConfirm' => $pendingPasskeyConfirm,
        ]);
    }

    // ==================================================================
    // 2FA / TOTP
    // ==================================================================

    /**
     * POST /account/security/2fa
     * Step 1 of TOTP setup: stages recovery codes for confirmation. No code
     * entry here — see totpConfirm() for why.
     */
    public function totpStart(array $params = []): void
    {
        $this->requireLogin();
        if (!csrf_verify()) {
            abort(403);
        }

        $secret = $_SESSION['totp_setup_secret'] ?? '';
        if ($secret === '') {
            flash('error', 'Session abgelaufen. Bitte Einrichtung erneut starten.');
            redirect(base_url('account/security'));
        }

        // Nothing is persisted here — the secret is only proven to work (and
        // saved to the DB together with the recovery codes) at the confirm
        // step below, once the user has seen and acknowledged the codes.
        // This keeps a single TOTP code entry for the whole setup instead of
        // one here and another at confirm.
        $_SESSION['totp_pending'] = [
            'secret' => $secret,
            'codes'  => $this->generateRecoveryCodes(),
        ];

        redirect(base_url('account/security'));
    }

    /**
     * POST /account/security/2fa/confirm
     * Final step of TOTP setup: requires the "codes saved" checkbox and a
     * valid code from the pending secret. Only now are secret/enabled/codes
     * persisted — mirrors the passkey pending-confirmation flow below.
     */
    public function totpConfirm(array $params = []): void
    {
        $this->requireLogin();
        if (!csrf_verify()) {
            abort(403);
        }

        $pending = $_SESSION['totp_pending'] ?? null;
        if (!$pending || empty($pending['secret'])) {
            flash('error', 'Keine ausstehende TOTP-Einrichtung gefunden.');
            redirect(base_url('account/security'));
        }

        if (empty($_POST['confirmed'])) {
            flash('error', 'Bitte bestätige, dass du die Notfallcodes gesichert hast.');
            redirect(base_url('account/security'));
        }

        $code = trim($_POST['totp_code'] ?? '');
        if (!Totp::verify($pending['secret'], $code)) {
            flash('error', __('admin.totp_error'));
            redirect(base_url('account/security'));
        }

        $hashedCodes = array_map(
            fn($c) => password_hash($c, PASSWORD_BCRYPT),
            $pending['codes']
        );

        $user = $this->getCurrentUser();
        global $db;
        $db->update('users', [
            'totp_secret'    => $pending['secret'],
            'totp_enabled'   => 1,
            'recovery_codes' => json_encode($hashedCodes),
        ], ['id' => $user['id']]);

        unset($_SESSION['totp_pending'], $_SESSION['totp_setup_secret']);

        flash('success', '2FA wurde erfolgreich aktiviert. Bewahre deine Recovery-Codes sicher auf!');
        redirect(base_url('account/security'));
    }

    /**
     * POST /account/security/2fa/cancel
     * Discards a pending TOTP setup without persisting anything.
     */
    public function cancelTotpSetup(array $params = []): void
    {
        $this->requireLogin();
        if (!csrf_verify()) {
            abort(403);
        }

        unset($_SESSION['totp_pending']);

        flash('info', 'TOTP-Einrichtung abgebrochen.');
        redirect(base_url('account/security'));
    }

    /** POST /account/security/2fa/disable */
    public function totpDisable(array $params = []): void
    {
        $this->requireLogin();
        if (!csrf_verify()) {
            abort(403);
        }

        $password = $_POST['password'] ?? '';
        $user     = $this->getCurrentUser();

        if (!password_verify($password, (string) $user['password_hash'])) {
            flash('error', 'Falsches Passwort. 2FA wurde nicht deaktiviert.');
            redirect(base_url('account/security'));
        }

        global $db;
        $hasPasskeys = false;
        try {
            $pkStmt = $db->getPdo()->prepare('SELECT COUNT(*) FROM user_passkeys WHERE user_id = ?');
            $pkStmt->execute([(int) $user['id']]);
            $hasPasskeys = (int) $pkStmt->fetchColumn() > 0;
        } catch (Throwable) {}

        $db->update('users', [
            'totp_secret'    => null,
            'totp_enabled'   => 0,
            'recovery_codes' => $hasPasskeys ? $user['recovery_codes'] : null,
        ], ['id' => $user['id']]);

        flash('success', '2FA wurde deaktiviert.');
        redirect(base_url('account/security'));
    }

    // ==================================================================
    // Passkeys (WebAuthn)
    // ==================================================================

    /**
     * POST /account/security/2fa/passkey  (JSON)
     * Handles register_challenge and register_complete for passkey registration.
     */
    public function passkeyApi(array $params = []): void
    {
        $this->requireLogin();
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
        $user     = $this->getCurrentUser();
        $userId   = (int) $user['id'];
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
                    $stmt = $db->getPdo()->prepare('SELECT credential_id FROM user_passkeys WHERE user_id = ?');
                    $stmt->execute([$userId]);
                    $excludeIds = array_map('base64_decode', $stmt->fetchAll(PDO::FETCH_COLUMN));
                } catch (Throwable) {}

                // Use a stable, deterministic user ID so that re-registration of the
                // same authenticator replaces the old credential instead of adding a
                // new orphan entry in the authenticator (e.g. Vaultwarden).
                $stableUserId = pack('N', $userId) . "\0\0\0\0";  // 8-byte big-endian user ID

                $args = $wa->getCreateArgs(
                    $stableUserId,
                    $user['email'],
                    $user['email'],
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

                echo json_encode(['ok' => true, 'redirect' => base_url('account/security')]);
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
     * POST /account/security/2fa/passkey/confirm
     * Saves a pending passkey to the DB after the user confirms recovery codes are stored.
     */
    public function confirmPasskey(array $params = []): void
    {
        $this->requireLogin();
        if (!csrf_verify()) {
            abort(403);
        }

        $pending = $_SESSION['passkey_pending'] ?? null;
        $codes   = $_SESSION['passkey_pending_codes'] ?? [];

        if (!$pending || empty($pending['credential_id'])) {
            flash('error', 'Keine ausstehende Passkey-Registrierung gefunden.');
            redirect(base_url('account/security'));
        }

        global $db;
        $user   = $this->getCurrentUser();
        $userId = (int) $user['id'];

        try {
            $db->insert('user_passkeys', [
                'user_id'       => $userId,
                'credential_id' => $pending['credential_id'],
                'public_key'    => $pending['public_key'],
                'sign_counter'  => $pending['sign_counter'],
                'device_name'   => $pending['device_name'],
            ]);
            $hashedCodes = array_map(fn($c) => password_hash($c, PASSWORD_BCRYPT), $codes);
            $db->update('users', ['recovery_codes' => json_encode($hashedCodes)], ['id' => $userId]);
        } catch (Throwable $e) {
            flash('error', 'Fehler beim Speichern des Passkeys: ' . $e->getMessage());
            redirect(base_url('account/security'));
        }

        unset($_SESSION['passkey_pending'], $_SESSION['passkey_pending_codes']);

        flash('success', 'Passkey "' . h($pending['device_name']) . '" wurde gespeichert.');
        redirect(base_url('account/security'));
    }

    /**
     * POST /account/security/2fa/passkey/cancel
     * Cancels a pending passkey registration without saving anything.
     */
    public function cancelPendingPasskey(array $params = []): void
    {
        $this->requireLogin();
        if (!csrf_verify()) {
            abort(403);
        }

        unset($_SESSION['passkey_pending'], $_SESSION['passkey_pending_codes']);

        flash('info', 'Passkey-Registrierung abgebrochen.');
        redirect(base_url('account/security'));
    }

    /**
     * POST /account/security/2fa/passkey/delete
     * Deletes a registered passkey by ID.
     * Clears recovery codes if no 2FA method remains.
     */
    public function deletePasskey(array $params = []): void
    {
        $this->requireLogin();
        if (!csrf_verify()) {
            abort(403);
        }

        global $db;
        $user = $this->getCurrentUser();
        $pkId = (int) ($_POST['passkey_id'] ?? 0);

        try {
            $db->getPdo()->prepare('DELETE FROM user_passkeys WHERE id = ? AND user_id = ?')
               ->execute([$pkId, (int) $user['id']]);

            $stmt = $db->getPdo()->prepare('SELECT COUNT(*) FROM user_passkeys WHERE user_id = ?');
            $stmt->execute([(int) $user['id']]);
            $remaining = (int) $stmt->fetchColumn();

            if ($remaining === 0 && empty($user['totp_enabled'])) {
                $db->update('users', ['recovery_codes' => null], ['id' => $user['id']]);
            }
        } catch (Throwable) {}

        flash('success', __('admin.passkey_deleted'));
        redirect(base_url('account/security'));
    }

    /**
     * POST /account/security/2fa/recovery
     * Regenerates recovery codes (requires at least one active 2FA method).
     */
    public function regenerateRecoveryCodes(array $params = []): void
    {
        $this->requireLogin();
        if (!csrf_verify()) {
            abort(403);
        }

        global $db;
        $user = $this->getCurrentUser();

        $hasTOTP     = !empty($user['totp_enabled']);
        $hasPasskeys = false;
        try {
            $stmt = $db->getPdo()->prepare('SELECT COUNT(*) FROM user_passkeys WHERE user_id = ?');
            $stmt->execute([(int) $user['id']]);
            $hasPasskeys = (int) $stmt->fetchColumn() > 0;
        } catch (Throwable) {}

        if (!$hasTOTP && !$hasPasskeys) {
            flash('error', 'Keine 2FA-Methode aktiv. Aktiviere zuerst TOTP oder einen Passkey.');
            redirect(base_url('account/security'));
        }

        $plaintextCodes = $this->generateRecoveryCodes();
        $hashedCodes    = array_map(fn($c) => password_hash($c, PASSWORD_BCRYPT), $plaintextCodes);
        $db->update('users', ['recovery_codes' => json_encode($hashedCodes)], ['id' => $user['id']]);

        $_SESSION['recovery_codes_display'] = $plaintextCodes;
        flash('success', __('admin.recovery_regenerated'));
        redirect(base_url('account/security'));
    }
}
