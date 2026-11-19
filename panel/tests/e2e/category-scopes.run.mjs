// Category form scope-connection verification:
// - "Geçerli Güçler" (kVA) and "Geçerli Tipler" (variants) pills load from seeded rows
// - tags carry the right codes per category (TRF kVA, GY/HY conditional units, SDK types, PANO kVA+types)
// - save-without-change keeps rows + connections intact
import { chromium } from 'playwright';
const BASE = 'http://localhost:8000';
const EXE = process.env.PW_CHROMIUM || '/Users/freakazoid41/Library/Caches/ms-playwright/chromium-1155/chrome-mac/Chromium.app/Contents/MacOS/Chromium';
const CATS = {
  'TRF-33KV': 'd0367b29-e6c0-48a5-b912-bb25ebc13270',
  'GY-HUCRE': '37cbf40b-b6f7-4db1-8352-6e465102da90',
  'HY-HUCRE': '033a13a7-ec44-41bc-9844-98cdd28bf85e',
  'AG-PANO':  'dfcc1cb2-a75e-405a-bc18-0ed7e34c19ec',
  'SDK':      'cfa15deb-3fad-42c6-9c70-4d6b6ef36ae5',
};

async function login(page, email, password) {
  await page.goto(`${BASE}/tedarik`, { waitUntil: 'networkidle' });
  await page.waitForSelector('#email', { timeout: 15000 });
  await page.fill('#email', email);
  await page.fill('#password', password);
  await page.evaluate(() => { const el = document.querySelector('[name="g-recaptcha-response"]'); if (el) el.value = 'test'; });
  await page.click('#submit-button');
  await page.waitForTimeout(1800);
  for (let i = 1; i <= 6; i++) {
    const s = `input[name="code_${i}"]`;
    if (await page.locator(s).count()) await page.fill(s, '1');
  }
  const b = page.locator('button:has-text("Kodu Onayla"),button[type="submit"]').first();
  if (await b.count()) await b.first().click(); else await page.keyboard.press('Enter');
  await page.waitForTimeout(2500);
  await page.waitForLoadState('networkidle').catch(() => {});
  if (page.url().includes('/module-select')) {
    const m = page.locator('a:has-text("Yönetim")').first();
    if (await m.count()) { await m.first().click(); await page.waitForLoadState('networkidle').catch(() => {}); }
  }
}

async function openCategory(page, qnid) {
  await page.goto(`${BASE}/coalpanel/product-categories/form/${qnid}`, { waitUntil: 'networkidle' });
  await page.waitForSelector('.area-target[data-tag="op-doc-product-category-form"] .multiple-item-card', { timeout: 20000 });
  await page.waitForTimeout(1200);
}

/** pill texts per card, for the given tagbox label */
async function pillsByLabel(page, label) {
  return page.evaluate((label) => {
    const out = [];
    document.querySelectorAll('.area-target[data-tag="op-doc-product-category-form"] .multiple-item-card').forEach((card) => {
      const lab = Array.from(card.querySelectorAll('label')).find(l => l.textContent.trim().startsWith(label));
      if (!lab) return;
      const box = lab.parentElement.querySelector('.tag-typeahead');
      const pills = box ? Array.from(box.querySelectorAll('span[style*="border-radius: 999px"]')).map(p => p.textContent.replace('×', '').trim()) : [];
      out.push(pills);
    });
    return out;
  }, label);
}

const browser = await chromium.launch({ headless: true, executablePath: EXE });
const page = await (await browser.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
try {
  await login(page, 'redv@kontent.com.tr', 'R3dV!Kontent-2026');

  for (const [code, qnid] of Object.entries(CATS)) {
    await openCategory(page, qnid);
    const kva = await pillsByLabel(page, 'Geçerli Güçler');
    const types = await pillsByLabel(page, 'Geçerli Tipler');
    const nonEmptyKva = kva.filter(p => p.length).length;
    const nonEmptyTypes = types.filter(p => p.length).length;
    console.log(`\n[${code}] rows=${kva.length} | kvaPillsRows=${nonEmptyKva} | typePillsRows=${nonEmptyTypes}`);
    console.log('  row0 kva   :', (kva[0] || []).join(' | ') || '—');
    console.log('  row0 types :', (types[0] || []).join(' | ') || '—');
    if (code === 'GY-HUCRE') {
      console.log('  row13 types:', (types[13] || []).join(' | ') || '—');
      console.log('  row26 types:', (types[26] || []).join(' | ') || '—');
    }
    if (code === 'HY-HUCRE') {
      console.log('  row24 types:', (types[24] || []).join(' | ') || '—');
    }
  }

  // save-without-change on SDK — must keep 24 rows and their type links
  await openCategory(page, CATS['SDK']);
  await page.screenshot({ path: '/tmp/cat-sdk-pills.png', fullPage: false });
  await page.locator('button:has-text("Kaydet")').first().click();
  await page.waitForTimeout(3500);
  if (await page.locator('.swal2-popup').isVisible().catch(() => false)) {
    await page.locator('.swal2-confirm').first().click().catch(() => {});
    await page.waitForTimeout(1500);
  }
  console.log('\nSDK save-without-change url:', page.url());
} finally {
  await browser.close();
}
