<?php
/**
 * Admin security settings — site-wide only (rate limiting, claim delay,
 * list creation mode). SuperAdmin-only. Personal 2FA (TOTP/Passkeys) is a
 * separate page any logged-in account can reach: templates/account/security.php.
 */
?>
<div class="admin-layout">
    <?php include BASE_DIR . '/templates/partials/admin_sidebar.php'; ?>

    <div class="admin-content">
        <h1 style="font-size:1.5rem;margin-bottom:1.5rem;">
            <i class="fa-solid fa-lock" aria-hidden="true" style="color:var(--primary-color);"></i>
            <?= h(__('admin.settings_security')) ?>
        </h1>

        <!-- ============================================================ -->
        <!-- Listen-Erstellung: offen oder nur für registrierte User       -->
        <!-- ============================================================ -->
        <div class="card" style="margin-top:1.5rem;">
            <h2 style="font-size:1rem;margin-bottom:.375rem;">
                <i class="fa-solid fa-list-check" aria-hidden="true" style="color:var(--primary-color);"></i>
                Listen-Erstellung
            </h2>
            <p class="text-muted text-sm" style="margin-bottom:1.25rem;">
                Legt fest, wer neue Listen erstellen darf. Bereits bestehende Listen sind davon nicht betroffen —
                ohne Besitzer bleiben sie wie bisher allein über den Admin-Link verwaltbar, mit Besitzer braucht
                die Verwaltung weiterhin dessen Login.
            </p>

            <form method="post" action="<?= h(base_url('admin/settings/security')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="list_creation_mode">

                <div class="form-group">
                    <label style="display:flex;align-items:flex-start;gap:.6rem;cursor:pointer;margin-bottom:.75rem;">
                        <input type="radio" name="list_creation_mode" value="open"
                               style="margin-top:.2rem;"
                               <?= $listCreationMode === 'open' ? 'checked' : '' ?>>
                        <span>
                            <strong>Offen für alle</strong><br>
                            <span class="text-muted text-sm">Jeder mit Zugriff auf die Seite kann ohne Login eine neue Liste erstellen (bisheriges Verhalten).</span>
                        </span>
                    </label>
                    <label style="display:flex;align-items:flex-start;gap:.6rem;cursor:pointer;">
                        <input type="radio" name="list_creation_mode" value="restricted"
                               style="margin-top:.2rem;"
                               <?= $listCreationMode === 'restricted' ? 'checked' : '' ?>>
                        <span>
                            <strong>Nur für registrierte User</strong><br>
                            <span class="text-muted text-sm">Eine neue Liste erstellen kann nur, wer eingeloggt ist. Die Liste gehört dann diesem Account.</span>
                        </span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    <?= h(__('common.save')) ?>
                </button>
            </form>
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

    </div>
</div>
