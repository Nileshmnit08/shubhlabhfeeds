<?php

namespace ShubhLabh\App;

use ShubhLabh\Config\Config;

/**
 * Cross-Site Request Forgery (CSRF) Protection
 * 
 * Generates and validates cryptographically secure tokens using constant-time comparison.
 */
class CSRF
{
    /**
     * Get or generate the current session CSRF token
     */
    public static function getToken(): string
    {
        Session::start();

        $tokenKey = Config::get('security.csrf_token_name', 'csrf_token');

        if (empty($_SESSION[$tokenKey]) || !is_string($_SESSION[$tokenKey])) {
            $_SESSION[$tokenKey] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$tokenKey];
    }

    /**
     * Regenerate CSRF token (called upon login or privilege changes)
     */
    public static function regenerate(): string
    {
        Session::start();
        $tokenKey = Config::get('security.csrf_token_name', 'csrf_token');
        $_SESSION[$tokenKey] = bin2hex(random_bytes(32));
        return $_SESSION[$tokenKey];
    }

    /**
     * Generate HTML hidden input field for forms
     */
    public static function getInputField(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        $tokenKey = htmlspecialchars(Config::get('security.csrf_token_name', 'csrf_token'), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . $tokenKey . '" value="' . $token . '">';
    }

    /**
     * Validate submitted token from POST request or HTTP header
     */
    public static function validate(?string $submittedToken = null): bool
    {
        Session::start();

        $tokenKey = Config::get('security.csrf_token_name', 'csrf_token');
        $storedToken = $_SESSION[$tokenKey] ?? '';

        if (!is_string($storedToken) || empty($storedToken)) {
            Logger::security('CSRF validation failed: No session token present.');
            return false;
        }

        // If not explicitly provided, inspect $_POST and $_SERVER headers
        if ($submittedToken === null) {
            $submittedToken = $_POST[$tokenKey] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }

        if (!is_string($submittedToken) || empty($submittedToken)) {
            Logger::security('CSRF validation failed: Missing token in request payload or header.');
            return false;
        }

        // Constant-time string comparison to prevent timing attacks
        $isValid = hash_equals($storedToken, $submittedToken);

        if (!$isValid) {
            Logger::security('CSRF validation failed: Token mismatch.');
        }

        return $isValid;
    }
}
