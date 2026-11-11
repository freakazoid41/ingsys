import { chromium } from 'playwright';
const BASE='http://localhost:8000', PASS='Kadir412.';
async function login(page,email){
  await page.goto(`${BASE}/tedarik`,{waitUntil:'networkidle'});
  await page.waitForSelector('#email',{timeout:15000});
  await page.fill('#email',email); await page.fill('#password',PASS);
  await page.evaluate(()=>{const el=document.querySelector('[name="g-recaptcha-response"]');if(el)el.value='test';});
  await page.click('#submit-button'); await page.waitForTimeout(1800);
  for(let i=1;i<=6;i++){const s=`input[name="code_${i}"]`;if(await page.locator(s).count())await page.fill(s,'1');}
  const b=page.locator('button:has-text("Doğrula"),button:has-text("Onayla"),button[type="submit"]').first();
  if(await b.count())await b.first().click(); else await page.keyboard.press('Enter');
  await page.waitForTimeout(2500); await page.waitForLoadState('networkidle').catch(()=>{});
  if(page.url().includes('/module-select')){const m=page.locator('a:has-text("Yönetim")').first();if(await m.count()){await m.click();await page.waitForLoadState('networkidle').catch(()=>{});}}
}
(async()=>{
  const browser=await chromium.launch({headless:true});
  const ctx=await browser.newContext({viewport:{width:1360,height:900}});
  const page=await ctx.newPage();
  try{
    console.log('=== admin: open category list ===');
    await login(page,'kadir@kontent.com.tr');
    await page.goto(`${BASE}/coalpanel/product-categories`,{waitUntil:'networkidle'});
    await page.waitForSelector('#div_table',{timeout:15000}).catch(()=>{});
    await page.waitForTimeout(2000);
    await page.screenshot({path:'/tmp/pc-list-empty.png',fullPage:true});
    console.log('list url ok:',page.url());
    console.log('=== admin: create category ===');
    await page.locator('a:has-text("Kategori Oluştur")').first().click();
    await page.waitForSelector('.area-target',{timeout:15000});
    await page.fill('.area-target[data-tag="op-doc-product-category-form"] input[name="title"]','E2E Test Kategorisi');
    await page.waitForTimeout(300);
    await page.fill('.area-target[data-tag="op-doc-product-category-form"] input[name="code"]','E2E-001');
    await page.waitForTimeout(300);
    await page.screenshot({path:'/tmp/pc-form.png'});
    await page.locator('button:has-text("Kaydet")').first().click();
    await page.waitForTimeout(3000);
    const swal=await page.locator('.swal2-popup').isVisible().catch(()=>false);
    if(swal){console.log('swal:',(await page.locator('.swal2-popup').innerText()).slice(0,120));await page.locator('.swal2-confirm').first().click().catch(()=>{});await page.waitForTimeout(1500);}
    await page.screenshot({path:'/tmp/pc-after-save.png',fullPage:true});
    console.log('after save url:',page.url());
    const api=await page.evaluate(async()=>{
      const fd=new FormData();fd.append('tableReq',JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-product-category-form'},{key:'type',type:'=',value:'op-doc-product-category'}],scale:{page:1,limit:10}}));
      const r=await fetch('/api/v1/table/documents',{method:'POST',body:fd});const j=await r.json();
      const rows=j.data?.data||j.data||[];
      return rows.map(x=>{try{const a=JSON.parse(x.main_attr||'[]');const g=k=>(a.find(y=>y.Key===k)||{}).Value;return {title:g('title'),code:g('code'),qnid:x.id};}catch(e){return {qnid:x.id};}});
    });
    console.log('CATEGORIES:',JSON.stringify(api));
    if(!api.some(x=>x.code==='E2E-001'))throw new Error('created category not in list');
    console.log('PASS admin create+list');
    console.log('=== supplier without perm gets 403 ===');
    await page.goto(`${BASE}/logout`,{waitUntil:'networkidle'}).catch(()=>{});
    await ctx.clearCookies();
    await login(page,'kbbozat41@hotmail.com');
    const forb=await page.evaluate(async()=>{
      const env=new FormData();env.append('data',JSON.stringify({typeKey:'op-doc-product-category',dynamicF:{}}));
      const r=await fetch('/api/v1/document',{method:'POST',body:env});return {status:r.status,body:(await r.text()).slice(0,120)};
    });
    console.log('kbbozat POST:',JSON.stringify(forb));
    if(forb.status!==403)throw new Error('expected 403 for supplier, got '+forb.status);
    console.log('PASS 403 gate');
    console.log('E2E PRODUCT CATEGORY DONE');
  }catch(e){console.error('FAIL:',e.message);try{await page.screenshot({path:'/tmp/pc-FAIL.png',fullPage:true});}catch(x){}process.exitCode=1;}
  finally{await browser.close();}
})();
