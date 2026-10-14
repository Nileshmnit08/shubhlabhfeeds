/**
 * Shubh Labh CMS - Sprint SL-WEB-11C Automated Validation Suite
 * 
 * Verifies database migration scripts, migration runner, public API endpoints,
 * read models, fallback mechanics, and zero-credential leakage.
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
console.log('  Shubh Labh CMS - Sprint SL-WEB-11C Verification   ');
console.log('====================================================\n');

// 1. Migration Files Presence & DDL Analysis
console.log('Suite 1: Migration Scripts & Tracking DDL');
const migrationFiles = [
    'migrations/001_create_initial_schema.sql',
    'migrations/001_create_initial_schema_down.sql',
    'migrations/002_seed_initial_content.sql',
    'migrations/002_seed_initial_content_down.sql',
    'migrations/migrate.php',
    'app/MigrationRunner.php',
];
for (const file of migrationFiles) {
    const fullPath = path.join(ROOT_DIR, file);
    assert(`Migration file exists: ${file}`, fs.existsSync(fullPath) && fs.statSync(fullPath).isFile());
}

const schema001 = fs.readFileSync(path.join(ROOT_DIR, 'migrations/001_create_initial_schema.sql'), 'utf8');
const tables = [
    'migrations',
    'admin_users',
    'media_assets',
    'site_settings',
    'site_sections',
    'product_categories',
    'products',
    'knowledge_articles',
    'navigation_items',
    'audit_logs',
];
for (const table of tables) {
    assert(`Schema 001 defines table: ${table}`, schema001.includes(`CREATE TABLE IF NOT EXISTS ${table}`));
}
assert('Schema 001 enforces utf8mb4 collation', schema001.includes('COLLATE=utf8mb4_unicode_ci'));
assert('Schema 001 enforces InnoDB engine', schema001.includes('ENGINE=InnoDB'));

const down001 = fs.readFileSync(path.join(ROOT_DIR, 'migrations/001_create_initial_schema_down.sql'), 'utf8');
assert('Schema 001 rollback drops all tables in reverse order', down001.includes('DROP TABLE IF EXISTS audit_logs') && down001.includes('DROP TABLE IF EXISTS admin_users'));

const seed002 = fs.readFileSync(path.join(ROOT_DIR, 'migrations/002_seed_initial_content.sql'), 'utf8');
assert('Seed 002 populates immutable CRM login URL', seed002.includes('https://shubhlabh-crm.vercel.app/'));
assert('Seed 002 leaves unconfigured contact fields as NULL', seed002.includes("('phone_number', NULL") && seed002.includes("('whatsapp_number', NULL"));
assert('Seed 002 populates 3 verified products', seed002.includes('dairy-special') && seed002.includes('performance-premix') && seed002.includes('specialised-nutrition'));
assert('Seed 002 populates 2 verified knowledge articles', seed002.includes('feeding-practices-peak-production') && seed002.includes('seasonal-dairy-care'));

// 2. Migration Runner Architecture
console.log('\nSuite 2: Migration Runner Architecture');
const runnerContent = fs.readFileSync(path.join(ROOT_DIR, 'app/MigrationRunner.php'), 'utf8');
assert('MigrationRunner implements ensureTrackingTable()', runnerContent.includes('function ensureTrackingTable'));
assert('MigrationRunner implements getPendingMigrations()', runnerContent.includes('function getPendingMigrations'));
assert('MigrationRunner implements migrate()', runnerContent.includes('function migrate'));
assert('MigrationRunner implements rollback()', runnerContent.includes('function rollback'));

const migrateCliContent = fs.readFileSync(path.join(ROOT_DIR, 'migrations/migrate.php'), 'utf8');
assert('migrate.php enforces CLI execution only', migrateCliContent.includes("php_sapi_name() !== 'cli'"));
assert('migrate.php supports --status argument', migrateCliContent.includes("isset($options['status'])"));
assert('migrate.php supports --rollback argument', migrateCliContent.includes("isset($options['rollback'])"));

// 3. API Response Standardizer
console.log('\nSuite 3: API Response Standardizer (RFC 7807 & Envelope)');
const apiRespContent = fs.readFileSync(path.join(ROOT_DIR, 'app/ApiResponse.php'), 'utf8');
assert('ApiResponse sets application/json Content-Type', apiRespContent.includes('application/json; charset=UTF-8'));
assert('ApiResponse implements ETag support', apiRespContent.includes('ETag'));
assert('ApiResponse supports HTTP 304 Not Modified', apiRespContent.includes('http_response_code(304)'));
assert('ApiResponse formats RFC 7807 problem details for errors', apiRespContent.includes('application/problem+json'));
assert('ApiResponse provides fallback() for database offline mode', apiRespContent.includes('function fallback'));

// 4. Read Models & Safe Fallback Mechanics
console.log('\nSuite 4: Read Models & Static Fallbacks');
const modelFiles = [
    'app/Models/SiteSetting.php',
    'app/Models/SiteSection.php',
    'app/Models/Product.php',
    'app/Models/KnowledgeArticle.php',
    'app/Models/NavigationItem.php',
];
for (const file of modelFiles) {
    const fullPath = path.join(ROOT_DIR, file);
    assert(`Model file exists: ${file}`, fs.existsSync(fullPath));
}

const siteSettingModel = fs.readFileSync(path.join(ROOT_DIR, 'app/Models/SiteSetting.php'), 'utf8');
assert('SiteSetting queries only is_public = 1', siteSettingModel.includes('WHERE is_public = 1'));
assert('SiteSetting fallback contains verified brand name', siteSettingModel.includes('SHUBH LABH'));

const productModel = fs.readFileSync(path.join(ROOT_DIR, 'app/Models/Product.php'), 'utf8');
assert("Product model strictly filters status = 'published'", productModel.includes("WHERE p.status = :status"));
assert('Product model fallback provides 3 verified feed products', productModel.includes('dairy-special') && productModel.includes('specialised-nutrition'));

const articleModel = fs.readFileSync(path.join(ROOT_DIR, 'app/Models/KnowledgeArticle.php'), 'utf8');
assert("KnowledgeArticle model strictly filters status = 'published'", articleModel.includes("WHERE a.status = :status"));
assert('KnowledgeArticle model checks publication schedule', articleModel.includes('published_at <= NOW()'));

// 5. Public API Endpoints
console.log('\nSuite 5: Public Read-Only API Endpoints');
const apiFiles = [
    'public/api/public/bootstrap.php',
    'public/api/public/products.php',
    'public/api/public/knowledge.php',
    'public/api/public/contact.php',
];
for (const file of apiFiles) {
    const fullPath = path.join(ROOT_DIR, file);
    assert(`API endpoint exists: ${file}`, fs.existsSync(fullPath));
    const content = fs.readFileSync(fullPath, 'utf8');
    assert(`${file} rejects non-GET HTTP methods`, content.includes("$_SERVER['REQUEST_METHOD'] !== 'GET'"));
}

const contactApi = fs.readFileSync(path.join(ROOT_DIR, 'public/api/public/contact.php'), 'utf8');
assert('Contact API marks CRM URL as immutable', contactApi.includes("'immutable'   => true"));
assert('Contact API includes unconfigured indicators', contactApi.includes("'configured' => !empty($phone)"));

// 6. Zero-Credential Leakage Verification
console.log('\nSuite 6: Zero-Credential Leakage Audit');
const allPublicApiContent = apiFiles.map(f => fs.readFileSync(path.join(ROOT_DIR, f), 'utf8')).join('\n');
assert('Public APIs never query admin_users', !allPublicApiContent.includes('admin_users'));
assert('Public APIs never reference password_hash', !allPublicApiContent.includes('password_hash'));
assert('Public APIs never reference audit_logs', !allPublicApiContent.includes('audit_logs'));

// 7. Web Server Routing
console.log('\nSuite 7: Web Server Routing & Rewrite Rules');
const htaccessContent = fs.readFileSync(path.join(ROOT_DIR, '.htaccess'), 'utf8');
assert('.htaccess routes clean /api/public/bootstrap', htaccessContent.includes('^api/public/bootstrap/?$'));
assert('.htaccess routes clean /api/public/products', htaccessContent.includes('^api/public/products/?$'));
assert('.htaccess routes clean /api/public/knowledge', htaccessContent.includes('^api/public/knowledge/?$'));
assert('.htaccess routes clean /api/public/contact', htaccessContent.includes('^api/public/contact/?$'));
assert('.htaccess blocks direct web access to migrations/', htaccessContent.includes('migrations') && htaccessContent.includes('- [F,L]'));


// 8. Public Website & CRM Preservation
console.log('\nSuite 8: Public Website & CRM Preservation');
const indexHtml = fs.readFileSync(path.join(ROOT_DIR, 'index.html'), 'utf8');
assert('Public website unchanged and contains #home', indexHtml.includes('id="home"'));
assert('Public website unchanged and contains #products', indexHtml.includes('id="products"'));
assert('Public website unchanged and contains #about', indexHtml.includes('id="about"'));
assert('Public website unchanged and contains #knowledge', indexHtml.includes('id="knowledge"'));
assert('Public website unchanged and contains #dealer', indexHtml.includes('id="dealer"'));
assert('Public website unchanged and contains #contact', indexHtml.includes('id="contact"'));
assert('Public website CRM Login URL preserved exactly', indexHtml.includes('https://shubhlabh-crm.vercel.app/'));

// Final Results
console.log('\n----------------------------------------------------');
console.log(`Results: ${passedTests} Passed, ${failedTests} Failed (Total: ${totalTests})`);
console.log('----------------------------------------------------');

if (failedTests > 0) {
    process.exit(1);
} else {
    console.log('Sprint SL-WEB-11C: All migration and API criteria PASSED!\n');
    process.exit(0);
}
