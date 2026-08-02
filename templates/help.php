<?php
/**
 * Hilfe- & FAQ-Seite für Gäste und Eventersteller.
 * Statischer Inhalt (kein site_settings-Feld) — Sprache folgt der aktuell
 * gewählten Frontend-Sprache. Neue Sprache: templates/partials/help_{locale}.php
 * anlegen, sonst greift der Deutsch-Fallback.
 * Variables: $helpLocale (string)
 */
$partial = BASE_DIR . '/templates/partials/help_' . ($helpLocale ?? 'de') . '.php';
if (!file_exists($partial)) {
    $partial = BASE_DIR . '/templates/partials/help_de.php';
}
include $partial;
