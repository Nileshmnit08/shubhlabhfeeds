<?php
/**
 * CLI Administrator Provisioning Tool for Shubh Labh CMS
 * 
 * SECURITY RULES:
 * 1. CLI execution only. Web HTTP access is strictly rejected.
 * 2. Passwords are never logged or stored in plaintext.
 * 3. Rejects duplicate accounts.
 * 4. Safe retry behavior.
 * 
 * Usage:
 *   php cli/create_admin.php
 *   php cli/create_admin.php --email="admin@example.com" --username="admin" --name="Administrator"
 */

// 1. Strictly enforce CLI execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "Forbidden: Command-line execution only.\n";
    exit(1);
}

// Load application dependencies
require_once dirname(__DIR__) . '/config/Config.php';
require_once dirname(__DIR__) . '/app/Logger.php';
require_once dirname(__DIR__) . '/app/Database.php';
require_once dirname(__DIR__) . '/app/Session.php';
require_once dirname(__DIR__) . '/app/CSRF.php';
require_once dirname(__DIR__) . '/app/RateLimiter.php';
require_once dirname(__DIR__) . '/app/Auth.php';

use ShubhLabh\App\Auth;
use ShubhLabh\App\Database;
use ShubhLabh\App\Logger;

echo "====================================================\n";
echo "  Shubh Labh CMS - Administrator Provisioning Tool  \n";
echo "====================================================\n\n";

// Parse command-line options
$options = getopt('', ['email::', 'username::', 'name::', 'password::']);

$email = $options['email'] ?? null;
$username = $options['username'] ?? null;
$fullName = $options['name'] ?? null;
$password = $options['password'] ?? null;

// Interactive input if not provided via flags
if (empty($email)) {
    echo "Enter Administrator Email: ";
    $email = trim((string)fgets(STDIN));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "\n[ERROR] Invalid email format: '{$email}'.\n";
    exit(1);
}

if (empty($username)) {
    echo "Enter Username (alphanumeric, min 3 chars): ";
    $username = trim((string)fgets(STDIN));
}

if (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username)) {
    echo "\n[ERROR] Username must be between 3 and 50 alphanumeric characters.\n";
    exit(1);
}

if (empty($fullName)) {
    echo "Enter Full Name: ";
    $fullName = trim((string)fgets(STDIN));
    if (empty($fullName)) {
        $fullName = 'Administrator';
    }
}

if (empty($password)) {
    echo "Enter Administrator Password (min 8 chars): ";
    // Use hidden prompt if supported on UNIX, otherwise standard STDIN
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN' && exec('which stty')) {
        system('stty -echo');
        $password = trim((string)fgets(STDIN));
        system('stty echo');
        echo "\n";
    } else {
        $password = trim((string)fgets(STDIN));
    }
}

if (strlen($password) < 8) {
    echo "\n[ERROR] Password must be at least 8 characters long.\n";
    exit(1);
}

// Check database connection and schema presence
try {
    $pdo = Database::getConnection();

    // Check if table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'admin_users'")->fetch();
    if (!$checkTable) {
        echo "\n[ERROR] Table 'admin_users' does not exist.\n";
        echo "Database schema migrations have not yet been executed.\n";
        echo "Please execute schema migrations (Sprint SL-WEB-11C) prior to provisioning accounts.\n";
        exit(1);
    }

    // Check for duplicate account
    $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE email = :email OR username = :username LIMIT 1');
    $stmt->execute(['email' => strtolower($email), 'username' => $username]);
    if ($stmt->fetch()) {
        echo "\n[ERROR] An administrator with this email or username already exists.\n";
        echo "Provisioning refused to prevent overwriting existing accounts.\n";
        exit(1);
    }

    // Hash password and insert
    $passwordHash = Auth::hashPassword($password);

    $insert = $pdo->prepare('
        INSERT INTO admin_users (username, email, password_hash, full_name, role, is_active)
        VALUES (:username, :email, :password_hash, :full_name, :role, 1)
    ');

    $insert->execute([
        'username'      => $username,
        'email'         => strtolower($email),
        'password_hash' => $passwordHash,
        'full_name'     => $fullName,
        'role'          => 'super_admin',
    ]);

    Logger::info('New administrator provisioned via CLI', ['username' => $username, 'email' => $email]);

    echo "\n[SUCCESS] Administrator account provisioned successfully!\n";
    echo "Email:    " . strtolower($email) . "\n";
    echo "Username: " . $username . "\n";
    echo "Role:     super_admin\n\n";
    exit(0);

} catch (\Throwable $e) {
    echo "\n[FATAL ERROR] Provisioning failed: " . $e->getMessage() . "\n";
    exit(1);
}
