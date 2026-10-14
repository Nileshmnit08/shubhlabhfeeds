<?php

namespace ShubhLabh\App\Models;

use PDO;
use ShubhLabh\App\Database;

/**
 * Navigation Items Model (Read-Only Public Interface)
 */
class NavigationItem
{
    /**
     * Retrieve active navigation items by location ('header' or 'footer')
     */
    public static function getByLocation(string $location = 'header'): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('
                SELECT id, location, label, url, target, display_order
                FROM navigation_items
                WHERE location = :loc AND is_active = 1
                ORDER BY display_order ASC, id ASC
            ');
            $stmt->execute(['loc' => $location]);
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return self::getFallbackNavItems($location);
        }
    }

    /**
     * Verified static baseline navigation fallback
     */
    public static function getFallbackNavItems(string $location = 'header'): array
    {
        if ($location === 'header') {
            return [
                ['id' => 1, 'location' => 'header', 'label' => 'Home', 'url' => '#home', 'target' => '_self', 'display_order' => 1],
                ['id' => 2, 'location' => 'header', 'label' => 'Products', 'url' => '#products', 'target' => '_self', 'display_order' => 2],
                ['id' => 3, 'location' => 'header', 'label' => 'About', 'url' => '#about', 'target' => '_self', 'display_order' => 3],
                ['id' => 4, 'location' => 'header', 'label' => 'Knowledge', 'url' => '#knowledge', 'target' => '_self', 'display_order' => 4],
                ['id' => 5, 'location' => 'header', 'label' => 'Become a Dealer', 'url' => '#dealer', 'target' => '_self', 'display_order' => 5],
                ['id' => 6, 'location' => 'header', 'label' => 'Contact', 'url' => '#contact', 'target' => '_self', 'display_order' => 6],
            ];
        }

        return [];
    }
}
