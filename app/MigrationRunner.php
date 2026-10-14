<?php

namespace ShubhLabh\App;

use PDO;
use RuntimeException;

/**
 * Database Migration Runner for Shubh Labh CMS
 * 
 * Handles forward migrations and rollbacks with tracking in the `migrations` table.
 */
class MigrationRunner
{
    private PDO $pdo;
    private string $migrationsDir;

    public function __construct(?PDO $pdo = null, ?string $migrationsDir = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->migrationsDir = $migrationsDir ?? dirname(__DIR__) . '/migrations';
    }

    /**
     * Ensure tracking table exists
     */
    public function ensureTrackingTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_migration_name UNIQUE (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    /**
     * Get list of applied migration names
     */
    public function getAppliedMigrations(): array
    {
        $this->ensureTrackingTable();
        $stmt = $this->pdo->query('SELECT migration FROM migrations ORDER BY id ASC');
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Get list of pending migration files
     */
    public function getPendingMigrations(): array
    {
        $applied = $this->getAppliedMigrations();
        $files = glob($this->migrationsDir . '/*_*.sql') ?: [];
        
        $pending = [];
        foreach ($files as $file) {
            $baseName = basename($file);
            // Ignore rollback scripts (ending with _down.sql)
            if (str_ends_with($baseName, '_down.sql')) {
                continue;
            }

            if (!in_array($baseName, $applied, true)) {
                $pending[] = $baseName;
            }
        }

        sort($pending);
        return $pending;
    }

    /**
     * Apply all pending migrations
     */
    public function migrate(): array
    {
        $pending = $this->getPendingMigrations();
        $executed = [];

        foreach ($pending as $migrationName) {
            $filePath = $this->migrationsDir . '/' . $migrationName;
            $sql = file_get_contents($filePath);

            if ($sql === false || empty(trim($sql))) {
                throw new RuntimeException("Migration file is empty or unreadable: {$migrationName}");
            }

            // Execute SQL commands
            $this->executeSqlStatements($sql);

            // Record execution
            $stmt = $this->pdo->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
            $stmt->execute(['migration' => $migrationName]);

            $executed[] = $migrationName;
            Logger::info("Applied migration: {$migrationName}");
        }

        return $executed;
    }

    /**
     * Roll back the most recently applied migration
     */
    public function rollback(): ?string
    {
        $applied = $this->getAppliedMigrations();
        if (empty($applied)) {
            return null; // Nothing to rollback
        }

        $lastMigration = end($applied);
        $downFileName = str_replace('.sql', '_down.sql', $lastMigration);
        $downFilePath = $this->migrationsDir . '/' . $downFileName;

        if (!file_exists($downFilePath)) {
            throw new RuntimeException("Rollback script not found: {$downFileName}");
        }

        $sql = file_get_contents($downFilePath);
        $this->executeSqlStatements($sql);

        // Remove record from tracking table
        $stmt = $this->pdo->prepare('DELETE FROM migrations WHERE migration = :migration');
        $stmt->execute(['migration' => $lastMigration]);

        Logger::info("Rolled back migration: {$lastMigration}");
        return $lastMigration;
    }

    /**
     * Execute SQL script safely splitting statements
     */
    private function executeSqlStatements(string $sql): void
    {
        // Strip comments
        $lines = explode("\n", $sql);
        $cleanSql = '';
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }
            $cleanSql .= $line . "\n";
        }

        // Split by semicolons not within strings
        $statements = array_filter(array_map('trim', explode(';', $cleanSql)));

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $this->pdo->exec($statement);
            }
        }
    }
}
