<?php
/**
 * Personal account security — TOTP, Passkeys, recovery codes. Available to
 * any logged-in account (SuperAdmin included, managing their own 2FA here
 * too — site-wide settings live separately under /admin/settings/security).
 * Variables:
 *   $admin          array (the current user row — kept as 'admin' key to
 *                   match the shared markup this page was extracted from)
 *   $qrSvg          string|null     — inline SVG for QR code (null if already enabled)
 *   $pendingSecret  string|null     — Base32 secret to display
 *   $recoveryCodes  array|null      — plaintext codes shown exactly once
 *   $passkeys       array           — registered passkeys from DB
 *   $recoveryCount  int             — remaining (unused) recovery codes
 */
$backUrl = base_url(($_SESSION['user_role'] ?? '') === 'superadmin' ? 'admin/dashboard' : 'my/lists');
?>
<div style="max-width:640px;margin-inline:auto;">

    <div style="margin-bottom:.75rem;">
        <a href="<?= h($backUrl) ?>" class="text-sm" style="color:var(--text-muted);">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <?= h(__('admin.back_to_overview')) ?>
        </a>
    </div>

    <h1 style="font-size:1.5rem;margin-bottom:1.5rem;">
        <i class="fa-solid fa-shield-halved" aria-hidden="true" style="color:var(--primary-color);"></i>
        Meine Sicherheit
    </h1>

    <!-- Config element for passkey.js (no inline JS, CSP-compliant) -->
    <div id="passkey-config" hidden
         data-csrf="<?= h($_SESSION['csrf_token']) ?>"
         data-api-url="<?= h(base_url('account/security/2fa/passkey')) ?>"
         data-copy-success="<?= h(__('admin.recovery_copy_success')) ?>"
         data-not-supported="<?= h(__('admin.passkey_not_supported')) ?>">
    </div>

    <?php if (!empty($pendingPasskeyConfirm) && !empty($recoveryCodes)): ?>
    <!-- ============================================================ -->
    <!-- Passkey-Bestätigung: Passkey erst nach Bestätigung gespeichert -->
    <!-- ============================================================ -->
    <div class="card" style="margin-bottom:1.5rem;border:2px solid var(--warning,#f59e0b);">
        <div style="text-align:center;margin-bottom:1rem;">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"
               style="font-size:2rem;color:var(--warning,#f59e0b);"></i>
            <h2 style="font-size:1rem;margin:.5rem 0 .25rem;"><?= h(__('admin.recovery_new_title')) ?></h2>
            <p class="text-muted text-sm">
                Dein Passkey wird erst gespeichert, nachdem du die Notfallcodes gesichert hast.
            </p>
        </div>

        <div class="alert alert-warning" style="margin-bottom:1rem;">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <strong>Wichtig:</strong> <?= h(__('admin.recovery_new_hint')) ?>
        </div>

        <div id="new-codes-grid"
             style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.5rem;margin-bottom:1rem;"
             data-codes="<?= h(json_encode($recoveryCodes)) ?>">
            <?php foreach ($recoveryCodes as $code): ?>
            <code style="display:block;padding:.5rem .75rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);font-size:.9375rem;text-align:center;letter-spacing:.1em;">
                <?= h($code) ?>
            </code>
            <?php endforeach; ?>
        </div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem;">
            <button type="button" id="btn-copy-new-codes" class="btn btn-secondary btn-sm"
                    data-success-msg="<?= h(__('admin.recovery_copy_success')) ?>">
                <i class="fa-regular fa-copy" aria-hidden="true"></i>
                <?= h(__('admin.recovery_copy')) ?>
            </button>
            <button type="button" id="btn-dl-new-codes" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-download" aria-hidden="true"></i>
                <?= h(__('admin.recovery_download')) ?>
            </button>
        </div>

        <form method="post" action="<?= h(base_url('account/security/2fa/passkey/confirm')) ?>">
            <?= csrf_field() ?>
            <label style="display:flex;align-items:flex-start;gap:.6rem;cursor:pointer;margin-bottom:1rem;">
                <input type="checkbox" name="confirmed" value="1" required
                       style="margin-top:.2rem;width:1rem;height:1rem;flex-shrink:0;">
                <span class="text-sm">
                    Ich habe die Notfallcodes sicher gespeichert und verstehe,
                    dass sie nur einmal angezeigt werden.
                </span>
            </label>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Passkey speichern
                </button>
                <button type="submit" form="form-cancel-passkey" class="btn btn-secondary">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    Abbrechen
                </button>
            </div>
        </form>
        <form id="form-cancel-passkey" method="post"
              action="<?= h(base_url('account/security/2fa/passkey/cancel')) ?>" style="display:none;">
            <?= csrf_field() ?>
        </form>
    </div>

    <?php elseif (!empty($pendingTotpConfirm) && !empty($totpPendingCodes)): ?>
    <!-- ============================================================ -->
    <!-- TOTP-Bestätigung: Secret erst nach Bestätigung gespeichert    -->
    <!-- ============================================================ -->
    <div class="card" style="margin-bottom:1.5rem;border:2px solid var(--warning,#f59e0b);">
        <div style="text-align:center;margin-bottom:1rem;">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"
               style="font-size:2rem;color:var(--warning,#f59e0b);"></i>
            <h2 style="font-size:1rem;margin:.5rem 0 .25rem;"><?= h(__('admin.recovery_new_title')) ?></h2>
            <p class="text-muted text-sm">
                TOTP wird erst aktiviert, nachdem du die Notfallcodes gesichert
                und mit einem Code aus deiner App bestätigt hast.
            </p>
        </div>

        <div class="alert alert-warning" style="margin-bottom:1rem;">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <strong>Wichtig:</strong> <?= h(__('admin.recovery_new_hint')) ?>
        </div>

        <div id="new-codes-grid"
             style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.5rem;margin-bottom:1rem;"
             data-codes="<?= h(json_encode($totpPendingCodes)) ?>">
            <?php foreach ($totpPendingCodes as $code): ?>
            <code style="display:block;padding:.5rem .75rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);font-size:.9375rem;text-align:center;letter-spacing:.1em;">
                <?= h($code) ?>
            </code>
            <?php endforeach; ?>
        </div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem;">
            <button type="button" id="btn-copy-new-codes" class="btn btn-secondary btn-sm"
                    data-success-msg="<?= h(__('admin.recovery_copy_success')) ?>">
                <i class="fa-regular fa-copy" aria-hidden="true"></i>
                <?= h(__('admin.recovery_copy')) ?>
            </button>
            <button type="button" id="btn-dl-new-codes" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-download" aria-hidden="true"></i>
                <?= h(__('admin.recovery_download')) ?>
            </button>
        </div>

        <form method="post" action="<?= h(base_url('account/security/2fa/confirm')) ?>">
            <?= csrf_field() ?>
            <label style="display:flex;align-items:flex-start;gap:.6rem;cursor:pointer;margin-bottom:1rem;">
                <input type="checkbox" name="confirmed" value="1" required
                       style="margin-top:.2rem;width:1rem;height:1rem;flex-shrink:0;">
                <span class="text-sm">
                    Ich habe die Notfallcodes sicher gespeichert und verstehe,
                    dass sie nur einmal angezeigt werden.
                </span>
            </label>
            <div class="form-group">
                <label class="form-label" for="totp_confirm_code"><?= h(__('admin.totp_code')) ?></label>
                <input class="form-input" type="text" id="totp_confirm_code" name="totp_code"
                       inputmode="numeric"
                       maxlength="6"
                       autocomplete="one-time-code"
                       required
                       placeholder="000000"
                       style="width:10rem;text-align:center;letter-spacing:.25em;font-size:1.25rem;">
                <p class="form-hint">Gib den 6-stelligen Code aus deiner Authenticator-App ein, um die Einrichtung abzuschließen.</p>
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Bestätigen und aktivieren
                </button>
                <button type="submit" form="form-cancel-totp" class="btn btn-secondary">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    Abbrechen
                </button>
            </div>
        </form>
        <form id="form-cancel-totp" method="post"
              action="<?= h(base_url('account/security/2fa/cancel')) ?>" style="display:none;">
            <?= csrf_field() ?>
        </form>
    </div>

    <?php elseif (!empty($recoveryCodes)): ?>
    <!-- Recovery codes shown ONCE after TOTP enable or code regeneration -->
    <div class="alert alert-warning" style="margin-bottom:1rem;">
        <strong><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <?= h(__('admin.recovery_new_title')) ?></strong><br>
        <?= h(__('admin.recovery_new_hint')) ?>
    </div>
    <div class="card" style="margin-bottom:1.5rem;">
        <h2 style="font-size:1rem;margin-bottom:1rem;"><?= h(__('admin.recovery_new_title')) ?></h2>
        <div id="new-codes-grid"
             style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.5rem;margin-bottom:1rem;"
             data-codes="<?= h(json_encode($recoveryCodes)) ?>">
            <?php foreach ($recoveryCodes as $code): ?>
            <code style="display:block;padding:.5rem .75rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);font-size:.9375rem;text-align:center;letter-spacing:.1em;">
                <?= h($code) ?>
            </code>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.75rem;">
            <button type="button" id="btn-copy-new-codes" class="btn btn-secondary btn-sm"
                    data-success-msg="<?= h(__('admin.recovery_copy_success')) ?>">
                <i class="fa-regular fa-copy" aria-hidden="true"></i>
                <?= h(__('admin.recovery_copy')) ?>
            </button>
            <button type="button" id="btn-dl-new-codes" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-download" aria-hidden="true"></i>
                <?= h(__('admin.recovery_download')) ?>
            </button>
        </div>
        <p class="text-muted text-sm">
            Jeder Code kann nur einmal verwendet werden. Verbrauchte Codes werden automatisch entfernt.
        </p>
    </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- TOTP-Abschnitt                                                -->
    <!-- ============================================================ -->
    <div class="card" style="margin-bottom:1.5rem;">
        <h2 style="font-size:1rem;margin-bottom:1rem;">
            <i class="fa-solid fa-mobile-screen-button" aria-hidden="true" style="color:var(--primary-color);margin-right:.4em;"></i>
            TOTP-Authentifizierung
        </h2>

        <?php if ($admin['totp_enabled']): ?>
        <div class="alert alert-success" style="margin-bottom:1rem;">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <strong>TOTP ist aktiv.</strong>
            Bei jedem Login wirst du nach einem 6-stelligen Code gefragt.
        </div>

        <details>
            <summary style="cursor:pointer;color:var(--danger);font-size:.9375rem;font-weight:600;">
                <i class="fa-solid fa-shield-xmark" aria-hidden="true"></i>
                TOTP deaktivieren
            </summary>
            <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);">
                <?php if (!empty($passkeys)): ?>
                <p class="text-muted text-sm" style="margin-bottom:.875rem;">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    Deine Passkeys und Notfallcodes bleiben erhalten.
                </p>
                <?php else: ?>
                <p class="text-muted text-sm" style="margin-bottom:.875rem;">
                    Bestätige mit deinem Passwort um TOTP zu deaktivieren.
                </p>
                <?php endif; ?>
                <form method="post" action="<?= h(base_url('account/security/2fa/disable')) ?>">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label class="form-label" for="disable_password"><?= h(__('admin.password')) ?></label>
                        <input class="form-input" type="password" id="disable_password" name="password"
                               autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-danger"
                            data-confirm="TOTP wirklich deaktivieren? Dein Account wird weniger sicher.">
                        <i class="fa-solid fa-shield-xmark" aria-hidden="true"></i>
                        TOTP deaktivieren
                    </button>
                </form>
            </div>
        </details>

        <?php elseif (!empty($pendingTotpConfirm)): ?>
        <div class="alert alert-warning" style="margin-bottom:0;">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            Schließe die Einrichtung oben ab, um TOTP zu aktivieren.
        </div>

        <?php else: ?>
        <p class="text-muted text-sm" style="margin-bottom:.875rem;">
            Scanne den QR-Code mit einer Authenticator-App (z.B. Google Authenticator,
            Authy, Bitwarden) und klicke dann auf "TOTP aktivieren".
        </p>

        <?php if ($qrSvg): ?>
        <div style="margin-bottom:1.25rem;text-align:center;">
            <div style="display:inline-block;background:#fff;padding:.75rem;border-radius:var(--radius);border:1px solid var(--border);">
                <?= $qrSvg ?>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning" style="margin-bottom:1.25rem;">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            QR-Code konnte nicht gerendert werden.
            Composer-Paket <code>endroid/qr-code</code> prüfen.
        </div>
        <?php endif; ?>

        <?php if ($pendingSecret): ?>
        <div class="form-group" style="margin-bottom:1.25rem;">
            <label class="form-label">Manueller Schlüssel (Base32)</label>
            <div style="display:flex;align-items:center;gap:.5rem;">
                <input type="text" class="form-input" readonly
                       value="<?= h(implode(' ', str_split($pendingSecret, 4))) ?>"
                       data-select-on-click
                       style="font-family:monospace;font-size:.875rem;letter-spacing:.05em;">
                <button type="button" class="btn btn-secondary btn-sm"
                        data-copy-value="<?= h($pendingSecret) ?>"
                        data-success-msg="Kopiert!">
                    <i class="fa-solid fa-copy" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <form method="post" action="<?= h(base_url('account/security/2fa')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                TOTP aktivieren
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- ============================================================ -->
    <!-- Passkeys-Abschnitt                                            -->
    <!-- ============================================================ -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem;">
            <h2 style="font-size:1rem;margin:0;">
                <i class="fa-solid fa-key" aria-hidden="true" style="color:var(--primary-color);margin-right:.4em;"></i>
                <?= h(__('admin.passkey_title')) ?>
            </h2>
            <button type="button" id="btn-add-passkey" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <?= h(__('admin.passkey_add')) ?>
            </button>
        </div>

        <p id="passkey-unsupported" style="display:none;" class="text-muted text-sm">
            <?= h(__('admin.passkey_not_supported')) ?>
        </p>
        <p id="passkey-error" style="display:none;color:var(--danger);" class="text-sm" role="alert"></p>

        <?php if (empty($passkeys)): ?>
        <p class="text-muted text-sm"><?= h(__('admin.passkey_none')) ?></p>
        <?php else: ?>
        <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.5rem;">
            <?php foreach ($passkeys as $pk): ?>
            <li style="display:flex;align-items:center;justify-content:space-between;padding:.6rem .75rem;border-radius:var(--radius-sm);background:var(--bg-alt,var(--bg));">
                <span>
                    <i class="fa-solid fa-key" aria-hidden="true" style="opacity:.45;margin-right:.4rem;"></i>
                    <strong><?= h($pk['device_name']) ?></strong>
                    <small class="text-muted" style="margin-left:.5rem;"><?= h(substr($pk['created_at'], 0, 10)) ?></small>
                </span>
                <form method="post" action="<?= h(base_url('account/security/2fa/passkey/delete')) ?>" style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="passkey_id" value="<?= (int) $pk['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm"
                            style="color:var(--danger);border-color:var(--danger);"
                            data-confirm="<?= h(__('admin.passkey_delete_confirm', ['name' => $pk['device_name']])) ?>">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                        Löschen
                    </button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <!-- Modal: Passkey benennen -->
    <div id="passkey-name-modal"
         style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;"
         role="dialog" aria-modal="true" aria-labelledby="pk-modal-title">
        <div style="background:var(--card-bg,#fff);border-radius:var(--radius);padding:1.5rem;max-width:400px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.2);">
            <h2 id="pk-modal-title" style="font-size:1rem;margin-bottom:.5rem;"><?= h(__('admin.passkey_name_title')) ?></h2>
            <p class="text-muted text-sm" style="margin-bottom:1rem;"><?= h(__('admin.passkey_name_hint')) ?></p>
            <div class="form-group">
                <label class="form-label" for="pk-name-input"><?= h(__('admin.passkey_name_label')) ?></label>
                <input class="form-input" type="text" id="pk-name-input" maxlength="100" autocomplete="off"
                       placeholder="<?= h(__('admin.passkey_name_placeholder')) ?>">
            </div>
            <div style="display:flex;gap:.5rem;margin-top:1rem;">
                <button type="button" id="pk-name-ok" class="btn btn-primary"><?= h(__('common.confirm')) ?></button>
                <button type="button" id="pk-name-cancel" class="btn btn-secondary"><?= h(__('common.cancel')) ?></button>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- Notfallcodes-Abschnitt                                        -->
    <!-- ============================================================ -->
    <div class="card">
        <h2 style="font-size:1rem;margin-bottom:.75rem;">
            <i class="fa-solid fa-life-ring" aria-hidden="true" style="color:var(--primary-color);margin-right:.4em;"></i>
            <?= h(__('admin.recovery_section')) ?>
        </h2>

        <?php $has2fa = !empty($admin['totp_enabled']) || !empty($passkeys); ?>
        <?php if (!$has2fa): ?>
        <p class="text-muted text-sm"><?= h(__('admin.recovery_no_2fa')) ?></p>
        <?php else: ?>

        <?php if ($recoveryCount === 0): ?>
        <p class="text-muted text-sm" style="margin-bottom:1rem;"><?= h(__('admin.recovery_none')) ?></p>
        <?php else: ?>
        <p class="text-muted text-sm" style="margin-bottom:1rem;">
            <i class="fa-solid fa-key" aria-hidden="true"></i>
            <?= h(__('admin.recovery_count', ['count' => $recoveryCount, 'total' => 8])) ?>
        </p>
        <?php endif; ?>

        <form method="post" action="<?= h(base_url('account/security/2fa/recovery')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary"
                    <?= $recoveryCount > 0
                        ? 'data-confirm="' . h(__('admin.recovery_regenerate_confirm')) . '"'
                        : '' ?>>
                <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                <?= h($recoveryCount === 0 ? __('admin.recovery_generate') : __('admin.recovery_regenerate')) ?>
            </button>
        </form>
        <p class="text-muted text-sm" style="margin-top:.75rem;">
            Notfallcodes können anstelle von TOTP oder Passkey verwendet werden.
            Jeder Code ist nur einmal gültig.
        </p>
        <?php endif; ?>
    </div>

</div>
<script src="<?= h(base_url('assets/js/passkey.js')) ?>?v=<?= (int) @filemtime(BASE_DIR . '/assets/js/passkey.js') ?>"></script>
