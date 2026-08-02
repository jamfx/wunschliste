<?php
/**
 * Admin security settings — rate limiting, claim delay, and 2FA
 * (TOTP, Passkeys, Recovery codes).
 * Additional variables used by the 2FA section below:
 *   $admin          array
 *   $qrSvg          string|null     — inline SVG for QR code (null if already enabled)
 *   $pendingSecret  string|null     — Base32 secret to display
 *   $recoveryCodes  array|null      — plaintext codes shown exactly once
 *   $passkeys       array           — registered passkeys from DB
 *   $recoveryCount  int             — remaining (unused) recovery codes
 */
?>
<div class="admin-layout">
    <?php include BASE_DIR . '/templates/partials/admin_sidebar.php'; ?>

    <div class="admin-content">
        <h1 style="font-size:1.5rem;margin-bottom:1.5rem;">
            <i class="fa-solid fa-lock" aria-hidden="true" style="color:var(--primary-color);"></i>
            <?= h(__('admin.settings_security')) ?>
        </h1>

        <!-- Config element for passkey.js (no inline JS, CSP-compliant) -->
        <div id="passkey-config" hidden
             data-csrf="<?= h($_SESSION['csrf_token']) ?>"
             data-api-url="<?= h(base_url('admin/settings/2fa/passkey')) ?>"
             data-copy-success="<?= h(__('admin.recovery_copy_success')) ?>"
             data-not-supported="<?= h(__('admin.passkey_not_supported')) ?>">
        </div>

        <!-- ============================================================ -->
        <!-- Rate-Limit-Einstellungen                                      -->
        <!-- ============================================================ -->
        <div class="card" style="margin-top:1.5rem;">
            <h2 style="font-size:1rem;margin-bottom:.375rem;">
                <i class="fa-solid fa-gauge-high" aria-hidden="true" style="color:var(--primary-color);"></i>
                <?= h(__('admin.rate_limits_title')) ?>
            </h2>
            <p class="text-muted text-sm" style="margin-bottom:1.25rem;"><?= h(__('admin.rate_limits_desc')) ?></p>

            <form method="post" action="<?= h(base_url('admin/settings/security')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="rate_limits">

                <!-- Admin-Login -->
                <p style="font-weight:600;font-size:.9rem;margin-bottom:.625rem;border-bottom:1px solid var(--border);padding-bottom:.375rem;">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                    <?= h(__('admin.rate_login_section')) ?>
                </p>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem;">
                    <div class="form-group" style="flex:1;min-width:160px;margin-bottom:0;">
                        <label class="form-label" for="rate_login_max_attempts"><?= h(__('admin.rate_login_attempts')) ?></label>
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <input class="form-input" type="number" id="rate_login_max_attempts"
                                   name="rate_login_max_attempts"
                                   value="<?= (int) ($s['rate_login_max_attempts'] ?? 5) ?>"
                                   min="1" max="20" style="width:5.5rem;">
                            <span class="text-muted text-sm"><?= h(__('admin.rate_unit_attempts')) ?></span>
                        </div>
                    </div>
                    <div class="form-group" style="flex:1;min-width:160px;margin-bottom:0;">
                        <label class="form-label" for="rate_login_window_minutes"><?= h(__('admin.rate_login_window')) ?></label>
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <input class="form-input" type="number" id="rate_login_window_minutes"
                                   name="rate_login_window_minutes"
                                   value="<?= (int) ($s['rate_login_window_minutes'] ?? 15) ?>"
                                   min="1" max="60" style="width:5.5rem;">
                            <span class="text-muted text-sm"><?= h(__('admin.rate_unit_minutes')) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Event-Erstellung -->
                <p style="font-weight:600;font-size:.9rem;margin-bottom:.625rem;border-bottom:1px solid var(--border);padding-bottom:.375rem;">
                    <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
                    <?= h(__('admin.rate_event_section')) ?>
                </p>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem;">
                    <div class="form-group" style="flex:1;min-width:160px;margin-bottom:0;">
                        <label class="form-label" for="rate_event_max_count"><?= h(__('admin.rate_event_max')) ?></label>
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <input class="form-input" type="number" id="rate_event_max_count"
                                   name="rate_event_max_count"
                                   value="<?= (int) ($s['rate_event_max_count'] ?? 10) ?>"
                                   min="1" max="100" style="width:5.5rem;">
                            <span class="text-muted text-sm"><?= h(__('admin.rate_unit_events')) ?></span>
                        </div>
                    </div>
                    <div class="form-group" style="flex:1;min-width:160px;margin-bottom:0;">
                        <label class="form-label" for="rate_event_window_minutes"><?= h(__('admin.rate_event_window')) ?></label>
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <input class="form-input" type="number" id="rate_event_window_minutes"
                                   name="rate_event_window_minutes"
                                   value="<?= (int) ($s['rate_event_window_minutes'] ?? 60) ?>"
                                   min="1" max="1440" style="width:5.5rem;">
                            <span class="text-muted text-sm"><?= h(__('admin.rate_unit_minutes')) ?></span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    <?= h(__('common.save')) ?>
                </button>
            </form>
        </div>

        <!-- ============================================================ -->
        <!-- Bedenkzeit für Übernahmen                                     -->
        <!-- ============================================================ -->
        <div class="card" style="margin-top:1.5rem;">
            <h2 style="font-size:1rem;margin-bottom:.375rem;">
                <i class="fa-solid fa-hourglass-half" aria-hidden="true" style="color:var(--primary-color);"></i>
                <?= h(__('admin.claim_delay_title')) ?>
            </h2>
            <p class="text-muted text-sm" style="margin-bottom:1.25rem;"><?= h(__('admin.claim_delay_desc')) ?></p>

            <form method="post" action="<?= h(base_url('admin/settings/security')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="claim_delay">

                <div class="form-group" style="max-width:220px;">
                    <label class="form-label" for="claim_finalize_delay_minutes"><?= h(__('admin.claim_delay_label')) ?></label>
                    <div style="display:flex;align-items:center;gap:.5rem;">
                        <input class="form-input" type="number" id="claim_finalize_delay_minutes"
                               name="claim_finalize_delay_minutes"
                               value="<?= (int) ($s['claim_finalize_delay_minutes'] ?? 10) ?>"
                               min="1" max="1440" style="width:5.5rem;">
                        <span class="text-muted text-sm"><?= h(__('admin.rate_unit_minutes')) ?></span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    <?= h(__('common.save')) ?>
                </button>
            </form>
        </div>

        <!-- ============================================================ -->
        <!-- Zwei-Faktor-Authentifizierung                                 -->
        <!-- ============================================================ -->
        <h2 style="font-size:1.25rem;margin:2rem 0 1rem;padding-top:1rem;border-top:1px solid var(--border);">
            <i class="fa-solid fa-shield-halved" aria-hidden="true" style="color:var(--primary-color);"></i>
            <?= h(__('admin.settings_2fa')) ?>
        </h2>

        <?php if (!empty($pendingPasskeyConfirm) && !empty($recoveryCodes)): ?>
        <!-- ============================================================ -->
        <!-- Passkey-Bestätigung: Passkey erst nach Bestätigung gespeichert -->
        <!-- ============================================================ -->
        <div class="card" style="max-width:520px;margin-bottom:1.5rem;border:2px solid var(--warning,#f59e0b);">
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

            <form method="post" action="<?= h(base_url('admin/settings/2fa/passkey/confirm')) ?>">
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
                  action="<?= h(base_url('admin/settings/2fa/passkey/cancel')) ?>" style="display:none;">
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
        <div class="card" style="max-width:520px;margin-bottom:1.5rem;">
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
                    <form method="post" action="<?= h(base_url('admin/settings/2fa/disable')) ?>">
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

            <?php else: ?>
            <p class="text-muted text-sm" style="margin-bottom:.875rem;">
                Scanne den QR-Code mit einer Authenticator-App (z.B. Google Authenticator,
                Authy, Bitwarden) und bestätige dann mit dem generierten Code.
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

            <form method="post" action="<?= h(base_url('admin/settings/2fa')) ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="totp_code"><?= h(__('admin.totp_code')) ?></label>
                    <input class="form-input" type="text" id="totp_code" name="totp_code"
                           inputmode="numeric"
                           maxlength="6"
                           autocomplete="one-time-code"
                           required
                           placeholder="000000"
                           style="width:10rem;text-align:center;letter-spacing:.25em;font-size:1.25rem;">
                    <p class="form-hint">Gib den 6-stelligen Code aus der App ein um die Einrichtung abzuschließen.</p>
                </div>
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
        <div class="card" style="max-width:520px;margin-bottom:1.5rem;">
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
                    <form method="post" action="<?= h(base_url('admin/settings/2fa/passkey/delete')) ?>" style="margin:0;">
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
        <div class="card" style="max-width:520px;">
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

            <form method="post" action="<?= h(base_url('admin/settings/2fa/recovery')) ?>">
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
</div>
<script src="<?= h(base_url('assets/js/passkey.js')) ?>?v=<?= (int) @filemtime(BASE_DIR . '/assets/js/passkey.js') ?>"></script>
