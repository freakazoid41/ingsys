import { chromium } from 'playwright';

async function login(page, email, pass){
  await page.goto('http://localhost:8000/tedarik', {waitUntil:'networkidle'});
  await page.waitForSelector('#email', {timeout:10000});
  await page.fill('#email', email);
  await page.fill('#password', pass);
  await page.evaluate(()=>{
    const el=document.querySelector('[name="g-recaptcha-response"]');
    if(el) el.value='test';
    const ta=document.querySelector('textarea[name="g-recaptcha-response"]');
    if(ta) ta.value='test';
  });
  await page.click('#submit-button');
  await page.waitForTimeout(1500);
  // check for sms
  const smsVisible = await page.locator('input[name^="code_"]').first().isVisible().catch(()=>false);
  console.log(`[login ${email}] smsVisible=${smsVisible} url=${page.url()}`);
  if(smsVisible){
    for(let i=1;i<=6;i++){
      const sel=`input[name="code_${i}"]`;
      if(await page.locator(sel).count()) await page.fill(sel,'1');
    }
    const btn = page.locator('button:has-text("Doğrula"), button:has-text("Onayla"), button[type="submit"]');
    if(await btn.first().count()) await btn.first().click();
    else await page.keyboard.press('Enter');
    await page.waitForTimeout(2000);
    await page.waitForLoadState('networkidle').catch(()=>{});
    console.log(`[login ${email}] after sms url=${page.url()}`);
    // handle module-select if present
    if(page.url().includes('/module-select')){
      const plan = page.locator('a:has-text("Malzeme Planlama")').first();
      if(await plan.count()) await plan.click();
      else await page.goto('http://localhost:8000/planlamapanel');
      await page.waitForLoadState('networkidle');
    }
    if(page.url().includes('/tedarik') && !page.url().includes('planlama')){
      await page.goto('http://localhost:8000/planlamapanel');
      await page.waitForLoadState('networkidle');
    }
  }
  console.log(`[login ${email}] final url=${page.url()}`);
  await page.screenshot({path:`/tmp/login-${email.replace(/[@.]/g,'-')}.png`, fullPage:true}).catch(()=>{});
}

