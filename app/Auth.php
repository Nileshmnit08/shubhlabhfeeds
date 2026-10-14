<?php

namespace ShubhLabh\App;

use PDO;
use RuntimeException;
use ShubhLabh\Config\Config;

/**
 * Administrative Authentication Service
 * 
 * Handles credential verification, Argon2id/Bcrypt password hashing,
 * session lifecycle management, and brute-force throttling.
 */
class Auth
{
    public const ERROR_GENERIC = 'Invalid email or password.';
    public const ERROR_LOCKED  = 'Too many failed login attempts. Please try again later.';
    public const ERROR_INVALID_INPUT = 'Please provide a valid email and password.';

    /**
     * Hash a password using the strongest supported algorithm
     */
    public static function hashPassword(string $password): string
    {
        if (strlen($password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters in length.');
        }

        // Prioritize Argon2id if available in PHP runtime
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536, // 64 MB
                'time_cost'   => 4,
                'threads'     => 1,
            ]);
        }

        // Fallback to Bcrypt with cost >= 12
        $cost = (int)Config::get('security.bcrypt_cost', 12);
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => max(12, $cost)]);
    }

    /**
     * Verify a plaintext password against a stored hash
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Authenticate an administrative user via email and password
     * 
     * @return array [success => bool, error => string|null, user => array|null]
     */
    public static function authenticate(string $email, string $password): array
    {
        $email = trim(strtolower($email));

        // 1. Validate inputs
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
            return ['success' => false, 'error' => self::ERROR_INVALID_INPUT, 'user' => null];
        }

        // 2. Check rate limiting
        $rateLimitKey = RateLimiter::makeKey($email);
        if (RateLimiter::isBlocked($rateLimitKey)) {
            $remaining = RateLimiter::getRemainingLockout($rateLimitKey);
            $minutes = ceil($remaining / 60);
            Logger::security('Login blocked by rate limiter', ['email' => $email]);
            return [
                'success' => false, 
                'error' => sprintf('Too many failed attempts. Account locked for %d minute(s).', $minutes),
                'user' => null,
            ];
        }

        // 3. Retrieve user from database
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT id, username, email, password_hash, full_name, role, is_active FROM admin_users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();
        } catch (\Throwable $e) {
            Logger::error('Authentication query failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => self::ERROR_GENERIC, 'user' => null];
        }

        // 4. Verify user exists and is active
        if (!$user || empty($user['is_active'])) {
            RateLimiter::recordFailure($rateLimitKey);
            Logger::security('Failed login attempt: Account not found or inactive', ['email' => $email]);
            return ['success' => false, 'error' => self::ERROR_GENERIC, 'user' => null];
        }

        // 5. Verify password
        if (!self::verifyPassword($password, $user['password_hash'])) {
            RateLimiter::recordFailure($rateLimitKey);
            Logger::security('Failed login attempt: Password mismatch', ['email' => $email]);
            return ['success' => false, 'error' => self::ERROR_GENERIC, 'user' => null];
        }

        // 6. Login Succeeded: Reset rate limits
        RateLimiter::reset($rateLimitKey);

        // 7. Prevent session fixation: Regenerate session ID and store user context
        Session::start();
        Session::regenerate();
        Session::set('admin_authenticated', true);
        Session::set('admin_user_id', (int)$user['id']);
        Session::set('admin_username', $user['username']);
        Session::set('admin_email', $user['email']);
        Session::set('admin_role', $user['role']);
        Session::set('admin_full_name', $user['full_name']);

        // 8. Regenerate CSRF token
        CSRF::regenerate();

        // 9. Update last login timestamp in database
        try {
            $updateStmt = $pdo->prepare('UPDATE admin_users SET last_login_at = NOW(), last_login_ip = :ip, failed_login_attempts = 0 WHERE id = :id');
            $updateStmt->execute([
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'id' => $user['id'],
            ]);
        } catch (\Throwable) {
            // Non-critical; do not block login
        }

        Logger::info('Admin user authenticated successfully', ['email' => $email, 'user_id' => $user['id']]);

        // Strip password hash from returned payload
        unset($user['password_hash']);

        return ['success' => true, 'error' => null, 'user' => $user];
    }

    /**
     * Terminate admin session (Logout)
     */
    public static function logout(): void
    {
        $userId = Session::get('admin_user_id');
        if ($userId) {
            Logger::info('Admin logged out', ['user_id' => $userId]);
        }
        Session::destroy();
    }

    /**
     * Check if current session is authenticated
     */
    public static function isAuthenticated(): bool
    {
        return (bool)Session::get('admin_authenticated', false);
    }
}
