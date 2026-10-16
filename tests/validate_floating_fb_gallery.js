/**
 * Automated Validation Suite for Floating Social Media & Facebook Gallery
 * Sprint Additional Requirement Validation
 */

const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');
const INDEX_HTML = fs.readFileSync(path.join(ROOT_DIR, 'index.html'), 'utf8');
const STYLES_CSS = fs.readFileSync(path.join(ROOT_DIR, 'styles.css'), 'utf8');
const SCRIPT_JS = fs.readFileSync(path.join(ROOT_DIR, 'script.js'), 'utf8');
const DIST_HTML = fs.readFileSync(path.join(ROOT_DIR, 'dist', 'index.html'), 'utf8');

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
console.log('  Testing Floating Social Media & Facebook Gallery   ');
console.log('====================================================\n');

// 1. Floating Button Validation
console.log('--- 1. Floating Button Validation ---');
assert('Floating button element exists in index.html', INDEX_HTML.includes('id="fb-gallery-btn"'));
assert('Floating button has Facebook Gallery label', INDEX_HTML.includes('Facebook Gallery'));
assert('Floating button has accessible aria-haspopup="dialog"', INDEX_HTML.includes('aria-haspopup="dialog"'));
assert('Floating button has aria-expanded="false" default', INDEX_HTML.includes('aria-expanded="false"'));
assert('Floating button controls modal', INDEX_HTML.includes('aria-controls="fb-gallery-modal"'));
assert('Floating button has Facebook SVG icon', INDEX_HTML.includes('<svg') && INDEX_HTML.includes('fb-float-icon'));
assert('CSS defines fixed bottom-right position for fb-float-wrap', STYLES_CSS.includes('.fb-float-wrap') && STYLES_CSS.includes('position: fixed') && STYLES_CSS.includes('bottom: 24px') && STYLES_CSS.includes('right: 24px'));
assert('CSS defines high z-index for floating button', STYLES_CSS.includes('z-index: 990'));
assert('CSS defines mobile compact button layout (<= 640px)', STYLES_CSS.includes('@media (max-width: 640px)') && STYLES_CSS.includes('.fb-float-btn') && STYLES_CSS.includes('border-radius: 50%'));

// 2. Facebook Gallery Interaction & Modal Validation
console.log('\n--- 2. Facebook Gallery Modal & Interaction ---');
assert('Modal overlay container exists with id fb-gallery-modal', INDEX_HTML.includes('id="fb-gallery-modal"'));
assert('Modal has role="dialog" and aria-modal="true"', INDEX_HTML.includes('role="dialog"') && INDEX_HTML.includes('aria-modal="true"'));
assert('Modal has aria-labelledby="fb-modal-title"', INDEX_HTML.includes('aria-labelledby="fb-modal-title"'));
assert('Modal title is exactly "Shubh Labh Cattle Feed — Facebook Gallery"', INDEX_HTML.includes('Shubh Labh Cattle Feed — Facebook Gallery'));
assert('Official Facebook page URL is present', INDEX_HTML.includes('https://www.facebook.com/shubhlabhcattlefeed'));
assert('External link has target="_blank" and rel="noopener noreferrer"', INDEX_HTML.includes('href="https://www.facebook.com/shubhlabhcattlefeed"') && INDEX_HTML.includes('rel="noopener noreferrer"'));
assert('Action button "View All Photos & Videos on Facebook" is present', INDEX_HTML.includes('View All Photos & Videos on Facebook'));
assert('Meta Page Plugin iframe is configured with official page href', INDEX_HTML.includes('https%3A%2F%2Fwww.facebook.com%2Fshubhlabhcattlefeed'));
assert('Loading state skeleton element exists', INDEX_HTML.includes('id="fb-loading-state"') && INDEX_HTML.includes('fb-spinner'));
assert('Fallback state element exists', INDEX_HTML.includes('id="fb-fallback-state"') && INDEX_HTML.includes('fb-fallback-card'));

