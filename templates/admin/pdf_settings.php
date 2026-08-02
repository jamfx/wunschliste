<?php
/**
 * PDF export settings.
 * Variables: $s (site_settings key=>value array)
 */
$pageSize    = $s['pdf_page_size']   ?? 'A4';
$orientation = $s['pdf_orientation'] ?? 'portrait';
$fontSize    = $s['pdf_font_size']   ?? '12';
$showLogo    = ($s['pdf_show_logo']  ?? '1') === '1';
$footerText  = $s['pdf_footer_text'] ?? '';
?>
<div class="admin-layout">
    <?php include BASE_DIR . '/templates/partials/admin_sidebar.php'; ?>

    <div class="admin-content">
        <h1 style="font-size:1.5rem;margin-bottom:1.25rem;">
            <i class="fa-solid fa-file-pdf" aria-hidden="true" style="color:var(--primary-color);"></i>
            <?= h(__('admin.settings_pdf')) ?>
        </h1>

        <form method="post" action="<?= h(base_url('admin/pdf-settings')) ?>"
              class="card" style="max-width:580px;">
            <?= csrf_field() ?>

            <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                <div class="form-group" style="flex:1;min-width:160px;">
                    <label class="form-label" for="pdf_page_size"><?= h(__('pdf.page_size')) ?></label>
                    <select class="form-select" id="pdf_page_size" name="pdf_page_size">
                        <option value="A4"     <?= $pageSize === 'A4'     ? 'selected' : '' ?>>A4</option>
                        <option value="A5"     <?= $pageSize === 'A5'     ? 'selected' : '' ?>>A5</option>
                        <option value="Letter" <?= $pageSize === 'Letter' ? 'selected' : '' ?>>Letter</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1;min-width:160px;">
                    <label class="form-label" for="pdf_orientation"><?= h(__('pdf.orientation')) ?></label>
                    <select class="form-select" id="pdf_orientation" name="pdf_orientation">
                        <option value="portrait"  <?= $orientation === 'portrait'  ? 'selected' : '' ?>>
                            <?= h(__('pdf.orientation_portrait')) ?>
                        </option>
                        <option value="landscape" <?= $orientation === 'landscape' ? 'selected' : '' ?>>
                            <?= h(__('pdf.orientation_landscape')) ?>
                        </option>
                    </select>
                </div>
                <div class="form-group" style="width:8rem;">
                    <label class="form-label" for="pdf_font_size"><?= h(__('pdf.font_size')) ?></label>
                    <input class="form-input" type="number" id="pdf_font_size" name="pdf_font_size"
                           value="<?= h($fontSize) ?>" min="8" max="24">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="pdf_footer_text"><?= h(__('pdf.footer_text')) ?></label>
                <input class="form-input" type="text" id="pdf_footer_text" name="pdf_footer_text"
                       value="<?= h($footerText) ?>" maxlength="255"
                       placeholder="z.B. Erstellt mit Mitbringliste">
            </div>

            <div class="form-check">
                <input type="checkbox" id="pdf_show_logo" name="pdf_show_logo"
                       <?= $showLogo ? 'checked' : '' ?>>
                <label for="pdf_show_logo"><?= h(__('pdf.show_logo')) ?></label>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:1rem;">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <?= h(__('common.save')) ?>
            </button>
        </form>

    </div>
</div>
