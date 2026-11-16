<?php

namespace ShubhLabh\App\Models;

use PDO;
use ShubhLabh\App\Database;

/**
 * Company Address Model (Public Read & Admin Interface)
 */
class CompanyAddress
{
    /**
     * Retrieve all active addresses for public website, ordered by primary first, then display order
     */
    public static function getAllActive(): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('
                SELECT id, location_name, address_line1, address_line2, landmark,
                       city, state, pincode, country, office_phone, whatsapp_number,
                       email, google_maps_url, display_order, is_active, is_primary,
                       created_at, updated_at
                FROM company_addresses
                WHERE is_active = true
                ORDER BY is_primary DESC, display_order ASC, id ASC
            ');
            $stmt->execute();
            $rows = $stmt->fetchAll();

            if (empty($rows)) {
                return self::getFallbackAddresses();
            }

            return array_map([self::class, 'formatRecord'], $rows);
        } catch (\Throwable) {
            return self::getFallbackAddresses();
        }
    }

    /**
     * Retrieve all addresses (active and inactive) for CMS admin management
     */
    public static function getAll(): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query('
                SELECT id, location_name, address_line1, address_line2, landmark,
                       city, state, pincode, country, office_phone, whatsapp_number,
                       email, google_maps_url, display_order, is_active, is_primary,
                       created_at, updated_at
                FROM company_addresses
                ORDER BY is_primary DESC, display_order ASC, id ASC
            ');
            $rows = $stmt->fetchAll();
            return array_map([self::class, 'formatRecord'], $rows ?: []);
        } catch (\Throwable) {
            return self::getFallbackAddresses();
        }
    }

    /**
     * Retrieve a single address by ID
     */
    public static function getById(int $id): ?array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('
                SELECT id, location_name, address_line1, address_line2, landmark,
                       city, state, pincode, country, office_phone, whatsapp_number,
                       email, google_maps_url, display_order, is_active, is_primary,
                       created_at, updated_at
                FROM company_addresses
                WHERE id = :id
                LIMIT 1
            ');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            return $row ? self::formatRecord($row) : null;
        } catch (\Throwable) {
            foreach (self::getFallbackAddresses() as $addr) {
                if ((int)$addr['id'] === $id) {
                    return $addr;
                }
            }
            return null;
        }
    }

    /**
     * Retrieve the designated primary company address
     */
    public static function getPrimary(): ?array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('
                SELECT id, location_name, address_line1, address_line2, landmark,
                       city, state, pincode, country, office_phone, whatsapp_number,
                       email, google_maps_url, display_order, is_active, is_primary,
                       created_at, updated_at
                FROM company_addresses
                WHERE is_active = true AND is_primary = true
                LIMIT 1
            ');
            $stmt->execute();
            $row = $stmt->fetch();
            return $row ? self::formatRecord($row) : (self::getAllActive()[0] ?? null);
        } catch (\Throwable) {
            return self::getFallbackAddresses()[0] ?? null;
        }
    }

    /**
     * Helper to format record with clean full formatted_address string
     */
    public static function formatRecord(array $row): array
    {
        $parts = array_filter([
            $row['address_line1'] ?? '',
            $row['address_line2'] ?? '',
            $row['landmark'] ?? '',
            $row['city'] ?? '',
            isset($row['state']) && isset($row['pincode']) ? "{$row['state']} {$row['pincode']}" : ($row['state'] ?? ''),
            $row['country'] ?? 'India',
        ], fn($p) => !empty(trim((string)$p)));

        $row['formatted_address'] = implode(', ', $parts);
        $row['is_primary'] = (bool)($row['is_primary'] ?? 0);
        $row['is_active'] = (bool)($row['is_active'] ?? 1);
        $row['display_order'] = (int)($row['display_order'] ?? 0);

        return $row;
    }

    /**
     * Verified offline fallback address fixture
     */
    public static function getFallbackAddresses(): array
    {
        return [
            [
                'id'                => 1,
                'location_name'     => 'Head Office & Manufacturing Works',
                'address_line1'     => 'RIICO Industrial Area',
                'address_line2'     => 'Plot No. 42-45, Agro Food Park',
                'landmark'          => 'Near Highway By-Pass',
                'city'              => 'Jaipur',
                'state'             => 'Rajasthan',
                'pincode'           => '302013',
                'country'           => 'India',
                'formatted_address' => 'RIICO Industrial Area, Plot No. 42-45, Agro Food Park, Near Highway By-Pass, Jaipur, Rajasthan 302013, India',
                'office_phone'      => '+91 94619 24461',
                'whatsapp_number'   => '+91 94619 24461',
                'email'             => 'contact@shubhlabhfeeds.in',
                'google_maps_url'   => 'https://maps.google.com/?q=Shubh+Labh+Cattle+Feed+Rajasthan',
                'display_order'     => 1,
                'is_active'         => true,
                'is_primary'        => true,
            ],
        ];
    }
}