(async()=>{
  const browser = await chromium.launch({headless:true});
  const context = await browser.newContext({viewport:{width:1360,height:900}});
  const page = await context.newPage();
  page.on('console', msg=> console.log(`[browser console] ${msg.text()}`));
  try{
    console.log('=== STEP 1 kadir create ihtiyac ===');
    await login(page, 'redv@kontent.com.tr','R3dV!Kontent-2026');
    await page.goto('http://localhost:8000/planlamapanel/talepler'); await page.waitForLoadState('networkidle');
    await page.waitForSelector('#div_table',{timeout:10000}).catch(()=>{});
    await page.screenshot({path:'/tmp/step1-list.png', fullPage:true});
    console.log('list loaded url', page.url());
    // click create
    const btn = page.locator('a:has-text("İhtiyaç Oluştur")').first();
    if(await btn.count()){
      await btn.click();
      await page.waitForLoadState('networkidle');
      await page.waitForSelector('.area-target',{timeout:15000});
      const title=`E2E Browser ${Date.now()}`;
      console.log(`[ihtiyac] title=${title}`);
      await page.fill('.area-target[data-tag="op-doc-request-form"] input[name="title"]', title);
      await page.evaluate(()=>{ const a=document.querySelector('.area-target[data-tag="op-doc-request-form"] input[name="donem_bas"]'); if(a){ a.removeAttribute('readonly'); a.value='01/2026'; a.dispatchEvent(new Event('input',{bubbles:true})); a.dispatchEvent(new Event('change',{bubbles:true})); } });
      await page.evaluate(()=>{ const b=document.querySelector('.area-target[data-tag="op-doc-request-form"] input[name="donem_bit"]'); if(b){ b.removeAttribute('readonly'); b.value='03/2026'; b.dispatchEvent(new Event('input',{bubbles:true})); b.dispatchEvent(new Event('change',{bubbles:true})); } });
      const desc = page.locator('.area-target[data-tag="op-doc-request-form"] textarea[name="desc"]');
      if(await desc.count()) await desc.fill('browser e2e');
      else await page.fill('.area-target[data-tag="op-doc-request-form"] input[name="desc"]','browser e2e');
      await page.waitForTimeout(500);
      // fill first item
      const itemRows = page.locator('.area-target[data-tag="op-doc-request-item-form"] .multiple-item-row');
      let c = await itemRows.count(); console.log(`[ihtiyac] initial rows=${c}`);
      if(c===0){
        await page.locator('i[id*="request_items-subadd"]').first().click(); await page.waitForTimeout(600);
      }
      const row0 = page.locator('.area-target[data-tag="op-doc-request-item-form"] .multiple-item-row').first();
      await row0.locator('input[name^="prod_code"]').fill('B-E2E-001');
      await row0.locator('input[name^="title"]').fill('Browser Mat 1');
      const sel0 = row0.locator('select[name^="unit"]'); if(await sel0.count()) await sel0.selectOption('ST');
      await page.locator('i[id*="request_items-subadd"]').first().click(); await page.waitForTimeout(600);
      const row1 = page.locator('.area-target[data-tag="op-doc-request-item-form"] .multiple-item-row').nth(1);
      await row1.locator('input[name^="prod_code"]').fill('B-E2E-002');
      await row1.locator('input[name^="title"]').fill('Browser Mat 2');
      const sel1 = row1.locator('select[name^="unit"]'); if(await sel1.count()) await sel1.selectOption('KG');
      await page.waitForTimeout(400);
      await page.locator('button:has-text("Kaydet")').first().click();
      await page.waitForTimeout(2500);
      const swal = page.locator('.swal2-confirm');
      if(await swal.count()) await swal.click().catch(()=>{});
      await page.waitForLoadState('networkidle'); await page.waitForTimeout(1000);
      await page.screenshot({path:'/tmp/step1-after-save.png', fullPage:true});
      console.log('[ihtiyac] saved');
      // get qnid via API
      const qnid = await page.evaluate(async (t)=>{
        const fd=new FormData(); fd.append('tableReq', JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-request-form'},{key:'type',type:'=',value:'op-doc-request'}],scale:{page:1,limit:10}}));
        const r=await fetch('/api/v1/table/documents',{method:'POST',body:fd}); const j=await r.json(); const rows=j.data?.data||j.data||[]; for(const row of rows){ try{const a=JSON.parse(row.main_attr||'[]'); const ttl=a.find(x=>x.Key==='title')?.Value; if(ttl===t) return row.id;}catch(e){}} return rows[0]?.id||null;
      }, title);
      console.log(`[ihtiyac] qnid=${qnid}`);
      if(!qnid) throw new Error('ihtiyac not found');
      // Store for next step
      await page.evaluate((q)=> localStorage.setItem('e2e_ihtiyac', q), qnid);
    } else {
      console.log('create btn not found');
      await page.screenshot({path:'/tmp/step1-no-btn.png', fullPage:true});
    }

    console.log('=== STEP 2 kbbozat create talep ===');
    await page.goto('http://localhost:8000/logout'); await page.waitForLoadState('networkidle');
    await login(page, 'kbbozat41@hotmail.com','Kadir412.');
    const ihtiyancQnid = await page.evaluate(()=> localStorage.getItem('e2e_ihtiyac'));
    console.log(`[talep] ihtiyanc=${ihtiyancQnid}`);
    await page.goto(`http://localhost:8000/planlamapanel/talep-dokumanlari/form?from=${ihtiyancQnid}`);
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('.area-target[data-tag="op-doc-requestf-form"]',{timeout:15000});
    await page.waitForTimeout(2500);
    let rows = await page.locator('.area-target[data-tag="op-doc-requestf-item-form"] .multiple-item-row').count();
    console.log(`[talep] expanded rows=${rows} (expected 6)`);
    await page.screenshot({path:'/tmp/step2-form.png', fullPage:true});
    // select mudur (first system client) — fixes empty picker guard
    const mudurSel = page.locator('select.planlama-mudur__select, .tedarik-step-body select').first();
    if(await mudurSel.count()){
      const opts = await mudurSel.locator('option').all();
      console.log(`[talep] mudur options total=${opts.length}`);
      // pick second option (first is Seçiniz)
      const val = await mudurSel.locator('option').nth(1).getAttribute('value').catch(()=>null);
      if(val) { await mudurSel.selectOption(val); console.log(`[talep] selected mudur=${val}`); await page.waitForTimeout(500); }
    }
    // fill qty
    const qtyRows = page.locator('.area-target[data-tag="op-doc-requestf-item-form"] .multiple-item-row');
    const n = await qtyRows.count();
    console.log(`[talep] before qty canSave debug`);
    const canSaveDbg = await page.evaluate(()=>{
      // try to read Vue
      const el=document.querySelector('.planlama-rform, .tedarik-detail');
      return { url: location.href, perms: (window.__PINIA__||{}), html: document.documentElement.outerHTML.slice(0,500) };
    }); console.log(JSON.stringify(canSaveDbg).slice(0,400));
    for(let i=0;i<n;i++){
      await page.evaluate((idx)=>{
        const rows=document.querySelectorAll('.area-target[data-tag="op-doc-requestf-item-form"] .multiple-item-row');
        const inp=rows[idx]?.querySelector('input[name^="quantity"]');
        if(inp){ inp.removeAttribute('readonly'); inp.removeAttribute('disabled'); inp.value='7'; inp.dispatchEvent(new Event('input',{bubbles:true})); inp.dispatchEvent(new Event('change',{bubbles:true})); }
      }, i);
    }
    await page.waitForTimeout(400);
    const [resp] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/v1/document') && r.request().method()==='POST', {timeout:10000}).catch(()=>null),
      page.locator('button:has-text("Kaydet")').first().click()
    ]);
    if(resp){ try{ const j=await resp.json(); console.log(`[talep] save resp status=${resp.status()} body=${JSON.stringify(j).slice(0,300)}`);}catch(e){ console.log(`[talep] save resp status=${resp?.status()}`);} }
    await page.waitForTimeout(2500);
    if(await page.locator('.swal2-confirm').count()) await page.locator('.swal2-confirm').click().catch(()=>{});
    await page.waitForLoadState('networkidle'); await page.waitForTimeout(1000);
    await page.screenshot({path:'/tmp/step2-after.png', fullPage:true});
    const talepQnid = await page.evaluate(async ()=>{
      const fd=new FormData(); fd.append('tableReq', JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-requestf-form'},{key:'type',type:'=',value:'op-doc-requestf'}],scale:{page:1,limit:5}}));
      const r=await fetch('/api/v1/table/documents',{method:'POST',body:fd}); const j=await r.json(); const rows=j.data?.data||j.data||[]; return rows[0]?.id||null;
    });
    console.log(`[talep] new qnid=${talepQnid} rows=${n}`);
    await page.evaluate((q)=> localStorage.setItem('e2e_talep', q), talepQnid);
    // guard check: try edit awaiting (should fail)
    const editBlocked = await page.evaluate(async (qnid)=>{
      const r=await fetch(`/api/v1/document/${qnid}`); const j=await r.json(); const conn=Object.keys(j.data?.formFormat?.['op-doc-requestf-form']||{})[0]; const fd=new FormData(); fd.append('data', JSON.stringify({typeKey:'op-doc-requestf', dynamicF:{[`op-doc-requestf-form**${conn}`]:{tag:'op-doc-requestf-form',entities:{title:'HACKED'}}}})); const res=await fetch(`/api/v1/document/${qnid}`,{method:'PUT', body:fd}); const jr=await res.json().catch(()=>({})); return jr.success;
    }, talepQnid);
    console.log(`[guard] edit awaiting blocked? ${!editBlocked} (success=${editBlocked})`);

    console.log('=== STEP 3 pickle approve ===');
    await page.goto('http://localhost:8000/logout'); await page.waitForLoadState('networkidle');
    await login(page, 'picklecanme@gmail.com','Kadir412.');
    const t2 = await page.evaluate(()=> localStorage.getItem('e2e_talep'));
    const appr = await page.evaluate(async (qnid)=>{ const fd=new FormData(); fd.append('id',qnid); fd.append('op_key','tr-request-man-approved'); const r=await fetch('/api/v1/trans/set-status',{method:'POST',body:fd}); return await r.json().catch(()=>({})); }, t2);
    console.log(`[approve] man-approved success=${appr.success} ${JSON.stringify(appr).slice(0,120)}`);
    await page.goto('http://localhost:8000/planlamapanel/talep-dokumanlari'); await page.waitForSelector('#div_table',{timeout:8000}); await page.waitForTimeout(1200);
    await page.screenshot({path:'/tmp/step3-list.png', fullPage:true});

    console.log('=== STEP 4 kadir std approve ===');
    await page.goto('http://localhost:8000/logout'); await page.waitForLoadState('networkidle');
    await login(page, 'redv@kontent.com.tr','R3dV!Kontent-2026');
    const std = await page.evaluate(async (qnid)=>{ const fd=new FormData(); fd.append('id',qnid); fd.append('op_key','tr-request-std-approved'); const r=await fetch('/api/v1/trans/set-status',{method:'POST',body:fd}); return await r.json().catch(()=>({})); }, t2);
    console.log(`[std] success=${std.success}`);
    await page.goto('http://localhost:8000/planlamapanel/talep-dokumanlari'); await page.waitForTimeout(1200);
    await page.screenshot({path:'/tmp/step4-std.png', fullPage:true});

    console.log('=== STEP 5 rejected requeue ===');
    // create new ihtiyanc+talep for rejected flow via API quickly
    const title2=`E2E Rej Browser ${Date.now()}`;
    const ihtiyanc2 = await page.evaluate(async (t)=>{
      const fd=new FormData(); fd.append('data', JSON.stringify({typeKey:'op-doc-request', dynamicF:{'op-doc-request-form**new-1':{tag:'op-doc-request-form',entities:{title:t, donem_bas:'04/2026', donem_bit:'04/2026', desc:'rej'}}, 'op-doc-request-item-form**new-2':{tag:'op-doc-request-item-form',entities:{mal_grubu:'T',ihale_grubu:'T',prod_code:'REJ-B-001',title:'Rej Browser Mat',unit:'ST',quantity:'0'}}}})); const r=await fetch('/api/v1/document',{method:'POST',body:fd}); const j=await r.json().catch(()=>({})); return j.data?.qnid||null;
    }, title2);
    console.log(`[rej] ihtiyanc2=${ihtiyanc2}`);
    await page.goto('http://localhost:8000/logout'); await page.waitForLoadState('networkidle');
    await login(page, 'kbbozat41@hotmail.com','Kadir412.');
    const mudurQ2 = await page.evaluate(async ()=>{
      const fd=new FormData(); fd.append('tableReq', JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-client-form'},{key:'type',type:'=',value:'op-doc-client'},{key:'system_only',type:'=',value:'1'}],scale:{page:1,limit:1}})); const r=await fetch('/api/v1/table/documents',{method:'POST',body:fd}); const j=await r.json(); return j.data?.data?.[0]?.id||null;
    });
    const talep2 = await page.evaluate(async ({t,mq})=>{
      const fd=new FormData(); fd.append('data', JSON.stringify({typeKey:'op-doc-requestf', dynamicF:{'op-doc-requestf-form**new-10':{tag:'op-doc-requestf-form',entities:{title:'Rej Browser Talep', request_no:t, desc:'rej', firma_adi:'GDZ-90000001 Planlama', mudur_qnid:mq}}, 'op-doc-requestf-item-form**new-20':{tag:'op-doc-requestf-item-form',entities:{mal_grubu:'T',ihale_grubu:'T',prod_code:'REJ-B-001',title:'Rej Browser Mat',unit:'ST', ay:'04/2026', quantity:'9'}}}})); const r=await fetch('/api/v1/document',{method:'POST',body:fd}); const j=await r.json().catch(()=>({})); return j.data?.qnid||null;
    }, {t:title2, mq:mudurQ2});
    console.log(`[rej] talep2=${talep2}`);
    await page.goto('http://localhost:8000/logout'); await page.waitForLoadState('networkidle');
    await login(page, 'picklecanme@gmail.com','Kadir412.');
    const rej = await page.evaluate(async (qnid)=>{ const fd=new FormData(); fd.append('id',qnid); fd.append('op_key','tr-request-man-rejected'); fd.append('note','Browser e2e red'); const r=await fetch('/api/v1/trans/set-status',{method:'POST',body:fd}); return await r.json().catch(()=>({})); }, talep2);
    console.log(`[rej] rejected success=${rej.success}`);
    await page.goto('http://localhost:8000/logout'); await page.waitForLoadState('networkidle');
    await login(page, 'kbbozat41@hotmail.com','Kadir412.');
    const editRej = await page.evaluate(async (qnid)=>{
      const r=await fetch(`/api/v1/document/${qnid}`); const j=await r.json(); const conn=Object.keys(j.data?.formFormat?.['op-doc-requestf-form']||{})[0]; const fd=new FormData(); fd.append('data', JSON.stringify({typeKey:'op-doc-requestf', dynamicF:{[`op-doc-requestf-form**${conn}`]:{tag:'op-doc-requestf-form',entities:{title:'Rej REV'}}}})); const res=await fetch(`/api/v1/document/${qnid}`,{method:'PUT',body:fd}); return await res.json().catch(()=>({}));
    }, talep2);
    console.log(`[rej] edit rejected success=${editRej.success}`);
    const requeue = await page.evaluate(async (qnid)=>{ const fd=new FormData(); fd.append('id',qnid); fd.append('op_key','tr-request-man-awaiting'); const r=await fetch('/api/v1/trans/set-status',{method:'POST',body:fd}); return await r.json().catch(()=>({})); }, talep2);
    console.log(`[rej] requeue success=${requeue.success}`);

    // final nav smoke
    await page.goto('http://localhost:8000/planlamapanel/bilgilendirmeler'); await page.waitForLoadState('networkidle'); await page.waitForTimeout(1000);
    await page.screenshot({path:'/tmp/step-final.png', fullPage:true});
    console.log('=== BROWSER PIPELINE DONE ===');
  } catch(e){
    console.error('BROWSER PIPELINE FAILED', e);
    await page.screenshot({path:'/tmp/failed.png', fullPage:true}).catch(()=>{});
    throw e;
  } finally {
    await browser.close();
  }
})();
