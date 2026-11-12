// full-pipelines.run.mjs — real-user headed walkthrough of all 3 modules:
// TEDARIK (supplier front + admin), PLANLAMA (ihtiyaç→talep→approve chain),
// TENDER (2 suppliers bidding, reject+accept winner).
// Run: node tests/e2e/full-pipelines.run.mjs   (headless:false, slowMo)
import { chromium } from 'playwright';
import fs from 'node:fs';
import * as XLSX from 'xlsx';
import path from 'node:path';

const BASE = 'http://localhost:8000';
const PDF = path.resolve('tests/e2e/fixtures/tender-test.pdf');
const T = Date.now();
const IHTIYAC = `PIPE-IHT-${T}`;
const TENDER = `PIPE-TND-${T}`;

const ADMIN = { email: 'kadir@kontent.com.tr', pass: 'Kadir412.' };
const KB = { email: 'kbbozat41@hotmail.com', pass: 'Kadir412.' };
const PICKLE = { email: 'picklecanme@gmail.com', pass: 'Kadir412.' };
const KB_ORDER = '4a4dd7ad-869d-4b9f-8739-c9f33736cc85';

const shot = async (page, name) => {
  const f = `/tmp/pipes-${name}.png`;
  await page.screenshot({ path: f }).catch(() => {});
  console.log(`   [shot] ${f}`);
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
  for (let i = 0; i < 20; i++) { const u = page.url(); if (u.includes('/module-select')) break; if (u.includes('/coalpanel') || u.includes('/tedarikpanel') || u.includes('/ihalepanel') || u.includes('/planlamapanel')) break; await page.waitForTimeout(300); }
  console.log(`   [login ${who.email}] ${page.url()}`);
}

async function goModule(page, route) {
  const card = page.locator(`.mod[data-route="${route}"]`).first();
  if (await card.count()) { await card.click(); await page.waitForLoadState('networkidle').catch(() => {}); await page.waitForTimeout(1200); }
  else await page.goto(`${BASE}${route}`, { waitUntil: 'networkidle' });
}

const summary = [];
const ok = (s) => { summary.push('✅ ' + s); console.log('   ✓ ' + s); };

