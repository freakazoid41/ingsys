import { chromium } from 'playwright';
import fs from 'fs';

const BASE = 'http://localhost:8000';
const PASS = 'Kadir412.';

async function login(page, email) {
  await page.goto(`${BASE}/tedarik`, { waitUntil: 'networkidle' });
  await page.waitForSelector('#email', { timeout: 15000 });
  await page.fill('#email', email);
  await page.fill('#password', PASS);
  await page.evaluate(() => {
    const el = document.querySelector('[name="g-recaptcha-response"]');
    if (el) el.value = 'test';
    const ta = document.querySelector('textarea[name="g-recaptcha-response"]');
    if (ta) ta.value = 'test';
  });
  await page.click('#submit-button');
  await page.waitForTimeout(1800);
  const smsVisible = await page.locator('input[name^="code_"]').first().isVisible().catch(() => false);
  console.log(`[login ${email}] smsVisible=${smsVisible} url=${page.url()}`);
  if (smsVisible) {
    for (let i = 1; i <= 6; i++) {
      const sel = `input[name="code_${i}"]`;
      if (await page.locator(sel).count()) await page.fill(sel, '1');
    }
    const btn = page.locator('button:has-text("Doğrula"), button:has-text("Onayla"), button[type="submit"]').first();
    if (await btn.count()) await btn.first().click();
    else await page.keyboard.press('Enter');
    await page.waitForTimeout(2500);
    await page.waitForLoadState('networkidle').catch(() => {});
  }
  console.log(`[login ${email}] post-sms url=${page.url()}`);
  if (page.url().includes('/module-select')) {
    // pick Tedarik for suppliers, Yönetim for admin later via direct nav
    const ted = page.locator('a:has-text("Tedarik")').first();
    if (await ted.count()) { await ted.click(); await page.waitForLoadState('networkidle').catch(()=>{}); }
  }
  await page.waitForTimeout(1000);
  console.log(`[login ${email}] final url=${page.url()}`);
}

async function apiOrders(page) {
  return await page.evaluate(async () => {
    const fd = new FormData();
    fd.append('tableReq', JSON.stringify({ filter: [{ key: 'form-type', type: '=', value: 'op-doc-order-form' }, { key: 'type', type: '=', value: 'op-doc-order' }], scale: { page: 1, limit: 50 } }));
    const r = await fetch('/api/v1/table/documents', { method: 'POST', body: fd });
    const j = await r.json();
    const rows = j.data?.data || j.data || [];
    return rows.map(row => {
      try {
        const a = JSON.parse(row.main_attr || '[]');
        const no = (a.find(x => x.Key === 'order_no') || {}).Value || row.order_no || '?';
        return { no, qnid: row.id || row.qnid, raw: row };
      } catch (e) { return { no: '?', qnid: row.id }; }
    });
  });
}

async function apiLastStatus(page, orderQnid) {
  return await page.evaluate(async (qnid) => {
    const r = await fetch('/api/v1/document/' + qnid, { method: 'GET' });
    const j = await r.json();
    const st = j.data?.document?.status || [];
    const last = st[st.length - 1];
    return last ? (last.op_key || last.op_title || JSON.stringify(last)) : 'none';
  }, orderQnid);
}

