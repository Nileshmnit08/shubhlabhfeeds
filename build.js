/**
 * Deterministic Static Site Builder for Shubh Labh Public Website
 * 
 * Safely bundles only approved public website assets into the deployment output directory (`dist`).
 * Strictly excludes private PHP backend source files, database migrations, configuration files,
 * credentials, and CMS admin scripts from the static web distribution.
 */

const fs = require('fs');
const path = require('path');

const ROOT_DIR = __dirname;
const DIST_DIR = path.join(ROOT_DIR, 'dist');

// Approved public static files for the brand website
const APPROVED_FILES = [
  'index.html',
  'styles.css',
  'script.js',
];

console.log('====================================================');
console.log('  Shubh Labh Website - Production Static Build      ');
console.log('====================================================\n');

// 1. Prepare clean dist directory
if (fs.existsSync(DIST_DIR)) {
  fs.rmSync(DIST_DIR, { recursive: true, force: true });
}
fs.mkdirSync(DIST_DIR, { recursive: true });

// 2. Copy and verify approved files
for (const file of APPROVED_FILES) {
  const src = path.join(ROOT_DIR, file);
  const dest = path.join(DIST_DIR, file);

  if (!fs.existsSync(src)) {
    console.error(`[BUILD ERROR] Required public file missing: ${file}`);
    process.exit(1);
  }

  const content = fs.readFileSync(src);
  if (content.length === 0) {
    console.error(`[BUILD ERROR] Required public file is empty: ${file}`);
    process.exit(1);
  }

  fs.writeFileSync(dest, content);
  console.log(`  [COPIED] ${file} (${(content.length / 1024).toFixed(2)} KB)`);
}

// 3. Security Sanity Check: Ensure no sensitive files or directories leaked
const distEntries = fs.readdirSync(DIST_DIR);
for (const entry of distEntries) {
  if (entry.endsWith('.php') || entry.endsWith('.sql') || entry.startsWith('.env')) {
    console.error(`[SECURITY ERROR] Sensitive file leaked into dist: ${entry}`);
    process.exit(1);
  }
}

// 4. Verify index.html exists in dist
if (!fs.existsSync(path.join(DIST_DIR, 'index.html'))) {
  console.error('[BUILD ERROR] dist/index.html is missing!');
  process.exit(1);
}

console.log('\n[SUCCESS] Static build completed successfully into dist/');
