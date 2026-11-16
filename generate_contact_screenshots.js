/**
 * Capture High-Fidelity Screenshots of Desktop & Mobile Contact Page
 * Shubh Labh Cattle Feed - Sprint SL-WEB-CONTACT-INFO-01
 */

const path = require('path');
const fs = require('fs');
const puppeteer = require('../app/node_modules/puppeteer');

const ARTIFACT_DIR = 'C:\\Users\\dell\\.gemini\\antigravity\\brain\\81a47899-3932-4a28-a500-5d18dac96ab8';
const LOCAL_SCREENSHOTS_DIR = path.resolve(__dirname, 'screenshots');

if (!fs.existsSync(LOCAL_SCREENSHOTS_DIR)) {
  fs.mkdirSync(LOCAL_SCREENSHOTS_DIR, { recursive: true });
}

(async () => {
  console.log('📸 Launching Puppeteer browser to capture Contact page screenshots...');
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu']
  });

  const page = await browser.newPage();
  const fileUrl = 'file:///' + path.resolve(__dirname, 'dist', 'index.html').replace(/\\/g, '/');

  // 1. Desktop Screenshot
  console.log('  Capturing Desktop Contact Section (1280x900)...');
  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 2 });
  await page.goto(fileUrl, { waitUntil: 'networkidle0' });

  // Make sure all reveal elements are visible
  await page.evaluate(() => {
    document.querySelectorAll('.reveal').forEach(el => el.classList.add('is-visible'));
    const contactEl = document.getElementById('contact');
    if (contactEl) {
      contactEl.scrollIntoView();
    }
  });

  await new Promise(r => setTimeout(r, 600));

  const contactHandle = await page.$('#contact');
  const desktopArtifactPath = path.join(ARTIFACT_DIR, 'contact_page_desktop.png');
  const desktopLocalPath = path.join(LOCAL_SCREENSHOTS_DIR, 'contact_page_desktop.png');

  if (contactHandle) {
    await contactHandle.screenshot({ path: desktopArtifactPath });
    await contactHandle.screenshot({ path: desktopLocalPath });
  } else {
    await page.screenshot({ path: desktopArtifactPath });
    await page.screenshot({ path: desktopLocalPath });
  }
  console.log(`  ✅ Saved Desktop screenshot to: ${desktopArtifactPath}`);

  // 2. Mobile Screenshot
  console.log('  Capturing Mobile Contact Section (390x844)...');
  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  await page.goto(fileUrl, { waitUntil: 'networkidle0' });

  await page.evaluate(() => {
    document.querySelectorAll('.reveal').forEach(el => el.classList.add('is-visible'));
    const contactEl = document.getElementById('contact');
    if (contactEl) {
      contactEl.scrollIntoView();
    }
  });

  await new Promise(r => setTimeout(r, 600));

  const mobileContactHandle = await page.$('#contact');
  const mobileArtifactPath = path.join(ARTIFACT_DIR, 'contact_page_mobile.png');
  const mobileLocalPath = path.join(LOCAL_SCREENSHOTS_DIR, 'contact_page_mobile.png');

  if (mobileContactHandle) {
    await mobileContactHandle.screenshot({ path: mobileArtifactPath });
    await mobileContactHandle.screenshot({ path: mobileLocalPath });
  } else {
    await page.screenshot({ path: mobileArtifactPath });
    await page.screenshot({ path: mobileLocalPath });
  }
  console.log(`  ✅ Saved Mobile screenshot to: ${mobileArtifactPath}`);

  await browser.close();
  console.log('🎉 Screenshot generation complete!');
})();