(async () => {
  // dummy pdfs
  for (const [name, txt] of [['kabul.pdf', 'KABUL FORM DUMMY'], ['cins.pdf', 'CINS FORM DUMMY'], ['test.pdf', 'TEST DOC DUMMY']]) {
    if (!fs.existsSync('/tmp/' + name)) fs.writeFileSync('/tmp/' + name, `%PDF-1.4\n%${txt}\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF`);
  }
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 1360, height: 900 } });
  const page = await ctx.newPage();
  page.on('console', m => { const t = m.text(); if (t && t.length < 300) console.log('[pg]', t); });
  const summary = {};
  try {
    console.log('=== A pickle list isolation ===');
    await login(page, 'picklecanme@gmail.com');
    await page.goto(`${BASE}/tedarikpanel/orders`, { waitUntil: 'networkidle' });
    await page.waitForSelector('#div_table', { timeout: 15000 }).catch(() => {});
    await page.waitForTimeout(2000);
    await page.screenshot({ path: '/tmp/tedarik-A-pickle-list.png', fullPage: true });
    let orders = await apiOrders(page);
    console.log('[pickle orders]', orders.map(o => o.no).join(','));
    summary.pickleSees = orders.map(o => o.no);
    const sees4200 = orders.some(o => o.no === '3510004200');
    const sees4400 = orders.some(o => o.no.startsWith('3510004400'));
    console.log(`[pickle] sees4200=${sees4200} sees4400=${sees4400}`);
    if (!sees4200) throw new Error('pickle should see 3510004200 (bound 0000300184)');
    if (sees4400) throw new Error('ISOLATION FAIL: pickle (184) sees 4400 (186)');
    console.log('PASS isolation pickle 184-only');

    console.log('=== B pickle at_once 4200 full send ===');
    const o4200 = orders.find(o => o.no === '3510004200');
    const qnid4200 = o4200.qnid;
    console.log('[4200 qnid]', qnid4200);
    let st0 = await apiLastStatus(page, qnid4200);
    console.log('[4200 status before]', st0);
    summary.statusBefore = st0;
    await page.goto(`${BASE}/tedarikpanel/orders/form/${qnid4200}`, { waitUntil: 'networkidle' });
    await page.waitForSelector('.tedarik-detail', { timeout: 20000 });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: '/tmp/tedarik-B-detail-before.png', fullPage: true });
    // transfer mode must be at_once enabled (no partitions for 4200)
    const atOnceDisabled = await page.locator('input[value="at_once"]').isDisabled().catch(() => true);
    console.log('[4200] at_once disabled?', atOnceDisabled);
    if (atOnceDisabled) throw new Error('4200 at_once should be enabled (no clones)');
    // ensure at_once selected
    const atOnceChecked = await page.locator('input[value="at_once"]').isChecked().catch(() => false);
    if (!atOnceChecked) await page.locator('label:has-text("Tek Parça Sevkiyat")').first().click().catch(()=>{});
    // fill desc + imalatci (detached v-model trap: must use placeholder inputs)
    await page.fill('textarea[placeholder*="açıklama"]', 'Browser E2E aciklama ' + Date.now());
    await page.fill('input[placeholder*="İmalatçı"]', 'Browser E2E Imalatci Ltd');
    await page.waitForTimeout(400);
    // upload kabul + cins (first two file wraps)
    const fileInputs = page.locator('.tedarik-file-wrap input[type="file"]');
    const nFile = await fileInputs.count();
    console.log('[4200] file inputs', nFile);
    if (nFile < 2) throw new Error('expected >=2 order file inputs, got ' + nFile);
    await fileInputs.nth(0).setInputFiles('/tmp/kabul.pdf');
    await page.waitForTimeout(2500); // temp-upload finalize
    await fileInputs.nth(1).setInputFiles('/tmp/cins.pdf');
    await page.waitForTimeout(2500);
    await page.screenshot({ path: '/tmp/tedarik-B-after-upload.png', fullPage: true });
    // per-item test file optional: try OrderItemTable file input if present
    // Gönder
    const gonder = page.locator('.tedarik-gonder-btn').first();
    if (!(await gonder.count())) throw new Error('Gonder button missing (order locked?) status=' + st0);
    await gonder.scrollIntoViewIfNeeded().catch(()=>{});
    await gonder.click();
    await page.waitForTimeout(4000);
    await page.screenshot({ path: '/tmp/tedarik-B-after-gonder.png', fullPage: true });
    // Swal may show success or still-rejected info or validation
    const swalVisible = await page.locator('.swal2-popup').isVisible().catch(() => false);
    if (swalVisible) {
      const txt = await page.locator('.swal2-popup').innerText().catch(() => '');
      console.log('[gonder swal]', txt.slice(0, 300));
      await page.locator('.swal2-confirm').first().click().catch(()=>{});
      await page.waitForTimeout(2000);
    }
    let st1 = await apiLastStatus(page, qnid4200);
    console.log('[4200 status after]', st1);
    summary.statusAfterSend = st1;
    if (st1 !== 'doc_trans_order_transfer_sent') throw new Error('expected transfer_sent after Gonder, got ' + st1);
    console.log('PASS at_once 4200 -> transfer_sent');

    console.log('=== C kbbozat isolation + partition guard ===');
    await page.goto(`${BASE}/logout`, { waitUntil: 'networkidle' }).catch(()=>{});
    await ctx.clearCookies();
    await login(page, 'kbbozat41@hotmail.com');
    await page.goto(`${BASE}/tedarikpanel/orders`, { waitUntil: 'networkidle' });
    await page.waitForSelector('#div_table', { timeout: 15000 }).catch(()=>{});
    await page.waitForTimeout(2000);
    await page.screenshot({ path: '/tmp/tedarik-C-kbbozat-list.png', fullPage: true });
    orders = await apiOrders(page);
    console.log('[kbbozat orders]', orders.map(o => o.no).join(','));
    summary.kbbozatSees = orders.map(o => o.no);
    if (orders.some(o => o.no === '3510004200')) throw new Error('ISOLATION FAIL: kbbozat (186) sees 4200 (184)');
    if (!orders.some(o => o.no.startsWith('3510004400'))) throw new Error('kbbozat should see 4400 family');
    console.log('PASS isolation kbbozat 186-only');
    const o4400 = orders.find(o => o.no === '3510004400');
    await page.goto(`${BASE}/tedarikpanel/orders/form/${o4400.qnid}`, { waitUntil: 'networkidle' });
    await page.waitForSelector('.tedarik-detail', { timeout: 20000 });
    await page.waitForTimeout(1500);
    const atOnceDis4400 = await page.locator('input[value="at_once"]').isDisabled().catch(() => false);
    const banner = await page.locator('text=Parçalı').first().isVisible().catch(()=>false);
    console.log(`[4400] at_once disabled=${atOnceDis4400} banner=${banner}`);
    await page.screenshot({ path: '/tmp/tedarik-C-4400-guard.png', fullPage: true });
    if (!atOnceDis4400) throw new Error('4400 base has 4 clones, at_once must be disabled');
    console.log('PASS partition guard 4400 at_once disabled');

    console.log('=== D admin approve + reject loop + kalite ===');
    await page.goto(`${BASE}/logout`, { waitUntil: 'networkidle' }).catch(()=>{});
    await ctx.clearCookies();
    await login(page, 'kadir@kontent.com.tr');
    // admin needs coalpanel; login lands maybe tedarik, force coalpanel
    await page.goto(`${BASE}/coalpanel/documents`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    await page.screenshot({ path: '/tmp/tedarik-D-admin-docs.png', fullPage: true });
    // get file qnids for order 4200 via API
    const files = await page.evaluate(async (orderQnid) => {
      const fd = new FormData();
      fd.append('tableReq', JSON.stringify({ filter: [], scale: { page: 1, limit: 100 } }));
      const r = await fetch('/api/v1/table/document_files', { method: 'POST', body: fd });
      const j = await r.json();
      const rows = j.data?.data || j.data || [];
      return rows.filter(x => (x.group_key === '3510004200') || (x.relation_detail && JSON.stringify(x.relation_detail).includes('3510004200'))).map(x => ({ qnid: x.id || x.qnid, file: x.file, group: x.group_key, status: x.last_status }));
    }, qnid4200);
    console.log('[4200 files]', JSON.stringify(files).slice(0, 800));
    summary.files4200 = files.length;
    if (!files.length) throw new Error('no files found for 4200 after send');
    // approve first file
    const f1 = files[0].qnid;
    let res = await page.evaluate(async (fq) => {
      const fd = new FormData(); fd.append('id', fq); fd.append('op_key', 'doc_file_accepted'); fd.append('note', 'browser e2e approve');
      const r = await fetch('/api/v1/trans/set-file-status', { method: 'POST', body: fd });
      return { status: r.status, body: (await r.text()).slice(0, 300) };
    }, f1);
    console.log('[approve f1]', JSON.stringify(res));
    // reject second file if exists, else reject first then re-approve path
    if (files.length > 1) {
      const f2 = files[1].qnid;
      res = await page.evaluate(async (fq) => {
        const fd = new FormData(); fd.append('id', fq); fd.append('op_key', 'doc_file_rejected'); fd.append('note', 'browser e2e kalite uygun degil');
        const r = await fetch('/api/v1/trans/set-file-status', { method: 'POST', body: fd });
        return { status: r.status, body: (await r.text()).slice(0, 500) };
      }, f2);
      console.log('[reject f2]', JSON.stringify(res));
      summary.rejected = f2;
      // order should now be files_rejected (auto sync)
      const stR = await apiLastStatus(page, qnid4200);
      console.log('[4200 after reject]', stR);
      summary.statusAfterReject = stR;
      if (stR !== 'doc_trans_order_files_rejected') throw new Error('expected files_rejected after 1 reject, got ' + stR);
      console.log('PASS auto files_rejected');
    }
    // verify enriched logs
    const logs = await page.evaluate(async () => {
      const fd = new FormData();
      fd.append('tableReq', JSON.stringify({ filter: [], scale: { page: 1, limit: 5 } }));
      const r = await fetch('/api/v1/table/userlog', { method: 'POST', body: fd });
      const j = await r.json();
      const rows = j.data?.data || j.data || [];
      return rows.map(x => { try { const d = JSON.parse(x.description || '{}'); return { keys: Object.keys(d), hasActor: !!d.actor, hasDoc: !!d.document, hasFile: !!d.file }; } catch (e) { return { keys: [], err: true }; } });
    });
    console.log('[logs]', JSON.stringify(logs).slice(0, 500));
    summary.logs = logs;
    // kalite close attempt (should work from files_rejected or transfer_sent: approved from ANY)
    const kal = await page.evaluate(async (oq) => {
      const fd = new FormData(); fd.append('id', oq); fd.append('op_key', 'doc_trans_order_approved'); fd.append('note', 'browser e2e kalite');
      const r = await fetch('/api/v1/trans/set-status', { method: 'POST', body: fd });
      return { status: r.status, body: (await r.text()).slice(0, 500) };
    }, qnid4200);
    console.log('[kalite]', JSON.stringify(kal));
    summary.kalite = kal.status;
    const stK = await apiLastStatus(page, qnid4200);
    console.log('[4200 after kalite]', stK);
    summary.statusAfterKalite = stK;

    console.log('=== SUMMARY ===');
    console.log(JSON.stringify(summary, null, 2));
    console.log('E2E TEDARIK PIPELINE DONE');
  } catch (e) {
    console.error('E2E FAIL:', e.message);
    console.error(e.stack);
    try { await page.screenshot({ path: '/tmp/tedarik-FAIL.png', fullPage: true }); } catch (x) {}
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
