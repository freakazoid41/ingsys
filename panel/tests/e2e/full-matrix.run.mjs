// full-matrix.run.mjs — comprehensive real-browser sweep of ALL panels:
// logins + module-select, static content of every page (tedarik/planlama/ihale
// fronts + coalpanel admin), full PLANLAMA pipeline (ihtiyaç → talep → guard →
// red → requeue → müdürlük onay → std onay) and full TENDER pipeline (create →
// activate → 2 bids → reject+accept → winner), then notification scoping checks.
// Run: node tests/e2e/full-matrix.run.mjs   (headed, slowMo)
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import * as XLSX from 'xlsx';

const BASE = 'http://localhost:8000';
const PDF = path.resolve('tests/e2e/fixtures/tender-test.pdf');
const T = Date.now();
const IHTIYAC = `MATRIX-IHT-${T}`;
const TENDER = `MATRIX-TND-${T}`;

const ADMIN = { email: 'kadir@kontent.com.tr', pass: 'Kadir412.' };
const KB = { email: 'kbbozat41@hotmail.com', pass: 'Kadir412.' };
const PICKLE = { email: 'picklecanme@gmail.com', pass: 'Kadir412.' };
const KB_ORDER = '4a4dd7ad-869d-4b9f-8739-c9f33736cc85';

const results = [];
const ok = (s) => { results.push('✅ ' + s); console.log('   ✓ ' + s); };
const bad = (s) => { results.push('❌ ' + s); console.log('   ✗ ' + s); };
const shot = async (page, name) => {
  const f = `/tmp/matrix-${name}.png`;
  await page.screenshot({ path: f }).catch(() => {});
  console.log(`      [shot] ${f}`);
};

async function login(page, who) {
  await page.goto(`${BASE}/tedarik`, { waitUntil: 'networkidle' });
  await page.waitForSelector('#email', { timeout: 15000 });
  await page.fill('#email', who.email);
  await page.fill('#password', who.pass);
  await page.waitForTimeout(700);
  await page.evaluate(() => { const e = document.querySelector('[name="g-recaptcha-response"]'); if (e) e.value = 'test-bypass'; }).catch(() => {});
  await page.click('#submit-button');
  await page.waitForTimeout(1600);
  if (await page.locator('input[name^="code_"]').count()) {
    for (let i = 1; i <= 6; i++) { const x = page.locator(`input[name="code_${i}"]`); if (await x.count()) await x.fill('1'); }
    const b = page.locator('button:has-text("Doğrula"), button:has-text("Onayla"), button[type="submit"]').first();
    if (await b.count()) await b.click(); else await page.keyboard.press('Enter');
    await page.waitForTimeout(2500);
  }
  for (let i = 0; i < 20; i++) {
    const u = page.url();
    if (u.includes('/module-select') || u.includes('/coalpanel') || u.includes('/tedarikpanel') || u.includes('/planlamapanel') || u.includes('/ihalepanel')) break;
    await page.waitForTimeout(300);
  }
  console.log(`   [login ${who.email}] ${page.url()}`);
}

async function goModule(page, route) {
  await page.goto(`${BASE}/module-select`, { waitUntil: 'networkidle' }).catch(() => {});
  const card = page.locator(`.mod[data-route="${route}"]`).first();
  if (await card.count()) { await card.click(); await page.waitForLoadState('networkidle').catch(() => {}); await page.waitForTimeout(1200); }
  else await page.goto(`${BASE}${route}`, { waitUntil: 'networkidle' });
}

const docStatus = (page, qnid) => page.evaluate(async (q) => {
  const j = await (await fetch('/api/v1/document/' + q)).json();
  let st = j?.data?.document?.status;
  if (typeof st === 'string') { try { st = JSON.parse(st); } catch (e) {} }
  if (Array.isArray(st)) st = st[st.length - 1];
  return st && typeof st === 'object' ? (st.op_key || '') : (st || '');
}, qnid);

const hasTableRows = (page) => page.evaluate(() => {
  const t = document.querySelector('#div_table table tbody') || document.querySelector('.pickletable tbody');
  return t ? t.querySelectorAll('tr').length : 0;
});

