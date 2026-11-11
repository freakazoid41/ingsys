import { chromium } from 'playwright';
async function login(page, email, pass){
  await page.goto('http://localhost:8000/tedarik', {waitUntil:'networkidle'});
  await page.waitForSelector('#email', {timeout:10000});
  await page.fill('#email', email);
  await page.fill('#password', pass);
  await page.evaluate(()=>{ const el=document.querySelector('[name="g-recaptcha-response"]'); if(el) el.value='test'; });
  await page.click('#submit-button');
  await page.waitForTimeout(1500);
  const smsVisible = await page.locator('input[name^="code_"]').first().isVisible().catch(()=>false);
  console.log(`[login ${email}] smsVisible=${smsVisible}`);
  if(smsVisible){
    for(let i=1;i<=6;i++){ const sel=`input[name="code_${i}"]`; if(await page.locator(sel).count()) await page.fill(sel,'1'); }
    const btn = page.locator('button:has-text("Doğrula"), button:has-text("Onayla"), button[type="submit"]');
    if(await btn.first().count()) await btn.first().click(); else await page.keyboard.press('Enter');
    await page.waitForTimeout(2000);
    await page.waitForLoadState('networkidle').catch(()=>{});
    if(page.url().includes('/module-select')){
      const plan = page.locator('a:has-text("Malzeme Planlama")').first();
      if(await plan.count()) await plan.click(); else await page.goto('http://localhost:8000/planlamapanel');
      await page.waitForLoadState('networkidle');
    }
    if(page.url().includes('/tedarik') && !page.url().includes('planlama')){
      await page.goto('http://localhost:8000/planlamapanel'); await page.waitForLoadState('networkidle');
    }
  }
  console.log(`[login ${email}] final ${page.url()}`);
}
(async()=>{
  const browser = await chromium.launch({headless:false, slowMo:400});
  const context = await browser.newContext({viewport:{width:1360,height:900}});
  const page = await context.newPage();
  page.on('console', m=> console.log(`[console] ${m.text()}`));
  try{
    console.log('=== VISIBLE RUN: kadir → kbbozat → pickle → kadir ===');
    await login(page, 'redv@kontent.com.tr','R3dV!Kontent-2026');
    await page.goto('http://localhost:8000/planlamapanel/talepler'); await page.waitForLoadState('networkidle');
    await page.waitForSelector('#div_table',{timeout:10000}); await page.waitForTimeout(1000);
    const title=`VISIBLE ${Date.now()}`;
    console.log(`[ihtiyac] ${title}`);
    await page.locator('a:has-text("İhtiyaç Oluştur")').first().click();
    await page.waitForLoadState('networkidle'); await page.waitForSelector('.area-target',{timeout:15000});
    await page.fill('.area-target[data-tag="op-doc-request-form"] input[name="title"]', title);
    await page.evaluate(()=>{ const a=document.querySelector('.area-target[data-tag="op-doc-request-form"] input[name="donem_bas"]'); if(a){a.removeAttribute('readonly');a.value='01/2026';a.dispatchEvent(new Event('input',{bubbles:true}));}});
    await page.evaluate(()=>{ const b=document.querySelector('.area-target[data-tag="op-doc-request-form"] input[name="donem_bit"]'); if(b){b.removeAttribute('readonly');b.value='03/2026';b.dispatchEvent(new Event('input',{bubbles:true}));}});
    const desc = page.locator('.area-target[data-tag="op-doc-request-form"] textarea[name="desc"]');
    if(await desc.count()) await desc.fill('visible test'); else await page.fill('.area-target[data-tag="op-doc-request-form"] input[name="desc"]','visible test');
    await page.waitForTimeout(500);
    let c = await page.locator('.area-target[data-tag="op-doc-request-item-form"] .multiple-item-row').count();
    if(c===0){ await page.locator('i[id*="request_items-subadd"]').first().click(); await page.waitForTimeout(600); }
    const r0=page.locator('.area-target[data-tag="op-doc-request-item-form"] .multiple-item-row').first();
    await r0.locator('input[name^="prod_code"]').fill('VIS-001');
    await r0.locator('input[name^="title"]').fill('Visible Mat 1');
    const s0=r0.locator('select[name^="unit"]'); if(await s0.count()) await s0.selectOption('ST');
    await page.locator('i[id*="request_items-subadd"]').first().click(); await page.waitForTimeout(600);
    const r1=page.locator('.area-target[data-tag="op-doc-request-item-form"] .multiple-item-row').nth(1);
    await r1.locator('input[name^="prod_code"]').fill('VIS-002');
    await r1.locator('input[name^="title"]').fill('Visible Mat 2');
    const s1=r1.locator('select[name^="unit"]'); if(await s1.count()) await s1.selectOption('KG');
    await page.waitForTimeout(1200);
    console.log('[ihtiyac] saving... watch the form');
    await new Promise(r=>setTimeout(r,2000));
    const [resp] = await Promise.all([page.waitForResponse(r=>r.url().includes('/api/v1/document')&&r.request().method()==='POST',{timeout:10000}).catch(()=>null), page.locator('button:has-text("Kaydet")').first().click()]);
    if(resp){ console.log(`[ihtiyac] save ${resp.status()} ${JSON.stringify(await resp.json().catch(()=>({})).then(j=>j.data?.qnid||j.qnid||'')).slice(0,80)}`); }
    await page.waitForTimeout(2500); if(await page.locator('.swal2-confirm').count()) await page.locator('.swal2-confirm').click().catch(()=>{});
    await page.waitForLoadState('networkidle'); await page.waitForTimeout(1500);
    const qnid = await page.evaluate(async (t)=>{
      const fd=new FormData(); fd.append('tableReq', JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-request-form'},{key:'type',type:'=',value:'op-doc-request'}],scale:{page:1,limit:10}}));
      const r=await fetch('/api/v1/table/documents',{method:'POST',body:fd}); const j=await r.json(); const rows=j.data?.data||j.data||[]; for(const row of rows){ try{const a=JSON.parse(row.main_attr||'[]'); if(a.find(x=>x.Key==='title')?.Value===t) return row.id;}catch(e){}} return rows[0]?.id||null;
    }, title);
    console.log(`[ihtiyac] qnid=${qnid} - KEEP VISIBLE 3s`);
    await page.goto('http://localhost:8000/planlamapanel/talepler'); await page.waitForTimeout(2000);
    // kbbozat
    await page.goto('http://localhost:8000/logout'); await page.waitForLoadState('networkidle');
    await login(page, 'kbbozat41@hotmail.com','Kadir412.');
    console.log(`[talep] from ${qnid} - watch expansion`);
    await page.goto(`http://localhost:8000/planlamapanel/talep-dokumanlari/form?from=${qnid}`);
    await page.waitForLoadState('networkidle'); await page.waitForSelector('.area-target[data-tag="op-doc-requestf-form"]',{timeout:15000});
    await page.waitForTimeout(2500);
    let rows = await page.locator('.area-target[data-tag="op-doc-requestf-item-form"] .multiple-item-row').count();
    console.log(`[talep] rows=${rows} (6 expected) - WATCH`);
    await page.waitForTimeout(2000);
    const mudurSel = page.locator('select.planlama-mudur__select, .tedarik-step-body select').first();
    if(await mudurSel.count()){
      const val = await mudurSel.locator('option').nth(1).getAttribute('value').catch(()=>null);
      if(val){ await mudurSel.selectOption(val); console.log(`[talep] mudur ${val}`); await page.waitForTimeout(1000); }
    }
    const qtyRows = page.locator('.area-target[data-tag="op-doc-requestf-item-form"] .multiple-item-row');
    const n = await qtyRows.count();
    for(let i=0;i<n;i++) await page.evaluate((idx)=>{ const rows=document.querySelectorAll('.area-target[data-tag="op-doc-requestf-item-form"] .multiple-item-row'); const inp=rows[idx]?.querySelector('input[name^="quantity"]'); if(inp){inp.removeAttribute('readonly');inp.removeAttribute('disabled');inp.value='7';inp.dispatchEvent(new Event('input',{bubbles:true}));}}, i);
    console.log(`[talep] qty filled ${n} - saving... WATCH`);
    await new Promise(r=>setTimeout(r,2000));
    const [resp2] = await Promise.all([page.waitForResponse(r=>r.url().includes('/api/v1/document')&&r.request().method()==='POST',{timeout:10000}).catch(()=>null), page.locator('button:has-text("Kaydet")').first().click()]);
    if(resp2) console.log(`[talep] save ${resp2.status()}`);
    await page.waitForTimeout(3000); if(await page.locator('.swal2-confirm').count()) await page.locator('.swal2-confirm').click().catch(()=>{});
    await page.waitForLoadState('networkidle'); await page.waitForTimeout(2000);
    const talepQnid = await page.evaluate(async ()=>{ const fd=new FormData(); fd.append('tableReq', JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-requestf-form'},{key:'type',type:'=',value:'op-doc-requestf'}],scale:{page:1,limit:5}})); const r=await fetch('/api/v1/table/documents',{method:'POST',body:fd}); const j=await r.json(); return j.data?.data?.[0]?.id||null; });
    console.log(`[talep] qnid=${talepQnid} - WATCH TABLE`);
    await page.goto('http://localhost:8000/planlamapanel/talep-dokumanlari'); await page.waitForSelector('#div_table',{timeout:8000}); await page.waitForTimeout(3000);
    // pickle approve
    await page.goto('http://localhost:8000/logout'); await page.waitForLoadState('networkidle');
    await login(page, 'picklecanme@gmail.com','Kadir412.');
    console.log(`[approve] as pickle - WATCH`);
    await page.goto('http://localhost:8000/planlamapanel/talep-dokumanlari'); await page.waitForTimeout(2000);
    const appr = await page.evaluate(async (qnid)=>{ const fd=new FormData(); fd.append('id',qnid); fd.append('op_key','tr-request-man-approved'); const r=await fetch('/api/v1/trans/set-status',{method:'POST',body:fd}); return await r.json().catch(()=>({})); }, talepQnid);
    console.log(`[approve] ${JSON.stringify(appr).slice(0,100)}`);
    await page.waitForTimeout(3000);
    // kadir std
    await page.goto('http://localhost:8000/logout'); await page.waitForLoadState('networkidle');
    await login(page, 'redv@kontent.com.tr','R3dV!Kontent-2026');
    console.log(`[std] as kadir - WATCH`);
    const std = await page.evaluate(async (qnid)=>{ const fd=new FormData(); fd.append('id',qnid); fd.append('op_key','tr-request-std-approved'); const r=await fetch('/api/v1/trans/set-status',{method:'POST',body:fd}); return await r.json().catch(()=>({})); }, talepQnid);
    console.log(`[std] ${JSON.stringify(std).slice(0,100)}`);
    await page.goto('http://localhost:8000/planlamapanel/talep-dokumanlari'); await page.waitForTimeout(3000);
    console.log('=== VISIBLE DONE - LEAVING BROWSER OPEN 15s FOR YOU TO WATCH ===');
    await page.waitForTimeout(15000);
  } catch(e){ console.error(e); await page.screenshot({path:'/tmp/visible-fail.png',fullPage:true}).catch(()=>{}); throw e; } finally { await browser.close(); }
})();
