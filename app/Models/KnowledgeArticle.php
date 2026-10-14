<?php

namespace ShubhLabh\App\Models;

use PDO;
use ShubhLabh\App\Database;

/**
 * Knowledge Articles Model (Read-Only Public Interface)
 */
class KnowledgeArticle
{
    /**
     * Retrieve all published educational articles
     */
    public static function getAllPublished(?string $category = null): array
    {
        try {
            $pdo = Database::getConnection();

            $sql = '
                SELECT a.id, a.title, a.slug, a.category, a.summary, a.content_body,
                       a.display_order, a.status, a.published_at,
                       m.file_path AS cover_image_url, m.alt_text AS cover_image_alt
                FROM knowledge_articles a
                LEFT JOIN media_assets m ON a.cover_image_id = m.id
                WHERE a.status = :status AND (a.published_at IS NULL OR a.published_at <= NOW())
            ';

            $params = ['status' => 'published'];

            if (!empty($category)) {
                $sql .= ' AND a.category = :category';
                $params['category'] = $category;
            }

            $sql .= ' ORDER BY a.display_order ASC, a.published_at DESC';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return self::getFallbackArticles($category);
        }
    }

    /**
     * Retrieve single article by slug
     */
    public static function getBySlug(string $slug): ?array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('
                SELECT a.id, a.title, a.slug, a.category, a.summary, a.content_body,
                       a.display_order, a.status, a.published_at,
                       m.file_path AS cover_image_url, m.alt_text AS cover_image_alt
                FROM knowledge_articles a
                LEFT JOIN media_assets m ON a.cover_image_id = m.id
                WHERE a.slug = :slug AND a.status = :status AND (a.published_at IS NULL OR a.published_at <= NOW())
                LIMIT 1
            ');
            $stmt->execute(['slug' => $slug, 'status' => 'published']);
            $article = $stmt->fetch();
            return $article ?: null;
        } catch (\Throwable) {
            $fallbacks = self::getFallbackArticles();
            foreach ($fallbacks as $item) {
                if ($item['slug'] === $slug) {
                    return $item;
                }
            }
            return null;
        }
    }

    /**
     * Verified static baseline articles fallback
     */
    public static function getFallbackArticles(?string $category = null): array
    {
        $articles = [
            [
                'id'              => 1,
                'title'           => 'Essential Feeding Practices for Peak Milk Production',
                'slug'            => 'feeding-practices-peak-production',
                'category'        => 'Dairy Nutrition',
                'summary'         => 'Practical guidance on dry matter intake, green fodder proportion, and clean drinking water for lactating animals.',
                'content_body'    => '<p>A balanced ration provides energy, protein, fiber, minerals, and vitamins in correct proportion. Ensuring ad-libitum clean drinking water and consistent daily feeding schedules enhances rumination and lactation stability.</p>',
                'display_order'   => 1,
                'status'          => 'published',
                'published_at'    => '2026-01-15 09:00:00',
                'cover_image_url' => null,
                'cover_image_alt' => 'Dairy cow feeding management',
            ],
            [
                'id'              => 2,
                'title'           => 'Seasonal Dairy Care & Transition Management',
                'slug'            => 'seasonal-dairy-care',
                'category'        => 'Dairy Care',
                'summary'         => 'Supporting cow health and metabolic balance through weather shifts and calving cycles.',
                'content_body'    => '<p>Managing heat stress during peak summers and providing shelter during chilly winter nights preserves lactation persistence and prevents metabolic disorders during transition phases.</p>',
                'display_order'   => 2,
                'status'          => 'published',
                'published_at'    => '2026-02-10 10:30:00',
                'cover_image_url' => null,
                'cover_image_alt' => 'Seasonal herd care practices',
            ],
        ];

        if ($category !== null) {
            return array_values(array_filter($articles, fn($a) => $a['category'] === $category));
        }

        return $articles;
    }
}
