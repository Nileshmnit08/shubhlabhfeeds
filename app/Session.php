<?php

namespace ShubhLabh\App;

use ShubhLabh\Config\Config;

/**
 * Hardened Session Manager for Shubh Labh CMS
 * 
 * Enforces production cookie security attributes (HttpOnly, SameSite=Strict, Secure),
 * session fixation defense via ID regeneration, and 30-minute idle session timeout.
 */
class Session
{
    private static bool $started = false;

    /**
     * Start secure session if not already started
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $sessionName = Config::get('session.name', 'shubhlabh_session');
        $idleTimeout = (int)Config::get('session.idle_timeout', 1800); // 30 minutes
        $lifetime = (int)Config::get('session.lifetime', 28800); // 8 hours
        $path = Config::get('session.path', '/');
        $domain = Config::get('session.domain', '');
        $secure = (bool)Config::get('session.secure', true);
        $httpOnly = (bool)Config::get('session.httponly', true);
        $sameSite = Config::get('session.samesite', 'Strict');

        // Check if HTTPS is active or explicit local dev override
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

        // Enforce secure cookie in production
        $cookieSecure = $secure && $isHttps;
        if ($secure && !$isHttps && Config::get('app.env') === 'production') {
            // In production, force cookie secure even if reverse-proxy header is missing
            $cookieSecure = true;
        }

        session_name($sessionName);

        // Apply PHP session cookie parameters
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $cookieSecure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_start();
        self::$started = true;

        // Verify idle timeout
        self::checkIdleTimeout($idleTimeout);
    }

    /**
     * Regenerate session ID upon privilege change / login
     */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['last_activity'] = time();
    }

    /**
     * Check if session has exceeded idle timeout
     */
    private static function checkIdleTimeout(int $timeout): void
    {
        $now = time();
        if (isset($_SESSION['last_activity'])) {
            if ($now - $_SESSION['last_activity'] > $timeout) {
                // Session expired
                Logger::info('User session expired due to inactivity', [
                    'user_id' => $_SESSION['admin_user_id'] ?? null,
                ]);
                self::destroy();
                return;
            }
        }
        $_SESSION['last_activity'] = $now;
    }

    /**
     * Get a session variable
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a session variable
     */
    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Remove a session variable
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Set a flash message
     */
    public static function setFlash(string $type, string $message): void
    {
        self::start();
        $_SESSION['flash_messages'][$type][] = $message;
    }

    /**
     * Retrieve and clear flash messages
     */
    public static function getFlashes(): array
    {
        self::start();
        $flashes = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        return $flashes;
    }

    /**
     * Destroy current session and clear cookies
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            session_destroy();
            self::$started = false;
        }
    }

    /**
     * Route guard: Require authenticated administrative user
     */
    public static function requireAuth(string $loginUrl = '/admin/login.php'): void
    {
        self::start();

        if (empty($_SESSION['admin_user_id']) || empty($_SESSION['admin_authenticated'])) {
            self::destroy();
            header("Location: {$loginUrl}");
            exit;
        }
    }
}
