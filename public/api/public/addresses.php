<?php
/**
 * Shubh Labh CMS - Public Company Addresses API
 * Endpoint: GET /api/public/addresses
 */

require_once dirname(__DIR__, 3) . '/config/Config.php';
require_once dirname(__DIR__, 3) . '/app/Logger.php';
require_once dirname(__DIR__, 3) . '/app/Database.php';
require_once dirname(__DIR__, 3) . '/app/ApiResponse.php';
require_once dirname(__DIR__, 3) . '/app/Models/CompanyAddress.php';

use ShubhLabh\App\ApiResponse;
use ShubhLabh\App\Models\CompanyAddress;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed. Only GET is supported.', 405, 'Method Not Allowed');
}

try {
    $addresses = CompanyAddress::getAllActive();
    ApiResponse::success($addresses, 200, [], 120);
} catch (\Throwable $e) {
    ApiResponse::error('Failed to retrieve company addresses.', 500, 'Internal Server Error');
}
