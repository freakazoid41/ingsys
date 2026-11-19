// Scope-value dictionary UI verification — /coalpanel/kva tabs + create/edit.
// Login: admin. IS_TEST=true => 2FA 111111.
import { chromium } from 'playwright';
const BASE = 'http://localhost:8000';

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

async function fetchScopeCount(page) {
  return page.evaluate(async () => {
    const fd = new FormData();
    fd.append('tableReq', JSON.stringify({
      filter: [
        { key: 'form-type', type: '=', value: 'op-doc-scope-value-form' },
        { key: 'type', type: '=', value: 'op-doc-scope-value' },
      ],
      scale: { page: 1, limit: 200 },
    }));
    const r = await fetch('/api/v1/table/documents', {
      method: 'POST', credentials: 'include',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        ...(localStorage.getItem('token') ? { Authorization: 'Bearer ' + localStorage.getItem('token') } : {}),
      },
      body: fd,
    });
    const j = await r.json();
    return (j?.data?.data || j?.data || []).length;
  });
}

const browser = await chromium.launch({
  headless: true,
  executablePath: process.env.PW_CHROMIUM || '/Users/freakazoid41/Library/Caches/ms-playwright/chromium-1155/chrome-mac/Chromium.app/Contents/MacOS/Chromium',
});
const ctx = await browser.newContext({ viewport: { width: 1360, height: 900 } });
const page = await ctx.newPage();
try {
  console.log('=== login ===');
  await login(page, 'redv@kontent.com.tr', 'R3dV!Kontent-2026');

  console.log('=== /coalpanel/kva tabs ===');
  await page.goto(`${BASE}/coalpanel/kva`, { waitUntil: 'networkidle' });
  await page.waitForSelector('.kv-tabs', { timeout: 15000 });
  const tabs = await page.locator('.kv-tab').allTextContents();
  console.log('tabs:', tabs.map(t => t.trim()).join(' | '));

  const kvaRows = await page.locator('#div_table tbody tr').count();
  console.log('kva table rows (first page):', kvaRows);

  await page.locator('.kv-tab:has-text("Kapsam Tipleri")').click();
  await page.waitForSelector('#div_scope_table table.dataTable', { timeout: 15000 });
  await page.waitForTimeout(800);
  const scopeRows = await page.locator('#div_scope_table tbody tr').count();
  const headers = await page.locator('#div_scope_table thead th').allTextContents();
  console.log('scope table headers:', headers.map(h => h.trim()).filter(Boolean).join(' | '));
  console.log('scope table rows (first page):', scopeRows);
  console.log('scope docs via API:', await fetchScopeCount(page));
  await page.screenshot({ path: '/tmp/scope-tab.png', fullPage: false });

  console.log('=== create scope value ===');
  await page.locator('button:has-text("Kapsam Tipi Oluştur")').click();
  await page.waitForSelector('.area-target[data-tag="op-doc-scope-value-form"]', { timeout: 15000 });
  await page.fill('.area-target[data-tag="op-doc-scope-value-form"] input[name="title"]', 'E2E KAPSAM');
  await page.fill('.area-target[data-tag="op-doc-scope-value-form"] input[name="code"]', 'E2E-KAPSAM');
  await page.locator('.area-target[data-tag="op-doc-scope-value-form"] select[name^="scope_group"]').selectOption('Hücre Birimi');
  await page.locator('button:has-text("Kaydet")').first().click();
  await page.waitForTimeout(3000);
  if (await page.locator('.swal2-popup').isVisible().catch(() => false)) {
    await page.locator('.swal2-confirm').first().click().catch(() => {});
    await page.waitForTimeout(1500);
  }
  console.log('after save url:', page.url());
  console.log('scope docs after create:', await fetchScopeCount(page));

  await page.goto(`${BASE}/coalpanel/kva`, { waitUntil: 'networkidle' });
  await page.waitForSelector('.kv-tabs', { timeout: 15000 });
  await page.locator('.kv-tab:has-text("Kapsam Tipleri")').click();
  await page.waitForSelector('#div_scope_table table.dataTable', { timeout: 15000 });
  await page.fill('#mainSearch', 'E2E KAPSAM');
  await page.locator('button:has-text("Ara")').click();
  await page.waitForTimeout(1500);
  const found = await page.locator('#div_scope_table tbody tr').count();
  console.log('search E2E KAPSAM rows:', found);
  await page.screenshot({ path: '/tmp/scope-created.png', fullPage: false });
} finally {
  await browser.close();
}
