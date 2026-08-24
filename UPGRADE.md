[English](#english) | [Deutsch](#deutsch)

<a id="deutsch"></a>
# Upgrade-Anleitung

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
