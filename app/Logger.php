<?php

namespace ShubhLabh\App;

use ShubhLabh\Config\Config;

/**
 * Security-Hardened Application Logger
 * 
 * Writes operational and security events to storage/logs/app.log.
 * Automatically sanitizes sensitive keys (passwords, tokens, credentials) before writing.
 */
class Logger
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_hash',
        'confirm_password',
        'token',
        'csrf_token',
        'session_id',
        'secret',
        'db_pass',
        'authorization',
        'cookie',
    ];

    /**
     * Log an informational message
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /**
     * Log a warning message
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    /**
     * Log an error message
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /**
     * Log a security-specific event (failed logins, rate limit triggers, CSRF blocks)
     */
    public static function security(string $message, array $context = []): void
    {
        self::write('SECURITY', $message, $context);
    }

    /**
     * Core write implementation with redaction
     */
    private static function write(string $level, string $message, array $context = []): void
    {
        try {
            $logPath = Config::get('storage.logs_path', dirname(__DIR__) . '/storage/logs/app.log');
            $logDir = dirname($logPath);

            if (!is_dir($logDir)) {
                @mkdir($logDir, 0750, true);
            }

            $sanitizedContext = self::sanitizeContext($context);
            $contextString = !empty($sanitizedContext) ? ' ' . json_encode($sanitizedContext, JSON_UNESCAPED_SLASHES) : '';
            $timestamp = date('Y-m-d H:i:s');
            $ip = self::getClientIp();

            $logLine = sprintf("[%s] [%s] [IP:%s] %s%s\n", $timestamp, $level, $ip, $message, $contextString);

            @file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Fail safely: Never crash application or print credentials if logging fails
        }
    }

    /**
     * Recursively redact sensitive values from context
     */
    public static function sanitizeContext(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $normalizedKey = strtolower((string)$key);
            $isSensitive = false;

            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if (str_contains($normalizedKey, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeContext($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Get client IP safely without relying naively on spoofable headers
     */
    private static function getClientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    }
}
