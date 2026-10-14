<?php

namespace ShubhLabh\App;

/**
 * Standardized JSON API Response Handler
 * 
 * Complies with CMS-API-SPECIFICATION.md and RFC 7807 error formatting.
 * Supports ETags, HTTP 304 Not Modified, and caching directives.
 */
class ApiResponse
{
    /**
     * Send a successful JSON response
     */
    public static function success(mixed $data, int $statusCode = 200, array $meta = [], int $cacheSeconds = 60): void
    {
        $payload = [
            'status'  => $statusCode,
            'success' => true,
            'data'    => $data,
            'meta'    => array_merge([
                'timestamp' => date('c'),
            ], $meta),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $etag = '"' . md5($json) . '"';

        // Check If-None-Match header for client caching
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304);
            header("ETag: {$etag}");
            exit;
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header("ETag: {$etag}");

        if ($cacheSeconds > 0) {
            header("Cache-Control: public, max-age={$cacheSeconds}, stale-while-revalidate=300");
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }

        echo $json;
        exit;
    }

    /**
     * Send an RFC 7807 compliant error response
     */
    public static function error(string $message, int $statusCode = 400, string $errorType = 'Bad Request', array $details = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/problem+json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $payload = [
            'status'  => $statusCode,
            'success' => false,
            'error'   => $errorType,
            'message' => $message,
        ];

        if (!empty($details)) {
            $payload['details'] = $details;
        }

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send a graceful static fallback response when database is unreachable
     */
    public static function fallback(mixed $data, string $message = 'Database temporarily offline; serving cached static fallback.'): void
    {
        header('X-CMS-DataSource: static-fallback');
        header('X-CMS-Status: degraded');
        self::success($data, 200, ['fallback' => true, 'notice' => $message], 0);
    }
}
