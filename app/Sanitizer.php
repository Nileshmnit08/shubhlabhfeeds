<?php

namespace ShubhLabh\App;

/**
 * Input Sanitizer and Security Validator
 * 
 * Provides safe slug generation, URL validation, and vetted allowlist HTML sanitization.
 */
class Sanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><h2><h3><h4><ul><ol><li><blockquote><a>';

    /**
     * Generate URL-safe slug from text
     */
    public static function slug(string $text): string
    {
        // Transliterate non-ASCII if iconv available
        if (function_exists('iconv')) {
            $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        }

        // Lowercase and strip invalid characters
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Validate and sanitize URL (restricts to safe HTTP/HTTPS or internal paths)
     */
    public static function cleanUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $trimmed = trim($url);
        if (empty($trimmed)) {
            return null;
        }

        // Allow anchor scrolls like #products or #contact
        if (str_starts_with($trimmed, '#')) {
            return preg_match('/^#[a-zA-Z0-9_-]+$/', $trimmed) ? $trimmed : null;
        }

        // Allow relative internal paths like / or /admin/
        if (str_starts_with($trimmed, '/') && !str_starts_with($trimmed, '//')) {
            return $trimmed;
        }

        // Restrict external URLs to http and https
        if (preg_match('#^https?://#i', $trimmed)) {
            $filtered = filter_var($trimmed, FILTER_VALIDATE_URL);
            return $filtered !== false ? $filtered : null;
        }

        // Block dangerous schemes (javascript:, data:, file:)
        return null;
    }

    /**
     * Sanitize formatted rich-text content using a strict allowlist
     * Strips <script>, <style>, <iframe>, and all inline event handlers
     */
    public static function sanitizeHtml(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // 1. Remove dangerous script and style tags and their contents
        $clean = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
        $clean = preg_replace('#<style(.*?)>(.*?)</style>#is', '', $clean);
        $clean = preg_replace('#<iframe(.*?)>(.*?)</iframe>#is', '', $clean);

        // 2. Strip tags not in the allowed list
        $clean = strip_tags($clean, self::ALLOWED_TAGS);

        // 3. Remove inline event handlers (e.g. onclick, onerror, onload)
        $clean = preg_replace('/\s*on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $clean);

        // 4. Sanitize javascript: inside href attributes
        $clean = preg_replace('/href\s*=\s*["\']\s*javascript:[^"\']*["\']/i', 'href="#"', $clean);

        return trim($clean);
    }
}
