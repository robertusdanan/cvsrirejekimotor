<?php
declare(strict_types=1);

/**
 * Cron safety net: periodically rebuilds the entire public JSON cache directly
 * from Supabase, in case a webhook call was missed (network hiccup, etc.).
 *
 * Recommended cPanel schedule: every 15 minutes.
 */

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Cache.php';
require __DIR__ . '/../src/MySQLWriter.php';
require __DIR__ . '/../src/CacheBuilder.php';

$builder = new CacheBuilder(
    new MySQLWriter(),
    new Cache()
);

try {
    $builder->rebuildAll();
    echo "Cron reconcile complete (" . date('c') . ")." . PHP_EOL;
} catch (Throwable $e) {
    error_log('cron_reconcile.php failed: ' . $e->getMessage());
    fwrite(STDERR, "Cron reconcile failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
