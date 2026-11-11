<?php
/**
 * ALTCHA proof-of-work widget (only rendered when enabled in admin settings).
 * Expects $altchaForm: one of AltchaGuard::FORMS.
 * Must be placed inside the <form> it protects (the widget adds the hidden
 * "altcha" field to its form).
 */
if (!AltchaGuard::enabled()) {
    return;
}
$altchaLang   = ($GLOBALS['translator']->getLocale() === 'de') ? 'de' : 'en';
$altchaHintId = 'altcha-hint-' . $altchaForm;
$altchaAsset  = static fn(string $f): string => base_url('assets/js/altcha/' . $f) . '?v=' . (int) @filemtime(BASE_DIR . '/assets/js/altcha/' . $f);
?>
<div class="altcha-field" data-altcha-field style="margin-top:.75rem;">
    <altcha-widget
        challenge="<?= h(base_url('altcha/challenge') . '?f=' . $altchaForm) ?>"
        type="checkbox"
        auto="onfocus"
        language="<?= h($altchaLang) ?>"
        aria-describedby="<?= h($altchaHintId) ?>"></altcha-widget>
    <p class="text-muted text-sm" id="<?= h($altchaHintId) ?>" style="margin:.375rem 0 0;">
        <?= h(__('altcha.hint')) ?>
    </p>
    <p class="text-sm" role="alert" hidden data-altcha-hint="unsupported" style="margin:.375rem 0 0;color:var(--danger);">
        <?= h(__('altcha.unsupported')) ?>
    </p>
    <p class="text-sm" role="alert" hidden data-altcha-hint="error" style="margin:.375rem 0 0;color:var(--danger);">
        <?= h(__('altcha.error')) ?>
    </p>
    <noscript>
        <p class="text-sm" role="alert" style="margin:.375rem 0 0;color:var(--danger);">
            <?= h(__('altcha.noscript')) ?>
        </p>
    </noscript>
</div>
<?php if (empty($GLOBALS['altcha_assets_loaded'])): $GLOBALS['altcha_assets_loaded'] = true; ?>
<link rel="stylesheet" href="<?= h($altchaAsset('altcha.css')) ?>">
<script type="module" src="<?= h($altchaAsset('altcha.min.js')) ?>"></script>
<script type="module" src="<?= h($altchaAsset('i18n-de.js')) ?>"></script>
<script type="module" src="<?= h($altchaAsset('altcha-init.js')) ?>"></script>
<?php endif; ?>
