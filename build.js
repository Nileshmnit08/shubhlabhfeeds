/**
 * Deterministic Static Site Builder for Shubh Labh Public Website
 * 
 * Safely bundles only approved public website assets and APK release artifacts
 * into the deployment output directory (`dist`).
 * Strictly excludes private PHP backend source files, database migrations,
 * configuration files, credentials, and CMS admin scripts from the static web distribution.
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

// Approved static directories to bundle
const APPROVED_DIRECTORIES = [
  'downloads',
  'order-app',
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

// 3. Copy approved directories (order-app, downloads)
for (const dir of APPROVED_DIRECTORIES) {
  const srcDir = path.join(ROOT_DIR, dir);
  const destDir = path.join(DIST_DIR, dir);

  if (fs.existsSync(srcDir)) {
    fs.cpSync(srcDir, destDir, { recursive: true });
    console.log(`  [COPIED DIR] ${dir}/`);
  } else {
    console.warn(`  [WARN] Optional directory not found: ${dir}/`);
  }
}

// 4. Security Sanity Check: Ensure no sensitive files or directories leaked
function checkDir(currentDir) {
  const entries = fs.readdirSync(currentDir, { withFileTypes: true });
  for (const entry of entries) {
    const fullPath = path.join(currentDir, entry.name);
    if (entry.name.endsWith('.php') || entry.name.endsWith('.sql') || entry.name.startsWith('.env')) {
      console.error(`[SECURITY ERROR] Sensitive file leaked into dist: ${fullPath}`);
      process.exit(1);
    }
    if (entry.isDirectory()) {
      checkDir(fullPath);
    }
  }
}
checkDir(DIST_DIR);

// 5. Verification checks
if (!fs.existsSync(path.join(DIST_DIR, 'index.html'))) {
  console.error('[BUILD ERROR] dist/index.html is missing!');
  process.exit(1);
}

if (!fs.existsSync(path.join(DIST_DIR, 'order-app', 'index.html'))) {
  console.error('[BUILD ERROR] dist/order-app/index.html is missing!');
  process.exit(1);
}

const apkPath = path.join(DIST_DIR, 'downloads', 'shubhlabh-order-v1.0.4.apk');
if (!fs.existsSync(apkPath)) {
  console.error('[BUILD ERROR] dist/downloads/shubhlabh-order-v1.0.4.apk is missing!');
  process.exit(1);
}

const apkStats = fs.statSync(apkPath);
console.log(`\n  [VERIFIED APK] ${apkPath}`);
console.log(`  Size: ${(apkStats.size / (1024 * 1024)).toFixed(2)} MB (${apkStats.size} bytes)`);

console.log('\n[SUCCESS] Static build completed successfully into dist/');
