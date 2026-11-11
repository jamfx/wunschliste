[English](#english) | [Deutsch](#deutsch)

<a id="deutsch"></a>
# Upgrade-Anleitung

## Allgemein: Update per Drüberkopieren

Bei jedem Update die Dateien der neuen Version über die bestehende Installation kopieren (`config/config.php` und `uploads/` bleiben unberührt) und danach als SuperAdmin unter **Admin → Datenbank-Update** auf „Migrationen jetzt ausführen“ klicken, falls dort etwas aussteht.

- Es werden **nicht** bei jedem Update alle SQL-Schritte erneut ausgeführt. Jede Migrationsdatei läuft genau einmal und wird in der Tabelle `migrations` vermerkt; ausstehend ist nur, was neu hinzugekommen ist.
- Prüfe den Ordner `migrations/` auf dem Server: Er darf nur Dateien der aktuellen Version enthalten (plus die `.htaccess`). Dateien, die nicht dazugehören – etwa Reste früherer Entwicklungsstände –, werden vom Migrator als „ausstehend“ angezeigt. In diesem Fall **nicht** ausführen, sondern die Dateien löschen.
- Migrationsdateien niemals umbenennen: eine umbenannte Datei gilt als neu und würde erneut ausgeführt.

## Upgrade auf 1.3.0 (Zusage bei Vorschlägen, übersetzte Texte)

Alle Dateien der neuen Version über die bestehende Installation kopieren (Ordner `vendor/` und `migrations/` vollständig, nur Dateien dieser Version), danach als SuperAdmin unter **Admin → Datenbank-Update** die Migration `004_suggester_claim.sql` einspielen (der Hinweis erscheint auf dem Dashboard). Wichtig: Spiele die Migration direkt nach dem Hochladen ein – solange sie fehlt, schlägt das Absenden eines Vorschlags **mit gesetztem Häkchen „Ich bringe das auch mit“** fehl.

Neu für Organisatoren: Mails an dich (Übernahme, Liste vollständig, neuer Vorschlag) und Cron-Mails kommen jetzt in der **Standardsprache der Seite** (vorher immer Deutsch). Einzelne Übernahmen lassen sich in der Verwaltung mit dem × entfernen.

---

## Upgrade auf 1.2.0 (Vorschlags-Benachrichtigung, Spam-Schutz, Datenbank-Update)

Neue Funktion: Gäste können bei Mitbringsel-Vorschlägen optional Anzahl und E-Mail-Adresse angeben und werden über die Entscheidung benachrichtigt. Nach dem Hochladen der Dateien als SuperAdmin einloggen und unter **Admin → Datenbank-Update** auf „Migrationen jetzt ausführen“ klicken (vorher ein Datenbank-Backup anlegen). Auf dem Dashboard weist ein Hinweis darauf hin, solange Migrationen ausstehen. Die SQL-Dateien müssen dafür nicht mehr von Hand eingespielt werden (die manuelle Variante per phpMyAdmin/`mysql` bleibt aber möglich, solange du danach nicht zusätzlich den Knopf drückst – der Migrator erkennt den Stand aus dem Schema). Neuinstallationen brauchen nichts zu tun.

Außerdem neu: optionaler Proof-of-Work-Spam-Schutz (ALTCHA). Dafür zusätzlich die Ordner `assets/js/altcha/` und `vendor/altcha-org/` **vollständig** hochladen. Aktivierung im Admin-Bereich unter Sicherheit (Standard: aus). Ohne diese Ordner schlägt die Prüfung fehl, sobald die Funktion eingeschaltet wird.

---

## Upgrade auf 1.1.0

Version 1.1.0 führt Benutzerkonten ein (SuperAdmin lädt registrierte User per E-Mail ein, optionale 2FA für jeden Account), Listen-Besitzer sowie einen Schalter, mit dem die Listen-Erstellung auf registrierte User beschränkt werden kann. Das Upgrade ist **abwärtskompatibel**: im Standardzustand ("Offen für alle") verhält sich eine bestehende Installation nach dem Upgrade exakt wie vorher — bestehende Listen bleiben unverändert nur über ihren Admin-Link erreichbar, ohne Login.

### Voraussetzungen

- PHP 8.1+ und MySQL/MariaDB wie bisher
- Zugriff auf die Datenbank (phpMyAdmin oder `mysql`-CLI)
- Zugriff auf den Webserver zum Hochladen von Dateien

### Schritte

**1. Backup erstellen**

Vor jedem Upgrade: Datenbank und Anwendungsverzeichnis sichern.

```bash
mysqldump -h DB_HOST -u DB_USER -p DB_NAME > backup_vor_1.1.0_$(date +%Y%m%d).sql
```

**2. Anwendungsdateien aktualisieren**

Alle Dateien der neuen Version auf den Server hochladen (bestehende Dateien überschreiben). `config/config.php` bleibt unverändert und wird nicht überschrieben, da sie nicht Teil des Repositories ist.

**3. Migration einspielen**

Die neue Datei `migrations/002_multi_user.sql` **einmalig** gegen die bestehende Datenbank ausführen — entweder per phpMyAdmin (SQL-Tab, komplette Datei einfügen und ausführen) oder per CLI:

```bash
mysql -h DB_HOST -u DB_USER -p DB_NAME < migrations/002_multi_user.sql
```

Die Migration:
- legt eine neue `users`-Tabelle an und übernimmt den bestehenden SuperAdmin-Account automatisch hinein
- legt `user_passkeys`, `user_invites`, `password_resets` an
- ergänzt `events` um eine `user_id`-Spalte (nullable — bestehende Listen bleiben unangetastet)
- lässt die alten Tabellen `admin`/`admin_passkeys` unangetastet stehen (kein Datenverlust, falls etwas schiefgeht)

**Wichtig:** Die Migration darf nur **einmal** ausgeführt werden (sie kopiert Daten aus `admin` nach `users` — ein zweiter Lauf schlägt wegen eindeutiger E-Mail-Adressen fehl bzw. würde Duplikate erzeugen).

**4. Prüfen**

- Als SuperAdmin einloggen (Passwort, ggf. TOTP/Passkey) — sollte sich exakt wie vorher verhalten
- Dashboard öffnen, prüfen dass alle bestehenden Listen weiterhin da sind
- Eine bestehende Liste über ihren Admin-Link öffnen — sollte ohne Login funktionieren

### Danach: neue Funktionen (optional)

Nichts davon ist für den Betrieb erforderlich — die Installation läuft nach Schritt 4 bereits vollständig.

- **Listen-Erstellung einschränken**: Admin-Backend → Sicherheit → „Listen-Erstellung" auf „Nur für registrierte User" stellen
- **User einladen**: Admin-Backend → Benutzer → E-Mail-Adresse eingeben (setzt funktionierende E-Mail-Einstellungen voraus)
- **Eigene 2FA einrichten**: „Meine Sicherheit" (verfügbar für SuperAdmin und jeden registrierten User)

---

<a id="english"></a>
# Upgrade Guide (English)

## General: updating by copying over

For every update, copy the files of the new version over the existing installation (`config/config.php` and `uploads/` stay untouched), then, as SuperAdmin, click "Run migrations now" under **Admin → Database update** if anything is pending.

- Not all SQL steps are re-run on every update. Each migration file runs exactly once and is recorded in the `migrations` table; only files that are new are pending.
- Check the `migrations/` folder on the server: it must contain only files of the current version (plus the `.htaccess`). Files that do not belong there – e.g. leftovers from earlier development states – are shown as "pending" by the migrator. In that case do **not** run them; delete the files instead.
- Never rename migration files: a renamed file counts as new and would be run again.

## Upgrading to 1.3.0 (pledges on suggestions, translated texts)

Copy all files of the new version over the existing installation (`vendor/` and `migrations/` complete, only files of this version), then, as SuperAdmin, apply the migration `004_suggester_claim.sql` under **Admin → Database update** (a notice appears on the dashboard). Important: apply the migration right after uploading – until then, submitting a suggestion **with the "I will bring this too" box ticked** fails.

New for organisers: emails to you (claimed, list complete, new suggestion) and cron emails are now sent in the **site's default language** (previously always German). Single claims can be removed in the management page with the ×.

---

## Upgrading to 1.2.0 (suggestion notifications, spam protection, database update)

New feature: guests can optionally add a quantity and an email address to suggested items and are notified of the decision. After uploading the files, log in as SuperAdmin and click "Run migrations now" under **Admin → Database update** (back up the database first). A notice on the dashboard shows while migrations are pending. You no longer need to apply the SQL files by hand (applying them manually via phpMyAdmin/`mysql` still works – the migrator detects the state from the schema). Fresh installs need no action.

---

## Upgrading to 1.1.0

Version 1.1.0 introduces user accounts (the SuperAdmin invites registered users by email, with optional 2FA for every account), list ownership, and a switch to restrict list creation to registered users. The upgrade is **backward compatible**: in the default state ("Open to everyone") an existing installation behaves exactly as before after the upgrade — existing lists remain reachable only via their admin link, without login.

### Requirements

- PHP 8.1+ and MySQL/MariaDB, same as before
- Database access (phpMyAdmin or the `mysql` CLI)
- Web server access to upload files

### Steps

**1. Back up**

Before any upgrade: back up the database and the application directory.

```bash
mysqldump -h DB_HOST -u DB_USER -p DB_NAME > backup_before_1.1.0_$(date +%Y%m%d).sql
```

**2. Update application files**

Upload all files of the new version to your server (overwriting existing files). `config/config.php` is untouched, as it isn't part of the repository.

**3. Run the migration**

Run the new `migrations/002_multi_user.sql` file **once** against your existing database — either via phpMyAdmin (SQL tab, paste the full file and execute) or via CLI:

```bash
mysql -h DB_HOST -u DB_USER -p DB_NAME < migrations/002_multi_user.sql
```

The migration:
- creates a new `users` table and automatically carries the existing SuperAdmin account into it
- creates `user_passkeys`, `user_invites`, `password_resets`
- adds a nullable `user_id` column to `events` (existing lists are left untouched)
- leaves the old `admin`/`admin_passkeys` tables in place (no data loss if something goes wrong)

**Important:** run the migration only **once** (it copies data from `admin` into `users` — a second run fails on the unique email constraint, or would create duplicates).

**4. Verify**

- Log in as the SuperAdmin (password, TOTP/passkey if configured) — should behave exactly as before
- Open the dashboard, confirm all existing lists are still there
- Open an existing list via its admin link — should work without logging in

### Afterwards: new features (optional)

None of this is required for the app to keep working — it's fully operational after step 4.

- **Restrict list creation**: Admin backend → Security → set "List creation" to "Registered users only"
- **Invite users**: Admin backend → Users → enter an email address (requires working email settings)
- **Set up your own 2FA**: "My Security" (available to the SuperAdmin and every registered user)
