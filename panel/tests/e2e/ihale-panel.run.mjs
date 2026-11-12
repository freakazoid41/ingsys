// İhale front panel smoke: real login + panel navigation (headless).
import { chromium } from 'playwright';

const BASE = 'http://localhost:8000';

async function login(page, email, password) {
  await page.goto(`${BASE}/tedarik`, { waitUntil: 'networkidle' });
  await page.waitForSelector('#email', { timeout: 15000 });
  await page.fill('#email', email);
  await page.fill('#password', password);
  await page.waitForTimeout(700);
  await page.evaluate(() => { const el = document.querySelector('[name="g-recaptcha-response"]'); if (el) el.value = 'test-bypass'; }).catch(() => {});
  await page.click('#submit-button');
  await page.waitForTimeout(1400);
  if (await page.locator('input[name^="code_"]').count()) {
    for (let i = 1; i <= 6; i++) { const inp = page.locator(`input[name="code_${i}"]`); if (await inp.count()) await inp.fill('1'); }
    const btn = page.locator('button:has-text("Doğrula"), button:has-text("Onayla"), #sms-submit, button[type="submit"]').first();
    if (await btn.count()) await btn.click(); else await page.keyboard.press('Enter');
    await page.waitForTimeout(2200);
  }
  for (let i = 0; i < 20; i++) { const u = page.url(); if (u.includes('/module-select') || u.includes('/coalpanel') || u.includes('/ihalepanel')) break; await page.waitForTimeout(300); }
}

const browser = await chromium.launch({ headless: true });
const adminCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const admin = await adminCtx.newPage();
await login(admin, 'kadir@kontent.com.tr', 'Kadir412.');
await admin.goto(`${BASE}/ihalepanel`, { waitUntil: 'networkidle' });
await admin.waitForTimeout(1800);
console.log('admin url:', admin.url());
console.log('admin dashboard:', (await admin.locator('body').innerText()).slice(0, 220).replace(/\n/g, ' | '));
console.log('admin menu:', await admin.locator('.tedarik-menu-item').allTextContents());
await admin.screenshot({ path: '/tmp/ihalepanel-admin.png' });

await admin.goto(`${BASE}/ihalepanel/ihaleler`, { waitUntil: 'networkidle' });
await admin.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
console.log('admin tender rows:', await admin.locator('#div_table table tbody tr').count());
await admin.goto(`${BASE}/ihalepanel/ihaleler/form`, { waitUntil: 'networkidle' });
await admin.waitForSelector('input[name="ihale_kodu"]', { timeout: 20000 });
console.log('form loaded:', await admin.locator('input[name="ihale_kodu"]').count());
await admin.screenshot({ path: '/tmp/ihalepanel-form.png' });

await admin.goto(`${BASE}/ihalepanel/ihaleler/teklifler`, { waitUntil: 'networkidle' });
await admin.waitForTimeout(1200);
console.log('offers page:', (await admin.locator('body').innerText()).slice(0, 180).replace(/\n/g, ' | '));

const supCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const sup = await supCtx.newPage();
await login(sup, 'kbbozat41@hotmail.com', 'Kadir412.');
await sup.goto(`${BASE}/ihalepanel`, { waitUntil: 'networkidle' });
await sup.waitForTimeout(1600);
console.log('supplier url:', sup.url());
console.log('supplier menu:', await sup.locator('.tedarik-menu-item').allTextContents());
await sup.screenshot({ path: '/tmp/ihalepanel-supplier.png' });
await sup.goto(`${BASE}/ihalepanel/ihaleler`, { waitUntil: 'networkidle' });
await sup.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
console.log('supplier tender rows:', await sup.locator('#div_table table tbody tr').count());
const offerBtn = sup.locator('#div_table button[title="Teklif Ver"]').first();
if (await offerBtn.count()) {
  await offerBtn.click();
  await sup.waitForTimeout(2200);
  console.log('supplier offer url:', sup.url());
  console.log('offer page loaded:', (await sup.locator('body').innerText()).includes('Ürün Listesi'));
  await sup.goto(`${BASE}/ihalepanel/ihaleler/form`, { waitUntil: 'networkidle' });
  await sup.waitForTimeout(700);
  console.log('supplier form blocked ->', sup.url());
  await sup.goto(`${BASE}/ihalepanel/ihaleler/teklifler`, { waitUntil: 'networkidle' });
  await sup.waitForTimeout(700);
  console.log('supplier offers blocked ->', sup.url());
  await sup.screenshot({ path: '/tmp/ihalepanel-offer.png' });
} else {
  console.log('no active tender offer button');
}

await browser.close();
