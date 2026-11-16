<?php
/**
 * Shubh Labh CMS - Public Contact & Business API
 * Endpoint: GET /api/public/contact
 */

require_once dirname(__DIR__, 3) . '/config/Config.php';
require_once dirname(__DIR__, 3) . '/app/Logger.php';
require_once dirname(__DIR__, 3) . '/app/Database.php';
require_once dirname(__DIR__, 3) . '/app/ApiResponse.php';
require_once dirname(__DIR__, 3) . '/app/Models/SiteSetting.php';
require_once dirname(__DIR__, 3) . '/app/Models/CompanyAddress.php';

use ShubhLabh\App\ApiResponse;
use ShubhLabh\App\Models\SiteSetting;
use ShubhLabh\App\Models\CompanyAddress;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed. Only GET is supported.', 405, 'Method Not Allowed');
}

try {
    $settings = SiteSetting::getAllPublic();
    $addresses = CompanyAddress::getAllActive();
    $primaryAddress = !empty($addresses) ? $addresses[0] : null;

    $phone = $settings['phone_number'] ?? ($primaryAddress['office_phone'] ?? null);
    $whatsapp = $settings['whatsapp_number'] ?? ($primaryAddress['whatsapp_number'] ?? null);
    $email = $settings['public_email'] ?? ($primaryAddress['email'] ?? null);
    $address = $settings['postal_address'] ?? ($primaryAddress['formatted_address'] ?? null);
    $crmUrl = $settings['crm_login_url'] ?? 'https://shubhlabh-crm.vercel.app/';

    $response = [
        'brand_name'      => $settings['brand_name'] ?? 'SHUBH LABH',
        'brand_tagline'   => $settings['brand_tagline'] ?? 'DAIRY NUTRITION',
        'channels' => [
            'phone' => [
                'value'      => $phone,
                'configured' => !empty($phone),
            ],
            'whatsapp' => [
                'number'     => $whatsapp,
                'configured' => !empty($whatsapp),
            ],
            'email' => [
                'value'      => $email,
                'configured' => !empty($email),
            ],
            'postal_address' => [
                'value'      => $address,
                'configured' => !empty($address),
            ],
        ],
        'addresses' => $addresses,
        'portals' => [
            'crm_login' => [
                'url'         => $crmUrl,
                'label'       => 'CRM Portal Login',
                'description' => 'External administrative sales and dealer CRM',
                'immutable'   => true,
            ],
        ],
    ];

    ApiResponse::success($response, 200, [], 180);

} catch (\Throwable $e) {
    ApiResponse::error('Failed to retrieve contact data.', 500, 'Internal Server Error');
}
