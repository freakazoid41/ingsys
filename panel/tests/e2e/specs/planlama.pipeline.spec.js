import { test, expect } from '@playwright/test';
import { login, logout } from '../helpers/auth.js';
import { createIhtiyac, getIhtiyacQnidFromList, createTalepFromIhtiyac } from '../helpers/planlama.js';

const title = `E2E Ihtiyac ${Date.now()}`;

test.describe('Malzeme Planlama — Real Browser Pipeline (7 steps)', () => {
  test.setTimeout(180000);

  test('full pipeline: ihtiyac → talep → approve → std → rejected requeue (+ guards)', async ({ page }) => {
    // STEP 1: kadir creates İhtiyaç (05-02)
    await login(page, 'kadir@kontent.com.tr', 'Kadir412.');
    expect(page.url()).toContain('planlama');
    await createIhtiyac(page, { title, donemBas: '01/2026', donemBit: '03/2026', desc: 'e2e pipeline test' });
    // verify ihtiyanc in list
    const ihtiyancQnid = await getIhtiyacQnidFromList(page, title);
    console.log(`[e2e] ihtiyancQnid=${ihtiyancQnid}`);
    expect(ihtiyancQnid).toBeTruthy();
    // verify via API that birth is doc_trans_request_created (fixed)
    const birth = await page.evaluate(async (qnid) => {
      const r = await fetch(`/api/v1/document/${qnid}`, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
      const j = await r.json(); const doc = j.data?.document; let st = doc?.status; if(typeof st==='string') try{st=JSON.parse(st)}catch(e){}; const last=Array.isArray(st)?st[st.length-1]:null; return last?.op_key || '';
    }, ihtiyancQnid);
    console.log(`[e2e] ihtiyanc birth=${birth}`);
    expect(birth).toBe('doc_trans_request_created');

    // STEP 2: kbbozat creates Talep from İhtiyaç (05-03) — tests mudur picker fix + linkFromSource
    await logout(page);
    await login(page, 'kbbozat41@hotmail.com', 'Kadir412.');
    const talepInfo = await createTalepFromIhtiyac(page, ihtiyancQnid);
    console.log(`[e2e] talep rows=${talepInfo.n} qnid=${talepInfo.talepQnid}`);
    expect(talepInfo.n).toBeGreaterThanOrEqual(6);
    expect(talepInfo.talepQnid).toBeTruthy();
    const talepQnid = talepInfo.talepQnid;
    // verify birth awaiting
    const tbirth = await page.evaluate(async (qnid)=>{
      const r=await fetch(`/api/v1/document/${qnid}`); const j=await r.json(); let st=j.data?.document?.status; if(typeof st==='string') st=JSON.parse(st); return (Array.isArray(st)?st[st.length-1]?.op_key:'');
    }, talepQnid);
    expect(tbirth).toBe('tr-request-man-awaiting');
    // verify tableList status
    const listStatus = await page.evaluate(async ()=>{
      const fd=new FormData(); fd.append('tableReq', JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-requestf-form'},{key:'type',type:'=',value:'op-doc-requestf'}],scale:{page:1,limit:5}}));
      const r=await fetch('/api/v1/table/documents',{method:'POST',body:fd}); const j=await r.json(); const rows=j.data?.data||j.data||[]; const first=rows[0]; return first?.status||'';
    });
    expect(listStatus).toContain('tr-request-man-awaiting');

    // Guard: illegal edit on awaiting should be blocked (backend 422)
    const editBlocked = await page.evaluate(async (qnid)=>{
      // need connId
      const r=await fetch(`/api/v1/document/${qnid}`); const j=await r.json(); const ff=j.data?.formFormat?.['op-doc-requestf-form']||{}; const connId=Object.keys(ff)[0]; const fd=new FormData(); fd.append('data', JSON.stringify({typeKey:'op-doc-requestf', dynamicF:{[`op-doc-requestf-form**${connId}`]:{tag:'op-doc-requestf-form', entities:{title:'HACKED'}}} })); const res=await fetch(`/api/v1/document/${qnid}`,{method:'PUT', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}}); const jr=await res.json().catch(()=>({})); return { ok: res.ok, status: res.status, success: jr.success };
    }, talepQnid);
    console.log(`[e2e] edit awaiting blocked=${JSON.stringify(editBlocked)}`);
    expect(editBlocked.success).toBeFalsy();

    // Mudur picker test already done via helper: system_only should have 6
    const mudurCount = await page.evaluate(async ()=>{
      const fd=new FormData(); fd.append('tableReq', JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-client-form'},{key:'type',type:'=',value:'op-doc-client'},{key:'system_only',type:'=',value:'1'}],scale:{page:1,limit:10}}));
      const r=await fetch('/api/v1/table/documents',{method:'POST',body:fd}); const j=await r.json(); return (j.data?.data||j.data||[]).length;
    });
    expect(mudurCount).toBe(6);

    // STEP 3: Approve as picklecanme (05-01)
    await logout(page);
    await login(page, 'picklecanme@gmail.com', 'Kadir412.');
    // use API set-status (UI also has button but API is deterministic)
    const appr = await page.evaluate(async (qnid)=>{
      const fd=new FormData(); fd.append('id', qnid); fd.append('op_key','tr-request-man-approved'); const r=await fetch('/api/v1/trans/set-status',{method:'POST', body:fd}); return { status:r.status, json:await r.json().catch(()=>({})) };
    }, talepQnid);
    console.log(`[e2e] man-approved=${JSON.stringify(appr.json).slice(0,120)}`);
    expect(appr.json.success).toBeTruthy();
    // verify edit after approved still blocked
    await logout(page); await login(page, 'kbbozat41@hotmail.com','Kadir412.');
    const editAfterAppr = await page.evaluate(async (qnid)=>{
      const r=await fetch(`/api/v1/document/${qnid}`); const j=await r.json(); const conn=Object.keys(j.data?.formFormat?.['op-doc-requestf-form']||{})[0]; const fd=new FormData(); fd.append('data', JSON.stringify({typeKey:'op-doc-requestf', dynamicF:{[`op-doc-requestf-form**${conn}`]:{tag:'op-doc-requestf-form', entities:{title:'HACK2'}}}})); const res=await fetch(`/api/v1/document/${qnid}`,{method:'PUT', body:fd}); const jr=await res.json().catch(()=>({})); return jr.success;
    }, talepQnid);
    expect(editAfterAppr).toBeFalsy();

    // STEP 4: Std approve as kadir (05-04)
    await logout(page); await login(page, 'kadir@kontent.com.tr','Kadir412.');
    const std = await page.evaluate(async (qnid)=>{
      const fd=new FormData(); fd.append('id',qnid); fd.append('op_key','tr-request-std-approved'); const r=await fetch('/api/v1/trans/set-status',{method:'POST', body:fd}); return { json:await r.json().catch(()=>({})), status:r.status };
    }, talepQnid);
    console.log(`[e2e] std-approved=${JSON.stringify(std.json).slice(0,120)}`);
    expect(std.json.success).toBeTruthy();
    // verify terminal
    const bad = await page.evaluate(async (qnid)=>{
      const fd=new FormData(); fd.append('id',qnid); fd.append('op_key','tr-request-man-rejected'); const r=await fetch('/api/v1/trans/set-status',{method:'POST', body:fd}); return await r.json().catch(()=>({}));
    }, talepQnid);
    expect(bad.success).toBeFalsy();

    // STEP 5: Rejected → edit → requeue (full cycle on new talep)
    // create new ihtiyac+talep as kadir/kbbozat
    const title2 = `E2E Rej ${Date.now()}`;
    await logout(page); await login(page, 'kadir@kontent.com.tr','Kadir412.');
    // create ihtiyanc2 via API quickly
    const ihtiyanc2 = await page.evaluate(async (t)=>{
      const fd=new FormData(); fd.append('data', JSON.stringify({typeKey:'op-doc-request', dynamicF:{'op-doc-request-form**new-1':{tag:'op-doc-request-form', entities:{title:t, donem_bas:'04/2026', donem_bit:'04/2026', desc:'rej'}}, 'op-doc-request-item-form**new-2':{tag:'op-doc-request-item-form', entities:{mal_grubu:'T',ihale_grubu:'T',prod_code:'REJ-001',title:'Rej Mat',unit:'ST',quantity:'0'}}}})); const r=await fetch('/api/v1/document',{method:'POST', body:fd}); const j=await r.json().catch(()=>({})); return j.data?.qnid||j.qnid||null;
    }, title2);
    expect(ihtiyanc2).toBeTruthy();
    await logout(page); await login(page, 'kbbozat41@hotmail.com','Kadir412.');
    // create talep2
    const mudurQ = await page.evaluate(async ()=>{
      const fd=new FormData(); fd.append('tableReq', JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-client-form'},{key:'type',type:'=',value:'op-doc-client'},{key:'system_only',type:'=',value:'1'}],scale:{page:1,limit:1}})); const r=await fetch('/api/v1/table/documents',{method:'POST',body:fd}); const j=await r.json(); return j.data?.data?.[0]?.id||null;
    });
    const talep2 = await page.evaluate(async ({t,mq})=>{
      const fd=new FormData(); fd.append('data', JSON.stringify({typeKey:'op-doc-requestf', dynamicF:{'op-doc-requestf-form**new-10':{tag:'op-doc-requestf-form', entities:{title:'Rej Talep', request_no:t, desc:'rej', firma_adi:'GDZ-90000001 Planlama', mudur_qnid:mq}}, 'op-doc-requestf-item-form**new-20':{tag:'op-doc-requestf-item-form', entities:{mal_grubu:'T',ihale_grubu:'T',prod_code:'REJ-001',title:'Rej Mat',unit:'ST', ay:'04/2026', quantity:'10'}}}})); const r=await fetch('/api/v1/document',{method:'POST', body:fd}); const j=await r.json().catch(()=>({})); return j.data?.qnid||null;
    }, {t:title2, mq:mudurQ});
    expect(talep2).toBeTruthy();
    await logout(page); await login(page, 'picklecanme@gmail.com','Kadir412.');
    const rej = await page.evaluate(async (qnid)=>{ const fd=new FormData(); fd.append('id',qnid); fd.append('op_key','tr-request-man-rejected'); fd.append('note','Red test e2e'); const r=await fetch('/api/v1/trans/set-status',{method:'POST',body:fd}); return await r.json().catch(()=>({})); }, talep2);
    expect(rej.success).toBeTruthy();
    await logout(page); await login(page, 'kbbozat41@hotmail.com','Kadir412.');
    const editRej = await page.evaluate(async (qnid)=>{
      const r=await fetch(`/api/v1/document/${qnid}`); const j=await r.json(); const conn=Object.keys(j.data?.formFormat?.['op-doc-requestf-form']||{})[0]; const fd=new FormData(); fd.append('data', JSON.stringify({typeKey:'op-doc-requestf', dynamicF:{[`op-doc-requestf-form**${conn}`]:{tag:'op-doc-requestf-form', entities:{title:'Rej Talep REV'}}}})); const res=await fetch(`/api/v1/document/${qnid}`,{method:'PUT', body:fd}); return await res.json().catch(()=>({}));
    }, talep2);
    expect(editRej.success).toBeTruthy();
    const requeue = await page.evaluate(async (qnid)=>{ const fd=new FormData(); fd.append('id',qnid); fd.append('op_key','tr-request-man-awaiting'); fd.append('note','Revize'); const r=await fetch('/api/v1/trans/set-status',{method:'POST',body:fd}); return await r.json().catch(()=>({})); }, talep2);
    expect(requeue.success).toBeTruthy();

    // STEP 6: UI navigation smoke — visit pages
    await page.goto('/planlamapanel/talepler'); await page.waitForSelector('#div_table', {timeout:8000});
    await page.goto('/planlamapanel/talep-dokumanlari'); await page.waitForSelector('#div_table', {timeout:8000});
    await page.goto('/planlamapanel/bilgilendirmeler'); await page.waitForLoadState('networkidle');
    await expect(page.locator('.tdk-bilgi__title')).toContainText('Bilgilendirmeler');
  });
});
