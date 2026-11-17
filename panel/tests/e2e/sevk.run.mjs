// sevk.run.mjs — FULL real-user Sevk loop on GDZ scratch order 3510006329.
// Supplier = picklecanme (bound lifnr 300458 GDZ). Admin = kadir.
// Setup (admin session API): transfer → accept → ready → start (STOP at started).
// Browser: Kalite modal → adres UI → pickle date UI → pickle tile upload →
// Dökümanlar Uygun → pickle ship. Stops at SHIPPED. Cleanup via sev-cleanup.php.
// Run: node tests/e2e/sevk.run.mjs
import { chromium } from 'playwright';

const BASE = 'http://localhost:8000';
const ADMIN = { email: 'kadir@kontent.com.tr', pass: 'Kadir412.' };
const SUP = { email: 'picklecanme@gmail.com', pass: 'Kadir412.' };
const SCRATCH = '82361888-3ef2-4dbe-a9b7-e399f46c4868';
const ONO = '3510006329';

const summary = [];
const errs = [];
const ok = (s) => { summary.push('PASS ' + s); console.log('   PASS ' + s); };
const bad = (s) => { errs.push(s); console.log('   FAIL ' + s); };
const shot = async (page, name) => {
  const f = `/tmp/sevk-${name}.png`;
  await page.screenshot({ path: f }).catch(() => {});
  console.log(`   [shot] ${f}`);
};
const swalTxt = async (page) => page.locator('.swal2-popup').innerText().catch(() => '');
const closeSwal = async (page) => {
  const b = page.locator('.swal2-confirm, .swal2-cancel, .swal2-close').first();
  if (await b.count()) await b.click().catch(() => {});
  await page.waitForTimeout(400);
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
}

async function apiStatus(page, qnid) {
  return page.evaluate(async (q) => {
    const j = await (await fetch('/api/v1/document/' + q)).json();
    let st = j?.data?.document?.status;
    if (typeof st === 'string') { try { st = JSON.parse(st); } catch (e) {} }
    if (Array.isArray(st)) st = st[st.length - 1];
    return (st && st.op_key) || st || '';
  }, qnid);
}

