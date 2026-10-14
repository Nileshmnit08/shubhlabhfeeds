<?php

namespace ShubhLabh\App\Models;

use PDO;
use ShubhLabh\App\Database;

/**
 * Product Catalog Model (Read-Only Public Interface)
 */
class Product
{
    /**
     * Retrieve all published products, optionally filtered by category slug
     */
    public static function getAllPublished(?string $categorySlug = null): array
    {
        try {
            $pdo = Database::getConnection();

            $sql = '
                SELECT p.id, p.name, p.slug, p.short_description, p.full_description,
                       p.packaging_desc, p.display_order, p.status, p.created_at,
                       c.id AS category_id, c.name AS category_name, c.slug AS category_slug, c.category_badge,
                       m.file_path AS image_url, m.alt_text AS image_alt
                FROM products p
                INNER JOIN product_categories c ON p.category_id = c.id
                LEFT JOIN media_assets m ON p.main_image_id = m.id
                WHERE p.status = :status
            ';

            $params = ['status' => 'published'];

            if (!empty($categorySlug)) {
                $sql .= ' AND c.slug = :category_slug';
                $params['category_slug'] = $categorySlug;
            }

            $sql .= ' ORDER BY p.display_order ASC, p.id ASC';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return self::getFallbackProducts($categorySlug);
        }
    }

    /**
     * Retrieve single product by slug
     */
    public static function getBySlug(string $slug): ?array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('
                SELECT p.id, p.name, p.slug, p.short_description, p.full_description,
                       p.packaging_desc, p.display_order, p.status, p.created_at,
                       c.id AS category_id, c.name AS category_name, c.slug AS category_slug, c.category_badge,
                       m.file_path AS image_url, m.alt_text AS image_alt
                FROM products p
                INNER JOIN product_categories c ON p.category_id = c.id
                LEFT JOIN media_assets m ON p.main_image_id = m.id
                WHERE p.slug = :slug AND p.status = :status
                LIMIT 1
            ');
            $stmt->execute(['slug' => $slug, 'status' => 'published']);
            $product = $stmt->fetch();
            return $product ?: null;
        } catch (\Throwable) {
            $fallbacks = self::getFallbackProducts();
            foreach ($fallbacks as $item) {
                if ($item['slug'] === $slug) {
                    return $item;
                }
            }
            return null;
        }
    }

    /**
     * Verified static baseline products fallback
     */
    public static function getFallbackProducts(?string $categorySlug = null): array
    {
        $products = [
            [
                'id'                => 1,
                'name'              => 'Shubh Labh Dairy Special',
                'slug'              => 'dairy-special',
                'category_name'     => 'Cattle Feed',
                'category_slug'     => 'cattle-feed',
                'category_badge'    => 'CATTLE FEED',
                'short_description' => 'Balanced dairy ration crafted to support milk yield, digestion, and daily body condition in milking cows and buffaloes.',
                'full_description'  => null,
                'packaging_desc'    => 'Available in 50 kg moisture-resistant woven bags',
                'display_order'     => 1,
                'status'            => 'published',
                'image_url'         => null,
                'image_alt'         => 'Shubh Labh Dairy Special Cattle Feed packaging mockup',
            ],
            [
                'id'                => 2,
                'name'              => 'Performance Premix',
                'slug'              => 'performance-premix',
                'category_name'     => 'Nutrition Support',
                'category_slug'     => 'nutrition-support',
                'category_badge'    => 'NUTRITION SUPPORT',
                'short_description' => 'Nutritional formulation built for balanced energy, mineral support, and consistent dairy performance.',
                'full_description'  => null,
                'packaging_desc'    => 'Available in 25 kg & 50 kg packs',
                'display_order'     => 2,
                'status'            => 'published',
                'image_url'         => null,
                'image_alt'         => 'Performance Premix packaging mockup',
            ],
            [
                'id'                => 3,
                'name'              => 'Specialised Nutrition',
                'slug'              => 'specialised-nutrition',
                'category_name'     => 'Nutrition Support',
                'category_slug'     => 'nutrition-support',
                'category_badge'    => 'NUTRITION SUPPORT',
                'short_description' => 'Targeted feeding solutions for transition phases, dry cows, and growing calves.',
                'full_description'  => null,
                'packaging_desc'    => 'Available in 25 kg bags',
                'display_order'     => 3,
                'status'            => 'published',
                'image_url'         => null,
                'image_alt'         => 'Specialised Nutrition packaging mockup',
            ],
        ];

        if ($categorySlug !== null) {
            return array_values(array_filter($products, fn($p) => $p['category_slug'] === $categorySlug));
        }

        return $products;
    }
}
