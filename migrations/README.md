# Migrationen / Migrations

Die SQL-Dateien in diesem Ordner werden vom **Migrator** (`src/Migrator.php`) eingespielt –
per Knopfdruck im Admin-Bereich unter **Datenbank-Update** (nur SuperAdmin). Jede Datei läuft
genau einmal; erledigte Dateien stehen in der Tabelle `migrations`.

## Regeln für neue Migrationen

- Neue Datei mit der nächsten Nummer anlegen: `004_kurzer_name.sql`, `005_…` usw.
- **`001_initial.sql` ist eingefroren.** Sie ist das Gesamtschema bis einschließlich 003
  (`Migrator::BASELINE_COVERED`). Neuinstallationen führen 001 aus, markieren 001–003 als erledigt
  und spielen danach alle neueren Dateien (004+) ein. Schemaänderungen daher **nur** als neue Datei,
  nie zusätzlich in 001 nachtragen.
- Eine Datei enthält nur SQL (DDL/DML). Kommentare mit `--` sind erlaubt. Keine `DELIMITER`,
  Trigger oder Stored Procedures (der Splitter trennt an `;`).
- MySQL/MariaDB führt DDL ohne Transaktion aus: schlägt eine Datei auf halbem Weg fehl, bleiben die
  bis dahin ausgeführten Statements bestehen, die Datei gilt aber **nicht** als erledigt. Deshalb
  möglichst idempotent schreiben (`CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`), wo es
  die Datenbank erlaubt.
- Der Admin-Login und das Dashboard müssen auch **vor** dem Einspielen einer neuen Migration noch
  funktionieren, weil der Admin den Knopf sonst nicht erreicht. Migrationen, die Tabellen oder
  Spalten ändern, die diese Seiten brauchen, sind entsprechend abwärtskompatibel zu gestalten.

## Update per Drüberkopieren

Der Migrator führt nur Dateien aus, die noch nicht in `migrations` vermerkt sind – ein Update
spielt also nicht jedes Mal alles neu ein. Er kennt aber nur Dateinamen: Dateien, die nicht zur
Version gehören (z. B. Reste früherer Entwicklungsstände im Ordner auf dem Server), erscheinen als
ausstehend und müssen **gelöscht**, nicht ausgeführt werden. Ausgelieferte Migrationen werden
nie umbenannt oder gelöscht (eine umbenannte Datei gilt als neu und liefe erneut).

## Bestehende Installationen

Installationen aus der Zeit vor dem Migrator haben eine leere `migrations`-Tabelle. Beim ersten
Aufruf wird ihr Stand aus dem tatsächlichen Schema erkannt (Tabelle `events` → 001, Tabelle `users`
→ 002, Spalte `items.suggester_email` → 003). Nur was wirklich fehlt, steht dann als „ausstehend“ da.
