<?php

namespace ShubhLabh\App;

use PDO;
use ShubhLabh\App\Database;
use ShubhLabh\App\Session;

/**
 * Audit Logger Service
 * 
 * Records administrative content operations in the immutable `audit_logs` table.
 * Strips all credentials, tokens, and sensitive keys.
 */
class AuditLogger
{
    /**
     * Record an administrative event
     */
    public static function log(string $action, string $entityType, ?int $entityId = null, array $details = []): bool
    {
        $userId = Session::get('admin_user_id');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Redact any accidental secrets
        $sanitizedDetails = Logger::sanitizeContext($details);
        $jsonDetails = !empty($sanitizedDetails) ? json_encode($sanitizedDetails, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('
                INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip, NOW())
            ');

            return $stmt->execute([
                'user_id'     => $userId ? (int)$userId : null,
                'action'      => strtoupper(trim($action)),
                'entity_type' => strtolower(trim($entityType)),
                'entity_id'   => $entityId,
                'details'     => $jsonDetails,
                'ip'          => $ip,
            ]);
        } catch (\Throwable $e) {
            // Failsafe: log failure to file logger without exposing secrets
            Logger::error('Audit log database insert failed', [
                'action' => $action,
                'entity_type' => $entityType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Retrieve recent audit events (read-only)
     */
    public static function getRecent(int $limit = 50): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('
                SELECT a.id, a.user_id, a.action, a.entity_type, a.entity_id, a.details, a.ip_address, a.created_at,
                       u.username, u.full_name, u.email
                FROM audit_logs a
                LEFT JOIN admin_users u ON a.user_id = u.id
                ORDER BY a.id DESC
                LIMIT :limit
            ');
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            foreach ($rows as &$row) {
                if (!empty($row['details'])) {
                    $row['details'] = json_decode($row['details'], true);
                }
            }
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }
}
