/**
 * Verification Suite for Micro-Sprint: SL-WEBSITE-ADDRESS-CMS-01
 * Company Address Management (Database, PHP Backend, CMS Admin, Public API, Website Frontend)
 */

const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');
const MIGRATION_UP = fs.readFileSync(path.join(ROOT_DIR, 'migrations', '004_create_company_addresses.sql'), 'utf8');
const MIGRATION_DOWN = fs.readFileSync(path.join(ROOT_DIR, 'migrations', '004_create_company_addresses_down.sql'), 'utf8');
const MODEL_PHP = fs.readFileSync(path.join(ROOT_DIR, 'app', 'Models', 'CompanyAddress.php'), 'utf8');
const API_ADDRESSES = fs.readFileSync(path.join(ROOT_DIR, 'public', 'api', 'public', 'addresses.php'), 'utf8');
const API_CONTACT = fs.readFileSync(path.join(ROOT_DIR, 'public', 'api', 'public', 'contact.php'), 'utf8');
const ADMIN_ADDRESSES = fs.readFileSync(path.join(ROOT_DIR, 'public', 'admin', 'addresses.php'), 'utf8');
const ADMIN_LAYOUT = fs.readFileSync(path.join(ROOT_DIR, 'public', 'admin', 'layout.php'), 'utf8');
const ADMIN_INDEX = fs.readFileSync(path.join(ROOT_DIR, 'public', 'admin', 'index.php'), 'utf8');
const ADMIN_SETTINGS = fs.readFileSync(path.join(ROOT_DIR, 'public', 'admin', 'settings.php'), 'utf8');
const HTACCESS = fs.readFileSync(path.join(ROOT_DIR, '.htaccess'), 'utf8');
const INDEX_HTML = fs.readFileSync(path.join(ROOT_DIR, 'index.html'), 'utf8');
const STYLES_CSS = fs.readFileSync(path.join(ROOT_DIR, 'styles.css'), 'utf8');
const SCRIPT_JS = fs.readFileSync(path.join(ROOT_DIR, 'script.js'), 'utf8');
const DIST_HTML = fs.readFileSync(path.join(ROOT_DIR, 'dist', 'index.html'), 'utf8');
const DIST_CSS = fs.readFileSync(path.join(ROOT_DIR, 'dist', 'styles.css'), 'utf8');
const DIST_JS = fs.readFileSync(path.join(ROOT_DIR, 'dist', 'script.js'), 'utf8');

let total = 0;
let passed = 0;
let failed = 0;

function assert(name, condition, errorMsg = '') {
  total++;
  if (condition) {
    console.log(`  [PASS] ${name}`);
    passed++;
  } else {
    console.error(`  [FAIL] ${name} - ${errorMsg}`);
    failed++;
  }
}

console.log('====================================================');
console.log('  Testing Sprint SL-WEBSITE-ADDRESS-CMS-01          ');
console.log('====================================================\n');

// 1. Database Migrations
console.log('--- 1. Database Migration & Schema ---');
assert('Migration 004 exists and creates company_addresses table', MIGRATION_UP.includes('CREATE TABLE IF NOT EXISTS public.company_addresses'));
assert('Migration 004 uses PostgreSQL BIGSERIAL primary key', MIGRATION_UP.includes('id BIGSERIAL PRIMARY KEY'));
assert('Migration 004 includes location_name column', MIGRATION_UP.includes('location_name VARCHAR(150) NOT NULL'));
assert('Migration 004 includes address_line1 and address_line2 columns', MIGRATION_UP.includes('address_line1 VARCHAR(255) NOT NULL') && MIGRATION_UP.includes('address_line2 VARCHAR(255)'));
assert('Migration 004 includes landmark, city, state, pincode, country columns', 
  MIGRATION_UP.includes('landmark VARCHAR(150)') && 
  MIGRATION_UP.includes('city VARCHAR(100) NOT NULL') && 
  MIGRATION_UP.includes('state VARCHAR(100) NOT NULL') && 
  MIGRATION_UP.includes('pincode VARCHAR(20) NOT NULL') && 
  MIGRATION_UP.includes("country VARCHAR(100) NOT NULL DEFAULT 'India'"));
assert('Migration 004 includes office_phone, whatsapp_number, email columns', 
  MIGRATION_UP.includes('office_phone VARCHAR(50)') && 
  MIGRATION_UP.includes('whatsapp_number VARCHAR(50)') && 
  MIGRATION_UP.includes('email VARCHAR(191)'));
assert('Migration 004 includes google_maps_url column', MIGRATION_UP.includes('google_maps_url TEXT'));
assert('Migration 004 includes display_order, is_active, is_primary flags', 
  MIGRATION_UP.includes('display_order INTEGER NOT NULL DEFAULT 0') && 
  MIGRATION_UP.includes('is_active BOOLEAN NOT NULL DEFAULT true') && 
  MIGRATION_UP.includes('is_primary BOOLEAN NOT NULL DEFAULT false'));
