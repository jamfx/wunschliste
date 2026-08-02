<?php
/**
 * Language switcher partial.
 * Expects $availableLanguages (array: locale => name) and $currentLocale (string) from layout scope.
 */
if (count($availableLanguages ?? []) < 2) {
    return;
}
?>
<div class="lang-switcher">
    <select id="langSwitcherSelect" aria-label="<?= h(__('app.language_switcher', [])) ?>">
        <?php foreach ($availableLanguages as $code => $name): ?>
        <option value="<?= h($code) ?>" <?= $code === ($currentLocale ?? 'de') ? 'selected' : '' ?>>
            <?= h($name) ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>
