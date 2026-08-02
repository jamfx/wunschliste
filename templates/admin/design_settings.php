<?php
/**
 * Design settings.
 * Variables: $s (site_settings key=>value array)
 */
$primaryColor = $s['primary_color'] ?? '#4F46E5';
$siteTitle    = $s['site_title']    ?? 'Mitbringliste';
$logoPath     = $s['logo_path']     ?? '';
$imprint      = $s['legal_imprint'] ?? '';
$privacy      = $s['legal_privacy'] ?? '';
$licenseUrl   = $s['license_url']   ?? '';
?>
<div class="admin-layout">
    <?php include BASE_DIR . '/templates/partials/admin_sidebar.php'; ?>

    <div class="admin-content">
        <h1 style="font-size:1.5rem;margin-bottom:1.25rem;">
            <i class="fa-solid fa-palette" aria-hidden="true" style="color:var(--primary-color);"></i>
            <?= h(__('admin.settings_design')) ?>
        </h1>

        <!-- === General design form === -->
        <form method="post" action="<?= h(base_url('admin/settings/design')) ?>" class="card" style="margin-bottom:1.25rem;">
            <?= csrf_field() ?>

            <h2 style="font-size:1rem;margin-bottom:1rem;">Allgemeines</h2>

            <div class="form-group">
                <label class="form-label" for="site_title"><?= h(__('admin.design_site_title')) ?></label>
                <input class="form-input" type="text" id="site_title" name="site_title"
                       value="<?= h($siteTitle) ?>" maxlength="100">
            </div>

            <div class="form-group">
                <label class="form-label" for="default_language"><?= h(__('admin.design_default_language')) ?></label>
                <select class="form-select" id="default_language" name="default_language" style="max-width:16rem;">
                    <?php foreach ($availableLanguages as $code => $name): ?>
                    <option value="<?= h($code) ?>" <?= $code === ($s['default_language'] ?? 'de') ? 'selected' : '' ?>>
                        <?= h($name) ?> (<?= h($code) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-hint"><?= h(__('admin.design_default_language_hint')) ?></p>
            </div>

            <div class="form-group">
                <label class="form-label" for="primaryColor">
                    <?= h(__('admin.design_primary_color')) ?>
                </label>
                <div style="display:flex;align-items:center;gap:.75rem;">
                    <input type="hidden" id="primaryColorHidden" name="primary_color" value="<?= h($primaryColor) ?>">
                    <input type="color" id="primaryColor"
                           value="<?= h($primaryColor) ?>"
                           style="width:3rem;height:2.25rem;border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;padding:.1rem;">
                    <input class="form-input" type="text" id="colorText"
                           value="<?= h($primaryColor) ?>" maxlength="9"
                           style="width:8rem;font-family:monospace;">
                    <span class="color-preview" id="colorPreview"
                          style="background:<?= h($primaryColor) ?>;"></span>
                </div>
                <p class="form-hint">Dieser Farbwert wird als CSS Custom Property gesetzt.</p>
                <p class="form-hint" id="colorContrastWarning" role="status" hidden
                   style="color:var(--warning);"
                   data-contrast-warning-template="<?= h(__('admin.design_contrast_warning')) ?>"></p>
            </div>

            <hr style="border:none;border-top:1px solid var(--border);margin:1rem 0;">

            <div class="form-group">
                <label class="form-label" for="legal_imprint"><?= h(__('admin.design_imprint')) ?></label>
                <textarea class="form-textarea" id="legal_imprint" name="legal_imprint"
                          rows="6" placeholder="<p>Mein Impressum...</p>"><?= h($imprint) ?></textarea>
                <p class="form-hint">HTML erlaubt. Leer lassen um den Impressum-Link im Footer auszublenden.</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="legal_privacy"><?= h(__('admin.design_privacy')) ?></label>
                <textarea class="form-textarea" id="legal_privacy" name="legal_privacy"
                          rows="6" placeholder="<p>Datenschutzerklärung...</p>"><?= h($privacy) ?></textarea>
                <p class="form-hint">HTML erlaubt. Leer lassen um den Datenschutz-Link im Footer auszublenden.</p>
            </div>

            <hr style="border:none;border-top:1px solid var(--border);margin:1rem 0;">

            <div class="form-group">
                <label class="form-label" for="license_url">
                    <i class="fa-brands fa-osi" aria-hidden="true"></i>
                    <?= h(__('admin.design_license_url')) ?>
                </label>
                <p class="text-muted text-sm" style="margin:0 0 .5rem;">
                    <?= h(__('footer.license_text')) ?>
                </p>
                <input class="form-input" type="url" id="license_url" name="license_url"
                       value="<?= h($licenseUrl) ?>" maxlength="500"
                       placeholder="https://www.gnu.org/licenses/gpl-3.0.html">
                <p class="form-hint"><?= h(__('admin.design_license_url_hint')) ?></p>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <?= h(__('common.save')) ?>
            </button>
        </form>

        <!-- === Logo upload === -->
        <div class="card">
            <h2 style="font-size:1rem;margin-bottom:1rem;"><?= h(__('admin.design_logo')) ?></h2>

            <?php if (!empty($logoPath)): ?>
            <div style="margin-bottom:1rem;">
                <p class="text-muted text-sm" style="margin-bottom:.5rem;">Aktuelles Logo:</p>
                <img src="<?= h(base_url($logoPath)) ?>" alt="Logo"
                     style="max-height:80px;max-width:300px;object-fit:contain;border:1px solid var(--border);border-radius:var(--radius-sm);padding:.5rem;margin-bottom:.75rem;">
                <br>
                <form method="post" action="<?= h(base_url('admin/settings/logo/delete')) ?>"
                      onsubmit="return confirm('<?= h(__('admin.design_logo_remove')) ?>?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary" style="color:#dc2626;border-color:#dc2626;">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                        <?= h(__('admin.design_logo_remove')) ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <form method="post" action="<?= h(base_url('admin/settings/logo')) ?>"
                  enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="form-group" style="margin-bottom:.75rem;">
                    <label class="form-label" for="logoUpload"><?= h(__('admin.design_logo_upload')) ?></label>
                    <input type="file" id="logoUpload" name="logo"
                           accept="image/jpeg,image/png"
                           class="form-input" style="padding:.4rem;">
                </div>
                <?php if (!empty($logoPath)): ?>
                <img id="logoPreview" src="" alt="Vorschau" hidden
                     style="max-height:80px;max-width:300px;object-fit:contain;border:1px solid var(--border);border-radius:var(--radius-sm);padding:.5rem;margin-bottom:.75rem;">
                <?php else: ?>
                <img id="logoPreview" src="" alt="Vorschau" hidden
                     style="max-height:80px;max-width:300px;object-fit:contain;border:1px solid var(--border);border-radius:var(--radius-sm);padding:.5rem;margin-bottom:.75rem;">
                <?php endif; ?>
                <button type="submit" class="btn btn-secondary">
                    <i class="fa-solid fa-upload" aria-hidden="true"></i>
                    Logo hochladen
                </button>
            </form>
        </div>

    </div>
</div>
