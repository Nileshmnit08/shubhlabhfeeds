<?php
/**
 * Shubh Labh CMS - Public Products API
 * Endpoint: GET /api/public/products
 * Query Params: ?category=slug | ?slug=product-slug
 */

require_once dirname(__DIR__, 3) . '/config/Config.php';
require_once dirname(__DIR__, 3) . '/app/Logger.php';
require_once dirname(__DIR__, 3) . '/app/Database.php';
require_once dirname(__DIR__, 3) . '/app/ApiResponse.php';
require_once dirname(__DIR__, 3) . '/app/Models/Product.php';

use ShubhLabh\App\ApiResponse;
use ShubhLabh\App\Models\Product;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed. Only GET is supported.', 405, 'Method Not Allowed');
}

try {
    $slug = $_GET['slug'] ?? null;
    $category = $_GET['category'] ?? null;

    if (!empty($slug)) {
        $product = Product::getBySlug($slug);
        if (!$product) {
            ApiResponse::error("Product with slug '{$slug}' not found.", 404, 'Not Found');
        }
        ApiResponse::success($product, 200, [], 60);
    }

    $products = Product::getAllPublished($category);
    ApiResponse::success($products, 200, ['count' => count($products)], 60);

} catch (\Throwable $e) {
    ApiResponse::error('Failed to retrieve products.', 500, 'Internal Server Error');
}
