<?php
declare(strict_types=1);

/**
 * Runs the numbered SQL files in migrations/ exactly once each and records
 * them in the `migrations` table (modelled on the booking project's Migrator).
 *
 * Baseline: 001_initial.sql is the cumulative schema up to and including
 * BASELINE_COVERED. A fresh install therefore runs only 001 and marks all of
 * BASELINE_COVERED as done (markBaselineApplied()). Installations that predate
 * this tracker are detected from their actual schema (adoptExistingInstallation())
 * so already-applied migrations are not run a second time. From 004 on,
 * 001 stays frozen and every schema change is a new numbered file
 * (see migrations/README.md).
 */
final class Migrator
{
    public const BASELINE_COVERED = [
        '001_initial.sql',
        '002_multi_user.sql',
        '003_suggestion_notify.sql',
    ];

    private const LOCK_NAME = 'wunschliste_migrate';

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $dir = BASE_DIR . '/migrations',
    ) {
    }

    /** Creates the tracking table if needed and adopts a pre-tracker installation. */
    public function prepare(): void
    {
        $this->ensureTable();
        $this->adoptExistingInstallation();
    }

    /**
     * Filenames of migrations not yet applied, in order. Read-only apart from
     * creating the tracking table / adopting an existing installation.
     *
     * @return string[]
     */
    public function pending(): array
    {
        $this->prepare();
        $done = $this->executed();

        return array_values(array_filter(
            $this->files(),
            static fn(string $f): bool => !isset($done[$f])
        ));
    }

    /**
     * Applied migrations as [filename => executed_at].
     *
     * @return array<string, string>
     */
    public function executed(): array
    {
        $this->prepare();
        $rows = $this->pdo->query('SELECT filename, executed_at FROM migrations ORDER BY filename')->fetchAll();
        $out  = [];
        foreach ($rows as $row) {
            $out[$row['filename']] = (string) $row['executed_at'];
        }
        return $out;
    }

    /**
     * Applies all pending migrations. DDL statements commit implicitly in
     * MySQL/MariaDB, so there is no transaction: if a file fails half-way,
     * it is NOT recorded and the error names the file.
     *
     * @return array<int, array{file: string, status: string}>
     * @throws RuntimeException
     */
    public function run(): array
    {
        $this->prepare();

        $lockStmt = $this->pdo->query("SELECT GET_LOCK('" . self::LOCK_NAME . "', 0)");
        $locked   = (int) $lockStmt->fetchColumn();
        $lockStmt->closeCursor();
        if ($locked !== 1) {
            throw new RuntimeException('Es läuft bereits eine andere Migration. Bitte kurz warten und erneut versuchen.');
        }

        $results = [];
        try {
            $done = $this->executed();
            foreach ($this->files() as $file) {
                if (isset($done[$file])) {
                    $results[] = ['file' => $file, 'status' => 'skipped'];
                    continue;
                }
                $sql = file_get_contents($this->dir . '/' . $file);
                if ($sql === false) {
                    throw new RuntimeException("{$file}: Datei nicht lesbar.");
                }
                try {
                    foreach (split_sql_statements($sql) as $statement) {
                        // query() + closeCursor() so a stray SELECT can't leave an open result set behind
                        $this->pdo->query($statement)->closeCursor();
                    }
                } catch (Throwable $e) {
                    throw new RuntimeException("{$file}: " . $e->getMessage(), 0, $e);
                }
                $this->record($file);
                $results[] = ['file' => $file, 'status' => 'executed'];
            }
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('" . self::LOCK_NAME . "')")->closeCursor();
        }

        return $results;
    }

    /** For the installer: record the migrations that 001_initial.sql already contains. */
    public function markBaselineApplied(): void
    {
        $this->ensureTable();
        foreach (self::BASELINE_COVERED as $file) {
            $this->record($file);
        }
    }

    /** @return string[] */
    private function files(): array
    {
        $files = glob($this->dir . '/*.sql') ?: [];
        $names = array_map('basename', $files);
        sort($names);
        return $names;
    }

    private function ensureTable(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS `migrations` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `filename`    VARCHAR(255) NOT NULL,
                `executed_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_filename` (`filename`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    /**
     * An installation that existed before the tracker has data but an empty
     * `migrations` table: work out from its schema which of the baseline
     * migrations it already contains, so they aren't run again (002 copies data
     * and 003 adds columns — neither is safe to repeat).
     */
    private function adoptExistingInstallation(): void
    {
        if ((int) $this->pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn() > 0) {
            return;
        }
        if (!$this->tableExists('events')) {
            return; // empty database: 001 is genuinely pending
        }

        $this->record('001_initial.sql');
        if ($this->tableExists('users')) {
            $this->record('002_multi_user.sql');
        }
        if ($this->columnExists('items', 'suggester_email')) {
            $this->record('003_suggestion_notify.sql');
        }
    }

    private function record(string $file): void
    {
        $this->pdo->prepare('INSERT IGNORE INTO migrations (filename) VALUES (?)')->execute([$file]);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