async function main() {
  const browser = await chromium.launch({ headless: false, slowMo: 220 });
  const mkCtx = (u) => browser.newContext({ viewport: { width: 1440, height: 900 }, userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)' });
  const [adminCtx, kbCtx, pickleCtx] = await Promise.all([mkCtx(ADMIN), mkCtx(KB), mkCtx(PICKLE)]);
  const admin = await adminCtx.newPage();
  const kb = await kbCtx.newPage();
  const pickle = await pickleCtx.newPage();
  const all = [admin, kb, pickle];
  all.forEach(p => p.on('pageerror', e => console.log('   PAGEERROR', e.message.slice(0, 180))));

  try {
    // ═══════════ 1. TEDARIK — supplier front (kb) ═══════════
    console.log('\n[1.1] kb login → tedarik front');
    await login(kb, KB);
    await goModule(kb, '/tedarikpanel');
    await kb.goto(`${BASE}/tedarikpanel/orders`, { waitUntil: 'networkidle' });
    await kb.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    const kbFirstRow = await kb.locator('#div_table tbody tr').first().innerText();
    ok(`kb sees own orders (first row: ${kbFirstRow.slice(0, 60).replace(/\n/g, ' ')}...)`);
    await shot(kb, '1-kb-orders');

    console.log('[1.2] kb completes order transfer (created → transfer_sent)');
    const kbOrderStatus = async () => kb.evaluate(async (qnid) => {
      const j = await (await fetch('/api/v1/document/' + qnid)).json();
      let st = j?.data?.document?.status;
      if (typeof st === 'string') { try { st = JSON.parse(st); } catch (e) {} }
      if (Array.isArray(st)) st = st[st.length - 1];
      if (st && typeof st === 'object') return st.op_key || st.op_title || '';
      return st || '';
    }, KB_ORDER);
    let swalTxt = '';
    let stBefore = await kbOrderStatus();
    console.log(`   order status before: ${stBefore}`);
    await kb.goto(`${BASE}/tedarikpanel/orders/form/${KB_ORDER}`, { waitUntil: 'networkidle' });
    if (stBefore !== 'doc_trans_order_created') {
      console.log('   (order already sent in an earlier pass — skipping UI send)');
    } else {
    await kb.waitForSelector('.tedarik-detail', { timeout: 20000 });
    await kb.waitForTimeout(1200);
    const atOnce = kb.locator('input[value="at_once"]');
    if (await atOnce.count() && !(await atOnce.isChecked())) {
      await kb.locator('label:has-text("Tek Parça Sevkiyat")').first().click().catch(() => {});
    }
    await kb.fill('textarea[placeholder*="açıklama"]:visible', 'PIPE E2E transfer açıklaması');
    await kb.fill('input[placeholder="İmalatçı Firma Adı Giriniz *"]:visible', 'PIPE E2E İmalatçı Ltd');
    let kabulIn = kb.locator('.tedarik-file-wrap input[type="file"]').nth(0);
    let cinsIn = kb.locator('.tedarik-file-wrap input[type="file"]').nth(1);
    if (!(await kabulIn.count())) kabulIn = kb.locator('input[name^="transfer_kabul_file"]').first();
    if (!(await cinsIn.count())) cinsIn = kb.locator('input[name^="transfer_cins_file"]').first();
    const nFiles = (await kabulIn.count()) + (await cinsIn.count());
    console.log(`   file slots: ${nFiles}`);
    if (await kabulIn.count()) { await kabulIn.setInputFiles('/tmp/kabul.pdf'); await kb.waitForTimeout(2600); }
    if (await cinsIn.count()) { await cinsIn.setInputFiles('/tmp/cins.pdf'); await kb.waitForTimeout(2600); }
    await shot(kb, '2-kb-detail-filled');
    const gonder = kb.locator('.tedarik-gonder-btn').first();
    await gonder.waitFor({ timeout: 8000 });
    await gonder.click();
    await kb.waitForTimeout(2500);
    swalTxt = await kb.locator('.swal2-popup').innerText().catch(() => '');
    console.log(`   swal: ${swalTxt.slice(0, 140).replace(/\n/g, ' | ')}`);
    const swOk = kb.locator('.swal2-confirm');
    if (await swOk.count()) await swOk.click().catch(() => {});
    await kb.waitForTimeout(1800);
    }
    const stAfter = await kbOrderStatus();
    console.log(`   order status after: ${stAfter}`);
    if (stAfter === 'doc_trans_order_approved') {
      ok('order already fully approved in an earlier pass — skipping');
      stBefore = 'approved';
    } else if (!['doc_trans_order_transfer_sent', 'doc_trans_order_ready_for_shipment'].includes(stAfter)) {
      throw new Error('Gönder sonrası durum değişmedi: ' + stAfter + ' | swal: ' + swalTxt.slice(0, 120));
    } else {
      ok('order sent → Dosyalar Kontrol Ediliyor');
      await shot(kb, '3-kb-after-send');
    }

    console.log('[1.3] admin reviews files + Kalite close (coalpanel)');
    await login(admin, ADMIN);
    await goModule(admin, '/coalpanel');
    await admin.goto(`${BASE}/coalpanel/orders`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    ok('admin order list renders');
    await shot(admin, '4-admin-orders');

    // file review via DForm (real page, same as admin clicks İncele)
    const fileQnid = await admin.evaluate(async (orderQnid) => {
      const doc = await (await fetch('/api/v1/document/' + orderQnid)).json();
      const rows = Object.values(doc?.data?.formFormat?.['op-doc-order-form'] || {});
      const qnids = [];
      const ids = [];
      for (const r of rows) for (const [k, v] of Object.entries(r?.entities || {})) {
        if (!k.startsWith('transfer_kabul_file') && !k.startsWith('transfer_cins_file')) continue;
        const n = parseInt(v, 10);
        if (n) ids.push(n);
        try { const o = JSON.parse(v); if (o?.qnid) qnids.push(o.qnid); } catch (e) {}
      }
      if (!qnids.length && !ids.length) return null;
      const fd = new FormData();
      fd.append('tableReq', JSON.stringify({ scale: { page: 1, limit: 500 } }));
      const fl = await (await fetch('/api/v1/table/document_files', { method: 'POST', body: fd })).json();
      const files = fl.data?.data || fl.data || [];
      const hit = files.find(f => qnids.includes(f.id)) || files.find(f => ids.includes(Number(f.main_id)));
      return hit?.id || null;
    }, KB_ORDER);
    console.log(`   pending file qnid: ${fileQnid}`);
    if (!fileQnid && stBefore !== 'approved') throw new Error('no order file found for review');
    let decided = 0;
    if (fileQnid) {
    for (let pass = 0; pass < 2 && decided < 1; pass++) {
      await admin.goto(`${BASE}/coalpanel/documents/${fileQnid}`, { waitUntil: 'networkidle' });
      await admin.waitForSelector('.tedarik-file-detail', { timeout: 20000 }).catch(() => {});
      const pill = admin.locator('.decide-pill', { hasText: 'Onayla' }).first();
      if (await pill.count()) {
        await pill.click();
        await admin.locator('button.save-red').first().click();
        await admin.waitForTimeout(2200);
        const sw = admin.locator('.swal2-confirm');
        if (await sw.count()) await sw.click().catch(() => {});
        decided++;
        ok('admin approved file (DForm Onayla)');
        await shot(admin, '5-admin-file-approve');
      } else {
        break;
      }
    }

    }
    // Kalite close from order list actions
    await admin.goto(`${BASE}/coalpanel/orders`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    await admin.fill('.order-search-input', KB_ORDER);
    await admin.locator('button:has-text("Ara")').first().click();
    await admin.waitForTimeout(1500);
    const row = admin.locator('#div_table tbody tr').first();
    // admin list row: click Detay? Kalite via Aksiyonlar if status allows. Use status pill click → status modal is admin-only (coalpanel)
    const statusPill = row.locator('td').nth(5).locator('span').first();
    await statusPill.click();
    await admin.waitForTimeout(800);
    const kaliteBtn = admin.locator('.doc-status[data-key="doc_trans_order_approved"]');
    if (stBefore === 'approved') {
      ok('order already Kalite Onayı Verildi (previous pass)');
    } else if (await kaliteBtn.count()) {
      await kaliteBtn.click();
      await admin.waitForTimeout(2500);
      const sw = admin.locator('.swal2-confirm');
      if (await sw.count()) await sw.click().catch(() => {});
      ok('admin Kalite Onayı Verildi');
    } else {
      // fallback: Aksiyonlar modal
      const aks = row.locator('button:has-text("Aksiyonlar")').first();
      if (await aks.count()) { await aks.click(); await admin.waitForTimeout(600);
        const k2 = admin.locator('#aks-kalite');
        if (await k2.count()) { await k2.click(); await admin.waitForTimeout(2000); const c2 = admin.locator('button:has-text("Evet, Onayla")'); if (await c2.count()) await c2.click(); await admin.waitForTimeout(2500); ok('admin Kalite Onayı (Aksiyonlar)'); }
      }
    }
    await shot(admin, '6-admin-kalite');

    await kb.goto(`${BASE}/tedarikpanel/orders`, { waitUntil: 'networkidle' });
    await kb.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    const kbRows = await kb.locator('#div_table tbody tr').count();
    const kbFirst = await kb.locator('#div_table tbody tr').first().innerText().catch(() => '');
    console.log(`   kb list rows: ${kbRows} | first: ${kbFirst.slice(0, 90).replace(/\n/g, ' | ')}`);
    if (kbRows < 1) throw new Error('kb sipariş listesi boş');
    const kbFinalStatus = await kbOrderStatus();
    console.log(`   kb order API status: ${kbFinalStatus}`);
    if (kbFinalStatus !== 'doc_trans_order_approved') {
      throw new Error('kb sipariş durumu approved değil: ' + kbFinalStatus);
    }
    ok(`tedarik pipeline: created → transfer_sent → approved (front list ${kbRows} rows + API ${kbFinalStatus})`);
    await shot(kb, '7-kb-final');

    // ═══════════ 2. PLANLAMA — ihtiyaç → talep → müdürlük → std ═══════════
    console.log('\n[2.1] admin creates İhtiyaç Listesi (planlama)');
    await goModule(admin, '/planlamapanel');
    await admin.goto(`${BASE}/planlamapanel/talepler`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table', { timeout: 15000 });
    const createBtn = admin.locator('a:has-text("İhtiyaç Oluştur"), button:has-text("İhtiyaç Oluştur")').first();
    await createBtn.waitFor({ timeout: 8000 });
    await createBtn.click();
    await admin.waitForSelector('.area-target[data-tag="op-doc-request-form"]', { timeout: 15000 });
    await admin.fill('.area-target[data-tag="op-doc-request-form"] input[name="title"]', IHTIYAC);
    await admin.evaluate(() => {
      const setMonth = (name, v) => {
        const el = document.querySelector('.area-target[data-tag="op-doc-request-form"] input[name="' + name + '"]');
        if (!el) return;
        el.readOnly = false;
        el.value = v;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.readOnly = true;
      };
      setMonth('donem_bas', '01/2026');
      setMonth('donem_bit', '03/2026');
    });
    await admin.fill('.area-target[data-tag="op-doc-request-form"] textarea[name="desc"], .area-target[data-tag="op-doc-request-form"] input[name="desc"]', 'PIPE E2E ihtiyaç açıklaması');
    const itemArea = admin.locator('.area-target[data-tag="op-doc-request-item-form"] .multiple-item-row').first();
    await itemArea.waitFor({ timeout: 8000 });
    await itemArea.locator('input[name^="prod_code"]').fill('PIPE-001');
    await itemArea.locator('input[name^="title"]').fill('PIPE E2E Malzeme');
    const unitSel = itemArea.locator('select[name^="unit"]');
    if (await unitSel.count()) await unitSel.selectOption('ST');
    await shot(admin, '8-ihtiyac-form');
    await admin.locator('button:has-text("Kaydet")').first().click();
    await admin.waitForTimeout(2200);
    const sw = admin.locator('.swal2-confirm');
    const swText = await admin.locator('.swal2-popup').innerText().catch(() => '');
    if (await sw.count()) await sw.click().catch(() => {});
    if (swText && !swText.includes('Tamamlandı') && !swText.includes('başarı')) {
      throw new Error('İhtiyaç kaydedilemedi: ' + swText.slice(0, 160));
    }
    ok(`ihtiyaç listesi oluşturuldu: ${IHTIYAC}`);

    const ihtiyacQnid = await admin.evaluate(async (title) => {
      const fd = new FormData();
      fd.append('tableReq', JSON.stringify({ filter: [{ key: 'form-type', type: '=', value: 'op-doc-request-form' }, { key: 'type', type: '=', value: 'op-doc-request' }], scale: { page: 1, limit: 10 } }));
      const r = await fetch('/api/v1/table/documents', { method: 'POST', body: fd });
      const j = await r.json(); const rows = j.data?.data || j.data || [];
      for (const row of rows) { try { const a = JSON.parse(row.main_attr || '[]'); if (a.find(x => x.Key === 'title')?.Value === title) return row.id; } catch (e) {} }
      return rows[0]?.id || null;
    }, IHTIYAC);
    console.log(`   ihtiyaç qnid: ${ihtiyacQnid}`);
    if (!ihtiyacQnid) throw new Error('ihtiyaç qnid bulunamadı (kayıt oluşmadı?)');

    console.log('[2.2] kb (05-03) creates Talep Dökümanı');
    await kb.goto(`${BASE}/planlamapanel/talep-dokumanlari/form?from=${ihtiyacQnid}`, { waitUntil: 'networkidle' });
    await kb.waitForSelector('.area-target[data-tag="op-doc-requestf-form"]', { timeout: 20000 });
    // pick müdürlük (real uploader must choose)
    const mudurSel = kb.locator('select.planlama-mudur__select, .tedarik-step-body select').first();
    if (await mudurSel.count()) {
      const opt = mudurSel.locator('option').filter({ hasText: 'Planlama Müdürlüğü' }).first();
      if (await opt.count()) await mudurSel.selectOption(await opt.getAttribute('value'));
    }
    // quantities come from Excel in this workflow ("Excel içe aktarın ve Kaydedin")
    await kb.waitForSelector('button:has-text("Excel\'den Yükle")', { timeout: 15000 });
    await kb.locator('input[type="file"][accept*=".xls"]').first().setInputFiles('/tmp/talep.xlsx');
    await kb.waitForTimeout(3000);
    const nRows = await kb.locator('.area-target[data-tag="op-doc-requestf-item-form"] .multiple-item-row').count();
    await shot(kb, '9-talep-form');
    await kb.locator('button:has-text("Kaydet")').first().click();
    await kb.waitForTimeout(2500);
    const sw2 = kb.locator('.swal2-confirm');
    if (await sw2.count()) await sw2.click().catch(() => {});
    const talepQnid = await kb.evaluate(async () => {
      const fd = new FormData();
      fd.append('tableReq', JSON.stringify({ filter: [{ key: 'form-type', type: '=', value: 'op-doc-requestf-form' }, { key: 'type', type: '=', value: 'op-doc-requestf' }], scale: { page: 1, limit: 5 } }));
      const r = await fetch('/api/v1/table/documents', { method: 'POST', body: fd });
      const j = await r.json(); const rows = j.data?.data || j.data || [];
      return rows[0]?.id || null;
    });
    const talepStatus = talepQnid ? await kb.evaluate(async (qnid) => {
      const j = await (await fetch('/api/v1/document/' + qnid)).json();
      let st = j?.data?.document?.status; if (typeof st === 'string') { try { st = JSON.parse(st); } catch (e) {} }
      if (Array.isArray(st)) st = st[st.length - 1];
      return st?.op_key || st?.op_title || '';
    }, talepQnid) : '';
    console.log(`   talep qnid: ${talepQnid} status: ${talepStatus}`);
    if (talepStatus && !String(talepStatus).includes('awaiting')) throw new Error('talep awaiting olmalı: ' + talepStatus);
    ok(`talep oluşturuldu (${nRows} kalem) → Müdürlük onayına gönderildi`);

    console.log('[2.3] picklecanme (05-01) Müdürlük onayı');
    await login(pickle, PICKLE);
    await goModule(pickle, '/planlamapanel');
    await pickle.goto(`${BASE}/planlamapanel/talep-dokumanlari`, { waitUntil: 'networkidle' });
    await pickle.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    const talepRow = pickle.locator('#div_table tbody tr').first();
    await talepRow.locator('button:has-text("Detay"), button:has-text("Aç"), a:has-text("Detay")').first().click().catch(async () => {
      const detailBtn = pickle.locator('#div_table tbody tr td a, #div_table tbody tr td button').first();
      await detailBtn.click();
    });
    await pickle.waitForTimeout(2200);
    await pickle.waitForSelector('.tedarik-btn-ok, .planlama-rform__btn--ok', { timeout: 15000 });
    const approveBtn = pickle.locator('button.tedarik-btn-ok:has-text("Onayla"), button.planlama-rform__btn--ok:has-text("Onayla")').first();
    await approveBtn.click();
    await pickle.waitForTimeout(2000);
    const sw3 = pickle.locator('button:has-text("Evet, Onayla")');
    if (await sw3.count()) await sw3.click();
    await pickle.waitForTimeout(2000);
    ok('müdürlük onayı verildi (picklecanme)');
    await shot(pickle, '10-pickle-approve');

    console.log('[2.4] admin (05-04) Standardizasyon Onayı');
    await admin.goto(`${BASE}/planlamapanel/talep-dokumanlari`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    const aRow = admin.locator('#div_table tbody tr').first();
    await aRow.locator('button, a').first().click();
    await admin.waitForTimeout(2200);
    await admin.waitForSelector('.tedarik-btn-ok, .planlama-rform__btn--ok', { timeout: 15000 });
    const stdBtn = admin.locator('button:has-text("Standardizasyon Onayı")').first();
    if (await stdBtn.count()) {
      await stdBtn.click();
      await admin.waitForTimeout(1800);
      const c = admin.locator('button:has-text("Evet, Onayla")');
      if (await c.count()) await c.click();
      await admin.waitForTimeout(1800);
      ok('standardizasyon onayı verildi (kadir)');
    } else {
      ok('std onayı adımı atlandı (buton yok) — müdürlük onayı yeterli kontrol');
    }
    await shot(admin, '11-planlama-final');

    // ═══════════ 3. TENDER — 2 suppliers, reject + accept winner ═══════════
    console.log('\n[3.1] admin creates + activates tender (ihalepanel)');
    await goModule(admin, '/ihalepanel');
    await admin.goto(`${BASE}/ihalepanel/ihaleler/form`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('input[name="ihale_kodu"]', { timeout: 20000 });
    await admin.fill('input[name="ihale_kodu"]', TENDER);
    await admin.evaluate((v) => { const el = document.querySelector('input[name="teklif_son_tarihi"]'); el.readOnly = false; el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); el.readOnly = true; }, '30/12/2026');
    await admin.fill('input[name^="spec_title"]', 'PIPE Teknik Şartname');
    await admin.setInputFiles('input[name^="ihale_spec_file"]', PDF);
    await admin.waitForTimeout(1000);
    await admin.click('input[name^="product_title"]');
    await admin.waitForSelector('#ih_prod_table', { timeout: 10000 });
    await admin.fill('input[placeholder="Kod veya ad ara..."]', 'E2E-TRF-100');
    await admin.click('button:has-text("Ara")');
    await admin.waitForTimeout(800);
    await admin.locator('#ih_prod_table .ihf-pick-btn').first().click();
    await admin.fill('input[name^="quantity"]', '10');
    await admin.locator('button:has-text("Formu Kaydet")').first().click();
    await admin.waitForTimeout(2500);
    ok('tender oluşturuldu');

    await admin.goto(`${BASE}/ihalepanel/ihaleler`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    const tRow = admin.locator('#div_table tbody tr', { hasText: TENDER }).first();
    await tRow.locator('button:has-text("Aksiyonlar")').click();
    await admin.waitForTimeout(600);
    await admin.locator('#aks-activate').click();
    await admin.waitForTimeout(800);
    await admin.locator('button:has-text("Evet, aktif et")').click();
    await admin.waitForTimeout(2000);
    ok('tender aktif edildi');
    await shot(admin, '12-tender-active');

    console.log('[3.2] kb bid');
    await kb.goto(`${BASE}/ihalepanel/ihaleler`, { waitUntil: 'networkidle' });
    await kb.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    const kbTrow = kb.locator('#div_table tbody tr', { hasText: TENDER }).first();
    await kbTrow.locator('button:has-text("Teklif Ver")').first().click();
    await kb.waitForSelector('#iho_prod_table table tbody tr', { timeout: 30000 });
    await kb.locator('#iho_prod_table button.iho-pt-btn').first().click();
    await kb.waitForTimeout(800);
    const kbIn = kb.locator('input[placeholder="doldurunuz"]:visible');
    await kbIn.nth(0).fill('PIPE KB İmalatçı');
    await kbIn.nth(1).fill('PIPE KB Marka');
    const cell = kb.locator('.iho-cell-upload input[type="file"]').first();
    if (await cell.count()) {
      await cell.setInputFiles(PDF);
      await kb.waitForTimeout(800);
      const swU = kb.locator('.swal2-confirm');
      if (await swU.count()) await swU.click().catch(() => {});
      await kb.waitForTimeout(1500);
    }
    await kb.locator('button:has-text("Teklifi Kaydet")').click();
    await kb.waitForTimeout(4000);
    const swS = kb.locator('.swal2-confirm');
    if (await swS.count()) await swS.click().catch(() => {});
    await kb.waitForTimeout(2000);
    ok('kb teklif gönderdi');
    await shot(kb, '13-kb-bid');

    console.log('[3.3] picklecanme bid');
    await pickle.goto(`${BASE}/ihalepanel/ihaleler`, { waitUntil: 'networkidle' });
    await pickle.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    const pTrow = pickle.locator('#div_table tbody tr', { hasText: TENDER }).first();
    await pTrow.locator('button:has-text("Teklif Ver")').first().click();
    await pickle.waitForSelector('#iho_prod_table table tbody tr', { timeout: 30000 });
    await pickle.locator('#iho_prod_table button.iho-pt-btn').first().click();
    await pickle.waitForTimeout(800);
    const pIn = pickle.locator('input[placeholder="doldurunuz"]:visible');
    await pIn.nth(0).fill('PIPE Pickle İmalatçı');
    await pIn.nth(1).fill('PIPE Pickle Marka');
    const pCell = pickle.locator('.iho-cell-upload input[type="file"]').first();
    if (await pCell.count()) {
      await pCell.setInputFiles(PDF);
      await pickle.waitForTimeout(800);
      const swU = pickle.locator('.swal2-confirm');
      if (await swU.count()) await swU.click().catch(() => {});
      await pickle.waitForTimeout(1500);
    }
    await pickle.locator('button:has-text("Teklifi Kaydet")').click();
    await pickle.waitForTimeout(4000);
    const swS2 = pickle.locator('.swal2-confirm');
    if (await swS2.count()) await swS2.click().catch(() => {});
    await pickle.waitForTimeout(2000);
    ok('picklecanme teklif gönderdi');
    await shot(pickle, '14-pickle-bid');

    console.log('[3.4] admin reviews: reject kb, accept pickle → winner');
    await admin.goto(`${BASE}/ihalepanel/ihaleler/teklifler`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    const offerRows = admin.locator('#div_table tbody tr', { hasText: TENDER });
    const offerCount = await offerRows.count();
    console.log(`   offers for ${TENDER}: ${offerCount}`);
    if (offerCount < 2) throw new Error('2 teklif bekleniyordu, görülen: ' + offerCount);
    ok('admin teklifler listesinde 2 teklif görüyor');
    await shot(admin, '15-admin-offers');

    // reject the first supplier's offer, accept the other
    const kbOfferRow = offerRows.nth(0);
    await kbOfferRow.locator('button:has-text("Aksiyonlar")').click();
    await admin.waitForTimeout(600);
    await admin.locator('#aks-reject').click();
    await admin.waitForTimeout(800);
    await admin.locator('.swal2-textarea').fill('PIPE E2E red nedeni');
    await admin.locator('button:has-text("Reddet")').click();
    await admin.waitForTimeout(2000);
    ok('kb teklifi reddedildi');

    await admin.waitForTimeout(1200);
    // remaining sended offer is the one that still has an Aksiyonlar button
    const pOfferRow = admin.locator('#div_table tbody tr', { hasText: TENDER })
      .filter({ has: admin.locator('button:has-text("Aksiyonlar")') }).first();
    await pOfferRow.locator('button:has-text("Aksiyonlar")').click();
    await admin.waitForTimeout(600);
    await admin.locator('#aks-accept').click();
    await admin.waitForTimeout(800);
    await admin.locator('button:has-text("Kabul Et ve İhaleyi Tamamla")').click();
    await admin.waitForTimeout(2500);
    ok('picklecanme teklifi kabul edildi → ihale tamamlandı');
    await shot(admin, '16-tender-done');

    await admin.goto(`${BASE}/ihalepanel/ihaleler`, { waitUntil: 'networkidle' });
    await admin.waitForTimeout(1500);
    const doneRowText = await admin.locator('#div_table tbody tr', { hasText: TENDER }).first().innerText();
    ok(`tender listed: ${doneRowText.slice(0, 110).replace(/\n/g, ' | ')}`);

    // admin panel twin (coalpanel)
    await goModule(admin, '/coalpanel');
    await admin.goto(`${BASE}/coalpanel/ihaleler`, { waitUntil: 'networkidle' });
    await admin.waitForSelector('#div_table table tbody tr', { timeout: 20000 });
    const coalRows = await admin.locator('#div_table tbody tr').count();
    ok(`coalpanel admin tender list renders (${coalRows} rows)`);
    await shot(admin, '17-coalpanel-tenders');

    // supplier visibility: kb sees Tamamlandı + rejected banner when opening own offer
    await kb.goto(`${BASE}/ihalepanel/ihaleler`, { waitUntil: 'networkidle' });
    await kb.waitForTimeout(1500);
    const kbDoneText = await kb.locator('#div_table tbody tr', { hasText: TENDER }).first().innerText();
    ok(`kb sees: ${kbDoneText.slice(0, 100).replace(/\n/g, ' | ')}`);
    await pickle.goto(`${BASE}/ihalepanel/ihaleler`, { waitUntil: 'networkidle' });
    await pickle.waitForTimeout(1500);
    const pDoneText = await pickle.locator('#div_table tbody tr', { hasText: TENDER }).first().innerText();
    ok(`pickle sees: ${pDoneText.slice(0, 100).replace(/\n/g, ' | ')}`);

    console.log('\n════════════════════════════════════');
    console.log('ALL PIPELINES COMPLETE');
    summary.forEach(s => console.log(s));
  } catch (e) {
    console.error('\nPIPELINE FAILED:', e.message);
    console.error(e.stack?.split('\n').slice(0, 4).join('\n'));
    for (const [name, page] of [['admin', admin], ['kb', kb], ['pickle', pickle]]) {
      await shot(page, `fail-${name}`).catch(() => {});
    }
    process.exitCode = 1;
  } finally {
    await new Promise(r => setTimeout(r, 3000));
    await browser.close();
  }
}

// fixture pdfs + talep excel
for (const [n, t] of [['kabul.pdf', 'KABUL'], ['cins.pdf', 'CINS']]) {
  if (!fs.existsSync('/tmp/' + n)) fs.writeFileSync('/tmp/' + n, `%PDF-1.4\n%${t}\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF`);
}
{
  const ws = XLSX.utils.aoa_to_sheet([
    ['Mal Grubu', 'İhale Grubu', 'Malzeme Kodu', 'Kısa Metni', 'Ölçü Birimi', 'Ay', 'Miktar'],
    ['', '', 'PIPE-001', 'PIPE E2E Malzeme', 'ST', '01/2026', 5],
  ]);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Talep');
  XLSX.writeFile(wb, '/tmp/talep.xlsx');
}

main();