// 3. User Experience & Accessibility Validation
console.log('\n--- 3. User Experience & Accessibility ---');
assert('Visible close button with accessible label exists in header', INDEX_HTML.includes('id="fb-modal-close-btn"') && INDEX_HTML.includes('aria-label="Close Facebook Gallery"'));
assert('Secondary close button exists in modal footer', INDEX_HTML.includes('id="fb-modal-close-footer-btn"'));
assert('Clickable backdrop overlay exists', INDEX_HTML.includes('id="fb-modal-backdrop"'));
assert('Escape key close handler is implemented in script.js', SCRIPT_JS.includes('e.key === \'Escape\'') || SCRIPT_JS.includes('e.key === \'Esc\''));
assert('Tab focus trap logic is implemented in script.js', SCRIPT_JS.includes('e.key === \'Tab\'') && SCRIPT_JS.includes('focusable'));
assert('Focus restores to trigger button upon closing', SCRIPT_JS.includes('fbBtn?.focus()'));
assert('Lazy-loading iframe prevents initial layout shift & loading', SCRIPT_JS.includes('data-src') && SCRIPT_JS.includes('loadFacebookIframe'));
assert('Adblock / privacy timeout fallback handler exists (5.5s guard)', SCRIPT_JS.includes('5500') && SCRIPT_JS.includes('showFallback'));
assert('View mode toggle tabs exist between Embed and Direct Info', INDEX_HTML.includes('id="fb-tab-embed"') && INDEX_HTML.includes('id="fb-tab-info"'));

// 4. Zero Fabrication & Security Compliance
console.log('\n--- 4. Zero Fabrication & Security Compliance ---');
assert('No fabricated post cards or dummy like counts in HTML', !INDEX_HTML.includes('1.2k likes') && !INDEX_HTML.includes('45 shares') && !INDEX_HTML.includes('mock-post'));
assert('No frontend API credentials or Facebook access tokens stored', !INDEX_HTML.includes('access_token=') && !SCRIPT_JS.includes('access_token=') && !SCRIPT_JS.includes('app_secret'));
assert('CRM Login URL preserved intact', INDEX_HTML.includes('https://shubhlabh-crm.vercel.app/'));

// 5. Preserved Header & Footer Verified Social Channels
console.log('\n--- 5. Preserved Header & Footer Verified Channels ---');
assert('Footer has official Facebook channel link', INDEX_HTML.includes('https://www.facebook.com/shubhlabhcattlefeed'));
assert('Footer has official YouTube channel link', INDEX_HTML.includes('https://www.youtube.com/@shubhlabhcattlefeed'));
assert('Footer has official Instagram profile link', INDEX_HTML.includes('https://www.instagram.com/shubhlabhcattlefeed'));
assert('Footer has official verified WhatsApp channel link', INDEX_HTML.includes('https://wa.me/?text=Hello%20Shubh%20Labh%2C%20I%20would%20like%20to%20know%20more.'));
assert('WhatsApp quick contact in contact section preserved', INDEX_HTML.includes('contact-option') && INDEX_HTML.includes('Start a WhatsApp conversation'));

// 6. Production Static Build Sync
console.log('\n--- 6. Production Static Distribution Sync ---');
assert('dist/index.html is synced with Facebook gallery button', DIST_HTML.includes('id="fb-gallery-btn"'));
assert('dist/index.html contains exact modal title', DIST_HTML.includes('Shubh Labh Cattle Feed — Facebook Gallery'));
assert('dist/index.html contains official page link', DIST_HTML.includes('https://www.facebook.com/shubhlabhcattlefeed'));

console.log('\n====================================================');
console.log(`  Validation Results: ${passed} PASSED, ${failed} FAILED (Total: ${total})`);
console.log('====================================================\n');

if (failed > 0) {
  process.exit(1);
}
