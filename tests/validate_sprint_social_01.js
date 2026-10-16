/**
 * Verification Suite for Micro-Sprint: SL-WEB-SOCIAL-01
 * Social Media Icons Integration (Header, Footer, Floating WhatsApp, Responsive Layout)
 */

const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');
const INDEX_HTML = fs.readFileSync(path.join(ROOT_DIR, 'index.html'), 'utf8');
const STYLES_CSS = fs.readFileSync(path.join(ROOT_DIR, 'styles.css'), 'utf8');
const SCRIPT_JS = fs.readFileSync(path.join(ROOT_DIR, 'script.js'), 'utf8');
const DIST_HTML = fs.readFileSync(path.join(ROOT_DIR, 'dist', 'index.html'), 'utf8');
const DIST_CSS = fs.readFileSync(path.join(ROOT_DIR, 'dist', 'styles.css'), 'utf8');

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
console.log('  Testing Sprint SL-WEB-SOCIAL-01 Integration       ');
console.log('====================================================\n');

// 1. Official Social Media URLs
console.log('--- 1. Official Social Media URLs Validation ---');
const OFFICIAL_FB = 'https://www.facebook.com/shubhlabhcattlefeed';
const OFFICIAL_YT = 'https://www.youtube.com/@shubhlabhcattlefeed2654';
const OFFICIAL_IG = 'https://www.instagram.com/shubhlabhcattlefeed';
const APPROVED_WA = 'https://wa.me/?text=Hello%20Shubh%20Labh%2C%20I%20would%20like%20to%20know%20more.';

assert('Facebook URL matches exact official address', INDEX_HTML.includes(OFFICIAL_FB));
assert('YouTube URL matches exact official channel with @shubhlabhcattlefeed2654', INDEX_HTML.includes(OFFICIAL_YT));
assert('Instagram URL matches exact official profile', INDEX_HTML.includes(OFFICIAL_IG));
assert('WhatsApp URL uses approved existing click-to-chat configuration without fabricated phone', INDEX_HTML.includes(APPROVED_WA));
assert('No fabricated or random phone number introduced in WhatsApp links', !INDEX_HTML.includes('919876543210') && !INDEX_HTML.includes('919999999999'));

// 2. Header Social Icons Integration
console.log('\n--- 2. Website Header Placement & Accessibility ---');
assert('Header contains compact social group container (.header-social)', INDEX_HTML.includes('class="header-social"'));
assert('Header social has accessible label', INDEX_HTML.includes('aria-label="Official Social Media Links"'));
assert('Header includes Facebook link with exact official URL', INDEX_HTML.includes(`class="header-social-btn" href="${OFFICIAL_FB}"`));
assert('Header includes YouTube link with exact official URL', INDEX_HTML.includes(`class="header-social-btn" href="${OFFICIAL_YT}"`));
assert('Header includes Instagram link with exact official URL', INDEX_HTML.includes(`class="header-social-btn" href="${OFFICIAL_IG}"`));
assert('Header includes WhatsApp link with approved URL', INDEX_HTML.includes(`class="header-social-btn header-social-wa" href="${APPROVED_WA}"`));
assert('Header Facebook icon has accessible label', INDEX_HTML.includes('aria-label="Visit Shubh Labh on Facebook (opens in new tab)"'));
assert('Header YouTube icon has accessible label', INDEX_HTML.includes('aria-label="Visit Shubh Labh on YouTube (opens in new tab)"'));
assert('Header Instagram icon has accessible label', INDEX_HTML.includes('aria-label="Visit Shubh Labh on Instagram (opens in new tab)"'));
assert('Header WhatsApp icon has accessible label', INDEX_HTML.includes('aria-label="Contact Shubh Labh on WhatsApp (opens in new tab)"'));
assert('Header buttons include target="_blank" and rel="noopener noreferrer"', 
  INDEX_HTML.includes(`href="${OFFICIAL_FB}" target="_blank" rel="noopener noreferrer"`) &&
  INDEX_HTML.includes(`href="${OFFICIAL_YT}" target="_blank" rel="noopener noreferrer"`));

// 3. Mobile Navigation Social Integration
console.log('\n--- 3. Mobile Navigation Drawer Integration ---');
assert('Mobile nav contains dedicated .nav-social container', INDEX_HTML.includes('class="nav-social"'));
assert('Mobile nav social contains Facebook link', INDEX_HTML.includes(`class="nav-social-btn" href="${OFFICIAL_FB}"`));
assert('Mobile nav social contains YouTube link', INDEX_HTML.includes(`class="nav-social-btn" href="${OFFICIAL_YT}"`));
assert('Mobile nav social contains Instagram link', INDEX_HTML.includes(`class="nav-social-btn" href="${OFFICIAL_IG}"`));
assert('Mobile nav social contains WhatsApp link', INDEX_HTML.includes(`class="nav-social-btn nav-social-wa" href="${APPROVED_WA}"`));

