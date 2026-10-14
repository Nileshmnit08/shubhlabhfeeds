<?php
/**
 * Shubh Labh CMS - Public Knowledge & Nutrition API
 * Endpoint: GET /api/public/knowledge
 * Query Params: ?category=name | ?slug=article-slug
 */

require_once dirname(__DIR__, 3) . '/config/Config.php';
require_once dirname(__DIR__, 3) . '/app/Logger.php';
require_once dirname(__DIR__, 3) . '/app/Database.php';
require_once dirname(__DIR__, 3) . '/app/ApiResponse.php';
require_once dirname(__DIR__, 3) . '/app/Models/KnowledgeArticle.php';

use ShubhLabh\App\ApiResponse;
use ShubhLabh\App\Models\KnowledgeArticle;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed. Only GET is supported.', 405, 'Method Not Allowed');
}

try {
    $slug = $_GET['slug'] ?? null;
    $category = $_GET['category'] ?? null;

    if (!empty($slug)) {
        $article = KnowledgeArticle::getBySlug($slug);
        if (!$article) {
            ApiResponse::error("Article with slug '{$slug}' not found.", 404, 'Not Found');
        }
        ApiResponse::success($article, 200, [], 60);
    }

    $articles = KnowledgeArticle::getAllPublished($category);
    ApiResponse::success($articles, 200, ['count' => count($articles)], 60);

} catch (\Throwable $e) {
    ApiResponse::error('Failed to retrieve knowledge articles.', 500, 'Internal Server Error');
}
