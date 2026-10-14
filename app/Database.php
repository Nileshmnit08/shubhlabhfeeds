<?php

namespace ShubhLabh\App;

use PDO;
use PDOException;
use RuntimeException;
use ShubhLabh\Config\Config;

/**
 * PDO Database Manager for Shubh Labh CMS
 * 
 * Provides secure singleton PDO database access with:
 * - Native prepared statements (emulation disabled)
 * - Full UTF-8 (utf8mb4) support
 * - Exception error handling with credential sanitization
 * - Test fixture / mock injection support
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Get or initialize PDO connection instance
     */
    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = Config::get('database.host');
        $port = Config::get('database.port', 3306);
        $dbname = Config::get('database.dbname');
        $username = Config::get('database.username');
        $password = Config::get('database.password');
        $charset = Config::get('database.charset', 'utf8mb4');
        $timeout = Config::get('database.timeout', 5);

        if (empty($host) || empty($dbname)) {
            throw new RuntimeException('Database configuration is incomplete.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Enforce native MySQL prepared statements
            PDO::ATTR_TIMEOUT            => (int)$timeout,
        ];

        try {
            self::$instance = new PDO($dsn, $username, $password, $options);
            return self::$instance;
        } catch (PDOException $e) {
            // Log error with stripped credentials
            Logger::error('Database connection failed', [
                'error_code' => $e->getCode(),
                'message'    => 'Connection refused or database unreachable.',
            ]);

            // Throw generic exception without leaking host, dbname, or credentials
            throw new RuntimeException('Database connection could not be established. Please check server logs.');
        }
    }

    /**
     * Inject an explicit PDO instance (used for unit testing with SQLite or mock PDO)
     */
    public static function setConnection(?PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    /**
     * Check if connection is currently active
     */
    public static function isConnected(): bool
    {
        return self::$instance !== null;
    }

    /**
     * Close the connection
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
