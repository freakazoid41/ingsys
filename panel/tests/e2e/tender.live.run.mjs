// Real-user tender pipeline walkthrough (headed Chromium).
// Run: node tests/e2e/tender.live.run.mjs
import { chromium } from 'playwright';
import path from 'node:path';

const BASE = 'http://localhost:8000';
const PDF = path.resolve('tests/e2e/fixtures/tender-test.pdf');

async function setDeadline(page, value = '30/11/2026') {
  await page.evaluate((v) => {
    const el = document.querySelector('input[name="teklif_son_tarihi"]');
    el.readOnly = false;
    el.value = v;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.readOnly = true;
  }, value);
  await page.waitForTimeout(250);
}

const stamp = Date.now();
const CODE = `E2E-${stamp}`;
const ADMIN = { email: 'kadir@kontent.com.tr', password: 'Kadir412.' };
const SUPPLIER = { email: 'kbbozat41@hotmail.com', password: 'Kadir412.' };
const shot = async (page, name) => {
  const file = `/tmp/tender-e2e-${name}.png`;
  await page.screenshot({ path: file, fullPage: false }).catch(() => {});
  console.log(`  screenshot: ${file}`);
};

async function login(page, email, password, target) {
  await page.goto(`${BASE}/tedarik`, { waitUntil: 'networkidle' });
  await page.waitForSelector('#email', { timeout: 15000 });
  await page.fill('#email', email);
  await page.fill('#password', password);
  await page.evaluate(() => {
    const el = document.querySelector('[name="g-recaptcha-response"]');
    if (el) el.value = 'test-bypass';
  }).catch(() => {});
  await page.click('#submit-button');
  await page.waitForTimeout(1600);

  const codeInputs = page.locator('input[name^="code_"]');
  if (await codeInputs.count()) {
    for (let i = 1; i <= 6; i++) {
      const inp = page.locator(`input[name="code_${i}"]`);
      if (await inp.count()) await inp.fill('1');
    }
    const smsBtn = page.locator('button:has-text("Doğrula"), button:has-text("Onayla"), #sms-submit, button[type="submit"]').first();
    if (await smsBtn.count()) await smsBtn.click();
    else await page.keyboard.press('Enter');
    await page.waitForTimeout(2500);
  }

  // Wait until the session is established / module selection.
  for (let i = 0; i < 20; i++) {
    const u = page.url();
    if (u.includes('/module-select') || u.includes('/coalpanel') || u.includes('/tedarikpanel')) break;
    await page.waitForTimeout(400);
  }
  await page.goto(`${BASE}${target}`, { waitUntil: 'networkidle' });
  if (await page.locator('#email').count()) throw new Error(`login failed for ${email}`);
  console.log(`  logged in: ${email} → ${page.url()}`);
}