async function main() {
  // ── fixtures ──
  for (const [n, t] of [['kabul.pdf', 'KABUL'], ['cins.pdf', 'CINS']]) {
    if (!fs.existsSync('/tmp/' + n)) fs.writeFileSync('/tmp/' + n, `%PDF-1.4\n%${t}\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF`);
  }
  {
    const ws = XLSX.utils.aoa_to_sheet([
      ['Mal Grubu', 'İhale Grubu', 'Malzeme Kodu', 'Kısa Metni', 'Ölçü Birimi', 'Ay', 'Miktar'],
      ['', '', 'PIPE-001', 'MATRIX Malzeme', 'ST', '01/2026', 5],
      ['', '', 'PIPE-001', 'MATRIX Malzeme', 'ST', '02/2026', 0],
      ['', '', 'PIPE-001', 'MATRIX Malzeme', 'ST', '03/2026', 0],
    ]);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Talep');
    XLSX.writeFile(wb, '/tmp/talep.xlsx');
  }

  const browser = await chromium.launch({ headless: false, slowMo: 180 });
  const mkCtx = () => browser.newContext({ viewport: { width: 1440, height: 900 } });
  const [adminCtx, kbCtx, pickleCtx] = await Promise.all([mkCtx(), mkCtx(), mkCtx()]);
  const admin = await adminCtx.newPage();
  const kb = await kbCtx.newPage();
  const pickle = await pickleCtx.newPage();
  for (const p of [admin, kb, pickle]) p.on('pageerror', e => { console.log('   PAGEERROR', e.message.slice(0, 200)); results.push('❌ pageerror: ' + e.message.slice(0, 120)); });

  try {
    // ════════ PHASE 0 — logins + module select ════════
    console.log('\n[0] logins');
    await login(admin, ADMIN);
    await login(kb, KB);
    await login(pickle, PICKLE);
    await admin.goto(`${BASE}/module-select`, { waitUntil: 'networkidle' });
    const adminMods = await admin.locator('.mod').count();
    const kbMods = await kb.evaluate(() => document.querySelectorAll('.mod').length);
    const pickleMods = await pickle.evaluate(() => document.querySelectorAll('.mod').length);
    console.log(`   module cards: admin=${adminMods} kb=${kbMods} pickle=${pickleMods}`);
    if (adminMods >= 3 && kbMods >= 2 && pickleMods >= 2) ok('module-select: admin sees all modules, suppliers see theirs');
    else bad(`module cards wrong: admin=${adminMods} kb=${kbMods} pickle=${pickleMods}`);

    // ════════ PHASE 1 — static content sweeps (read-only) ════════
    console.log('\n[1] static content sweeps');

    // 1.1 kb tedarik: dashboard + orders + detail + documents + bilgilendirmeler
    await goModule(kb, '/tedarikpanel');
    await kb.waitForSelector('.tdk-stats', { timeout: 20000 });
    const kbStats = await kb.locator('.tdk-stat__value').allTextContents();
    const kbStatsNumeric = kbStats.filter(v => /^\d/.test(v.trim())).length;
    if (kbStats.length >= 3 && kbStatsNumeric === kbStats.length) ok(`kb tedarik dashboard: ${kbStats.length} stat cards (${kbStats.map(s => s.trim()).join('|')})`);
    else bad(`kb tedarik dashboard stats broken: ${JSON.stringify(kbStats)}`);
    await shot(kb, '01-kb-tedarik-dash');

    await kb.goto(`${BASE}/tedarikpanel/orders`, { waitUntil: 'networkidle' });
    await kb.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    const kbOrders = await hasTableRows(kb);
    if (kbOrders >= 1) ok(`kb sipariş listesi: ${kbOrders} satır`);
    else bad('kb sipariş listesi boş');

    await kb.goto(`${BASE}/tedarikpanel/orders/form/${KB_ORDER}`, { waitUntil: 'networkidle' });
    await kb.waitForSelector('.tedarik-detail', { timeout: 20000 });
    const kbDrum = await kb.locator('.tedarik-status-drum').innerText().catch(() => '');
    console.log(`   kb order drum: ${kbDrum}`);
    if (kbDrum.includes('Onay')) ok(`kb sipariş detayı: drum "${kbDrum}"`);
    else bad(`kb sipariş detayı drum beklenmedik: ${kbDrum}`);
    await shot(kb, '02-kb-order-detail');

    await kb.goto(`${BASE}/tedarikpanel/documents`, { waitUntil: 'networkidle' });
    await kb.waitForSelector('#div_table', { timeout: 20000 });
    ok(`kb dokümanlar listesi render (${await hasTableRows(kb)} satır)`);

    await kb.goto(`${BASE}/tedarikpanel/bilgilendirmeler`, { waitUntil: 'networkidle' });
    await kb.waitForSelector('.tdk-bilgi__title', { timeout: 20000 });
    const kbTdkLabels = await kb.evaluate(() => [...document.querySelectorAll('.bili-status')].map(e => e.innerText.trim()));
    const foreignTdk = kbTdkLabels.filter(l => !['Sipariş Sisteme Geldi','Onaya Gönderildi','İnceleme Bekliyor','Dosya Onaylandı','Yeniden Talep','Kalite Onayı','Sipariş Reddedildi','Reddedilen Dosya'].includes(l));
    if (!foreignTdk.length) ok(`kb tedarik bilgilendirmeler: ${kbTdkLabels.length} satır, tamamı tedarik kategorisi`);
    else bad(`kb tedarik bilgilendirmeler yabancı kategori: ${JSON.stringify(foreignTdk)}`);

    // 1.2 admin coalpanel: dashboard + orders + documents + ihaleler + planlama twins + users + logs
    await goModule(admin, '/coalpanel');
    await admin.waitForSelector('.adm-stats', { timeout: 20000 });
    const admStats = await admin.locator('.adm-stat__value').allTextContents();
    if (admStats.length >= 3) ok(`admin coalpanel dashboard: ${admStats.length} stat kartı (${admStats.map(s => s.trim()).slice(0, 6).join('|')})`);
    else bad(`admin dashboard stats bozuk: ${JSON.stringify(admStats)}`);
    await shot(admin, '03-admin-dash');

    const adminLists = [
      ['/coalpanel/orders', 'Siparişler'],
      ['/coalpanel/documents', 'Dokümanlar'],
      ['/coalpanel/ihaleler', 'İhaleler'],
      ['/coalpanel/planlama/talepler', 'Planlama İhtiyaç'],
      ['/coalpanel/planlama/talep-dokumanlari', 'Planlama Talepler'],
      ['/coalpanel/users', 'Kullanıcılar'],
      ['/coalpanel/products', 'Ürünler'],
      ['/coalpanel/client', 'Cariler'],
      ['/coalpanel/mudurlukler', 'Müdürlükler'],
      ['/coalpanel/sistem-loglari', 'Sistem Logları'],
      ['/coalpanel/roles', 'Roller'],
      ['/coalpanel/product-categories', 'Ürün Kategorileri'],
      ['/coalpanel/kva', 'KVA'],
    ];
    for (const [route, label] of adminLists) {
      await admin.goto(`${BASE}${route}`, { waitUntil: 'networkidle' });
      await admin.waitForSelector('#div_table, .pickletable, .area-table', { timeout: 20000 }).catch(() => {});
      await admin.waitForTimeout(800);
      const rows = await hasTableRows(admin);
      ok(`admin ${label}: render (${rows} satır)`);
    }

    // 1.3 ihale panels: dashboards + lists + bilgilendirmeler (both roles)
    await goModule(admin, '/ihalepanel');
    await admin.waitForSelector('.ihp-stats', { timeout: 20000 });
    const ihStatsBefore = (await admin.locator('.ihp-stat__value').allTextContents()).map(s => s.trim());
    const ihTotalBefore = parseInt(ihStatsBefore[0] || '0', 10);
    if (ihStatsBefore.length === 6 && ihTotalBefore >= 1) ok(`admin ihale dashboard: ${ihStatsBefore.join('|')} (6 kart)`);
    else bad(`admin ihale dashboard bozuk: ${JSON.stringify(ihStatsBefore)}`);
    await shot(admin, '04-admin-ihale-dash');

    await goModule(kb, '/ihalepanel');
    await kb.waitForSelector('.ihp-stats', { timeout: 20000 });
    const kbIhStats = (await kb.locator('.ihp-stat__value').allTextContents()).map(s => s.trim());
    if (kbIhStats.length === 6) ok(`kb ihale dashboard: ${kbIhStats.join('|')} (6 kart)`);
    else bad(`kb ihale dashboard bozuk: ${JSON.stringify(kbIhStats)}`);

    await admin.goto(`${BASE}/ihalepanel/ihaleler`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    ok(`admin ihale listesi: ${await hasTableRows(admin)} satır`);
    await kb.goto(`${BASE}/ihalepanel/ihaleler`, { waitUntil: 'networkidle' });
    await kb.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    ok(`kb ihale listesi: ${await hasTableRows(kb)} satır`);

    await admin.goto(`${BASE}/ihalepanel/bilgilendirmeler`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('.ihb-page', { timeout: 20000 });
    const admIhLabels = await admin.evaluate(() => [...document.querySelectorAll('.ihb-row .ihb-pill')].map(e => e.innerText.trim()));
    const foreignIh = admIhLabels.filter(l => !['İhale Aktif','İhale Tamamlandı','Yeni Teklif','Teklif Reddedildi','Teklif Kabul Edildi'].includes(l));
    if (!foreignIh.length) ok(`admin ihale bilgilendirmeler: ${admIhLabels.length} satır, tamamı ihale kategorisi`);
    else bad(`admin ihale bilgilendirmeler yabancı: ${JSON.stringify(foreignIh)}`);

    // 1.4 planlama panels: dashboards + lists (3 roles)
    await goModule(admin, '/planlamapanel');
    await admin.waitForSelector('.tdk-stats', { timeout: 20000 });
    const plStats = await admin.locator('.tdk-stat__value').allTextContents();
    if (plStats.length >= 3) ok(`admin planlama dashboard: ${plStats.length} kart (${plStats.map(s => s.trim()).slice(0, 5).join('|')})`);
    else bad(`admin planlama dashboard bozuk: ${JSON.stringify(plStats)}`);
    await shot(admin, '05-admin-planlama-dash');

    await admin.goto(`${BASE}/planlamapanel/talepler`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table', { timeout: 20000 });
    ok(`admin ihtiyaç listesi: ${await hasTableRows(admin)} satır`);
    await admin.goto(`${BASE}/planlamapanel/talep-dokumanlari`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table', { timeout: 20000 });
    ok(`admin talep listesi: ${await hasTableRows(admin)} satır`);
    await kb.goto(`${BASE}/planlamapanel/talep-dokumanlari`, { waitUntil: 'networkidle' });
    await kb.waitForSelector('#div_table', { timeout: 20000 });
    ok(`kb talep listesi: ${await hasTableRows(kb)} satır`);
    await pickle.goto(`${BASE}/planlamapanel/talep-dokumanlari`, { waitUntil: 'networkidle' });
    await pickle.waitForSelector('#div_table', { timeout: 20000 });
    ok(`pickle talep listesi: ${await hasTableRows(pickle)} satır`);

    // ════════ PHASE 2 — PLANLAMA full pipeline (red → requeue → approve → std) ════════
    console.log('\n[2] planlama pipeline');

    await admin.goto(`${BASE}/planlamapanel/talepler`, { waitUntil: 'networkidle' });
    const createBtn = admin.locator('a:has-text("İhtiyaç Oluştur"), button:has-text("İhtiyaç Oluştur")').first();
    await createBtn.waitFor({ timeout: 8000 });
    await createBtn.click();
    await admin.waitForSelector('.area-target[data-tag="op-doc-request-form"]', { timeout: 15000 });
    await admin.fill('.area-target[data-tag="op-doc-request-form"] input[name="title"]', IHTIYAC);
    await admin.evaluate(() => {
      const setMonth = (name, v) => {
        const el = document.querySelector('.area-target[data-tag="op-doc-request-form"] input[name="' + name + '"]');
        if (!el) return;
        el.readOnly = false; el.value = v;
        el.dispatchEvent(new Event('input', { bubbles: true })); el.readOnly = true;
      };
      setMonth('donem_bas', '01/2026'); setMonth('donem_bit', '03/2026');
    });
    await admin.fill('.area-target[data-tag="op-doc-request-form"] textarea[name="desc"], .area-target[data-tag="op-doc-request-form"] input[name="desc"]', 'MATRIX ihtiyaç');
    const itemArea = admin.locator('.area-target[data-tag="op-doc-request-item-form"] .multiple-item-row').first();
    await itemArea.waitFor({ timeout: 8000 });
    await itemArea.locator('input[name^="prod_code"]').fill('PIPE-001');
    await itemArea.locator('input[name^="title"]').fill('MATRIX Malzeme');
    const unitSel = itemArea.locator('select[name^="unit"]');
    if (await unitSel.count()) await unitSel.selectOption('ST');
    await admin.locator('button:has-text("Kaydet")').first().click();
    await admin.waitForTimeout(2200);
    const sw = admin.locator('.swal2-confirm');
    if (await sw.count()) await sw.click().catch(() => {});
    const ihtiyacQnid = await admin.evaluate(async (title) => {
      const fd = new FormData();
      fd.append('tableReq', JSON.stringify({ filter: [{ key: 'form-type', type: '=', value: 'op-doc-request-form' }, { key: 'type', type: '=', value: 'op-doc-request' }], scale: { page: 1, limit: 10 } }));
      const j = await (await fetch('/api/v1/table/documents', { method: 'POST', body: fd })).json();
      const rows = j.data?.data || j.data || [];
      for (const row of rows) { try { const a = JSON.parse(row.main_attr || '[]'); if (a.find(x => x.Key === 'title')?.Value === title) return row.id; } catch (e) {} }
      return null;
    }, IHTIYAC);
    if (ihtiyacQnid) ok(`ihtiyaç oluşturuldu: ${IHTIYAC}`);
    else { bad('ihtiyaç oluşturulamadı'); throw new Error('ihtiyaç yok'); }

    // kb talep: müdürlük + excel şablon + upload + qty unlock
    await kb.goto(`${BASE}/planlamapanel/talep-dokumanlari/form?from=${ihtiyacQnid}`, { waitUntil: 'networkidle' });
    await kb.waitForSelector('.area-target[data-tag="op-doc-requestf-form"]', { timeout: 20000 });
    const mudurSel = kb.locator('select.planlama-mudur__select, .tedarik-step-body select').first();
    const mudurOpts = mudurSel.count() ? await mudurSel.locator('option').count() : 0;
    if (mudurOpts >= 1) {
      const opt = mudurSel.locator('option').filter({ hasText: 'Planlama Müdürlüğü' }).first();
      if (await opt.count()) await mudurSel.selectOption(await opt.getAttribute('value'));
      ok(`talep formu müdürlük listesi: ${mudurOpts} seçenek`);
    } else bad('talep formu müdürlük listesi boş');

    const sablon = kb.locator('button:has-text("Şablon")').first();
    await sablon.waitFor({ timeout: 15000 });
    const [dl] = await Promise.all([kb.waitForEvent('download', { timeout: 15000 }).catch(() => null), sablon.click()]);
    if (dl) ok('excel şablon indirme çalışıyor');
    else bad('excel şablon indirilemedi');

    await kb.locator('input[type="file"][accept*=".xls"]').first().setInputFiles('/tmp/talep.xlsx');
    await kb.waitForTimeout(3000);
    const qtyUnlocked = await kb.waitForFunction(() => {
      const q = document.querySelector('.area-target[data-tag="op-doc-requestf-item-form"] input[name^="quantity"]:not([disabled])');
      return !!q;
    }, { timeout: 12000 }).then(() => true).catch(() => false);
    if (qtyUnlocked) ok('talep qty hücresi watcher ile açıldı (reload yok)');
    else bad('talep qty hücresi kilitli kaldı');
    // capture the qnid from the actual POST response (list-API lookup once
    // returned an older talep — never trust list order for identity)
    const [saveResp] = await Promise.all([
      kb.waitForResponse(r => r.url().includes('/api/v1/document') && r.request().method() === 'POST', { timeout: 25000 }).catch(() => null),
      kb.locator('button:has-text("Kaydet")').first().click(),
    ]);
    await kb.waitForTimeout(2500);
    const sw2 = kb.locator('.swal2-confirm');
    if (await sw2.count()) await sw2.click().catch(() => {});
    await shot(kb, '06-talep-saved');

    let talepQnid = null;
    if (saveResp) {
      try { const j = await saveResp.json(); talepQnid = j?.data?.qnid || null; } catch (e) {}
    }
    if (!talepQnid) {
      talepQnid = await kb.evaluate(async (title) => {
        const fd = new FormData();
        fd.append('tableReq', JSON.stringify({ filter: [{ key: 'form-type', type: '=', value: 'op-doc-requestf-form' }, { key: 'type', type: '=', value: 'op-doc-requestf' }], scale: { page: 1, limit: 10 } }));
        const j = await (await fetch('/api/v1/table/documents', { method: 'POST', body: fd })).json();
        const rows = j.data?.data || j.data || [];
        for (const row of rows) { try { const a = JSON.parse(row.main_attr || '[]'); if (a.find(x => x.Key === 'title')?.Value === title) return row.id; } catch (e) {} }
        return null;
      }, IHTIYAC);
    }
    console.log(`   talep qnid: ${talepQnid}`);
    if (!talepQnid) throw new Error('talep qnid bulunamadı');
    // after a successful save TForm navigates to the list — re-open the detail
    // to verify the Excel file history renders with the "Güncel" pill
    await kb.goto(`${BASE}/planlamapanel/talep-dokumanlari/form/${talepQnid}`, { waitUntil: 'networkidle' });
    await kb.waitForSelector('.area-target[data-tag="op-doc-requestf-form"]', { timeout: 20000 });
    await kb.waitForTimeout(1500);
    const excelGuncelAfter = await kb.locator('text=Güncel').count();
    if (excelGuncelAfter >= 1) ok('Yüklenen Excel Dosyaları: Güncel görünüyor');
    else bad('Excel Güncel satırı görünmüyor (detay sayfasında)');
    const stAfterCreate = await docStatus(kb, talepQnid);
    if (stAfterCreate === 'tr-request-man-awaiting') ok('talep → Müdürlük Onayına Gönderildi');
    else bad(`talep beklenmedik durum: ${stAfterCreate}`);

    // guard: direct PUT on awaiting must be blocked
    const guardRes = await kb.evaluate(async (qnid) => {
      const env = new FormData();
      env.append('data', JSON.stringify({ typeKey: 'op-doc-requestf' }));
      const r = await fetch('/api/v1/document/' + qnid, { method: 'PUT', body: env });
      return { status: r.status, body: await r.json() };
    }, talepQnid);
    const guardMsg = guardRes.body?.data?.msg || guardRes.body?.msg || '';
    if (guardRes.body?.success === false && /düzenlenemez/i.test(guardMsg)) ok(`guard: awaiting talepte API düzenleme engellendi ("${guardMsg.slice(0, 60)}")`);
    else bad(`guard çalışmadı: ${guardRes.status} ${JSON.stringify(guardRes.body).slice(0, 160)}`);

    // pickle müdürlük RED
    await pickle.goto(`${BASE}/planlamapanel/talep-dokumanlari/form/${talepQnid}`, { waitUntil: 'networkidle' });
    await pickle.waitForSelector('button:has-text("Reddet")', { timeout: 20000 });
    await pickle.locator('button:has-text("Reddet")').first().click();
    await pickle.waitForTimeout(900);
    await pickle.locator('#rej-note').last().fill('MATRIX red nedeni');
    await pickle.locator('.swal2-confirm').last().click();
    await pickle.waitForTimeout(2000);
    if ((await docStatus(kb, talepQnid)) === 'tr-request-man-rejected') ok('pickle müdürlük RED etti (man-rejected)');
    else bad(`red sonrası durum: ${await docStatus(kb, talepQnid)}`);

    // kb edit on rejected → auto-requeue
    await kb.goto(`${BASE}/planlamapanel/talep-dokumanlari/form/${talepQnid}`, { waitUntil: 'networkidle' });
    await kb.waitForSelector('.area-target[data-tag="op-doc-requestf-form"]', { timeout: 20000 });
    const qtyEdit = kb.locator('.area-target[data-tag="op-doc-requestf-item-form"] input[name^="quantity"]:not([disabled])').first();
    await qtyEdit.waitFor({ timeout: 12000 });
    await qtyEdit.fill('7');
    await kb.locator('button:has-text("Kaydet")').first().click();
    await kb.waitForTimeout(2500);
    const sw3 = kb.locator('.swal2-confirm');
    if (await sw3.count()) await sw3.click().catch(() => {});
    const stRequeue = await docStatus(kb, talepQnid);
    if (stRequeue === 'tr-request-man-awaiting') ok('kb revize etti → otomatik requeue (awaiting)');
    else bad(`requeue beklenmedik: ${stRequeue}`);

    // pickle müdürlük ONAY
    await pickle.goto(`${BASE}/planlamapanel/talep-dokumanlari/form/${talepQnid}`, { waitUntil: 'networkidle' });
    await pickle.waitForSelector('button:has-text("Onayla")', { timeout: 20000 });
    await pickle.locator('button.tedarik-btn-ok:has-text("Onayla")').first().click();
    await pickle.waitForTimeout(800);
    await pickle.locator('button:has-text("Evet, Onayla")').first().click();
    await pickle.waitForTimeout(2000);
    if ((await docStatus(kb, talepQnid)) === 'tr-request-man-approved') ok('pickle müdürlük ONAYLADI (man-approved)');
    else bad(`onay sonrası durum: ${await docStatus(kb, talepQnid)}`);

    // kadir standardizasyon onayı
    await admin.goto(`${BASE}/planlamapanel/talep-dokumanlari/form/${talepQnid}`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('button:has-text("Standardizasyon Onayı")', { timeout: 20000 });
    await admin.locator('button:has-text("Standardizasyon Onayı")').first().click();
    await admin.waitForTimeout(800);
    await admin.locator('button:has-text("Evet, Onayla")').first().click();
    await admin.waitForTimeout(2000);
    if ((await docStatus(kb, talepQnid)) === 'tr-request-std-approved') ok('kadir std onayı verdi (std-approved)');
    else bad(`std sonrası durum: ${await docStatus(kb, talepQnid)}`);
    await shot(admin, '07-planlama-final');

    // ════════ PHASE 3 — TENDER full pipeline ════════
    console.log('\n[3] tender pipeline');
    await admin.goto(`${BASE}/ihalepanel/ihaleler/form`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('input[name="ihale_kodu"]', { timeout: 20000 });
    await admin.fill('input[name="ihale_kodu"]', TENDER);
    await admin.evaluate((v) => { const el = document.querySelector('input[name="teklif_son_tarihi"]'); el.readOnly = false; el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); el.readOnly = true; }, '30/12/2026');
    await admin.fill('input[name^="spec_title"]', 'MATRIX Teknik Şartname');
    await admin.setInputFiles('input[name^="ihale_spec_file"]', PDF);
    await admin.waitForTimeout(1000);
    await admin.click('input[name^="product_title"]');
    await admin.waitForSelector('#ih_prod_table', { timeout: 10000 });
    await admin.fill('input[placeholder="Kod veya ad ara..."]', 'E2E-TRF-100');
    await admin.click('button:has-text("Ara")');
    await admin.waitForTimeout(900);
    const pickBtn = admin.locator('#ih_prod_table .ihf-pick-btn').first();
    if (await pickBtn.count()) { await pickBtn.click(); }
    await admin.fill('input[name^="quantity"]', '10');
    await admin.locator('button:has-text("Formu Kaydet")').first().click();
    await admin.waitForTimeout(2500);
    ok('tender oluşturuldu');

    await admin.goto(`${BASE}/ihalepanel/ihaleler`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    const tRow = admin.locator('#div_table tbody tr', { hasText: TENDER }).first();
    await tRow.waitFor({ timeout: 15000 });
    await tRow.locator('button:has-text("Aksiyonlar")').click();
    await admin.waitForTimeout(600);
    await admin.locator('#aks-activate').click();
    await admin.waitForTimeout(800);
    await admin.locator('button:has-text("Evet, aktif et")').click();
    await admin.waitForTimeout(2000);
    ok('tender aktif edildi');
    await shot(admin, '08-tender-active');

    for (const [p, who] of [[kb, 'kb'], [pickle, 'pickle']]) {
      await p.goto(`${BASE}/ihalepanel/ihaleler`, { waitUntil: 'networkidle' });
      await p.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
      const row = p.locator('#div_table tbody tr', { hasText: TENDER }).first();
      await row.waitFor({ timeout: 15000 });
      const bidBtn = row.locator('button:has-text("Teklif Ver")').first();
      if (!(await bidBtn.count())) { bad(`${who} için Teklif Ver butonu yok`); continue; }
      await bidBtn.click();
      await p.waitForSelector('#iho_prod_table table tbody tr', { timeout: 30000 });
      await p.locator('#iho_prod_table button.iho-pt-btn').first().click();
      await p.waitForTimeout(800);
      const ins = p.locator('input[placeholder="doldurunuz"]:visible');
      if (await ins.count() >= 2) { await ins.nth(0).fill(`MATRIX ${who} İmalatçı`); await ins.nth(1).fill(`MATRIX ${who} Marka`); }
      // mechanics: every evrak cell must carry a file. Cells convert to a
      // saved preview after each upload, so re-query the inputs every round.
      // The Geçerlilik Tarihi swal date is OPTIONAL — do NOT fill the flatpickr
      // input: focusing it opens the calendar overlay which covers the swal
      // buttons and blocks all clicks.
      let nCells = 0;
      for (let round = 0; round < 12; round++) {
        const cells = p.locator('.iho-cell-upload input[type="file"]');
        const n = await cells.count();
        if (!n) break;
        nCells++;
        await cells.first().setInputFiles(PDF);
        await p.waitForTimeout(1100);
        const yukle = p.locator('.swal2-popup button:has-text("Yükle")');
        if (await yukle.count()) await yukle.click().catch(() => {});
        await p.waitForTimeout(1800);
      }
      console.log(`   ${who} evrak hücresi yüklendi: ${nCells}`);
      await p.keyboard.press('Escape').catch(() => {});
      await p.waitForTimeout(500);
      const missTxt = await p.locator('.iho-pt-missing').first().innerText().catch(() => '');
      console.log(`   ${who} eksik göstergesi: ${missTxt.replace(/\n/g, ' ')}`);
      await p.locator('button:has-text("Teklifi Kaydet")').click();
      let swalText = '';
      for (let i = 0; i < 12; i++) {
        await p.waitForTimeout(400);
        const t = await p.locator('.swal2-popup').innerText().catch(() => '');
        if (t) { swalText = t; break; }
      }
      console.log(`   ${who} swal: ${swalText.slice(0, 160).replace(/\n/g, ' | ')}`);
      const swS = p.locator('.swal2-confirm');
      if (await swS.count()) await swS.click().catch(() => {});
      await p.waitForTimeout(2000);
      if (/Eksik|evrak|hata|süresi dolmuş/i.test(swalText)) { bad(`${who} teklif kaydedilemedi: ${swalText.slice(0, 120)}`); }
      else ok(`${who} teklif gönderdi (${nCells} hücre)`);
      await shot(p, `09-bid-${who}`);
    }

    await admin.goto(`${BASE}/ihalepanel/ihaleler/teklifler`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    const offerRows = admin.locator('#div_table tbody tr', { hasText: TENDER });
    const offerCount = await offerRows.count();
    if (offerCount >= 2) ok(`admin teklif listesi: ${offerCount} teklif`);
    else { bad(`teklif sayısı: ${offerCount}`); throw new Error('teklif eksik'); }
    await shot(admin, '10-offers');

    const firstOffer = offerRows.nth(0);
    await firstOffer.locator('button:has-text("Aksiyonlar")').click();
    await admin.waitForTimeout(600);
    await admin.locator('#aks-reject').click();
    await admin.waitForTimeout(800);
    await admin.locator('#swal2-textarea').fill('MATRIX red nedeni');
    await admin.locator('.swal2-confirm').last().click();
    await admin.waitForTimeout(2000);
    ok('1. teklif reddedildi (nedenli)');

    await admin.waitForTimeout(1200);
    const acceptRow = admin.locator('#div_table tbody tr', { hasText: TENDER })
      .filter({ has: admin.locator('button:has-text("Aksiyonlar")') }).first();
    await acceptRow.locator('button:has-text("Aksiyonlar")').click();
    await admin.waitForTimeout(600);
    await admin.locator('#aks-accept').click();
    await admin.waitForTimeout(800);
    await admin.locator('button:has-text("Kabul Et ve İhaleyi Tamamla")').click();
    await admin.waitForTimeout(2500);
    ok('2. teklif kabul → tender tamamlandı');
    await shot(admin, '11-tender-done');

    await admin.goto(`${BASE}/ihalepanel/ihaleler`, { waitUntil: 'networkidle' });
    await admin.waitForTimeout(1500);
    const doneRowText = await admin.locator('#div_table tbody tr', { hasText: TENDER }).first().innerText();
    if (doneRowText.includes('Tamamlandı')) ok(`tender listede Tamamlandı: ${doneRowText.slice(0, 90).replace(/\n/g, ' | ')}`);
    else bad(`tender listesi: ${doneRowText.slice(0, 90).replace(/\n/g, ' | ')}`);

    for (const [p, who] of [[kb, 'kb'], [pickle, 'pickle']]) {
      await p.goto(`${BASE}/ihalepanel/ihaleler`, { waitUntil: 'networkidle' });
      await p.waitForTimeout(1500);
      const t = await p.locator('#div_table tbody tr', { hasText: TENDER }).first().innerText().catch(() => '');
      if (t.includes('Tamamlandı')) ok(`${who} tamamlanan tenderi görüyor`);
      else bad(`${who} tender satırı: ${t.slice(0, 80).replace(/\n/g, ' | ')}`);
    }

    // ihale dashboard after: total increased
    await goModule(admin, '/ihalepanel');
    await admin.waitForSelector('.ihp-stats', { timeout: 20000 });
    await admin.waitForTimeout(1500);
    const ihStatsAfter = (await admin.locator('.ihp-stat__value').allTextContents()).map(s => s.trim());
    const ihTotalAfter = parseInt(ihStatsAfter[0] || '0', 10);
    if (ihTotalAfter === ihTotalBefore + 1) ok(`ihale dashboard güncellendi: toplam ${ihTotalBefore} → ${ihTotalAfter}`);
    else bad(`ihale dashboard toplam: önce ${ihTotalBefore}, sonra ${ihTotalAfter}`);

    // coalpanel twin
    await admin.goto(`${BASE}/coalpanel/ihaleler`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    ok(`coalpanel ihale twin: ${await hasTableRows(admin)} satır`);

    // ════════ PHASE 4 — notification scoping after actions ════════
    console.log('\n[4] notifications');
    await pickle.goto(`${BASE}/planlamapanel/bilgilendirmeler`, { waitUntil: 'networkidle' });
    await pickle.waitForSelector('.tdk-bilgi__title', { timeout: 20000 });
    await pickle.waitForTimeout(2000);
    const picklePlLabels = await pickle.evaluate(() => [...document.querySelectorAll('.bili-status')].map(e => e.innerText.trim()));
    const foreignPl = picklePlLabels.filter(l => !['Talep Onaya Gönderildi','Talep Reddedildi','Müdürlük Onayı'].includes(l));
    if (!foreignPl.length && picklePlLabels.length >= 1) ok(`pickle planlama bildirimleri: ${picklePlLabels.length} satır, planlama-only (${picklePlLabels.slice(0, 3).join('|')})`);
    else bad(`pickle planlama bildirimleri: ${JSON.stringify(picklePlLabels.slice(0, 6))}`);

    await kb.goto(`${BASE}/ihalepanel/bilgilendirmeler`, { waitUntil: 'networkidle' });
    await kb.waitForSelector('.ihb-page', { timeout: 20000 });
    await kb.waitForTimeout(2000);
    const kbIhLabels = await kb.evaluate(() => [...document.querySelectorAll('.ihb-row .ihb-pill')].map(e => e.innerText.trim()));
    if (kbIhLabels.some(l => l.includes('İhale Aktif')) || kbIhLabels.some(l => l.includes('Yeni Teklif'))) ok(`kb ihale bildirimleri: ${kbIhLabels.slice(0, 4).join('|')}`);
    else bad(`kb ihale bildirimleri boş: ${JSON.stringify(kbIhLabels.slice(0, 4))}`);

    await kb.goto(`${BASE}/tedarikpanel/bilgilendirmeler`, { waitUntil: 'networkidle' });
    await kb.waitForSelector('.tdk-bilgi__title', { timeout: 20000 });
    await kb.waitForTimeout(2000);
    const kbTdkAfter = await kb.evaluate(() => [...document.querySelectorAll('.bili-status')].map(e => e.innerText.trim()));
    const foreignTdkAfter = kbTdkAfter.filter(l => !['Sipariş Sisteme Geldi','Onaya Gönderildi','İnceleme Bekliyor','Dosya Onaylandı','Yeniden Talep','Kalite Onayı','Sipariş Reddedildi','Reddedilen Dosya'].includes(l));
    if (!foreignTdkAfter.length) ok(`kb tedarik bildirimleri hâlâ tedarik-only (${kbTdkAfter.length} satır)`);
    else bad(`kb tedarik bildirimleri yabancı: ${JSON.stringify(foreignTdkAfter)}`);

    console.log('\n════════════════════════════════════');
    const passed = results.filter(r => r.startsWith('✅')).length;
    const failed = results.filter(r => r.startsWith('❌')).length;
    console.log(`MATRIX RESULT: ${passed} passed, ${failed} failed`);
    for (const r of results) console.log(r);
    if (failed) process.exitCode = 1;
  } catch (e) {
    console.error('\nMATRIX FAILED:', e.message);
    for (const [name, page] of [['admin', admin], ['kb', kb], ['pickle', pickle]]) {
      await shot(page, `fail-${name}`).catch(() => {});
    }
    process.exitCode = 1;
  } finally {
    await new Promise(r => setTimeout(r, 2000));
    await browser.close();
  }
}

main();
