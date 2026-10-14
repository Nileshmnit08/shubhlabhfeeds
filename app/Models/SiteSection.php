<?php

namespace ShubhLabh\App\Models;

use PDO;
use ShubhLabh\App\Database;

/**
 * Site Sections Model (Read-Only Public Interface)
 */
class SiteSection
{
    /**
     * Retrieve all published site sections keyed by section_key
     */
    public static function getAllPublished(): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query('
                SELECT s.section_key, s.title, s.subtitle, s.eyebrow, s.lead_text, s.body_text,
                       s.primary_cta_text, s.primary_cta_url, s.secondary_cta_text, s.secondary_cta_url,
                       s.additional_data, m.file_path AS media_path, m.alt_text AS media_alt
                FROM site_sections s
                LEFT JOIN media_assets m ON s.media_id = m.id
                WHERE s.is_published = 1
                ORDER BY s.id ASC
            ');
            $rows = $stmt->fetchAll();

            $sections = [];
            foreach ($rows as $row) {
                if (!empty($row['additional_data'])) {
                    $row['additional_data'] = json_decode($row['additional_data'], true);
                }
                $sections[$row['section_key']] = $row;
            }

            return $sections;
        } catch (\Throwable) {
            return self::getFallbackSections();
        }
    }

    /**
     * Get specific section
     */
    public static function get(string $sectionKey): ?array
    {
        $all = self::getAllPublished();
        return $all[$sectionKey] ?? null;
    }

    /**
     * Verified static baseline fallback
     */
    public static function getFallbackSections(): array
    {
        return [
            'hero' => [
                'section_key'        => 'hero',
                'title'              => 'Better nutrition. Stronger dairy.',
                'subtitle'           => 'Thoughtful cattle nutrition for the people who keep India\'s dairy story moving forward.',
                'eyebrow'            => 'A partner in dairy progress',
                'lead_text'          => null,
                'body_text'          => null,
                'primary_cta_text'   => 'Explore our products ↗',
                'primary_cta_url'    => '#products',
                'secondary_cta_text' => 'Discover Shubh Labh →',
                'secondary_cta_url'  => '#about',
                'additional_data'    => [
                    'kicker'    => 'NOURISH THE HERD. NURTURE THE FUTURE.',
                    'indicator' => '01 / 03 • Nutrition • Care • Progress',
                ],
                'media_path'         => null,
                'media_alt'          => null,
            ],
            'trust_strip' => [
                'section_key'     => 'trust_strip',
                'title'           => 'Core Guiding Pillars',
                'additional_data' => [
                    'Farmer-first thinking',
                    'Nutrition-led approach',
                    'Quality-minded processes',
                    'Growing together',
                ],
            ],
            'about' => [
                'section_key'     => 'about',
                'title'           => 'For every farmer\'s effort, there is a bigger dream.',
                'subtitle'        => 'Rooted in trust. Focused on progress.',
                'eyebrow'         => 'Welcome to Shubh Labh',
                'lead_text'       => 'Dairy farming is built on daily dedication. We bring thoughtful nutrition, balanced formulation, and honest quality to every bag.',
                'body_text'       => 'Shubh Labh brings together cattle-feed products designed to support animal wellness, milk productivity, and herd longevity. Rooted in farmer trust and focused on sustainable dairy progress.',
                'additional_data' => [
                    'pillars' => [
                        ['title' => 'Farmer at the centre', 'desc' => 'Every feed formulation starts from the practical realities of daily dairy management.'],
                        ['title' => 'Quality with care', 'desc' => 'Clean raw materials, balanced nutritional ratios, and strict batch hygiene.'],
                        ['title' => 'Progress together', 'desc' => 'Partnering with dairy families and rural distributors for lasting growth.'],
                    ],
                ],
            ],
            'dealer' => [
                'section_key'      => 'dealer',
                'title'            => 'Let\'s take dairy nutrition further.',
                'subtitle'         => 'Partnering with progressive rural entrepreneurs across Rajasthan.',
                'eyebrow'          => 'Become a Dealer',
                'body_text'        => 'We partner with passionate distributors and rural dealers who believe in farmer-first values, transparent quality, and steady business growth.',
                'primary_cta_text' => 'Enquire about dealership ↗',
                'primary_cta_url'  => '#contact',
                'additional_data'  => [
                    'benefits' => [
                        'Consistent commercial margins and dependable dispatch',
                        'Farmer-tested feed formulations with strong field acceptance',
                        'Dedicated marketing support and nutritional advisory guidance',
                    ],
                ],
            ],
        ];
    }
}