async function main() {
  const browser = await chromium.launch({ headless: false, slowMo: 250 });
  const adminCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const supplierCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const admin = await adminCtx.newPage();
  const supplier = await supplierCtx.newPage();

  try {
    console.log(`\n[1] Admin login + tender ${CODE}`);
    await login(admin, ADMIN.email, ADMIN.password, '/coalpanel/ihaleler');
    await admin.goto(`${BASE}/coalpanel/ihaleler/form`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('.area-target[data-tag="op-doc-ihale-form"]', { timeout: 15000 });
    await admin.fill('input[name="ihale_kodu"]', CODE);
    // deadline is required now — flatpickr input is readonly, so type into it
    const dateInput = admin.locator('input[name="teklif_son_tarihi"]');
    await setDeadline(admin);
    const dateCls = await dateInput.getAttribute('class');
    if (!String(dateCls).includes('form-control')) throw new Error('date input lost form-control class: ' + dateCls);
    await admin.fill('input[name^="spec_title"]', 'E2E Teknik Şartnamesi');
    await admin.setInputFiles('input[name^="ihale_spec_file"]', PDF);
    await admin.waitForTimeout(1200);

    await admin.click('input[name^="product_title"]');
    await admin.waitForSelector('#ih_prod_table', { timeout: 10000 });
    await admin.fill('input[placeholder="Kod veya ad ara..."]', 'E2E-TRF-100');
    await admin.click('button:has-text("Ara")');
    await admin.waitForTimeout(900);
    await admin.locator('#ih_prod_table button:has-text("Seç")').first().click();
    await admin.fill('input[name^="quantity"]', '10');
    await shot(admin, '01-tender-form');
    await admin.locator('button:has-text("Formu Kaydet"), button:has-text("Kaydet")').first().click();
    await admin.waitForTimeout(2200);
    const ok = admin.locator('.swal2-confirm');
    if (await ok.count()) await ok.click().catch(() => {});
    await admin.waitForTimeout(1200);
    console.log(`  after save: ${admin.url()}`);

    console.log('[2] Activate tender');
    await admin.goto(`${BASE}/coalpanel/ihaleler`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table', { timeout: 15000 });
    const adminRow = admin.locator('tr', { hasText: CODE }).first();
    await adminRow.waitFor({ timeout: 10000 });
    let apiMs = null;
    const t0holder = { t: 0 };
    const statusListener = (rsp) => {
      if (rsp.url().includes('/api/v1/tenders/activate')) apiMs = Date.now() - t0holder.t;
    };
    admin.on('response', statusListener);
    await adminRow.locator('button[title*="Aktif Et"]').first().click();
    await admin.waitForTimeout(400);
    t0holder.t = Date.now();
    await admin.locator('button:has-text("Evet, aktif et")').click();
    await adminRow.locator('text=İhale Aktif').first().waitFor({ timeout: 15000 });
    const rowMs = Date.now() - t0holder.t;
    admin.off('response', statusListener);
    console.log(`  activation: HTTP ${apiMs ?? '?'}ms, row repaint ${rowMs}ms`);
    if (rowMs > 4000) console.log('  WARNING: activation UI is slow');
    await shot(admin, '02-tender-active');

    console.log('[3] Supplier login + offer');
    await login(supplier, SUPPLIER.email, SUPPLIER.password, '/coalpanel/ihaleler');
    const supRow = supplier.locator('tr', { hasText: CODE }).first();
    await supRow.waitFor({ timeout: 10000 });
    await supRow.locator('button[title="Teklif Ver"]').click();
    // offer screen boots async — wait for the product overview table, not a timer
    await supplier.waitForSelector('#iho_prod_table table tbody tr', { timeout: 30000 });
    await shot(supplier, '03-offer-start');

    const fillBtn = supplier.locator('#iho_prod_table button.iho-pt-btn').first();
    await fillBtn.waitFor({ timeout: 15000 });
    await fillBtn.click();
    await supplier.waitForTimeout(800);

    const inputs = supplier.locator('input[placeholder="doldurunuz"]:visible');
    await inputs.first().waitFor({ timeout: 15000 });
    await supplier.locator('tr:has-text("İMALATÇI FİRMA ADI") input').first().fill('E2E İmalatçı');
    await supplier.locator('tr:has-text("ÜRÜN MARKASI") input').first().fill('E2E Marka');
    await supplier.locator('tr:has-text("ÜRÜN MARKASI") input').first().press('Tab');
    await supplier.waitForTimeout(800);

    for (let round = 0; round < 10; round++) {
      const uploadInput = supplier.locator('.iho-cell-upload input[type="file"]').first();
      if (!(await uploadInput.count())) break;
      await uploadInput.setInputFiles(PDF);
      await supplier.waitForTimeout(1000);
      const uploadConfirm = supplier.locator('.swal2-popup button:has-text("Yükle")');
      if (await uploadConfirm.count()) await uploadConfirm.click().catch(() => {});
      await supplier.waitForTimeout(1600);
    }
    await shot(supplier, '04-offer-matrix');

    await supplier.locator('button:has-text("Teklifi Kaydet")').click();
    await supplier.waitForTimeout(4200);
    const saveOk = supplier.locator('.swal2-confirm');
    if (await saveOk.count()) await saveOk.click().catch(() => {});
    await supplier.waitForTimeout(2200);
    await shot(supplier, '05-offer-sent');

    console.log('[4] Admin review: file accept + reject offer');
    await admin.goto(`${BASE}/coalpanel/ihaleler/teklifler`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table', { timeout: 15000 });
    let offerRow = admin.locator('tr', { hasText: CODE }).first();
    await offerRow.waitFor({ timeout: 10000 });
    await offerRow.locator('button[title="Teklifi Aç"]').click();
    await admin.waitForTimeout(2500);
    const fileTile = admin.locator('button.iho-cell.is-ok').first();
    if (await fileTile.count()) {
      await fileTile.click();
      await admin.waitForTimeout(600);
      const acceptFile = admin.locator('button:has-text("Dosyayı Kabul Et")');
      if (await acceptFile.count()) await acceptFile.click();
      await admin.waitForTimeout(1200);
      const close = admin.locator('button:has-text("Kapat")');
      if (await close.count()) await close.click();
    }
    await shot(admin, '06-offer-detail');

    await admin.goto(`${BASE}/coalpanel/ihaleler/teklifler`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table', { timeout: 15000 });
    offerRow = admin.locator('tr', { hasText: CODE }).first();
    await offerRow.waitFor({ timeout: 10000 });
    await offerRow.locator('button[title*="Teklifi Reddet"]').click();
    await admin.waitForTimeout(500);
    const note = admin.locator('.swal2-textarea');
    await note.fill('E2E revizyon testi: belge kontrolü');
    await admin.locator('button:has-text("Reddet")').click();
    await admin.waitForTimeout(1800);
    await shot(admin, '07-offer-rejected');

    console.log('[5] Supplier revises rejected offer');
    await supplier.goto(`${BASE}/coalpanel/ihaleler`, { waitUntil: 'networkidle' });
    await supplier.waitForSelector('#div_table', { timeout: 15000 });
    const supRow2 = supplier.locator('tr', { hasText: CODE }).first();
    await supRow2.locator('button[title="Teklif Ver"]').click();
    await supplier.waitForTimeout(2500);
    const rejectedBanner = supplier.locator('text=Teklif reddedildi').first();
    await rejectedBanner.waitFor({ timeout: 8000 }).catch(() => {});
    await shot(supplier, '08-offer-rejected-banner');
    const fillBtn2 = supplier.locator('#iho_prod_table button:has-text("Doldur"), #iho_prod_table button:has-text("Aç")').first();
    if (await fillBtn2.count()) {
      await fillBtn2.click();
      await supplier.waitForTimeout(800);
    }
    const save2 = supplier.locator('button:has-text("Teklifi Kaydet")');
    if (await save2.count()) await save2.click();
    await supplier.waitForTimeout(4200);
    const saveOk2 = supplier.locator('.swal2-confirm');
    if (await saveOk2.count()) await saveOk2.click().catch(() => {});
    await supplier.waitForTimeout(2200);
    await shot(supplier, '09-offer-resent');

    console.log('[6] Admin accepts offer → winner + completion');
    await admin.goto(`${BASE}/coalpanel/ihaleler/teklifler`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table', { timeout: 15000 });
    offerRow = admin.locator('tr', { hasText: CODE }).first();
    await offerRow.waitFor({ timeout: 10000 });
    await offerRow.locator('button[title*="Teklifi Kabul Et"]').click();
    await admin.waitForTimeout(500);
    await admin.locator('button:has-text("Kabul Et ve İhaleyi Tamamla")').click();
    await admin.waitForTimeout(2600);
    await shot(admin, '10-offer-accepted');

    await admin.goto(`${BASE}/coalpanel/ihaleler`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table', { timeout: 15000 });
    const doneRow = admin.locator('tr', { hasText: CODE }).first();
    await doneRow.waitFor({ timeout: 10000 });
    await doneRow.locator('text=İhale Tamamlandı').waitFor({ timeout: 8000 }).catch(() => {});
    await shot(admin, '11-tender-completed');

    await supplier.goto(`${BASE}/coalpanel/ihaleler`, { waitUntil: 'networkidle' });
    await supplier.waitForSelector('#div_table', { timeout: 15000 });
    await shot(supplier, '12-supplier-final');

    console.log(`\nTENDER E2E COMPLETE: ${CODE}`);
    console.log('Screenshots: /tmp/tender-e2e-*.png');
  } catch (e) {
    console.error('\nTENDER E2E FAILED:', e.stack || e);
    await shot(admin, '99-admin-failure').catch(() => {});
    await shot(supplier, '99-supplier-failure').catch(() => {});
    process.exitCode = 1;
  } finally {
    await new Promise(r => setTimeout(r, 4000));
    await browser.close();
  }
}

main();
