[English](#english) | [Deutsch](#deutsch)

[![License: AGPL v3](https://img.shields.io/badge/License-AGPLv3-blue.svg)](LICENSE.txt)
![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-777bb4.svg)
![Version](https://img.shields.io/badge/Version-1.3.1-orange.svg)

<a id="deutsch"></a>
# Wunschliste

Ein leichtgewichtiges, datenschutzfreundliches Tool zur Koordination von Mitbringlisten – wer bringt was zu gemeinsamen Veranstaltungen?

## 🔗 Demo

Eine Live-Demo findest du unter: **[wunschliste.n-systeme.de](https://wunschliste.n-systeme.de)**

## Funktionen

- **Eventverwaltung** – Events mit Artikellisten erstellen und über sichere Admin-Links verwalten
- **Gastzugang** – Gäste reservieren Artikel über tokenbasierte Links, keine Registrierung nötig
- **Admin-Backend** – SuperAdmin-Account verwaltet die Installation und lädt weitere registrierte User per E-Mail ein; jeder Account kann optional TOTP oder Passkeys als Zwei-Faktor-Authentifizierung einrichten
- **Zugriffskontrolle** – Listen-Erstellung wahlweise offen für alle oder nur für registrierte User; eigene Listen bleiben dem jeweiligen Account zugeordnet
- **Mehrsprachig** – Deutsch und Englisch, mit Übersetzungseditor im Admin-Backend
- **Vorschläge von Gästen** – Gäste können Mitbringsel (optional mit Anzahl) vorschlagen; mit angegebener E-Mail-Adresse erfahren sie, ob der Vorschlag angenommen oder abgelehnt wurde (die Adresse wird danach gelöscht)
- **Markdown in Beschreibungen** – Fett, kursiv, Links und Listen in Event-Beschreibungen (ohne rohes HTML)
- **Spam-Schutz** – Mindest-Ausfüllzeit, Filter gegen Links/HTML in Titeln und optional ein selbst gehostetes Proof-of-Work-Captcha ([ALTCHA](https://altcha.org)) – ohne Cookies, ohne externe Dienste, barrierefrei
- **PDF-Export** – Eventübersichten als PDF exportieren (via Dompdf)
- **E-Mail-Benachrichtigungen** – SMTP, Brevo API oder Sendmail
- **Datenbank-Updates per Knopfdruck** – Neue Migrationen werden im Admin-Backend eingespielt, kein manuelles SQL nötig
- **Theming** – Anpassbare Farben, Logo und Seitentitel
- **Shared-Hosting-tauglich** – Läuft mit PHP und MySQL, keine besonderen Serveranforderungen

## Voraussetzungen

- PHP 8.1 oder höher
- MySQL 5.7+ oder MariaDB 10.3+

## Installation

1. Alle Dateien (inklusive des Ordners `vendor/`) auf den Webserver hochladen.
2. Das Installationsskript im Browser aufrufen und dem Setup-Assistenten folgen.
3. Nach der Installation das Installationsskript löschen oder absichern.

## Update

Aktualisierst du eine bestehende Installation (nicht neu installieren), folge der **[Upgrade-Anleitung](UPGRADE.md)**: Dateien drüberkopieren und danach im Admin-Backend unter „Datenbank-Update“ die ausstehenden Migrationen per Knopfdruck einspielen. Was sich zwischen den Versionen im Detail geändert hat, steht im **[Changelog](CHANGELOG.md)**.

## Konfiguration

Alle Einstellungen (Titel, Farben, Logo, E-Mail, Sprache) können nach der Installation über das Admin-Backend verwaltet werden. Die Datenbank-Konfiguration wird während der Installation automatisch erstellt.

---
<a id="english"></a>
# Wunschliste (English)

A lightweight, privacy-conscious potluck coordination tool for organizing who brings what to shared events.

## 🔗 Demo

You can see a Live-Demo at: **[wunschliste.n-systeme.de](https://wunschliste.n-systeme.de)**

## Features

- **Event management** – Create events with item lists, manage everything via secure admin links
- **Guest access** – Guests claim items through token-based links, no registration required
- **Admin backend** – A SuperAdmin account manages the installation and invites further registered users by email; every account can optionally set up TOTP or passkeys for two-factor authentication
- **Access control** – List creation can be open to everyone or restricted to registered users; your own lists stay tied to your account
- **Multilingual** – German and English, with a translation editor in the admin backend
- **Guest suggestions** – Guests can suggest items (optionally with a quantity); if they give an email address they learn whether the suggestion was accepted or declined (the address is deleted afterwards)
- **Markdown in descriptions** – Bold, italic, links and lists in event descriptions (no raw HTML)
- **Spam protection** – Minimum fill time, filtering of links/HTML in titles and an optional self-hosted proof-of-work captcha ([ALTCHA](https://altcha.org)) – no cookies, no external services, accessible
- **PDF export** – Export event overviews as PDF (via Dompdf)
- **Email notifications** – SMTP, Brevo API, or sendmail
- **One-click database updates** – New migrations are applied from the admin backend, no manual SQL needed
- **Theming** – Customizable colors, logo, and site title
- **Shared hosting friendly** – Runs on PHP and MySQL, no special server requirements

## Requirements

- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB 10.3+

## Installation

1. Upload all files (including the `vendor/` directory) to your web server.
2. Open the install script in your browser and follow the setup wizard.
3. After installation, delete or protect the install script.

## Updating

Updating an existing installation (not a fresh install)? Follow the **[upgrade guide](UPGRADE.md)**: copy the files over, then apply pending migrations with one click under "Database update" in the admin backend. See the **[changelog](CHANGELOG.md)** for what changed between versions.

## Configuration

All settings (title, colors, logo, email, language) can be managed through the admin backend after installation. The database configuration is created automatically during setup.

---

## License / Lizenz

Wunschliste is free software licensed under the GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later). See the [LICENSE](LICENSE.txt) file for the full license text.

Copyright (C) 2026 Niko Winckel - n-Systeme.de

### Third-party components / Drittanbieter-Komponenten

This project bundles the following third-party libraries, each under its own license:

- [Font Awesome Free](https://fontawesome.com) 7.2.0 — Icons: [CC BY 4.0](https://fontawesome.com/license/free), Fonts: [SIL OFL 1.1](https://fontawesome.com/license/free), Code: [MIT](https://fontawesome.com/license/free)
- [Dompdf](https://github.com/dompdf/dompdf) — LGPL-2.1
- [php-font-lib](https://github.com/dompdf/php-font-lib) — LGPL-2.1-or-later
- [php-svg-lib](https://github.com/dompdf/php-svg-lib) — LGPL-3.0-or-later
- [endroid/qr-code](https://github.com/endroid/qr-code) — MIT
- [bacon/bacon-qr-code](https://github.com/Bacon/BaconQrCode) — BSD-2-Clause
- [dasprid/enum](https://github.com/DASPRiD/Enum) — BSD-2-Clause
- [lbuchs/webauthn](https://github.com/lbuchs/WebAuthn) — MIT
- [masterminds/html5](https://github.com/Masterminds/html5-php) — MIT
- [sabberworm/php-css-parser](https://github.com/sabberworm/PHP-CSS-Parser) — MIT
- [thecodingmachine/safe](https://github.com/thecodingmachine/safe) — MIT
- [Twemoji](https://github.com/jdecked/twemoji) 15.1.0 — a selection of 200 emoji graphics for the PDF export (`assets/emoji/`): [CC-BY 4.0](https://creativecommons.org/licenses/by/4.0/), Copyright 2020 Twitter, Inc and other contributors
- [ALTCHA widget](https://github.com/altcha-org/altcha) 3.4.1 (`assets/js/altcha/`) — MIT
- [altcha-org/altcha PHP library](https://github.com/altcha-org/altcha-lib-php) 2.3.0 (`vendor/altcha-org/altcha/`) — MIT
