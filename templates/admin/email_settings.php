<?php
/**
 * Email settings.
 * Variables: $settings (array — email_settings row, may be empty)
 */
$t = $settings['transport']    ?? 'smtp';
$v = fn(string $k) => h($settings[$k] ?? '');
?>
<div class="admin-layout">
    <?php include BASE_DIR . '/templates/partials/admin_sidebar.php'; ?>

    <div class="admin-content">
        <h1 style="font-size:1.5rem;margin-bottom:1.25rem;">
            <i class="fa-solid fa-envelope" aria-hidden="true" style="color:var(--primary-color);"></i>
            <?= h(__('admin.settings_email')) ?>
        </h1>

        <form method="post" action="<?= h(base_url('admin/email')) ?>" class="card" style="max-width:640px;">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="emailTransport"><?= h(__('admin.email_transport')) ?></label>
                <select class="form-select" id="emailTransport" name="transport">
                    <option value="smtp"      <?= $t === 'smtp'       ? 'selected' : '' ?>>SMTP</option>
                    <option value="brevo_api" <?= $t === 'brevo_api'  ? 'selected' : '' ?>>Brevo API</option>
                    <option value="sendmail"  <?= $t === 'sendmail'   ? 'selected' : '' ?>>PHP mail() / Sendmail</option>
                </select>
            </div>

            <!-- SMTP fields -->
            <div data-transport="smtp" style="margin-top:.5rem;">
                <h3 style="font-size:.9375rem;margin-bottom:.875rem;color:var(--text-muted);">SMTP-Verbindung</h3>
                <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                    <div class="form-group" style="flex:1;min-width:200px;">
                        <label class="form-label" for="smtp_host"><?= h(__('admin.email_smtp_host')) ?></label>
                        <input class="form-input" type="text" id="smtp_host" name="smtp_host"
                               value="<?= $v('smtp_host') ?>" placeholder="mail.example.com">
                    </div>
                    <div class="form-group" style="width:7rem;">
                        <label class="form-label" for="smtp_port"><?= h(__('admin.email_smtp_port')) ?></label>
                        <input class="form-input" type="number" id="smtp_port" name="smtp_port"
                               value="<?= h($settings['smtp_port'] ?? 587) ?>"
                               min="1" max="65535">
                    </div>
                </div>
                <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                    <div class="form-group" style="flex:1;min-width:200px;">
                        <label class="form-label" for="smtp_user"><?= h(__('admin.email_smtp_user')) ?></label>
                        <input class="form-input" type="text" id="smtp_user" name="smtp_user"
                               value="<?= $v('smtp_user') ?>" autocomplete="off">
                    </div>
                    <div class="form-group" style="flex:1;min-width:200px;">
                        <label class="form-label" for="smtp_password"><?= h(__('admin.email_smtp_pass')) ?></label>
                        <input class="form-input" type="password" id="smtp_password" name="smtp_password"
                               value="<?= $v('smtp_password') ?>" autocomplete="new-password"
                               placeholder="<?= empty($settings['smtp_password']) ? 'Leer = kein Passwort' : '••••••••' ?>">
                    </div>
                </div>
            </div>

            <!-- Brevo API field -->
            <div data-transport="brevo_api" style="margin-top:.5rem;">
                <h3 style="font-size:.9375rem;margin-bottom:.875rem;color:var(--text-muted);">Brevo-Konfiguration</h3>
                <div class="form-group">
                    <label class="form-label" for="api_key"><?= h(__('admin.email_api_key')) ?></label>
                    <input class="form-input" type="password" id="api_key" name="api_key"
                           value="<?= $v('api_key') ?>" autocomplete="off"
                           placeholder="xkeysib-...">
                </div>
            </div>

            <!-- Common: From fields -->
            <hr style="border:none;border-top:1px solid var(--border);margin:1rem 0;">
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                <div class="form-group" style="flex:1;min-width:200px;">
                    <label class="form-label" for="from_address"><?= h(__('admin.email_from_address')) ?></label>
                    <input class="form-input" type="email" id="from_address" name="from_address"
                           value="<?= $v('from_address') ?>" placeholder="noreply@example.com">
                </div>
                <div class="form-group" style="flex:1;min-width:200px;">
                    <label class="form-label" for="from_name"><?= h(__('admin.email_from_name')) ?></label>
                    <input class="form-input" type="text" id="from_name" name="from_name"
                           value="<?= $v('from_name') ?>" placeholder="Mitbringliste">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:.5rem;">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <?= h(__('common.save')) ?>
            </button>
        </form>

        <!-- Test-E-Mail (separate form to avoid nesting) -->
        <form method="post" action="<?= h(base_url('admin/email/test')) ?>"
              class="card" style="max-width:640px;margin-top:1rem;">
            <?= csrf_field() ?>
            <h3 style="font-size:.9375rem;margin-bottom:.875rem;">
                <i class="fa-solid fa-vial" aria-hidden="true"></i>
                Test-E-Mail senden
            </h3>
            <div style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
                <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0;">
                    <label class="form-label" for="test_email">Empfänger</label>
                    <input class="form-input" type="email" id="test_email" name="test_email"
                           placeholder="test@example.com">
                </div>
                <button type="submit" class="btn btn-secondary" style="margin-bottom:0;">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    <?= h(__('admin.email_test')) ?>
                </button>
            </div>
            <p class="form-hint" style="margin-top:.5rem;">Leer lassen → Test-Mail geht an deine Admin-E-Mail.</p>
        </form>

    </div>
</div>
