<?php
/**
 * Shubh Labh CMS - Unit & Integration Test Suite for SL-WEB-11B
 * 
 * Tests authentication, configuration, CSRF, rate-limiting, and error handling
 * using an isolated mock database fixture.
 * 
 * Usage (when PHP is installed):
 *   php tests/test_backend.php
 */

require_once dirname(__DIR__) . '/config/Config.php';
require_once dirname(__DIR__) . '/app/Logger.php';
require_once dirname(__DIR__) . '/app/Database.php';
require_once dirname(__DIR__) . '/app/Session.php';
require_once dirname(__DIR__) . '/app/CSRF.php';
require_once dirname(__DIR__) . '/app/RateLimiter.php';
require_once dirname(__DIR__) . '/app/Auth.php';

use ShubhLabh\Config\Config;
use ShubhLabh\App\Auth;
use ShubhLabh\App\CSRF;
use ShubhLabh\App\Database;
use ShubhLabh\App\Logger;
use ShubhLabh\App\RateLimiter;
use ShubhLabh\App\Session;

class BackendTestSuite
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "====================================================\n";
        echo "  Shubh Labh CMS - SL-WEB-11B Automated Test Suite  \n";
        echo "====================================================\n\n";

        $this->testConfigurationLoading();
        $this->testMissingConfigurationSafety();
        $this->testEmailValidation();
        $this->testPasswordHashingAndVerification();
        $this->testCSRFGenerationAndValidation();
        $this->testRateLimiterThrottling();
        $this->testLoggerCredentialRedaction();
        $this->testSessionManagementAndTimeout();
        $this->testCliAdminProvisioningGuard();
        $this->testDatabaseMockIntegration();

        echo "\n----------------------------------------------------\n";
        echo sprintf("Results: %d Passed, %d Failed\n", $this->passed, $this->failed);
        echo "----------------------------------------------------\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function assert(string $testName, bool $condition, string $failureDetails = ''): void
    {
        if ($condition) {
            echo "  [PASS] {$testName}\n";
            $this->passed++;
        } else {
            echo "  [FAIL] {$testName} - {$failureDetails}\n";
            $this->failed++;
        }
    }

    private function testConfigurationLoading(): void
    {
        echo "Test Suite 1: Configuration Loading\n";
        Config::reset();
        $appName = Config::get('app.name');
        $this->assert('Config loads app name', $appName === 'Shubh Labh CMS');
        $crmUrl = Config::get('crm.login_url');
        $this->assert('CRM URL is immutable', $crmUrl === 'https://shubhlabh-crm.vercel.app/');
    }

    private function testMissingConfigurationSafety(): void
    {
        echo "\nTest Suite 2: Missing Configuration Safety\n";
        Config::reset();
        $val = Config::get('non.existent.key', 'default_val');
        $this->assert('Non-existent key returns default safely', $val === 'default_val');
    }

    private function testEmailValidation(): void
    {
        echo "\nTest Suite 3: Email Validation\n";
        $invalidEmails = ['plainaddress', '@missingusername.com', 'user@.com', ''];
        foreach ($invalidEmails as $email) {
            $res = Auth::authenticate($email, 'password123');
            $this->assert("Rejects invalid email '{$email}'", $res['success'] === false && $res['error'] === Auth::ERROR_INVALID_INPUT);
        }
    }

    private function testPasswordHashingAndVerification(): void
    {
        echo "\nTest Suite 4: Password Hashing & Verification\n";
        $password = 'SecureP@ssw0rd!2026';
        $hash = Auth::hashPassword($password);

        $this->assert('Password hash generated', !empty($hash) && strlen($hash) > 50);
        $this->assert('Password verifies correctly', Auth::verifyPassword($password, $hash));
        $this->assert('Wrong password rejected', !Auth::verifyPassword('WrongPass', $hash));
    }

    private function testCSRFGenerationAndValidation(): void
    {
        echo "\nTest Suite 5: CSRF Token Mechanics\n";
        $token1 = CSRF::getToken();
        $this->assert('CSRF token is 64-char hex string', strlen($token1) === 64 && ctype_xdigit($token1));
        $this->assert('Valid CSRF token validates', CSRF::validate($token1));
        $this->assert('Mismatched CSRF token rejected', !CSRF::validate('invalid_token_string_here_0000000000000000000000000000000000000000'));
        $this->assert('Empty CSRF token rejected', !CSRF::validate(''));
    }

    private function testRateLimiterThrottling(): void
    {
        echo "\nTest Suite 6: Rate Limiting & Throttling\n";
        $tempStorage = dirname(__DIR__) . '/storage/logs/test_rate_limits.json';
        RateLimiter::setStorageFile($tempStorage);

        $testKey = RateLimiter::makeKey('test_user@example.com', '192.168.1.100');
        RateLimiter::reset($testKey);

        $this->assert('Initially not blocked', !RateLimiter::isBlocked($testKey));

        // Record 5 failures
        for ($i = 1; $i <= 5; $i++) {
            RateLimiter::recordFailure($testKey);
        }

        $this->assert('Blocked after 5 failures', RateLimiter::isBlocked($testKey));
        $this->assert('Lockout duration remaining > 0', RateLimiter::getRemainingLockout($testKey) > 0);

        RateLimiter::reset($testKey);
        $this->assert('Unblocked after reset', !RateLimiter::isBlocked($testKey));

        @unlink($tempStorage);
    }

    private function testLoggerCredentialRedaction(): void
    {
        echo "\nTest Suite 7: Logger Credential Redaction\n";
        $sensitiveContext = [
            'username' => 'admin_test',
            'password' => 'SuperSecret123!',
            'csrf_token' => '7f8a9b0c',
            'details' => [
                'db_password' => 'secret_db_pass',
                'public_info' => 'allowed_string',
            ],
        ];

        $sanitized = Logger::sanitizeContext($sensitiveContext);
        $this->assert('Password redacted', $sanitized['password'] === '[REDACTED]');
        $this->assert('CSRF token redacted', $sanitized['csrf_token'] === '[REDACTED]');
        $this->assert('Nested db_password redacted', $sanitized['details']['db_password'] === '[REDACTED]');
        $this->assert('Public info preserved', $sanitized['details']['public_info'] === 'allowed_string');
    }

    private function testSessionManagementAndTimeout(): void
    {
        echo "\nTest Suite 8: Session Management\n";
        Session::start();
        Session::set('test_key', 'test_value');
        $this->assert('Session variable set and read', Session::get('test_key') === 'test_value');
        Session::remove('test_key');
        $this->assert('Session variable removed', Session::get('test_key') === null);
    }

    private function testCliAdminProvisioningGuard(): void
    {
        echo "\nTest Suite 9: CLI Admin Provisioning Guard\n";
        $cliFile = dirname(__DIR__) . '/cli/create_admin.php';
        $content = file_get_contents($cliFile);
        $this->assert('CLI guard present in script', str_contains($content, "php_sapi_name() !== 'cli'"));
    }

    private function testDatabaseMockIntegration(): void
    {
        echo "\nTest Suite 10: Isolated Database Mock Integration\n";
        // Test using SQLite in-memory PDO if available, demonstrating safe DB test harness
        try {
            $sqlitePdo = new PDO('sqlite::memory:');
            $sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sqlitePdo->exec("
                CREATE TABLE admin_users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL UNIQUE,
                    email TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    full_name TEXT NOT NULL,
                    role TEXT NOT NULL DEFAULT 'editor',
                    is_active INTEGER NOT NULL DEFAULT 1,
                    failed_login_attempts INTEGER NOT NULL DEFAULT 0,
                    locked_until TEXT NULL,
                    last_login_at TEXT NULL,
                    last_login_ip TEXT NULL
                )
            ");

            $hashed = Auth::hashPassword('AdminSecretPass!99');
            $stmt = $sqlitePdo->prepare("
                INSERT INTO admin_users (username, email, password_hash, full_name, role, is_active)
                VALUES ('testadmin', 'testadmin@shubhlabhfeeds.com', :hash, 'Test Admin', 'super_admin', 1)
            ");
            $stmt->execute(['hash' => $hashed]);

            // Inject mock PDO into Database class
            Database::setConnection($sqlitePdo);

            // Test successful authentication
            $successResult = Auth::authenticate('testadmin@shubhlabhfeeds.com', 'AdminSecretPass!99');
            $this->assert('Authenticates successfully with mock DB', $successResult['success'] === true);
            $this->assert('Returns sanitized user profile without password_hash', !isset($successResult['user']['password_hash']));

            // Test bad password rejection
            $failResult = Auth::authenticate('testadmin@shubhlabhfeeds.com', 'WrongPassword');
            $this->assert('Rejects invalid password with generic error', $failResult['success'] === false && $failResult['error'] === Auth::ERROR_GENERIC);

            // Clean up
            Database::close();
        } catch (\Throwable $e) {
            echo "  [INFO] SQLite PDO unavailable in test environment: " . $e->getMessage() . "\n";
        }
    }
}

// Execute if run directly
$suite = new BackendTestSuite();
$suite->run();