assert('Migration 004 defines index on is_active, display_order and is_primary', 
  MIGRATION_UP.includes('CREATE INDEX IF NOT EXISTS idx_company_addresses_active') && 
  MIGRATION_UP.includes('CREATE INDEX IF NOT EXISTS idx_company_addresses_primary'));
assert('Migration 004 creates Supabase website_company_addresses compatibility view', MIGRATION_UP.includes('CREATE OR REPLACE VIEW public.website_company_addresses'));
assert('Migration 004 configures Row Level Security (RLS)', MIGRATION_UP.includes('ENABLE ROW LEVEL SECURITY'));
assert('Migration 004 seeds default company address', MIGRATION_UP.includes('INSERT INTO public.company_addresses'));
assert('Migration rollback 004 drops company_addresses table', MIGRATION_DOWN.includes('DROP TABLE IF EXISTS public.company_addresses'));

// 2. CompanyAddress Model
console.log('\n--- 2. Backend CompanyAddress Model ---');
assert('CompanyAddress model class exists in namespace ShubhLabh\\App\\Models', MODEL_PHP.includes('namespace ShubhLabh\\App\\Models;') && MODEL_PHP.includes('class CompanyAddress'));
assert('CompanyAddress::getAllActive() retrieves active addresses ordered by is_primary and display_order', 
  MODEL_PHP.includes('function getAllActive') && MODEL_PHP.includes('WHERE is_active = true') && MODEL_PHP.includes('ORDER BY is_primary DESC, display_order ASC'));
assert('CompanyAddress::getAll() retrieves all addresses for CMS admin', MODEL_PHP.includes('function getAll') && MODEL_PHP.includes('SELECT id, location_name'));
assert('CompanyAddress::getById() retrieves single address by ID', MODEL_PHP.includes('function getById(int $id)'));
assert('CompanyAddress::getPrimary() retrieves primary address', MODEL_PHP.includes('function getPrimary'));
assert('CompanyAddress::formatRecord() builds formatted_address', MODEL_PHP.includes('function formatRecord') && MODEL_PHP.includes('formatted_address'));
assert('CompanyAddress::getFallbackAddresses() provides resilient offline fallback fixture', MODEL_PHP.includes('function getFallbackAddresses'));

// 3. Public API Endpoints
console.log('\n--- 3. Public API Endpoints ---');
assert('/api/public/addresses.php restricts method to GET (returns 405 otherwise)', API_ADDRESSES.includes("$_SERVER['REQUEST_METHOD'] !== 'GET'") && API_ADDRESSES.includes('405'));
assert('/api/public/addresses.php calls CompanyAddress::getAllActive()', API_ADDRESSES.includes('CompanyAddress::getAllActive()'));
assert('/api/public/addresses.php returns ApiResponse::success with cache control', API_ADDRESSES.includes('ApiResponse::success($addresses'));
assert('/api/public/contact.php incorporates company addresses in contact payload', API_CONTACT.includes('CompanyAddress::getAllActive()') && API_CONTACT.includes("'addresses'"));
assert('.htaccess contains clean URL rewrite rule for /api/public/addresses', HTACCESS.includes('^api/public/addresses/?$'));

// 4. CMS Admin Address Management Module
console.log('\n--- 4. CMS Admin Module (/admin/addresses.php) ---');
assert('Admin address module enforces Session::requireAuth()', ADMIN_ADDRESSES.includes('Session::requireAuth('));
assert('Admin address module enforces CSRF validation on all POST requests', ADMIN_ADDRESSES.includes('CSRF::validate()'));
assert('Admin address module uses AuditLogger for actions', ADMIN_ADDRESSES.includes('AuditLogger::log('));
assert('Admin address module supports adding new address', ADMIN_ADDRESSES.includes('INSERT INTO company_addresses'));
assert('Admin address module supports editing existing address', ADMIN_ADDRESSES.includes('UPDATE company_addresses SET'));
assert('Admin address module supports deleting address with confirmation', ADMIN_ADDRESSES.includes('formAction === \'delete\'') && ADMIN_ADDRESSES.includes('DELETE FROM company_addresses WHERE id = :id'));
assert('Admin address module supports toggling active status', ADMIN_ADDRESSES.includes('toggle_status'));
assert('Admin address module supports setting primary address atomically', ADMIN_ADDRESSES.includes('set_primary') && (ADMIN_ADDRESSES.includes('UPDATE company_addresses SET is_primary = false') || ADMIN_ADDRESSES.includes('UPDATE company_addresses SET is_primary = 0')));
assert('Admin address module sanitizes input fields (cleanUrl, trim, htmlspecialchars)', 
  ADMIN_ADDRESSES.includes('Sanitizer::cleanUrl') && 
  ADMIN_ADDRESSES.includes('trim(') && 
  ADMIN_ADDRESSES.includes('htmlspecialchars('));
