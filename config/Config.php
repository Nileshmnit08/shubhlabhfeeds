<?php

namespace ShubhLabh\Config;

use RuntimeException;

/**
 * Configuration Loader for Shubh Labh CMS
 * 
 * Safely loads configuration from private configuration files and environment variables.
 * Never prints database credentials in error messages or exceptions.
 */
class Config
{
    private static ?array $settings = null;
    private static ?string $configFilePath = null;

    /**
     * Set explicit config file path (useful for testing or custom hosting layouts)
     */
    public static function setConfigPath(string $path): void
    {
        self::$configFilePath = $path;
        self::$settings = null; // Force reload
    }

    /**
     * Load and cache configuration array
     */
    public static function load(): array
    {
        if (self::$settings !== null) {
            return self::$settings;
        }

        $baseDir = dirname(__DIR__);
        $candidatePaths = [];

        if (self::$configFilePath !== null) {
            $candidatePaths[] = self::$configFilePath;
        }

        // Search order: private directory outside web root -> local config -> environment defaults
        $candidatePaths[] = $baseDir . '/private/config.php';
        $candidatePaths[] = $baseDir . '/config/config.local.php';
        $candidatePaths[] = $baseDir . '/config/config.example.php';

        $loadedConfig = null;
        foreach ($candidatePaths as $path) {
            if (is_file($path) && is_readable($path)) {
                $loadedConfig = require $path;
                if (is_array($loadedConfig)) {
                    break;
                }
            }
        }

        if (!is_array($loadedConfig)) {
            // Failsafe: Never print paths or credentials
            throw new RuntimeException('CMS Configuration error: Required application configuration could not be loaded.');
        }

        // Allow environment variables to override sensitive database settings if present
        if ($envHost = getenv('DB_HOST')) {
            $loadedConfig['database']['host'] = $envHost;
        }
        if ($envPort = getenv('DB_PORT')) {
            $loadedConfig['database']['port'] = (int)$envPort;
        }
        if ($envName = getenv('DB_NAME')) {
            $loadedConfig['database']['dbname'] = $envName;
        }
        if ($envUser = getenv('DB_USER')) {
            $loadedConfig['database']['username'] = $envUser;
        }
        if ($envPass = getenv('DB_PASS')) {
            $loadedConfig['database']['password'] = $envPass;
        }
        if ($envAppEnv = getenv('APP_ENV')) {
            $loadedConfig['app']['env'] = $envAppEnv;
        }

        // Validate essential keys exist
        self::validateRequiredStructure($loadedConfig);

        self::$settings = $loadedConfig;
        return self::$settings;
    }

    /**
     * Get a configuration value using dot notation (e.g. 'database.host')
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $data = self::load();
        $segments = explode('.', $key);

        foreach ($segments as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * Check if a configuration key exists
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Reset cached configuration (used in testing)
     */
    public static function reset(): void
    {
        self::$settings = null;
        self::$configFilePath = null;
    }

    /**
     * Validate essential configuration keys exist without exposing values
     */
    private static function validateRequiredStructure(array $config): void
    {
        $required = [
            'app' => ['name', 'env', 'url'],
            'database' => ['host', 'dbname', 'username', 'charset'],
            'session' => ['name', 'idle_timeout', 'httponly'],
            'security' => ['max_login_attempts', 'lockout_duration', 'csrf_token_name'],
            'crm' => ['login_url'],
        ];

        foreach ($required as $section => $fields) {
            if (!isset($config[$section]) || !is_array($config[$section])) {
                throw new RuntimeException("CMS Configuration error: Missing required section [{$section}].");
            }
            foreach ($fields as $field) {
                if (!array_key_exists($field, $config[$section])) {
                    throw new RuntimeException("CMS Configuration error: Missing required setting [{$section}.{$field}].");
                }
            }
        }
    }
}
