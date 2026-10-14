/**
 * Automated Verification for Static Build & Distribution Packaging
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT_DIR = path.resolve(__dirname, '..');
const DIST_DIR = path.join(ROOT_DIR, 'dist');

console.log('====================================================');
console.log('  Testing Static Build & Packaging Verification     ');
console.log('====================================================\n');

// 1. Run build script
try {
  execSync('node build.js', { cwd: ROOT_DIR, stdio: 'pipe' });
  console.log('  [PASS] build.js executed successfully without errors');
} catch (err) {
  console.error('  [FAIL] build.js failed:', err.message);
  process.exit(1);
}

// 2. Validate dist directory exists
if (!fs.existsSync(DIST_DIR)) {
  console.error('  [FAIL] dist directory does not exist');
  process.exit(1);
}
console.log('  [PASS] dist directory exists');

// 3. Validate required public files exist in dist
const requiredFiles = ['index.html', 'styles.css', 'script.js'];
for (const file of requiredFiles) {
  const filePath = path.join(DIST_DIR, file);
  if (!fs.existsSync(filePath)) {
    console.error(`  [FAIL] Missing expected file in dist: ${file}`);
    process.exit(1);
  }
  const stat = fs.statSync(filePath);
  if (stat.size === 0) {
    console.error(`  [FAIL] Empty file in dist: ${file}`);
    process.exit(1);
  }
  console.log(`  [PASS] dist/${file} exists and is non-empty (${stat.size} bytes)`);
}

// 4. Validate no PHP, SQL, private or config files leaked
const forbiddenExtensions = ['.php', '.sql', '.env', '.log', '.htaccess'];
const distFiles = fs.readdirSync(DIST_DIR);
for (const file of distFiles) {
  for (const ext of forbiddenExtensions) {
    if (file.endsWith(ext) || file.startsWith(ext)) {
      console.error(`  [FAIL] Forbidden file type found in dist: ${file}`);
      process.exit(1);
    }
  }
}
console.log('  [PASS] Zero backend, PHP, SQL, or credential files in dist');

// 5. Validate vercel.json configuration
const vercelConfig = JSON.parse(fs.readFileSync(path.join(ROOT_DIR, 'vercel.json'), 'utf8'));
if (vercelConfig.outputDirectory !== 'dist') {
  console.error(`  [FAIL] vercel.json outputDirectory is not 'dist': ${vercelConfig.outputDirectory}`);
  process.exit(1);
}
console.log('  [PASS] vercel.json outputDirectory correctly points to dist');

// 6. Validate package.json scripts
const pkg = JSON.parse(fs.readFileSync(path.join(ROOT_DIR, 'package.json'), 'utf8'));
if (!pkg.scripts || !pkg.scripts.build) {
  console.error('  [FAIL] package.json missing scripts.build');
  process.exit(1);
}
console.log('  [PASS] package.json scripts.build configured');

// 7. Verify index.html integrity inside dist
const distHtml = fs.readFileSync(path.join(DIST_DIR, 'index.html'), 'utf8');
if (!distHtml.includes('https://shubhlabh-crm.vercel.app/')) {
  console.error('  [FAIL] CRM URL missing from dist/index.html');
  process.exit(1);
}
console.log('  [PASS] CRM URL preserved exactly in dist/index.html');

console.log('\n====================================================');
console.log('  Build Test Results: ALL PASSED                    ');
console.log('====================================================\n');
