/**
 * Verification Suite for Micro-Sprint: SL-WEB-CONTACT-INFO-01
 * Fix Missing Contact Information on Shubh Labh Website
 */

const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');
const INDEX_HTML = fs.readFileSync(path.join(ROOT_DIR, 'index.html'), 'utf8');
const STYLES_CSS = fs.readFileSync(path.join(ROOT_DIR, 'styles.css'), 'utf8');
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
console.log('  Testing Sprint SL-WEB-CONTACT-INFO-01             ');
console.log('====================================================\n');

// 1. Company Name & Identity
console.log('--- 1. Company Details & Legal Identity ---');
assert('Company name contains Shiv Shanker Pashu Aahar Udyog', INDEX_HTML.includes('Shiv Shanker Pashu Aahar Udyog'));
assert('Company name contains Shubh Labh Cattle Feed', INDEX_HTML.includes('Shubh Labh Cattle Feed'));

// 2. Headings & Farmer-friendly Hindi Intro
console.log('\n--- 2. Headings & Bilingual Introduction ---');
assert('Heading contains exact title: हमसे संपर्क करें | Contact Shubh Labh', INDEX_HTML.includes('हमसे संपर्क करें |') && INDEX_HTML.includes('Contact Shubh Labh'));
assert('Farmer-friendly Hindi introduction is present in Contact section', 
  INDEX_HTML.includes('शुभ लाभ पशु आहार से संपर्क करें') && 
  INDEX_HTML.includes('फैक्ट्री या कार्यालय आएं'));

// 3. Four Contact Cards Design & Presence
console.log('\n--- 3. Four Professional Contact Cards ---');
assert('Card 1: Visit Our Factory card exists', INDEX_HTML.includes('Visit Our Factory'));
assert('Card 1 includes complete Factory address (F-772-B, Road No. 6-D, VKI Area, Jaipur, Rajasthan – 302013)', 
  INDEX_HTML.includes('F-772-B, Road No. 6-D, VKI Area, Jaipur, Rajasthan – 302013'));
assert('Card 1 includes Google Maps directions link for Factory', 
  INDEX_HTML.includes('maps.google.com') && 
  INDEX_HTML.includes('F-772-B') && 
  INDEX_HTML.includes('VKI+Area'));

assert('Card 2: Office Address card exists', INDEX_HTML.includes('Office Address'));
assert('Card 2 includes complete Office address (Shop No. 31, Sansar Chandra Road, Chandpole, Jaipur, Rajasthan, India)', 
  INDEX_HTML.includes('Shop No. 31, Sansar Chandra Road, Chandpole, Jaipur, Rajasthan, India'));
assert('Card 2 includes Google Maps directions link for Office', 
  INDEX_HTML.includes('maps.google.com') && 
  INDEX_HTML.includes('Shop+No+31+Sansar+Chandra+Road+Chandpole'));

assert('Card 3: Call / WhatsApp Us card exists', INDEX_HTML.includes('Call / WhatsApp Us'));
assert('Card 3 includes first business number: 9352276227', INDEX_HTML.includes('9352276227'));
assert('Card 3 includes second business number: 9928192696', INDEX_HTML.includes('9928192696'));
assert('Card 3 includes clickable calling link for 9352276227 (tel:+919352276227)', INDEX_HTML.includes('href="tel:+919352276227"'));
assert('Card 3 includes clickable calling link for 9928192696 (tel:+919928192696)', INDEX_HTML.includes('href="tel:+919928192696"'));
assert('Card 3 includes clickable WhatsApp link for 9352276227 (wa.me/919352276227)', INDEX_HTML.includes('href="https://wa.me/919352276227'));
assert('Card 3 includes clickable WhatsApp link for 9928192696 (wa.me/919928192696)', INDEX_HTML.includes('href="https://wa.me/919928192696'));

assert('Card 4: Email Us card exists', INDEX_HTML.includes('Email Us'));
assert('Card 4 includes official email address: shubhlabhfeedinfo@gmail.com', INDEX_HTML.includes('shubhlabhfeedinfo@gmail.com'));
assert('Card 4 includes clickable mailto link (mailto:shubhlabhfeedinfo@gmail.com)', INDEX_HTML.includes('href="mailto:shubhlabhfeedinfo@gmail.com"'));

// 4. UI & Performance Requirements
console.log('\n--- 4. UI Polish & Performance Safeguards ---');
assert('No slow embedded iframe map used inside Contact section', !INDEX_HTML.includes('<iframe class="contact-map"') && !INDEX_HTML.includes('<iframe id="contact-map"'));
assert('Recognizable icons used (map-pin, phone, whatsapp, email)', 
  INDEX_HTML.includes('location-postal-icon') && 
  INDEX_HTML.includes('location-channel-icon') && 
  INDEX_HTML.includes('comms-pill-icon'));
assert('External directions and WhatsApp links include target="_blank" and rel="noopener noreferrer"', 
  INDEX_HTML.includes('target="_blank" rel="noopener noreferrer"') && 
  INDEX_HTML.includes('rel="noopener noreferrer" class="comms-pill-btn wa-btn"'));

// 5. Preserved Existing Navigation, Contact Options & CRM Login
console.log('\n--- 5. Preservation of Existing Features & Options ---');
assert('Existing Quick Enquiry WhatsApp link preserved intact', INDEX_HTML.includes('Start a WhatsApp conversation'));
assert('Existing CRM portal login link preserved intact', INDEX_HTML.includes('https://shubhlabh-crm.vercel.app/'));
assert('Navigation bar contains #contact link', INDEX_HTML.includes('href="#contact"'));

// 6. Responsive CSS
console.log('\n--- 6. Responsive CSS Verification ---');
assert('CSS defines 2-column or responsive grid for contact-four-grid', STYLES_CSS.includes('.contact-four-grid'));
assert('CSS defines vertical stacking on mobile (max-width: 800px)', STYLES_CSS.includes('@media (max-width: 800px)') && STYLES_CSS.includes('grid-template-columns: 1fr'));
assert('CSS defines call pill buttons (.call-btn)', STYLES_CSS.includes('.call-btn'));
assert('CSS defines WhatsApp pill buttons (.wa-btn)', STYLES_CSS.includes('.wa-btn'));
assert('CSS defines email link and send button', STYLES_CSS.includes('.email-address-link') && STYLES_CSS.includes('.email-send-btn'));

// 7. Static Distribution Sync
console.log('\n--- 7. Static Distribution Sync (dist/) ---');
assert('dist/index.html includes Factory address', DIST_HTML.includes('F-772-B, Road No. 6-D, VKI Area'));
assert('dist/index.html includes Office address', DIST_HTML.includes('Shop No. 31, Sansar Chandra Road, Chandpole'));
assert('dist/index.html includes both business phone numbers', DIST_HTML.includes('9352276227') && DIST_HTML.includes('9928192696'));
assert('dist/index.html includes official email address', DIST_HTML.includes('shubhlabhfeedinfo@gmail.com'));
assert('dist/styles.css includes contact-four-grid styles', DIST_CSS.includes('contact-four-grid'));

console.log('\n====================================================');
console.log(`  Validation Results: ${passed} PASSED, ${failed} FAILED (Total: ${total})`);
console.log('====================================================');

if (failed > 0) {
  process.exit(1);
} else {
  console.log('\n[ALL TESTS PASSED] Sprint SL-WEB-CONTACT-INFO-01 verified successfully!\n');
}