// 4. Footer Social Integration
console.log('\n--- 4. Footer Social Placement & Alignment ---');
assert('Footer contains .footer-social section', INDEX_HTML.includes('class="footer-social"'));
assert('Footer has Facebook icon with official URL', INDEX_HTML.includes(`class="footer-social-btn" href="${OFFICIAL_FB}"`));
assert('Footer has YouTube icon with official URL (@shubhlabhcattlefeed2654)', INDEX_HTML.includes(`class="footer-social-btn" href="${OFFICIAL_YT}"`));
assert('Footer has Instagram icon with official URL', INDEX_HTML.includes(`class="footer-social-btn" href="${OFFICIAL_IG}"`));
assert('Footer has WhatsApp icon with approved URL', INDEX_HTML.includes(`class="footer-social-btn footer-social-wa" href="${APPROVED_WA}"`));
assert('Footer links have target="_blank" and rel="noopener noreferrer"',
  INDEX_HTML.includes(`class="footer-social-btn" href="${OFFICIAL_FB}" target="_blank" rel="noopener noreferrer"`));

// 5. Floating WhatsApp Button
console.log('\n--- 5. Floating WhatsApp Button ---');
assert('Floating WhatsApp container exists (.wa-float-wrap)', INDEX_HTML.includes('class="wa-float-wrap"'));
assert('Floating WhatsApp button element exists (#wa-float-btn)', INDEX_HTML.includes('id="wa-float-btn"'));
assert('Floating WhatsApp button has accessible label', INDEX_HTML.includes('aria-label="Chat with Shubh Labh on WhatsApp (opens in new tab)"'));
assert('Floating WhatsApp button points to approved WhatsApp link', INDEX_HTML.includes(`id="wa-float-btn" href="${APPROVED_WA}"`));
assert('Floating WhatsApp button opens in new tab with rel="noopener noreferrer"', INDEX_HTML.includes(`id="wa-float-btn" href="${APPROVED_WA}" target="_blank" rel="noopener noreferrer"`));
assert('Floating Facebook Gallery button remains preserved intact (#fb-gallery-btn)', INDEX_HTML.includes('id="fb-gallery-btn"'));

// 6. CSS Styling, Geometry & Accessibility
console.log('\n--- 6. Responsive CSS & Accessibility Tokens ---');
assert('CSS defines header social flex styling', STYLES_CSS.includes('.header-social') && STYLES_CSS.includes('margin-left: auto'));
assert('CSS defines circular header social buttons', STYLES_CSS.includes('.header-social-btn') && STYLES_CSS.includes('border-radius: 50%'));
assert('CSS defines visible keyboard focus (:focus-visible) on header social buttons', STYLES_CSS.includes('.header-social-btn:focus-visible'));
assert('CSS defines visible keyboard focus (:focus-visible) on footer social buttons', STYLES_CSS.includes('.footer-social-btn:focus-visible'));
assert('CSS defines visible keyboard focus (:focus-visible) on floating WhatsApp button', STYLES_CSS.includes('.wa-float-btn:focus-visible'));
assert('CSS defines desktop fixed bottom-right position for wa-float-wrap (bottom: 84px)', STYLES_CSS.includes('.wa-float-wrap') && STYLES_CSS.includes('bottom: 84px') && STYLES_CSS.includes('right: 24px'));
assert('CSS defines mobile stacked layout for floating WhatsApp button (bottom: calc(76px + env(safe-area-inset-bottom, 0px)))', STYLES_CSS.includes('.wa-float-wrap') && STYLES_CSS.includes('calc(76px'));
assert('CSS hides header social in top bar at <= 800px to prevent crowding', STYLES_CSS.includes('@media (max-width: 800px)') && STYLES_CSS.includes('.header-social') && STYLES_CSS.includes('display: none'));
assert('CSS displays nav-social in mobile drawer at <= 800px', STYLES_CSS.includes('@media (max-width: 800px)') && STYLES_CSS.includes('.nav-social') && STYLES_CSS.includes('display: flex'));

// 7. Production Static Build Sync
console.log('\n--- 7. Production Static Distribution Sync ---');
assert('dist/index.html is synced with header social links', DIST_HTML.includes('class="header-social"') && DIST_HTML.includes(OFFICIAL_YT));
assert('dist/index.html is synced with floating WhatsApp button', DIST_HTML.includes('id="wa-float-btn"') && DIST_HTML.includes(APPROVED_WA));
assert('dist/index.html is synced with footer social icons', DIST_HTML.includes('class="footer-social-icons"') && DIST_HTML.includes(OFFICIAL_YT));
assert('dist/styles.css is synced with wa-float-wrap styles', DIST_CSS.includes('.wa-float-wrap') && DIST_CSS.includes('.header-social'));

console.log('\n====================================================');
console.log(`  Validation Results: ${passed} PASSED, ${failed} FAILED (Total: ${total})`);
console.log('====================================================\n');

if (failed > 0) {
  process.exit(1);
}
