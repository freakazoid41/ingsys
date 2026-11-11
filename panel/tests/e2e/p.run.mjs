import { chromium } from 'playwright';
const BASE='http://localhost:8000';
async function login(page,email){
  await page.goto(`${BASE}/tedarik`,{waitUntil:'networkidle'});await page.waitForSelector('#email',{timeout:15000});
  await page.fill('#email',email);await page.fill('#password',email==='redv@kontent.com.tr'?'R3dV!Kontent-2026':'Kadir412.');
  await page.evaluate(()=>{const el=document.querySelector('[name="g-recaptcha-response"]');if(el)el.value='test';});
  await page.click('#submit-button');await page.waitForTimeout(1800);
  for(let i=1;i<=6;i++){const s=`input[name="code_${i}"]`;if(await page.locator(s).count())await page.fill(s,'1');}
  const b=page.locator('button:has-text("Kodu Onayla"),button[type="submit"]').first();
  if(await b.count())await b.first().click();else await page.keyboard.press('Enter');
  await page.waitForTimeout(2500);await page.waitForLoadState('networkidle').catch(()=>{});
  if(page.url().includes('/module-select')){const m=page.locator('a:has-text("Yönetim")').first();if(await m.count()){await m.first().click();await page.waitForLoadState('networkidle').catch(()=>{});}}
}
const browser=await chromium.launch({headless:true});
const ctx=await browser.newContext({viewport:{width:1360,height:900}});
const page=await ctx.newPage();
try{
  console.log('=== admin: category ===');
  await login(page,'redv@kontent.com.tr');
  await page.goto(`${BASE}/coalpanel/product-categories/form`,{waitUntil:'networkidle'});
  await page.waitForSelector('.area-target',{timeout:15000});
  await page.fill('.area-target[data-tag="op-doc-product-category-form"] input[name="title"]','E2E Kategori');
  await page.fill('.area-target[data-tag="op-doc-product-category-form"] input[name="code"]','E2EKAT');
  await page.locator('button:has-text("Kaydet")').first().click();
  await page.waitForTimeout(3000);
  if(await page.locator('.swal2-popup').isVisible().catch(()=>false)){await page.locator('.swal2-confirm').first().click().catch(()=>{});await page.waitForTimeout(1500);}
  console.log('cat saved:',page.url());
  console.log('=== admin: product with modal picker ===');
  await page.goto(`${BASE}/coalpanel/products/form`,{waitUntil:'networkidle'});
  await page.waitForSelector('.area-target',{timeout:15000});
  await page.fill('.area-target[data-tag="op-doc-product-form"] input[name="prod_code"]','E2E-P001');
  await page.fill('.area-target[data-tag="op-doc-product-form"] input[name="short_name"]','E2E Test Ürünü');
  await page.locator('.area-target[data-tag="op-doc-product-form"] select[name^="unit"]').selectOption('KG');
  await page.locator('button:has-text("Kategori Seç")').first().click();
  await page.waitForSelector('#pc_cat_table',{timeout:15000});
  await page.waitForTimeout(2500);
  await page.screenshot({path:'/tmp/p-catmodal.png'});
  const rows=await page.locator('#pc_cat_table tbody tr').count().catch(()=>0);
  console.log('modal rows:',rows);
  await page.fill('input[placeholder*="Başlık veya kod"]','E2EKAT');
  await page.locator('button:has-text("Ara")').last().click().catch(()=>{});
  await page.waitForTimeout(2500);
  const pickBtn=page.locator('#pc_cat_table button:has-text("Seç")').first();
  if(!(await pickBtn.count())) throw new Error('category not found in picker');
  await pickBtn.click();
  await page.waitForTimeout(800);
  console.log('picked, banner shows selection');
  await page.screenshot({path:'/tmp/p-picked.png'});
  await page.locator('button:has-text("Kaydet")').first().click();
  await page.waitForTimeout(3000);
  if(await page.locator('.swal2-popup').isVisible().catch(()=>false)){console.log('swal:',(await page.locator('.swal2-popup').innerText()).slice(0,150));await page.locator('.swal2-confirm').first().click().catch(()=>{});await page.waitForTimeout(1500);}
  console.log('after save:',page.url());
  const api=await page.evaluate(async()=>{
    const fd=new FormData();fd.append('tableReq',JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-product-form'},{key:'type',type:'=',value:'op-doc-product'}],scale:{page:1,limit:10}}));
    const r=await fetch('/api/v1/table/documents',{method:'POST',body:fd});const j=await r.json();
    const rows=j.data?.data||j.data||[];
    return rows.map(x=>{try{const a=JSON.parse(x.main_attr||'[]');const g=k=>(a.find(y=>y.Key===k)||{}).Value;return {code:g('prod_code'),name:g('short_name'),unit:g('unit'),cat:g('category_title')};}catch(e){return{};}});
  });
  console.log('PRODUCTS:',JSON.stringify(api));
  if(!api.some(x=>x.code==='E2E-P001'&&x.cat==='E2E Kategori'))throw new Error('product+category not saved');
  console.log('PASS product create+category link');
  console.log('=== reseller 403 ===');
  await page.goto(`${BASE}/logout`,{waitUntil:'networkidle'}).catch(()=>{});
  await ctx.clearCookies();
  await login(page,'kbbozat41@hotmail.com');
  const forb=await page.evaluate(async()=>{
    const env=new FormData();env.append('data',JSON.stringify({typeKey:'op-doc-product',dynamicF:{}}));
    const r=await fetch('/api/v1/document',{method:'POST',body:env});return {status:r.status};
  });
  console.log('kbbozat POST:',JSON.stringify(forb));
  if(forb.status!==403)throw new Error('expected 403, got '+forb.status);
  console.log('E2E PRODUCTS PASS');
}catch(e){console.error('FAIL:',e.message);try{await page.screenshot({path:'/tmp/p-FAIL.png',fullPage:true});}catch(x){}process.exitCode=1;}
finally{await browser.close();}
