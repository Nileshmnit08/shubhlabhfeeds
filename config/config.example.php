<?php
/**
 * Shubh Labh CMS - Example Configuration Template
 * 
 * INSTRUCTIONS:
 * 1. Copy this file to a private directory outside your public web root,
 *    or rename it to `config.local.php` in the `config/` directory.
 * 2. Fill in your actual database credentials and environment values.
 * 3. NEVER commit `config.local.php` or `.env` containing real credentials to Git.
 */

return [
    'app' => [
        'name'        => 'Shubh Labh CMS',
        'env'         => 'production', // 'production' or 'development'
        'debug'       => false,        // STRICTLY false in production
        'url'         => 'https://example.com',
        'timezone'    => 'Asia/Kolkata',
    ],

    'database' => [
        'host'        => '127.0.0.1',
        'port'        => 3306,
        'dbname'      => 'shubhlabh_cms',
        'username'    => 'db_username_placeholder',
        'password'    => 'db_password_placeholder',
        'charset'     => 'utf8mb4',
        'timeout'     => 5, // Connection timeout in seconds
    ],

    'session' => [
        'name'           => 'shubhlabh_session',
        'idle_timeout'   => 1800,  // 30 minutes in seconds
        'lifetime'       => 28800, // 8 hours in seconds
        'path'           => '/',
        'domain'         => '',
        'secure'         => true,  // Requires HTTPS (can be false ONLY during local dev)
        'httponly'       => true,  // Prevent JavaScript cookie access
        'samesite'       => 'Strict', // Strict CSRF cookie isolation
    ],

    'security' => [
        'max_login_attempts' => 5,   // Max failed logins before lockout
        'lockout_duration'   => 900, // 15 minutes lockout in seconds
        'csrf_token_name'    => 'csrf_token',
        'hash_algorithm'     => 'argon2id', // 'argon2id' or 'bcrypt'
        'bcrypt_cost'        => 12,
    ],

    'crm' => [
        // IMMUTABLE - Links directly to external Shubh Labh CRM
        'login_url' => 'https://shubhlabh-crm.vercel.app/',
    ],

    'storage' => [
        'logs_path'    => dirname(__DIR__) . '/storage/logs/app.log',
        'uploads_path' => dirname(__DIR__) . '/public/uploads',
    ],
];
