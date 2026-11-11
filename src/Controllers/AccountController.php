<?php
declare(strict_types=1);

/**
 * Public, unauthenticated account lifecycle: accepting an invite (setting
 * the initial password) and the forgot-password flow. Reached via emailed,
 * single-use tokens — no login required, mirrors GuestController's
 * token-based access model.
 */
class AccountController
{
    private function findValidInvite(string $token): ?array
    {
        global $db;
        $invite = $db->fetch(
            'SELECT ui.*, u.email FROM user_invites ui
             JOIN users u ON u.id = ui.user_id
             WHERE ui.token = ? AND ui.used_at IS NULL AND ui.expires_at > ?',
            [$token, date('Y-m-d H:i:s')]
        );
        return $invite ?: null;
    }

    private function findValidReset(string $token): ?array
    {
        global $db;
        $reset = $db->fetch(
            'SELECT * FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > ?',
            [$token, date('Y-m-d H:i:s')]
        );
        return $reset ?: null;
    }

    private function validatePassword(string $password, string $confirm): ?string
    {
        if (strlen($password) < 8) {
            return __('validation.password_min_8');
        }
        if ($password !== $confirm) {
            return __('validation.password_mismatch');
        }
        return null;
    }

    // ==================================================================
    // Invite acceptance
    // ==================================================================

    /** GET /invite/{token} */
    public function acceptInviteForm(array $params = []): void
    {
        $invite = $this->findValidInvite($params['token'] ?? '');
        if (!$invite) {
            render('account/link_invalid', [
                'pageTitle' => __('account.invite_invalid_title'),
                'message'   => __('account.invite_invalid_message'),
            ]);
            return;
        }

        render('account/accept_invite', [
            'pageTitle' => __('account.setup_title'),
            'email'     => $invite['email'],
            'token'     => $params['token'],
        ]);
    }

    /** POST /invite/{token} */
    public function acceptInvite(array $params = []): void
    {
        if (!csrf_verify()) {
            abort(403);
        }

        $token  = $params['token'] ?? '';
        $invite = $this->findValidInvite($token);
        if (!$invite) {
            render('account/link_invalid', [
                'pageTitle' => __('account.invite_invalid_title'),
                'message'   => __('account.invite_invalid_message'),
            ]);
            return;
        }

        $error = $this->validatePassword($_POST['password'] ?? '', $_POST['password_confirm'] ?? '');
        if ($error !== null) {
            flash('error', $error);
            redirect(base_url('invite/' . $token));
        }

        global $db;
        $db->update('users', [
            'password_hash' => password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]),
        ], ['id' => $invite['user_id']]);
        $db->update('user_invites', ['used_at' => date('Y-m-d H:i:s')], ['id' => $invite['id']]);

        flash('success', __('account.setup_done'));
        redirect(base_url('admin/login'));
    }

    // ==================================================================
    // Forgot password
    // ==================================================================

    /** GET /forgot-password */
    public function forgotPasswordForm(array $params = []): void
    {
        render('account/forgot_password', ['pageTitle' => __('account.forgot_title')]);
    }

    /** POST /forgot-password */
    public function forgotPassword(array $params = []): void
    {
        if (!csrf_verify()) {
            abort(403);
        }

        if (!rate_limit_check('password_reset_request', 5, 900)) {
            flash('error', __('validation.rate_limit'));
            redirect(base_url('forgot-password'));
        }
        rate_limit_record('password_reset_request');

        $email = trim($_POST['email'] ?? '');

        global $db;
        $user = $db->fetch("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);

        // Only accounts that have already set a password can be reset — an
        // invited-but-not-yet-activated account needs a fresh invite instead
        // (that's the SuperAdmin's job, not a self-service reset).
        if ($user && $user['password_hash'] !== null) {
            $db->delete('password_resets', ['user_id' => $user['id']]);
            $token = bin2hex(random_bytes(32));
            $db->insert('password_resets', [
                'user_id'    => $user['id'],
                'token'      => $token,
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            ]);

            $emailSettings = $db->fetch('SELECT * FROM email_settings LIMIT 1') ?: [];
            $mailer        = new Mailer($emailSettings);
            $html          = Mailer::renderTemplate('password_reset', [
                'resetUrl' => base_url('reset-password/' . $token),
            ], $GLOBALS['translator']->getLocale());
            @$mailer->send($email, __('mail.password_reset_subject', ['site' => site_title()]), $html);
        }

        // Same message regardless of whether the account exists, to avoid
        // leaking which email addresses have one.
        flash('success', __('account.reset_requested'));
        redirect(base_url('admin/login'));
    }

    // ==================================================================
    // Reset password
    // ==================================================================

    /** GET /reset-password/{token} */
    public function resetPasswordForm(array $params = []): void
    {
        $reset = $this->findValidReset($params['token'] ?? '');
        if (!$reset) {
            render('account/link_invalid', [
                'pageTitle' => __('account.link_invalid_title'),
                'message'   => __('account.link_invalid_message'),
            ]);
            return;
        }

        render('account/reset_password', [
            'pageTitle' => __('account.reset_title'),
            'token'     => $params['token'],
        ]);
    }

    /** POST /reset-password/{token} */
    public function resetPassword(array $params = []): void
    {
        if (!csrf_verify()) {
            abort(403);
        }

        $token = $params['token'] ?? '';
        $reset = $this->findValidReset($token);
        if (!$reset) {
            render('account/link_invalid', [
                'pageTitle' => __('account.link_invalid_title'),
                'message'   => __('account.link_invalid_message'),
            ]);
            return;
        }

        $error = $this->validatePassword($_POST['password'] ?? '', $_POST['password_confirm'] ?? '');
        if ($error !== null) {
            flash('error', $error);
            redirect(base_url('reset-password/' . $token));
        }

        global $db;
        $db->update('users', [
            'password_hash' => password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]),
        ], ['id' => $reset['user_id']]);
        $db->update('password_resets', ['used_at' => date('Y-m-d H:i:s')], ['id' => $reset['id']]);

        flash('success', __('account.password_changed'));
        redirect(base_url('admin/login'));
    }
}
