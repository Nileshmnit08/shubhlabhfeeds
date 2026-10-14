<?php
/**
 * Shubh Labh CMS - Public Bootstrap API
 * Endpoint: GET /api/public/bootstrap
 */

require_once dirname(__DIR__, 3) . '/config/Config.php';
require_once dirname(__DIR__, 3) . '/app/Logger.php';
require_once dirname(__DIR__, 3) . '/app/Database.php';
require_once dirname(__DIR__, 3) . '/app/ApiResponse.php';
require_once dirname(__DIR__, 3) . '/app/Models/SiteSetting.php';
require_once dirname(__DIR__, 3) . '/app/Models/SiteSection.php';
require_once dirname(__DIR__, 3) . '/app/Models/NavigationItem.php';

use ShubhLabh\App\ApiResponse;
use ShubhLabh\App\Models\SiteSetting;
use ShubhLabh\App\Models\SiteSection;
use ShubhLabh\App\Models\NavigationItem;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed. Only GET is supported.', 405, 'Method Not Allowed');
}

try {
    $settings = SiteSetting::getAllPublic();
    $sections = SiteSection::getAllPublished();
    $navigation = [
        'header' => NavigationItem::getByLocation('header'),
        'footer' => NavigationItem::getByLocation('footer'),
    ];

    ApiResponse::success([
        'settings'   => $settings,
        'sections'   => $sections,
        'navigation' => $navigation,
    ], 200, [], 120);

} catch (\Throwable $e) {
    ApiResponse::error('Failed to load site bootstrap data.', 500, 'Internal Server Error');
}