async function main() {
  const browser = await chromium.launch({ headless: false, slowMo: 160 });
  const mkCtx = () => browser.newContext({ viewport: { width: 1440, height: 900 }, userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)' });
  const adminCtx = await mkCtx(); const admin = await adminCtx.newPage();
  const supCtx = await mkCtx(); const sup = await supCtx.newPage();
  for (const p of [admin, sup]) {
    p.on('pageerror', (e) => { const m = 'PAGEERROR ' + String(e.message || e).slice(0, 160); errs.push(m); console.log('   ' + m); });
    p.on('console', (m) => { if (m.type() === 'error' && !/favicon/.test(m.text())) { const loc = m.location(); const t = 'CONSOLE ' + m.text().slice(0, 140) + (loc && loc.url ? ` @${loc.url.slice(-80)}:${loc.lineNumber}` : ''); errs.push(t); console.log('   ' + t); } });
    p.on('requestfailed', (r) => { const e = r.failure()?.errorText || ''; if (/aborted|cancel/i.test(e)) return; const t = `REQFAIL ${r.url().slice(0, 140)} :: ${e}`; errs.push(t); console.log('   ' + t); });
    p.on('response', (r) => { if (r.status() >= 400 && !/\/roles\//.test(r.url())) { const t = `HTTP ${r.status()} ${r.url().slice(0, 140)}`; errs.push(t); console.log('   ' + t); } });
  }

  try {
    // ── S0. setup scratch → started (admin session API) ──
    console.log('\n[S0] setup scratch → started');
    await login(admin, ADMIN);
    const setup = await admin.evaluate(async (qnid) => {
      const out = { files: [], errors: [] };
      const mkPdf = (t) => new File([`%PDF-1.4\n${t}\n`], t.replace(/\W+/g, '_') + '.pdf', { type: 'application/pdf' });
      const up = async (f) => {
        const fd = new FormData(); fd.append('file', f);
        const r = await (await fetch('/api/v1/temp-upload', { method: 'POST', body: fd })).json();
        return r?.data || r;
      };
      const doc = await (await fetch('/api/v1/document/' + qnid)).json();
      if (!doc?.success) { out.errors.push('GET failed'); return out; }
      const form = doc?.data?.formFormat?.['op-doc-order-form'] || {};
      const rowId = Object.keys(form)[0];
      if (!rowId) { out.errors.push('no order row'); return out; }
      const kabul = await up(mkPdf('kabul e2e'));
      const cins = await up(mkPdf('cins e2e'));
      if (!kabul?.reference_id || !cins?.reference_id) { out.errors.push('tempupload failed'); return out; }
      const env = new FormData();
      env.append('data', JSON.stringify({ typeKey: 'op-doc-order', transfer_mode: 'at_once', dynamicF: { [`op-doc-order-form**${rowId}`]: { tag: 'op-doc-order-form', entities: {} } } }));
      env.append(`dynamicFile**transfer_kabul**new-s1**${rowId}*-*transfer_kabul_file**transfer_kabul**${rowId}`, JSON.stringify({ reference_id: kabul.reference_id }));
      env.append(`dynamicFile**transfer_cins**new-s2**${rowId}*-*transfer_cins_file**transfer_cins**${rowId}`, JSON.stringify({ reference_id: cins.reference_id }));
      const put = await (await fetch('/api/v1/document/' + qnid, { method: 'PUT', body: env })).json();
      if (!put?.success) { out.errors.push('PUT failed: ' + JSON.stringify(put).slice(0, 200)); return out; }
      const d2 = await (await fetch('/api/v1/document/' + qnid)).json();
      const ents = Object.values(d2?.data?.formFormat?.['op-doc-order-form'] || {})[0]?.entities || {};
      for (const [k, v] of Object.entries(ents)) {
        if (!/transfer_(kabul|cins)_file/.test(k)) continue;
        try { const o = JSON.parse(v); if (o?.qnid) out.files.push(o.qnid); } catch (e) {}
      }
      for (const fq of out.files) {
        const fd = new FormData(); fd.append('id', fq); fd.append('op_key', 'doc_file_accepted'); fd.append('note', 'e2e setup');
        await fetch('/api/v1/trans/set-file-status', { method: 'POST', body: fd });
      }
      const st = new FormData(); st.append('id', qnid); st.append('note', 'e2e split');
      const start = await (await fetch('/api/v1/orders/sevk/start', { method: 'POST', body: st })).json();
      if (!start?.success) out.errors.push('start failed: ' + JSON.stringify(start).slice(0, 200));
      return out;
    }, SCRATCH);
    console.log('   setup: ' + JSON.stringify({ files: setup.files?.length, errors: setup.errors }));
    if (setup.errors?.length) throw new Error('setup failed: ' + setup.errors.join(' | '));
    const stS0 = await apiStatus(admin, SCRATCH);
    stS0 === 'doc_trans_order_sevk_started' ? ok('scratch ready at sevk_started') : bad('scratch status: ' + stS0);

    // ── A. Sevk tab + supplier sees own order ──
    console.log('\n[A] tabs');
    await admin.goto(`${BASE}/coalpanel/orders?f=sevk`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table table tbody tr', { timeout: 25000 });
    const rowS = admin.locator('#div_table tbody tr', { hasText: ONO }).first();
    if (await rowS.count()) {
      const pill = rowS.locator('td').nth(5).locator('span,button').first();
      const txt = (await pill.innerText().catch(() => '')).trim();
      const bg = await pill.evaluate((e) => getComputedStyle(e).backgroundColor).catch(() => '');
      /139|124|108|59|76|93|245|243|255|237|233|254/.test(bg) ? ok(`sevk pill purple ("${txt}")`) : bad('pill not purple: ' + txt + ' / ' + bg);
    } else bad('scratch row missing in Sevk tab');
    await shot(admin, 'A-tab');
    await login(sup, SUP);
    await sup.goto(`${BASE}/tedarikpanel/orders?f=sevk`, { waitUntil: 'networkidle' });
    await sup.waitForTimeout(2500);
    const supRows = await sup.locator('#div_table tbody tr').count();
    const supBody = await sup.locator('body').innerText().catch(() => '');
    if (supBody.includes(ONO) && supRows >= 1) ok('supplier sees OWN scratch in Sevk tab');
    else bad('supplier Sevk tab missing own order');
    // foreign order must NOT appear
    if (/3510013225-2|2010015154-1/.test(supBody)) bad('FOREIGN sevk order visible to supplier!');
    else ok('no foreign rows for supplier (fail-closed lists)');
    await shot(sup, 'A-sup-tab');

    // ── B. focused detail (admin) ──
    console.log('\n[B] focused detail');
    await admin.goto(`${BASE}/coalpanel/orders/form/${SCRATCH}?f=sevk`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#sevk-card', { timeout: 20000 });
    await admin.waitForTimeout(1200);
    const inView = await admin.evaluate(() => {
      const el = document.getElementById('sevk-card');
      if (!el) return 'missing';
      const r = el.getBoundingClientRect();
      return (r.top >= -10 && r.top < innerHeight) ? 'visible' : `out(top=${Math.round(r.top)})`;
    });
    inView === 'visible' ? ok('auto-scrolled to #sevk-card') : bad('sevk card not in view: ' + inView);
    (await admin.locator('#sevk-card.sevk-ring').count()) ? ok('ring highlight present') : bad('no sevk-ring');
    const stray = await admin.locator('body').evaluate(() => [...document.querySelectorAll('button')].filter((b) => b.textContent.trim() === 'Kaydet' && b.offsetParent).length);
    stray === 0 ? ok('no stray generic Kaydet bar') : bad(stray + ' stray Kaydet button(s)');
    await shot(admin, 'B-focus');

    // ── K. Kalite modal from Aksiyonlar (tedarik list as admin) ──
    console.log('\n[K] Kalite modal (Aksiyonlar)');
    await admin.goto(`${BASE}/tedarikpanel/orders?f=sevk`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table table tbody tr', { timeout: 25000 });
    const aRow = admin.locator('#div_table tbody tr', { hasText: ONO }).first();
    if (!(await aRow.count())) bad('scratch row missing in tedarik list');
    else {
      await aRow.locator('button', { hasText: 'Aksiyonlar' }).click();
      await admin.waitForTimeout(700);
      const kBtn = admin.locator('#aks-kalite-ok');
      if (!(await kBtn.count())) bad('Aksiyonlar Kalite button missing (needs started + 07-02)');
      else {
        await kBtn.click();
        await admin.waitForTimeout(700);
        const hasDepo = await admin.locator('#swal-depo').count();
        const permNote = (await swalTxt(admin)).includes('per-07-02');
        hasDepo && permNote ? ok('Kalite modal asks depo + states perm') : bad('Kalite modal incomplete');
        await shot(admin, 'K-modal');
        await admin.fill('#swal-depo', 'E2E-DEPO-01');
        await admin.locator('.swal2-confirm').click();
        await admin.waitForTimeout(2500);
        await closeSwal(admin);
        const stK = await apiStatus(admin, SCRATCH);
        stK === 'doc_trans_order_sevk_kalite_ok' ? ok('Kalite modal → kalite_ok') : bad('after modal: ' + stK);
      }
    }

    // ── AD. adres via UI (multiline textareas) ──
    console.log('\n[AD] adres UI');
    await admin.goto(`${BASE}/coalpanel/orders/form/${SCRATCH}?f=sevk`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#sevk-card', { timeout: 20000 });
    await admin.waitForTimeout(800);
    const taCount = await admin.locator('#sevk-card textarea.sevk-area').count();
    taCount >= 2 ? ok('adres textareas multiline present') : bad('adres textareas: ' + taCount);
    await admin.locator('#sevk-card textarea.sevk-area').first().fill('E2E Mah. E2E Cad. No:1\nKat:2 Depo Önü');
    await admin.locator('#sevk-card textarea.sevk-area').nth(1).fill('Köşk tipi trafo — manuel giriş');
    // admin branch button reads "Kaydet", tedarik branch "Adresi Kaydet" — scope to the adres step
    const adStep = admin.locator('#sevk-card .sevk-step', { hasText: 'Sevk Adresi' }).first();
    const adBtn = adStep.locator('button', { hasText: 'Kaydet' }).first();
    if (!(await adBtn.count())) bad('adres save button missing in step');
    else {
      await adBtn.click();
      await admin.waitForTimeout(2500);
      await closeSwal(admin);
      const stA = await apiStatus(admin, SCRATCH);
      stA === 'doc_trans_order_sevk_addressed' ? ok('adres UI → addressed') : bad('after adres: ' + stA);
    }
    await shot(admin, 'AD-adres');

    // ── DT. supplier date via UI ──
    console.log('\n[DT] supplier date UI');
    await sup.goto(`${BASE}/tedarikpanel/orders/form/${SCRATCH}?f=sevk`, { waitUntil: 'networkidle' });
    await sup.waitForSelector('#sevk-card', { timeout: 20000 });
    await sup.waitForTimeout(1000);
    const supStart = await sup.locator('#sevk-card button', { hasText: 'Sevk Onayına Al' }).count();
    supStart === 0 ? ok('supplier has no admin start button') : bad('supplier sees start button!');
    const dateIn = sup.locator('#sevk-card input[type="date"]');
    if (!(await dateIn.count())) bad('supplier date input missing');
    else {
      await dateIn.fill('2026-12-05');
      await sup.locator('#sevk-card button', { hasText: 'Tarihi Kaydet' }).click();
      await sup.waitForTimeout(2500);
      await closeSwal(sup);
      const stD = await apiStatus(admin, SCRATCH);
      stD === 'doc_trans_order_sevk_dated' ? ok('supplier date → dated (perm gate works)') : bad('after date: ' + stD);
    }
    await shot(sup, 'DT-date');

    // ── C. supplier tile upload → proforma ──
    console.log('\n[C] supplier tile upload');
    await sup.reload({ waitUntil: 'networkidle' });
    await sup.waitForSelector('#sevk-card', { timeout: 20000 });
    await sup.waitForTimeout(800);
    const tiles = sup.locator('#sevk-card .sevk-upload input[type="file"]');
    (await tiles.count()) >= 2 ? ok('tile inputs present') : bad('tiles: ' + (await tiles.count()));
    await tiles.nth(0).setInputFiles({ name: 'proforma.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\nproforma e2e\n') });
    await tiles.nth(1).setInputFiles({ name: 'irsaliye.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\nirsaliye e2e\n') });
    await sup.waitForTimeout(2500);
    await sup.locator('#sevk-card button', { hasText: 'Yükle+Onaya Sun' }).click();
    await sup.waitForTimeout(3000);
    await closeSwal(sup);
    const stC = await apiStatus(admin, SCRATCH);
    stC === 'doc_trans_order_sevk_proforma' ? ok('supplier tiles → Proforma Yüklendi') : bad('after upload: ' + stC);
    await shot(sup, 'C-tiles');

    // ── D. control via Dökümanlar ──
    console.log('\n[D] control via Dökümanlar');
    const proQ = await admin.evaluate(async (ono) => {
      const fd = new FormData();
      fd.append('tableReq', JSON.stringify({ scale: { page: 1, limit: 500 } }));
      const fl = await (await fetch('/api/v1/table/document_files', { method: 'POST', body: fd })).json();
      const files = fl.data?.data || fl.data || [];
      const hit = files.find((f) => /proforma/i.test(f.file_type || '') && [f.order_no, f.transfer_no, f.group_key, f.relation_qnid].filter(Boolean).join(' ').includes(ono));
      return hit?.id || null;
    }, ONO);
    proQ ? ok('proforma file found in Dökümanlar') : bad('proforma file missing');
    if (proQ) {
      await admin.goto(`${BASE}/coalpanel/documents/${proQ}`, { waitUntil: 'networkidle' });
      await admin.waitForSelector('.tedarik-file-detail', { timeout: 20000 });
      const uyg = admin.locator('.decide-pill', { hasText: 'Uygun' }).first();
      const onayla = admin.locator('.decide-pill', { hasText: 'Onayla' }).first();
      (await uyg.count()) && !(await onayla.count()) ? ok('DForm shows Uygun/Değil') : bad('wrong decide pills');
      await uyg.click();
      await admin.locator('button.save-red').first().click();
      await admin.waitForTimeout(2500);
      const t = await swalTxt(admin); await closeSwal(admin);
      /Uygun|kontrol/i.test(t) || t === '' ? ok('control Uygun accepted') : bad('control: ' + t.slice(0, 120));
      await shot(admin, 'D-control');
    }

    // ── F. supplier ship ──
    console.log('\n[F] supplier ship');
    await sup.goto(`${BASE}/tedarikpanel/orders/form/${SCRATCH}?f=sevk`, { waitUntil: 'networkidle' });
    await sup.waitForSelector('#sevk-card', { timeout: 20000 });
    await sup.waitForTimeout(1000);
    const shipBtn = sup.locator('#sevk-card button', { hasText: 'Sevkiyat Gerçekleşti' });
    if (!(await shipBtn.count())) bad('supplier ship button missing');
    else {
      await shipBtn.click();
      await sup.waitForTimeout(3000);
      await closeSwal(sup);
      const stF = await apiStatus(admin, SCRATCH);
      stF === 'doc_trans_order_sevk_shipped' ? ok('supplier shipped ✔ (stopping before terminal approve)') : bad('after ship: ' + stF);
    }
    await shot(sup, 'F-ship');
  } catch (e) {
    bad('EXCEPTION ' + String((e && e.message) || e).slice(0, 200));
    try { await admin.screenshot({ path: '/tmp/sevk-EX.png' }); } catch (x) {}
  } finally {
    await browser.close().catch(() => {});
  }
  console.log('\n══ SUMMARY ══');
  summary.forEach((s) => console.log('  ' + s));
  if (errs.length) { console.log('── ERRORS ──'); errs.forEach((s) => console.log('  ✗ ' + s)); process.exit(1); }
  console.log('ALL GREEN');
}

main();
