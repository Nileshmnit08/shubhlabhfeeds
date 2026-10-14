/**
 * Shubh Labh CMS - SL-WEB-11B Automated Validation Suite
 * Runs via Node.js to verify application structure, code security,
 * static frontend preservation, and non-negotiable architectural boundaries.
 */

const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');

let totalTests = 0;
let passedTests = 0;
let failedTests = 0;

function assert(description, condition, details = '') {
    totalTests++;
    if (condition) {
        console.log(`  [PASS] ${description}`);
        passedTests++;
    } else {
        console.error(`  [FAIL] ${description} - ${details}`);
        failedTests++;
    }
}

console.log('====================================================');
console.log('  Shubh Labh CMS - Sprint SL-WEB-11B Verification   ');
console.log('====================================================\n');

// 1. Directory Structure Checks
console.log('Suite 1: Directory Structure Verification');
const requiredDirs = [
    'app',
    'config',
    'private',
    'storage/logs',
    'cli',
    'public/admin',
    'tests',
];
for (const dir of requiredDirs) {
    const fullPath = path.join(ROOT_DIR, dir);
    assert(`Directory exists: ${dir}`, fs.existsSync(fullPath) && fs.statSync(fullPath).isDirectory());
}

// 2. File Presence Checks
console.log('\nSuite 2: Required File Presence');
const requiredFiles = [
    'config/config.example.php',
    'config/Config.php',
    'app/Logger.php',
    'app/Database.php',
    'app/CSRF.php',
    'app/Session.php',
    'app/RateLimiter.php',
    'app/Auth.php',
    'cli/create_admin.php',
    'public/admin/login.php',
    'public/admin/logout.php',
    'public/admin/index.php',
    '.htaccess',
    'private/.htaccess',
    'storage/logs/.htaccess',
    'tests/test_backend.php',
];
for (const file of requiredFiles) {
    const fullPath = path.join(ROOT_DIR, file);
    assert(`File exists: ${file}`, fs.existsSync(fullPath) && fs.statSync(fullPath).isFile());
}

// 3. Security Code Analysis
console.log('\nSuite 3: Backend Security Analysis');

// A. Config.php
const configContent = fs.readFileSync(path.join(ROOT_DIR, 'config/Config.php'), 'utf8');
assert('Config fails safely on missing configuration', configContent.includes('CMS Configuration error'));
assert('Config loads immutable CRM URL', configContent.includes('crm') && configContent.includes('login_url'));

// B. Database.php
const dbContent = fs.readFileSync(path.join(ROOT_DIR, 'app/Database.php'), 'utf8');
assert('Database disables emulated prepared statements', dbContent.includes('PDO::ATTR_EMULATE_PREPARES   => false'));
assert('Database enforces PDO::ERRMODE_EXCEPTION', dbContent.includes('PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION'));
assert('Database never logs credentials on failure', dbContent.includes("Logger::error('Database connection failed'"));

// C. CSRF.php
const csrfContent = fs.readFileSync(path.join(ROOT_DIR, 'app/CSRF.php'), 'utf8');
assert('CSRF uses cryptographically secure random_bytes', csrfContent.includes('random_bytes(32)'));
assert('CSRF uses timing-attack-safe hash_equals', csrfContent.includes('hash_equals('));

// D. Session.php
const sessionContent = fs.readFileSync(path.join(ROOT_DIR, 'app/Session.php'), 'utf8');
assert('Session configures HttpOnly', sessionContent.includes("'httponly' => $httpOnly"));
assert('Session configures SameSite=Strict', sessionContent.includes("'samesite' => $sameSite"));
assert('Session regenerates ID', sessionContent.includes('session_regenerate_id(true)'));
assert('Session enforces idle timeout', sessionContent.includes('checkIdleTimeout'));
assert('Session contains requireAuth route guard', sessionContent.includes('requireAuth'));

// E. Auth.php
const authContent = fs.readFileSync(path.join(ROOT_DIR, 'app/Auth.php'), 'utf8');
assert('Auth supports Argon2id hashing', authContent.includes('PASSWORD_ARGON2ID'));
assert('Auth supports Bcrypt fallback with cost >= 12', authContent.includes('PASSWORD_BCRYPT') && authContent.includes('max(12, $cost)'));
assert('Auth verifies password using password_verify', authContent.includes('password_verify('));
assert('Auth uses generic authentication error message', authContent.includes("ERROR_GENERIC = 'Invalid email or password.'"));
assert('Auth validates email via FILTER_VALIDATE_EMAIL', authContent.includes('FILTER_VALIDATE_EMAIL'));
assert('Auth strips password_hash from returned user object', authContent.includes("unset($user['password_hash'])"));

