<?php

namespace ShubhLabh\App\Models;

use PDO;
use ShubhLabh\App\Database;

/**
 * Site Settings Model (Read-Only Public Interface)
 */
class SiteSetting
{
    /**
     * Retrieve all public site settings as key-value pairs
     */
    public static function getAllPublic(): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query('
                SELECT setting_key, setting_value 
                FROM site_settings 
                WHERE is_public = 1 
                ORDER BY id ASC
            ');
            $rows = $stmt->fetchAll();

            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            return $settings;
        } catch (\Throwable) {
            // Database offline: return static verified fallback baseline
            return self::getFallbackSettings();
        }
    }

    /**
     * Get single setting value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::getAllPublic();
        return $all[$key] ?? $default;
    }

    /**
     * Verified static baseline fallback
     */
    public static function getFallbackSettings(): array
    {
        return [
            'brand_name'          => 'SHUBH LABH',
            'brand_tagline'       => 'DAIRY NUTRITION',
            'announcement_text'   => 'Nutrition made with farmers in mind ✳ Serving dairy ambitions across Rajasthan',
            'announcement_active' => '1',
            'crm_login_url'       => 'https://shubhlabh-crm.vercel.app/',
            'phone_number'        => null,
            'whatsapp_number'     => null,
            'public_email'        => null,
            'postal_address'      => null,
            'meta_title'          => 'Shubh Labh | Better Nutrition. Stronger Dairy.',
            'meta_description'    => 'Shubh Labh — cattle feed and dairy nutrition solutions built for cattle well-being and dairy farmer progress across Rajasthan.',
            'footer_text'         => 'Shubh Labh is committed to farmer-first dairy nutrition, balanced cattle feed formulation, and consistent quality across Rajasthan.',
        ];
    }
}
