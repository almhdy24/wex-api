// Browser pass over the INSTALLED release package (CI only): every key page renders at
// 360x800 and 1280x800 with no console errors, and a screenshot is kept for review.
// Usage: node package-ui.mjs <base-url> <out-dir> <product-slug>
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const [base, out, slug] = process.argv.slice(2);
const pages = [
  ['home', '/'], ['catalog', '/catalog'], ['product', `/products/${slug}`], ['cart', '/cart'],
  ['faq', '/faq'], ['terms', '/pages/terms'], ['returns', '/returns'], ['login', '/login'],
  ['admin-redirect', '/admin'], ['not-found', '/no-such-page'], ['offline', '/offline.html'],
];
const viewports = [['mobile-360', 360, 800], ['desktop-1280', 1280, 800]];
const failures = [];

// The CI certificate is self-signed: Chrome refuses service-worker scripts over it even
// with ignoreHTTPSErrors, so trust it at browser level (a real site has a valid cert).
const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });
for (const [vpName, width, height] of viewports) {
  const dir = path.join(out, vpName);
  fs.mkdirSync(dir, { recursive: true });
  const ctx = await browser.newContext({ viewport: { width, height }, ignoreHTTPSErrors: true, reducedMotion: 'reduce', locale: 'ar-SD' });
  for (const [name, p] of pages) {
    const page = await ctx.newPage();
    const errors = [];
    page.on('console', (m) => {
      // A guest's session bootstrap gets 401 from /auth/refresh by design.
      // "Failed to load resource" duplicates the response listener below, which names the URL.
      if (m.type() === 'error' && !/status of 401|Failed to load resource/.test(m.text())) errors.push(m.text());
    });
    page.on('pageerror', (e) => errors.push(e.message));
    // Name the URL behind every failed resource, so a "Failed to load resource" is actionable.
    page.on('response', (r) => {
      if (r.status() >= 400 && r.status() !== 401 && !r.request().isNavigationRequest()) errors.push(`HTTP ${r.status()} ${r.url().replace(base, '')}`);
    });
    try {
      const res = await page.goto(base + p, { waitUntil: 'networkidle', timeout: 30000 });
      const status = res ? res.status() : 0;
      const wantStatus = name === 'not-found' ? [200, 404] : [200];
      if (!wantStatus.includes(status)) failures.push(`${vpName} ${p}: HTTP ${status}`);
      await page.waitForTimeout(400);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
      if (overflow) failures.push(`${vpName} ${p}: horizontal overflow`);
      const text = await page.evaluate(() => document.body.innerText || '');
      if (/AED|درهم/.test(text)) failures.push(`${vpName} ${p}: AED shown to customers`);
      await page.screenshot({ path: path.join(dir, `${name}.png`), fullPage: true });
    } catch (e) {
      failures.push(`${vpName} ${p}: ${e.message.split('\n')[0]}`);
    }
    if (errors.length) failures.push(`${vpName} ${p}: console: ${errors.join(' | ').slice(0, 600)}`);
    await page.close();
    console.log(`  ${vpName} ${p}`);
  }
  await ctx.close();
}
await browser.close();

if (failures.length) {
  console.error('❌ package UI:\n - ' + failures.join('\n - '));
  process.exit(1);
}
console.log(`✅ package UI: ${pages.length * viewports.length} page views clean`);