// F. RateLimiter.php
const rateContent = fs.readFileSync(path.join(ROOT_DIR, 'app/RateLimiter.php'), 'utf8');
assert('RateLimiter enforces lockout threshold', rateContent.includes('$attempts >= $maxAttempts'));
assert('RateLimiter supports reset on success', rateContent.includes('function reset('));

// G. Logger.php
const loggerContent = fs.readFileSync(path.join(ROOT_DIR, 'app/Logger.php'), 'utf8');
assert('Logger redacts passwords', loggerContent.includes("'password'") && loggerContent.includes('[REDACTED]'));
assert('Logger redacts CSRF tokens', loggerContent.includes("'csrf_token'"));
assert('Logger redacts database passwords', loggerContent.includes("'db_pass'"));

// H. CLI create_admin.php
const cliContent = fs.readFileSync(path.join(ROOT_DIR, 'cli/create_admin.php'), 'utf8');
assert('CLI script strictly rejects non-CLI SAPI execution', cliContent.includes("php_sapi_name() !== 'cli'"));
assert('CLI script checks for duplicate accounts', cliContent.includes('SELECT id FROM admin_users WHERE email = :email'));

// I. Admin Login & Dashboard UI
const loginContent = fs.readFileSync(path.join(ROOT_DIR, 'public/admin/login.php'), 'utf8');
assert('Login UI includes CSRF hidden token field', loginContent.includes('name="csrf_token"'));
assert('Login UI includes password visibility toggle', loginContent.includes('togglePassword'));
assert('Login UI preserves CRM portal link', loginContent.includes('https://shubhlabh-crm.vercel.app/'));

const dashboardContent = fs.readFileSync(path.join(ROOT_DIR, 'public/admin/index.php'), 'utf8');
assert('Dashboard UI strictly requires authentication', dashboardContent.includes("Session::requireAuth('/admin/login.php')"));
assert('Dashboard UI preserves CRM portal link', dashboardContent.includes('https://shubhlabh-crm.vercel.app/'));

// J. Web Server Protection (.htaccess)
const rootHtaccess = fs.readFileSync(path.join(ROOT_DIR, '.htaccess'), 'utf8');
assert('Root .htaccess blocks access to app/ directory', rootHtaccess.includes('RewriteRule ^(app|config|private|storage|cli|tests|migrations)/ - [F,L]'));
assert('Root .htaccess blocks direct access to .env and .sql files', rootHtaccess.includes('\\.(env.*|sql|md|log|json|lock|git.*)$ - [F,L]'));
assert('Root .htaccess disables directory indexing', rootHtaccess.includes('Options -Indexes'));

const gitignoreContent = fs.readFileSync(path.join(ROOT_DIR, '.gitignore'), 'utf8');
assert('.gitignore ignores .env files', gitignoreContent.includes('.env'));
assert('.gitignore ignores local config files', gitignoreContent.includes('config.local.php'));
assert('.gitignore ignores logs', gitignoreContent.includes('storage/logs/*.log'));

// 4. Frontend & CRM Non-Negotiable Preservation
console.log('\nSuite 4: Frontend & CRM Preservation');
const indexHtml = fs.readFileSync(path.join(ROOT_DIR, 'index.html'), 'utf8');
const sections = ['#home', '#products', '#about', '#knowledge', '#dealer', '#contact'];
for (const sec of sections) {
    assert(`Public website section anchor preserved: ${sec}`, indexHtml.includes(sec));
}
assert('Public website CRM Login URL preserved exactly', indexHtml.includes('https://shubhlabh-crm.vercel.app/'));

const stylesCss = fs.readFileSync(path.join(ROOT_DIR, 'styles.css'), 'utf8');
assert('styles.css intact (>30KB)', stylesCss.length > 30000);

const scriptJs = fs.readFileSync(path.join(ROOT_DIR, 'script.js'), 'utf8');
assert('script.js intact', scriptJs.includes('.site-header') && scriptJs.includes('IntersectionObserver'));


// Final Results
console.log('\n----------------------------------------------------');
console.log(`Results: ${passedTests} Passed, ${failedTests} Failed (Total: ${totalTests})`);
console.log('----------------------------------------------------');

if (failedTests > 0) {
    process.exit(1);
} else {
    console.log('All static and security criteria PASSED!\n');
    process.exit(0);
}
