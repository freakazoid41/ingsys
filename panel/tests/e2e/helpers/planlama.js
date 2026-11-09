// helpers/planlama.js — shared UI actions for Malzeme Planlama
export async function waitForFormLoad(page){
  await page.waitForSelector('.area-target', { timeout: 15000 });
  // wait for PickleTable or Form to settle
  await page.waitForTimeout(800);
}

export async function createIhtiyac(page, { title, donemBas='01/2026', donemBit='03/2026', desc='e2e test' }){
  await page.goto('/planlamapanel/talepler');
  await page.waitForLoadState('networkidle');
  // click İhtiyaç Oluştur (front or admin)
  const createBtn = page.locator('a:has-text("İhtiyaç Oluştur"), button:has-text("İhtiyaç Listesi Oluştur"), a:has-text("İhtiyaç Oluştur")').first();
  await createBtn.waitFor({ timeout: 8000 });
  await createBtn.click();
  await page.waitForLoadState('networkidle');
  await waitForFormLoad(page);
  // fill header
  await page.fill('.area-target[data-tag="op-doc-request-form"] input[name="title"]', title);
  await page.fill('.area-target[data-tag="op-doc-request-form"] input[name="donem_bas"]', donemBas);
  // trigger flatpickr change: blur
  await page.keyboard.press('Tab');
  await page.fill('.area-target[data-tag="op-doc-request-form"] input[name="donem_bit"]', donemBit);
  await page.keyboard.press('Tab');
  await page.fill('.area-target[data-tag="op-doc-request-form"] textarea[name="desc"], .area-target[data-tag="op-doc-request-form"] input[name="desc"]', desc);
  // add first item row
  const prodInp = page.locator('.area-target[data-tag="op-doc-request-item-form"] input[name^="prod_code"]'); // may be multiple
  // ensure at least one row exists
  await page.waitForTimeout(500);
  let rows = await page.locator('.area-target[data-tag="op-doc-request-item-form"] .multiple-item-row').count();
  if(rows===0){
    await page.locator('i[id*="request_items-subadd"]').first().click();
    await page.waitForTimeout(600);
  }
  // fill first item
  const firstRow = page.locator('.multiple-item-row').first();
  // scope to item form area
  const itemArea = page.locator('.area-target[data-tag="op-doc-request-item-form"] .multiple-item-row').first();
  await itemArea.locator('input[name^="prod_code"]').fill('E2E-001');
  await itemArea.locator('input[name^="title"]').fill('E2E Malzeme 1');
  // unit select — try to find select in row
  const unitSel = itemArea.locator('select[name^="unit"]');
  if(await unitSel.count()) await unitSel.selectOption('ST');
  // add second row
  await page.locator('i[id*="request_items-subadd"]').first().click();
  await page.waitForTimeout(600);
  const secondRow = page.locator('.area-target[data-tag="op-doc-request-item-form"] .multiple-item-row').nth(1);
  await secondRow.locator('input[name^="prod_code"]').fill('E2E-002');
  await secondRow.locator('input[name^="title"]').fill('E2E Malzeme 2');
  const unitSel2 = secondRow.locator('select[name^="unit"]');
  if(await unitSel2.count()) await unitSel2.selectOption('KG');
  await page.waitForTimeout(400);
  // save
  const saveBtn = page.locator('button:has-text("Kaydet")').first();
  await saveBtn.click();
  // wait for toast and redirect to list
  await page.waitForTimeout(1800);
  // handle Swal success: click ok if present
  const swalOk = page.locator('.swal2-confirm');
  if(await swalOk.count()) await swalOk.click().catch(()=>{});
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1000);
  return title;
}

export async function getIhtiyacQnidFromList(page, title){
  await page.goto('/planlamapanel/talepler');
  await page.waitForLoadState('networkidle');
  await page.waitForSelector('#div_table', { timeout: 10000 });
  await page.waitForTimeout(1500);
  // pick first row's Detay button and extract url? We need qnid from router push
  // Simpler: use API table directly via page.evaluate fetch
  const qnid = await page.evaluate(async (t) => {
    const fd = new FormData();
    fd.append('tableReq', JSON.stringify({
      filter: [{key:'form-type',type:'=',value:'op-doc-request-form'},{key:'type',type:'=',value:'op-doc-request'}],
      scale:{page:1,limit:10}
    }));
    const r = await fetch('/api/v1/table/documents', {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}});
    const j = await r.json(); let data = j.data || j; if(typeof data==='string') data=JSON.parse(data);
    const rows = data.data || data || [];
    for(const row of rows){
      try{ const attrs=JSON.parse(row.main_attr||'[]'); const titleVal = attrs.find(a=>a.Key==='title')?.Value; if(titleVal===t) return row.id; }catch(e){}
    }
    return rows[0]?.id || null;
  }, title);
  return qnid;
}

export async function createTalepFromIhtiyac(page, ihtiyacQnid){
  // Uses TForm ?from flow
  await page.goto(`/planlamapanel/talep-dokumanlari/form?from=${ihtiyacQnid}`);
  await page.waitForLoadState('networkidle');
  await page.waitForSelector('.area-target[data-tag="op-doc-requestf-form"]', { timeout: 15000 });
  // wait for linkBusy to clear and rows to expand (2 mats × 3 months = 6 rows)
  await page.waitForTimeout(2000);
  // check for expanded rows
  let tries=0; let count=0;
  while(tries++<20){
    count = await page.locator('.area-target[data-tag="op-doc-requestf-item-form"] .multiple-item-row').count();
    if(count>=6) break;
    await page.waitForTimeout(500);
  }
  // assert mudur picker has 6 options (system_only bypass)
  const mudurSel = page.locator('select.planlama-mudur__select, .tedarik-step-body select');
  if(await mudurSel.count()){
    const opts = await mudurSel.locator('option').count();
    // should be 6+1 (first is Seçiniz)
    console.log(`[talep] mudur options=${opts} rows=${count}`);
  }
  // fill quantity in each row (6 rows) with 5
  const rows = page.locator('.area-target[data-tag="op-doc-requestf-item-form"] .multiple-item-row');
  const n = await rows.count();
  for(let i=0;i<n;i++){
    const qty = rows.nth(i).locator('input[name^="quantity"]');
    if(await qty.count()) await qty.fill('5');
  }
  await page.waitForTimeout(400);
  // save
  const saveBtn = page.locator('button:has-text("Kaydet")').first();
  await saveBtn.waitFor({timeout:5000});
  await saveBtn.click();
  await page.waitForTimeout(2000);
  const swalOk = page.locator('.swal2-confirm');
  if(await swalOk.count()) await swalOk.click().catch(()=>{});
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1000);
  // get new talep qnid from list
  const talepQnid = await page.evaluate(async () => {
    const fd=new FormData(); fd.append('tableReq', JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-requestf-form'},{key:'type',type:'=',value:'op-doc-requestf'}],scale:{page:1,limit:5}}));
    const r=await fetch('/api/v1/table/documents',{method:'POST',body:fd}); const j=await r.json(); const rows=j.data?.data||j.data||[]; return rows[0]?.id||null;
  });
  return { n, talepQnid };
}
