<?php

namespace ShubhLabh\App;

use ShubhLabh\Config\Config;

/**
 * Login Rate Limiter & Throttler
 * 
 * Protects against brute-force attacks by tracking failed authentication attempts per identifier.
 * Persists failure timestamps in a protected JSON storage cache.
 */
class RateLimiter
{
    private static ?string $storageFile = null;

    private static function getStorageFile(): string
    {
        if (self::$storageFile !== null) {
            return self::$storageFile;
        }

        $dir = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        self::$storageFile = $dir . '/rate_limits.json';
        return self::$storageFile;
    }

    /**
     * Override storage file path (used for testing)
     */
    public static function setStorageFile(?string $path): void
    {
        self::$storageFile = $path;
    }

    /**
     * Build rate-limit key from IP and identifier
     */
    public static function makeKey(string $identifier, ?string $ip = null): string
    {
        $ipAddress = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        return hash('sha256', strtolower(trim($identifier)) . '|' . $ipAddress);
    }

    /**
     * Check if key is currently blocked
     */
    public static function isBlocked(string $key): bool
    {
        $maxAttempts = (int)Config::get('security.max_login_attempts', 5);
        $lockoutSeconds = (int)Config::get('security.lockout_duration', 900);

        $data = self::readData();
        if (!isset($data[$key])) {
            return false;
        }

        $record = $data[$key];
        $attempts = $record['attempts'] ?? 0;
        $lastAttempt = $record['last_attempt'] ?? 0;

        if ($attempts >= $maxAttempts) {
            $elapsed = time() - $lastAttempt;
            if ($elapsed < $lockoutSeconds) {
                return true; // Still locked out
            }
            // Lockout period has elapsed; reset
            self::reset($key);
            return false;
        }

        return false;
    }

    /**
     * Record a failed login attempt
     */
    public static function recordFailure(string $key): int
    {
        $lockoutSeconds = (int)Config::get('security.lockout_duration', 900);
        $data = self::readData();

        $now = time();
        $record = $data[$key] ?? ['attempts' => 0, 'last_attempt' => $now];

        // If previous attempt was outside lockout window, restart count
        if ($now - $record['last_attempt'] > $lockoutSeconds) {
            $record['attempts'] = 0;
        }

        $record['attempts']++;
        $record['last_attempt'] = $now;

        $data[$key] = $record;
        self::writeData($data);

        return $record['attempts'];
    }

    /**
     * Reset rate limit after successful login
     */
    public static function reset(string $key): void
    {
        $data = self::readData();
        if (isset($data[$key])) {
            unset($data[$key]);
            self::writeData($data);
        }
    }

    /**
     * Get remaining lockout seconds
     */
    public static function getRemainingLockout(string $key): int
    {
        $lockoutSeconds = (int)Config::get('security.lockout_duration', 900);
        $data = self::readData();

        if (!isset($data[$key])) {
            return 0;
        }

        $elapsed = time() - ($data[$key]['last_attempt'] ?? 0);
        $remaining = $lockoutSeconds - $elapsed;

        return max(0, $remaining);
    }

    /**
     * Read tracking data safely
     */
    private static function readData(): array
    {
        $file = self::getStorageFile();
        if (!file_exists($file) || !is_readable($file)) {
            return [];
        }

        $content = @file_get_contents($file);
        if ($content === false || empty($content)) {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Write tracking data with file locking
     */
    private static function writeData(array $data): void
    {
        $file = self::getStorageFile();
        $json = json_encode($data, JSON_PRETTY_PRINT);
        @file_put_contents($file, $json, LOCK_EX);
    }
}
