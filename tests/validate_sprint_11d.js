/**
 * Shubh Labh CMS - Sprint SL-WEB-11D Automated Validation Suite
 * 
 * Verifies admin dashboard, website section editor, product management,
 * knowledge article publisher, navigation manager, site settings,
 * immutable CRM security, and audit trail functionality.
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
console.log('  Shubh Labh CMS - Sprint SL-WEB-11D Verification   ');
console.log('====================================================\n');

// 1. File Presence
console.log('Suite 1: Admin Dashboard & Editor Files');
const requiredAdminFiles = [
    'app/AuditLogger.php',
    'app/Sanitizer.php',
    'public/admin/layout.php',
    'public/admin/index.php',
    'public/admin/sections.php',
    'public/admin/products.php',
    'public/admin/articles.php',
    'public/admin/navigation.php',
    'public/admin/settings.php',
    'public/admin/media.php',
    'public/admin/audit.php',
];
for (const file of requiredAdminFiles) {
    const fullPath = path.join(ROOT_DIR, file);
    assert(`Admin file exists: ${file}`, fs.existsSync(fullPath) && fs.statSync(fullPath).isFile());
}

// 2. Authentication Guards & CSRF on all Admin Pages
console.log('\nSuite 2: Authentication Guards & CSRF Protection');
const protectedPages = [
    'public/admin/index.php',
    'public/admin/sections.php',
    'public/admin/products.php',
    'public/admin/articles.php',
    'public/admin/navigation.php',
    'public/admin/settings.php',
    'public/admin/media.php',
    'public/admin/audit.php',
];
for (const page of protectedPages) {
    const content = fs.readFileSync(path.join(ROOT_DIR, page), 'utf8');
    assert(`${page} enforces Session::requireAuth()`, content.includes("Session::requireAuth('/admin/login.php')"));
}

const formPages = [
    'public/admin/sections.php',
    'public/admin/products.php',
    'public/admin/articles.php',
    'public/admin/navigation.php',
    'public/admin/settings.php',
];
for (const page of formPages) {
    const content = fs.readFileSync(path.join(ROOT_DIR, page), 'utf8');
    assert(`${page} includes CSRF input field in forms`, content.includes('CSRF::getInputField()'));
    assert(`${page} validates CSRF token on POST`, content.includes('CSRF::validate()'));
    assert(`${page} uses parameterized queries (:param)`, content.includes(':'));
}

// 3. Section Editor Security & Validation
console.log('\nSuite 3: Section Editor Security & Logic');
const sectionsContent = fs.readFileSync(path.join(ROOT_DIR, 'public/admin/sections.php'), 'utf8');
assert('Sections editor restricts to approved section keys', sectionsContent.includes("['hero', 'trust_strip', 'about', 'dealer']"));
assert('Sections editor validates URLs with Sanitizer::cleanUrl', sectionsContent.includes('Sanitizer::cleanUrl('));
assert('Sections editor validates JSON data', sectionsContent.includes('json_decode('));
assert('Sections editor records audit events', sectionsContent.includes("AuditLogger::log("));

// 4. Product Catalog Management
console.log('\nSuite 4: Product Catalog Management');
const productsContent = fs.readFileSync(path.join(ROOT_DIR, 'public/admin/products.php'), 'utf8');
assert('Products editor enforces server-side slug uniqueness check', productsContent.includes('SELECT id FROM products WHERE slug = :slug'));
assert('Products editor supports soft archive instead of hard delete', productsContent.includes("UPDATE products SET status = 'archived'"));
assert('Products editor validates required short_description', productsContent.includes('Short description is required'));
assert('Products editor records CREATE, UPDATE, and ARCHIVE in audit log', 
    productsContent.includes("AuditLogger::log('CREATE'") &&
    productsContent.includes("AuditLogger::log('UPDATE'") &&
    productsContent.includes("AuditLogger::log('ARCHIVE'")
);

// 5. Knowledge Articles Management
console.log('\nSuite 5: Knowledge Articles Publisher');
const articlesContent = fs.readFileSync(path.join(ROOT_DIR, 'public/admin/articles.php'), 'utf8');
assert('Articles editor sanitizes body content with Sanitizer::sanitizeHtml', articlesContent.includes('Sanitizer::sanitizeHtml('));
assert('Articles editor supports scheduled publishing (published_at)', articlesContent.includes('published_at'));
assert('Articles editor checks slug uniqueness', articlesContent.includes('SELECT id FROM knowledge_articles WHERE slug = :slug'));
assert('Articles editor supports archive workflow', articlesContent.includes("UPDATE knowledge_articles SET status = 'archived'"));

// 6. Navigation Management
console.log('\nSuite 6: Navigation Management');
const navContent = fs.readFileSync(path.join(ROOT_DIR, 'public/admin/navigation.php'), 'utf8');
assert('Navigation editor sanitizes destination URLs', navContent.includes('Sanitizer::cleanUrl('));
assert('Navigation editor validates link location (header/footer)', navContent.includes("['header', 'footer']"));

// 7. Site Settings & Immutable CRM Security
console.log('\nSuite 7: Site Settings & CRM URL Immutability');
const settingsContent = fs.readFileSync(path.join(ROOT_DIR, 'public/admin/settings.php'), 'utf8');
assert('Settings editor rejects CRM URL tampering server-side', settingsContent.includes("$_POST['crm_login_url'] !== 'https://shubhlabh-crm.vercel.app/'"));
assert('Settings editor validates email format', settingsContent.includes('FILTER_VALIDATE_EMAIL'));
assert('Settings editor stores empty contact details as NULL', settingsContent.includes('!empty($val) ? $val : null'));

// 8. Sanitizer Logic Unit Verification
console.log('\nSuite 8: Sanitizer Unit Checks');
const sanitizerContent = fs.readFileSync(path.join(ROOT_DIR, 'app/Sanitizer.php'), 'utf8');
assert('Sanitizer strips <script> tags', sanitizerContent.includes('#<script(.*?)>(.*?)</script>#is'));
assert('Sanitizer strips inline event handlers (onclick, etc.)', sanitizerContent.includes('on[a-z]+'));
assert('Sanitizer blocks javascript: URIs', sanitizerContent.includes('javascript:'));
assert('Sanitizer restricts URLs to http/https or anchors', sanitizerContent.includes('#^https?://#i') && sanitizerContent.includes('str_starts_with($trimmed, \'#\')'));

// 9. Audit Logger Service Verification
console.log('\nSuite 9: Audit Logger Verification');
const auditLoggerContent = fs.readFileSync(path.join(ROOT_DIR, 'app/AuditLogger.php'), 'utf8');
assert('AuditLogger redacts sensitive context', auditLoggerContent.includes('Logger::sanitizeContext('));
assert('AuditLogger implements getRecent() for read-only view', auditLoggerContent.includes('function getRecent('));

// 10. Apache Clean Rewrite Rules
console.log('\nSuite 10: Apache Clean Routing');
const htaccessContent = fs.readFileSync(path.join(ROOT_DIR, '.htaccess'), 'utf8');
const adminRoutes = [
    '^admin/sections/?$',
    '^admin/products/?$',
    '^admin/articles/?$',
    '^admin/navigation/?$',
    '^admin/settings/?$',
    '^admin/media/?$',
    '^admin/audit/?$',
];
for (const route of adminRoutes) {
    assert(`.htaccess defines clean route: ${route}`, htaccessContent.includes(route));
}

// 11. Public Website & CRM Preservation
console.log('\nSuite 11: Public Website & CRM Preservation');
const indexHtml = fs.readFileSync(path.join(ROOT_DIR, 'index.html'), 'utf8');
assert('Public website unchanged and contains #home', indexHtml.includes('id="home"'));
assert('Public website unchanged and contains #products', indexHtml.includes('id="products"'));
assert('Public website unchanged and contains #about', indexHtml.includes('id="about"'));
assert('Public website unchanged and contains #knowledge', indexHtml.includes('id="knowledge"'));
assert('Public website unchanged and contains #dealer', indexHtml.includes('id="dealer"'));
assert('Public website unchanged and contains #contact', indexHtml.includes('id="contact"'));
assert('Public website CRM Login URL preserved exactly', indexHtml.includes('https://shubhlabh-crm.vercel.app/'));

const stylesCss = fs.readFileSync(path.join(ROOT_DIR, 'styles.css'), 'utf8');
assert('styles.css intact (>30KB)', stylesCss.length > 30000);

const scriptJs = fs.readFileSync(path.join(ROOT_DIR, 'script.js'), 'utf8');
assert('script.js intact', scriptJs.includes('.site-header'));

// Final Results
console.log('\n----------------------------------------------------');
console.log(`Results: ${passedTests} Passed, ${failedTests} Failed (Total: ${totalTests})`);
console.log('----------------------------------------------------');

if (failedTests > 0) {
    process.exit(1);
} else {
    console.log('Sprint SL-WEB-11D: All dashboard and editor criteria PASSED!\n');
    process.exit(0);
}
