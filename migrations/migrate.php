<?php
/**
 * Shubh Labh CMS - Database Migration CLI Runner
 * 
 * Usage:
 *   php migrations/migrate.php             # Run pending migrations
 *   php migrations/migrate.php --status    # Check migration status
 *   php migrations/migrate.php --rollback  # Roll back last migration
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden: Command-line execution only.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/config/Config.php';
require_once dirname(__DIR__) . '/app/Logger.php';
require_once dirname(__DIR__) . '/app/Database.php';
require_once dirname(__DIR__) . '/app/MigrationRunner.php';

use ShubhLabh\App\Database;
use ShubhLabh\App\MigrationRunner;

echo "====================================================\n";
echo "  Shubh Labh CMS - Database Migration Runner        \n";
echo "====================================================\n\n";

$options = getopt('', ['status', 'rollback']);

try {
    $runner = new MigrationRunner();

    if (isset($options['status'])) {
        $applied = $runner->getAppliedMigrations();
        $pending = $runner->getPendingMigrations();

        echo "Migration Status:\n";
        echo "----------------------------------------------------\n";
        echo sprintf("Total Applied: %d | Total Pending: %d\n\n", count($applied), count($pending));

        if (!empty($applied)) {
            echo "Applied Migrations:\n";
            foreach ($applied as $m) {
                echo "  [✓] {$m}\n";
            }
        }

        if (!empty($pending)) {
            echo "\nPending Migrations:\n";
            foreach ($pending as $m) {
                echo "  [ ] {$m}\n";
            }
        }
        echo "\n";
        exit(0);
    }

    if (isset($options['rollback'])) {
        echo "Rolling back last migration...\n";
        $rolledBack = $runner->rollback();
        if ($rolledBack) {
            echo "[SUCCESS] Rolled back: {$rolledBack}\n\n";
        } else {
            echo "[INFO] No migrations available to roll back.\n\n";
        }
        exit(0);
    }

    // Default action: Migrate
    echo "Checking for pending migrations...\n";
    $pending = $runner->getPendingMigrations();

    if (empty($pending)) {
        echo "[INFO] Database is up to date. No pending migrations.\n\n";
        exit(0);
    }

    echo sprintf("Found %d pending migration(s). Executing...\n", count($pending));
    $executed = $runner->migrate();

    foreach ($executed as $m) {
        echo "  [APPLIED] {$m}\n";
    }

    echo "\n[SUCCESS] All migrations executed successfully.\n\n";
    exit(0);

} catch (\Throwable $e) {
    echo "\n[FATAL ERROR] Migration failed: " . $e->getMessage() . "\n\n";
    exit(1);
}
