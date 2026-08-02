# Wunschliste

Ein leichtgewichtiges, datenschutzfreundliches Tool zur Koordination von Mitbringlisten – wer bringt was zu gemeinsamen Veranstaltungen?

## Funktionen

- **Eventverwaltung** – Events mit Artikellisten erstellen und über sichere Admin-Links verwalten
- **Gastzugang** – Gäste reservieren Artikel über tokenbasierte Links, keine Registrierung nötig
- **Admin-Backend** – Ein einzelner Superadmin-Account mit optionaler TOTP-Zwei-Faktor-Authentifizierung
- **Mehrsprachig** – Deutsch und Englisch, mit Übersetzungseditor im Admin-Backend
- **PDF-Export** – Eventübersichten als PDF exportieren (via Dompdf)
- **E-Mail-Benachrichtigungen** – SMTP, Brevo API oder Sendmail
- **Theming** – Anpassbare Farben, Logo und Seitentitel
- **Shared-Hosting-tauglich** – Läuft mit PHP und MySQL, keine besonderen Serveranforderungen

## Voraussetzungen

- PHP 8.1 oder höher
- MySQL 5.7+ oder MariaDB 10.3+

## Installation

1. Alle Dateien (inkl. `vendor/`-Verzeichnis) auf den Webserver hochladen.
2. Das Installationsskript im Browser aufrufen und dem Setup-Assistenten folgen.
3. Nach der Installation das Installationsskript löschen oder absichern.

## Konfiguration

Alle Einstellungen (Titel, Farben, Logo, E-Mail, Sprache) können nach der Installation über das Admin-Backend verwaltet werden. Die Datenbank-Konfiguration wird während der Installation automatisch erstellt.

---

# Wunschliste (English)

A lightweight, privacy-conscious potluck coordination tool for organizing who brings what to shared events.

## Features

- **Event management** – Create events with item lists, manage everything via secure admin links
- **Guest access** – Guests claim items through token-based links, no registration required
- **Admin backend** – Single superadmin account with optional TOTP two-factor authentication
- **Multilingual** – German and English, with a translation editor in the admin backend
- **PDF export** – Export event overviews as PDF (via Dompdf)
- **Email notifications** – SMTP, Brevo API, or sendmail
- **Theming** – Customizable colors, logo, and site title
- **Shared hosting friendly** – Runs on PHP and MySQL, no special server requirements

## Requirements

- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB 10.3+

## Installation

1. Upload all files (including the `vendor/` directory) to your web server.
2. Open the install script in your browser and follow the setup wizard.
3. After installation, delete or protect the install script.

## Configuration

All settings (title, colors, logo, email, language) can be managed through the admin backend after installation. The database configuration is created automatically during setup.

---

## License / Lizenz

Wunschliste is free software licensed under the GNU General Public License v3.0 or later. See the [LICENSE](LICENSE.txt) file for the full license text.

Copyright (C) 2026 Niko Winckel - n-Systeme.de
