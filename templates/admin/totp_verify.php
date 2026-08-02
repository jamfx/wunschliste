<?php
/**
 * 2FA verification step during login.
 * Variables:
 *   $hasTOTP     bool — TOTP is enabled for this admin
 *   $hasPasskeys bool — at least one passkey is registered
 *   $hasRecovery bool — recovery codes are available
 */
$hasTOTP     = $hasTOTP     ?? false;
$hasPasskeys = $hasPasskeys ?? false;
$hasRecovery = $hasRecovery ?? false;
?>
<div style="max-width:420px;margin-inline:auto;padding-top:3rem;">

    <div style="text-align:center;margin-bottom:2rem;">
        <i class="fa-solid fa-shield-halved"
           aria-hidden="true"
           style="font-size:2.5rem;color:var(--primary-color);"></i>
        <h1 style="font-size:1.5rem;margin-top:.5rem;"><?= h(__('admin.totp_title')) ?></h1>
    </div>

    <div class="card">

        <?php if ($hasTOTP): ?>
        <p class="text-muted text-sm" style="margin-bottom:1.25rem;text-align:center;">
            Gib den 6-stelligen Code aus deiner Authenticator-App ein.
        </p>

        <form method="post" action="<?= h(base_url('admin/totp')) ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="totp_code"><?= h(__('admin.totp_code')) ?></label>
                <input class="form-input"
                       type="text" id="totp_code" name="totp_code"
                       inputmode="numeric"
                       maxlength="8"
                       autocomplete="one-time-code"
                       required autofocus
                       placeholder="000000"
                       style="text-align:center;letter-spacing:.3em;font-size:1.5rem;">
            </div>

            <button type="submit" class="btn btn-primary btn-full">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                <?= h(__('admin.totp_verify')) ?>
            </button>
        </form>
        <?php endif; ?>

        <?php if ($hasPasskeys): ?>
        <div style="<?= $hasTOTP ? 'margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--border);' : '' ?>">
            <?php if ($hasTOTP): ?>
            <p class="text-muted text-sm" style="text-align:center;margin-bottom:.75rem;">
                <?= h(__('admin.passkey_or')) ?>
            </p>
            <?php else: ?>
            <p class="text-muted text-sm" style="margin-bottom:.75rem;">
                <?= h(__('admin.passkey_login_hint')) ?>
            </p>
            <?php endif; ?>

            <button type="button" id="btn-passkey-auth" class="btn btn-secondary btn-full">
                <i class="fa-solid fa-key" aria-hidden="true"></i>
                <?= h(__('admin.passkey_login_button')) ?>
            </button>
            <p id="passkey-auth-error" style="display:none;margin-top:.5rem;font-size:.875rem;color:var(--danger);" role="alert"></p>
        </div>
        <?php endif; ?>

        <?php if ($hasRecovery): ?>
        <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--border);">
            <p class="text-muted text-sm" style="margin-bottom:.75rem;text-align:center;">
                <i class="fa-solid fa-life-ring" aria-hidden="true"></i>
                Kein Zugriff auf deinen zweiten Faktor?
            </p>
            <form method="post" action="<?= h(base_url('admin/totp')) ?>" autocomplete="off" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="is_recovery" value="1">
                <div class="form-group">
                    <label class="form-label" for="recovery_code_input">Notfallcode</label>
                    <input class="form-input" type="text" id="recovery_code_input" name="totp_code"
                           maxlength="8" placeholder="XXXXXXXX"
                           style="text-transform:uppercase;letter-spacing:.15em;font-family:monospace;">
                </div>
                <button type="submit" class="btn btn-secondary btn-full">
                    <i class="fa-solid fa-key" aria-hidden="true"></i>
                    Notfallcode einlösen
                </button>
            </form>
        </div>
        <?php elseif (!$hasTOTP && !$hasPasskeys): ?>
        <p class="text-muted text-sm" style="text-align:center;">
            Keine 2FA-Methode verfügbar.
        </p>
        <?php endif; ?>

    </div>

    <p style="text-align:center;margin-top:1rem;">
        <a href="<?= h(base_url('admin/login')) ?>" class="text-muted text-sm">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Zurück zum Login
        </a>
    </p>

</div>

<?php if ($hasPasskeys): ?>
<div id="passkey-auth-config" hidden
     data-csrf="<?= h($_SESSION['csrf_token']) ?>"
     data-auth-url="<?= h(base_url('admin/settings/2fa/passkey-auth')) ?>"
     data-not-supported="<?= h(__('admin.passkey_not_supported')) ?>">
</div>
<script src="<?= h(base_url('assets/js/passkey.js')) ?>?v=<?= (int) @filemtime(BASE_DIR . '/assets/js/passkey.js') ?>"></script>
<?php endif; ?>
