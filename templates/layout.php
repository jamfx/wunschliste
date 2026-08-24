<?php
declare(strict_types=1);

/**
 * Main layout template.
 * Expected variables (set before include or via render() $vars):
 *   $content    string — rendered inner page HTML
 *   $pageTitle  string — browser tab prefix (optional)
 */

global $db, $translator;

// --- Load site settings ---------------------------------------------------
$primaryColor    = '#4F46E5';
$siteTitle       = 'Mitbringliste';
$logoPath        = '';
$legalImprint    = '';
$legalPrivacy    = '';
$licenseUrl      = '';
$availableLanguages = available_languages();
$currentLocale   = $translator ? $translator->getLocale() : 'de';

if ($db !== null) {
    try {
        $rows = $db->fetchAll('SELECT setting_key, setting_value FROM site_settings ORDER BY id ASC');
        $s    = array_column($rows, 'setting_value', 'setting_key');

        $primaryColor = $s['primary_color'] ?? $primaryColor;
        $siteTitle    = $s['site_title']    ?? $siteTitle;
        $logoPath     = $s['logo_path']     ?? '';
        $legalImprint = $s['legal_imprint'] ?? '';
        $legalPrivacy = $s['legal_privacy'] ?? '';
        $licenseUrl   = $s['license_url']   ?? '';
    } catch (PDOException) {
        // Use defaults
    }
}

// Guard CSS injection
if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $primaryColor)) {
    $primaryColor = '#4F46E5';
}

$pageTitle     = $pageTitle ?? $siteTitle;
$flashMessages = get_flash();
?>
<!DOCTYPE html>
<html lang="<?= h($currentLocale) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?><?= ($pageTitle !== $siteTitle) ? ' — ' . h($siteTitle) : '' ?></title>
    <link rel="stylesheet" href="<?= h(base_url('assets/fontawesome/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= h(base_url('assets/css/style.css')) ?>?v=<?= (int) @filemtime(BASE_DIR . '/assets/css/style.css') ?>">
    <style>
        :root {
            --primary-color: <?= $primaryColor ?>;
            --primary-hover: color-mix(in srgb, <?= $primaryColor ?> 82%, black);
        }
    </style>
</head>
<body>

<a class="skip-link" href="#main-content"><?= h(__('common.skip_to_content')) ?></a>

<!-- ================================================================ -->
<!-- Navigation                                                        -->
<!-- ================================================================ -->
<nav class="nav" role="navigation" aria-label="<?= h(__('app.title')) ?>">
    <div class="container nav-inner">
        <a href="<?= h(base_url()) ?>" class="nav-brand" aria-label="<?= h(__('app.title')) ?>">
            <?php if (!empty($logoPath)): ?>
                <img src="<?= h(base_url($logoPath)) ?>"
                     alt="<?= h($siteTitle) ?>"
                     height="28"
                     style="vertical-align:middle;margin-right:.4rem;object-fit:contain;">
            <?php endif; ?>
            <?= h($siteTitle) ?>
        </a>

        <div class="nav-actions">
            <?php include __DIR__ . '/partials/language_switcher.php'; ?>
        </div>
    </div>
</nav>

<!-- ================================================================ -->
<!-- Flash messages                                                    -->
<!-- ================================================================ -->
<?php if (!empty($flashMessages)): ?>
<div class="toast-container" role="region" aria-live="polite">
    <?php foreach ($flashMessages as $msg): ?>
    <div class="alert alert-<?= h($msg['type']) ?>" role="alert" data-auto-dismiss="6000">
        <?= h($msg['message']) ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ================================================================ -->
<!-- Main content                                                      -->
<!-- ================================================================ -->
<main class="container <?= $containerClass ?? '' ?>" id="main-content" tabindex="-1">
    <?= $content ?>
</main>

<!-- ================================================================ -->
<!-- Footer                                                            -->
<!-- ================================================================ -->
<footer class="footer" role="contentinfo">
    <div class="container">
        <span class="text-muted text-sm">&copy; <?= date('Y') ?> <?= h($siteTitle) ?></span>

        <a href="<?= h(base_url('hilfe')) ?>">
            <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
            <?= h(__('help.footer_link')) ?>
        </a>

        <?php if (!empty($legalImprint)): ?>
        <a href="<?= h(base_url('legal/imprint')) ?>">
            <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
            <?= h(__('legal.imprint')) ?>
        </a>
        <?php endif; ?>

        <?php if (!empty($legalPrivacy)): ?>
        <a href="<?= h(base_url('legal/privacy')) ?>">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <?= h(__('legal.privacy')) ?>
        </a>
        <?php endif; ?>

        <span class="text-muted text-sm" style="flex-basis:100%;">
            <i class="fa-brands fa-osi" aria-hidden="true"></i>
            <?php if (!empty($licenseUrl)): ?>
            <a href="<?= h($licenseUrl) ?>" target="_blank" rel="noopener noreferrer"><?= h(__('footer.license_text', ['version' => APP_VERSION])) ?></a>
            <?php else: ?>
            <?= h(__('footer.license_text', ['version' => APP_VERSION])) ?>
            <?php endif; ?>
        </span>
    </div>
</footer>

<!-- ================================================================ -->
<!-- Global confirm modal (replaces native confirm() for destructive   -->
<!-- actions; triggered via [data-confirm] on a form or submit button) -->
<!-- ================================================================ -->
<div class="modal-overlay" data-confirm-modal hidden>
    <div class="modal-box card" role="alertdialog" aria-modal="true" aria-labelledby="confirm-modal-title">
        <h2 id="confirm-modal-title">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" style="color:var(--danger);"></i>
            <?= h(__('common.confirm_title')) ?>
        </h2>
        <p data-confirm-modal-text></p>
        <div style="display:flex;flex-direction:column;gap:.5rem;margin-top:1.25rem;">
            <button type="button" class="btn btn-secondary btn-full" data-confirm-modal-cancel>
                <?= h(__('common.cancel')) ?>
            </button>
            <button type="button" class="btn btn-danger btn-full" data-confirm-modal-proceed>
                <?= h(__('common.confirm')) ?>
            </button>
        </div>
    </div>
</div>

<script src="<?= h(base_url('assets/js/app.js')) ?>?v=<?= (int) @filemtime(BASE_DIR . '/assets/js/app.js') ?>"></script>
</body>
</html>