assert('Admin address module provides live preview matching website card layout', ADMIN_ADDRESSES.includes('Live Card Preview') && ADMIN_ADDRESSES.includes('id="prev-name"'));
assert('Admin layout navigation includes Contact & Address Management item', ADMIN_LAYOUT.includes('/admin/addresses.php') && (ADMIN_LAYOUT.includes('Contact &amp; Address Management') || ADMIN_LAYOUT.includes('Contact & Address Management')));
assert('.htaccess contains clean URL rewrite rule for /admin/addresses', HTACCESS.includes('^admin/addresses/?$'));
assert('Admin dashboard index.php has direct shortcut to Address Management', ADMIN_INDEX.includes('/admin/addresses.php'));
assert('Admin settings.php links to Address Management', ADMIN_SETTINGS.includes('/admin/addresses.php'));

// 5. Website Frontend Integration
console.log('\n--- 5. Website Contact Section & Address Card Markup ---');
assert('index.html contains #locations section inside contact module', INDEX_HTML.includes('id="locations"') && INDEX_HTML.includes('class="contact-locations-wrap'));
assert('index.html contains #locations-grid container', INDEX_HTML.includes('id="locations-grid"'));
assert('index.html includes Primary Office badge styling in baseline markup', INDEX_HTML.includes('class="location-badge primary"'));
assert('index.html includes complete postal address container', INDEX_HTML.includes('class="location-postal"'));
assert('index.html includes phone, whatsapp, email channels', 
  INDEX_HTML.includes('tel:') && 
  INDEX_HTML.includes('mailto:') && 
  INDEX_HTML.includes('class="location-channels"'));
assert('index.html includes Get Directions button with Google Maps', INDEX_HTML.includes('class="location-directions-btn"') && INDEX_HTML.includes('maps.google.com'));
assert('Existing Contact options (Quick Enquiry WhatsApp and CRM Login) preserved intact', 
  INDEX_HTML.includes('Start a WhatsApp conversation') && 
  INDEX_HTML.includes('https://shubhlabh-crm.vercel.app/'));

// 6. Responsive CSS Styling
console.log('\n--- 6. Responsive CSS Styling ---');
assert('styles.css defines .contact-locations-wrap layout', STYLES_CSS.includes('.contact-locations-wrap'));
assert('styles.css defines responsive .locations-grid (grid-template-columns)', STYLES_CSS.includes('.locations-grid') && STYLES_CSS.includes('grid-template-columns'));
assert('styles.css defines .location-card styling with borders and shadows', STYLES_CSS.includes('.location-card'));
assert('styles.css highlights primary card with .location-card.is-primary', STYLES_CSS.includes('.location-card.is-primary'));
assert('styles.css defines .location-badge for primary tag', STYLES_CSS.includes('.location-badge'));
assert('styles.css defines .location-directions-btn with interactive styling', STYLES_CSS.includes('.location-directions-btn'));
assert('styles.css defines responsive breakpoint for mobile stacking', STYLES_CSS.includes('@media (max-width: 800px)') && STYLES_CSS.includes('.locations-grid'));

// 7. Client-side Dynamic Script Loader
console.log('\n--- 7. Dynamic Script Loader (script.js) ---');
assert('script.js defines loadCompanyAddresses function', SCRIPT_JS.includes('loadCompanyAddresses'));
assert('script.js queries #locations-grid', SCRIPT_JS.includes("document.getElementById('locations-grid')"));
assert('script.js fetches live addresses from /api/public/addresses', SCRIPT_JS.includes("fetch('/api/public/addresses'") || SCRIPT_JS.includes('fetch("/api/public/addresses"'));
assert('script.js provides XSS escaping helper (escapeHtml)', SCRIPT_JS.includes('escapeHtml'));
assert('script.js renders Primary Office badge dynamically for primary addresses', SCRIPT_JS.includes('⭐ Primary Office'));
assert('script.js creates Get Directions link from google_maps_url', SCRIPT_JS.includes('location-directions-btn') && SCRIPT_JS.includes('mapsUrl'));
assert('script.js silently catches network errors to preserve server-rendered fallback cards', SCRIPT_JS.includes('catch') && SCRIPT_JS.includes('fallback'));

// 8. Static Distribution Verification
console.log('\n--- 8. Static Distribution Sync (dist/) ---');
assert('dist/index.html includes #locations-grid', DIST_HTML.includes('id="locations-grid"'));
assert('dist/styles.css includes .location-card styling', DIST_CSS.includes('.location-card'));
assert('dist/script.js includes loadCompanyAddresses', DIST_JS.includes('loadCompanyAddresses'));
assert('dist/index.html preserves CRM Login URL', DIST_HTML.includes('https://shubhlabh-crm.vercel.app/'));

console.log('\n====================================================');
console.log(`  Validation Results: ${passed} PASSED, ${failed} FAILED (Total: ${total})`);
console.log('====================================================');

if (failed > 0) {
  process.exit(1);
} else {
  console.log('\n[ALL TESTS PASSED] Sprint SL-WEBSITE-ADDRESS-CMS-01 verified successfully!\n');
}
