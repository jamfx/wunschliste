<?php
/**
 * Mitbringliste — Konfiguration
 *
 * Kopiere diese Datei nach config.php und passe die Werte an.
 * config.php wird normalerweise automatisch beim Installations-Setup erstellt.
 *
 * WICHTIG: config.php niemals in public/ ablegen und nicht ins Git einchecken.
 */

$config = [
    // Datenbankverbindung
    'db_host' => 'localhost',       // MySQL-Hostname (meist localhost)
    'db_name' => 'mitbringliste',   // Name der Datenbank
    'db_user' => 'db_benutzer',     // Datenbankbenutzer
    'db_pass' => 'geheim',          // Datenbankpasswort

    // Basis-URL der Anwendung (kein abschließender Slash)
    // Beispiel: 'https://meine-domain.de' oder 'https://meine-domain.de/mitbringliste'
    'app_url' => 'https://example.com',

    // Zufälliges Geheimnis für CSRF und Sessions (wird bei Installation automatisch generiert)
    // Mindestens 64 Zeichen, zufällig, niemals teilen.
    'app_secret' => 'HIER_ZUFAELLIGES_64_ZEICHEN_GEHEIMNIS_EINTRAGEN',
];
