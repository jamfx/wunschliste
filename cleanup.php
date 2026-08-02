#!/usr/bin/env php
<?php
/**
 * Standalone cleanup script — run via cron, e.g.:
 *   0 3 * * * /usr/bin/php /path/to/app/cleanup.php
 *
 * Deletes:
 *  - Events whose expires_at is in the past (expires_at already bakes in
 *    each event's own retention period, chosen per event at creation/edit)
 *  - rate_limit rows older than 24 hours
 */
declare(strict_types=1);

// Block any web request — CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

define('BASE_DIR', __DIR__);
define('CONFIG_PATH', BASE_DIR . '/config/config.php');

$config = null;
if (file_exists(CONFIG_PATH)) {
    require CONFIG_PATH;
}
if ($config === null) {
    fwrite(STDERR, "No config found at " . CONFIG_PATH . "\n");
    exit(1);
}

require_once BASE_DIR . '/src/Database.php';

try {
    $db = new Database($config);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$deleted = ['events' => 0, 'rate_limit' => 0];

// Delete expired events (CASCADE removes items, claims, etc.)
try {
    $stmt = $db->getPdo()->prepare(
        'DELETE FROM events WHERE expires_at IS NOT NULL AND expires_at < ?'
    );
    $stmt->execute([date('Y-m-d H:i:s')]);
    $deleted['events'] = $stmt->rowCount();
} catch (PDOException $e) {
    fwrite(STDERR, "Event cleanup error: " . $e->getMessage() . "\n");
}

// Delete stale rate_limit rows (older than 24 h)
try {
    $cutoff = date('Y-m-d H:i:s', time() - 86400);
    $stmt   = $db->getPdo()->prepare('DELETE FROM rate_limit WHERE created_at < ?');
    $stmt->execute([$cutoff]);
    $deleted['rate_limit'] = $stmt->rowCount();
} catch (PDOException $e) {
    fwrite(STDERR, "Rate-limit cleanup error: " . $e->getMessage() . "\n");
}

printf(
    "[%s] Cleanup done — %d event(s) deleted, %d rate_limit row(s) deleted\n",
    date('Y-m-d H:i:s'),
    $deleted['events'],
    $deleted['rate_limit']
);
