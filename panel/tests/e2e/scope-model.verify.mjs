import { chromium } from 'playwright';
const BASE='http://localhost:8000';
const EXE='/Users/freakazoid41/Library/Caches/ms-playwright/chromium-1155/chrome-mac/Chromium.app/Contents/MacOS/Chromium';
const browser=await chromium.launch({headless:true, executablePath:EXE});
const page=await (await browser.newContext({viewport:{width:1440,height:950}})).newPage();
async function login(){
  await page.goto(`${BASE}/tedarik`,{waitUntil:'networkidle'});
  await page.waitForSelector('#email',{timeout:15000});
  await page.fill('#email','redv@kontent.com.tr'); await page.fill('#password','R3dV!Kontent-2026');
  await page.evaluate(()=>{const el=document.querySelector('[name="g-recaptcha-response"]');if(el)el.value='test';});
  await page.click('#submit-button'); await page.waitForTimeout(1800);
  for(let i=1;i<=6;i++){const s=`input[name="code_${i}"]`;if(await page.locator(s).count())await page.fill(s,'1');}
  const b=page.locator('button:has-text("Kodu Onayla"),button[type="submit"]').first();
  if(await b.count())await b.first().click();else await page.keyboard.press('Enter');
  await page.waitForTimeout(2500);
  if(page.url().includes('/module-select')){const m=page.locator('a:has-text("Yönetim")').first();if(await m.count()){await m.first().click();await page.waitForLoadState('networkidle').catch(()=>{});}}
}
async function countScopeDocs(){
  return page.evaluate(async()=>{
    const fd=new FormData();
    fd.append('tableReq', JSON.stringify({ filter:[{key:'form-type',type:'=',value:'op-doc-scope-value-form'},{key:'type',type:'=',value:'op-doc-scope-value'}], scale:{page:1,limit:200} }));
    const r=await fetch('/api/v1/table/documents',{method:'POST',credentials:'include',headers:{'X-Requested-With':'XMLHttpRequest',...(localStorage.getItem('token')?{Authorization:'Bearer '+localStorage.getItem('token')}:{})},body:fd});
    const j=await r.json(); return (j?.data?.data||j?.data||[]).length;
  });
}
async function pillsByLabel(page,label){
  return page.evaluate((label)=>{
    const out=[];
    document.querySelectorAll('.area-target[data-tag="op-doc-product-category-form"] .multiple-item-card').forEach(card=>{
      const lab=[...card.querySelectorAll('label')].find(l=>l.textContent.trim().startsWith(label));
      if(!lab) return;
      const box=lab.parentElement.querySelector('.tag-typeahead');
      out.push(box?[...box.querySelectorAll('span[style*="border-radius: 999px"]')].map(p=>p.textContent.replace('×','').trim()):[]);
    });
    return out;
  },label);
}
try{
  await login();
  await page.goto(`${BASE}/coalpanel/kva`,{waitUntil:'networkidle'});
  await page.waitForSelector('#div_table tbody tr',{timeout:20000});
  await page.waitForTimeout(900);
  console.log('kVA rows:', await page.locator('#div_table tbody tr').count());
  console.log('kVA first row:', (await page.locator('#div_table tbody tr').first().innerText()).replace(/\n/g,' | '));
  console.log('kVA pills:', await page.locator('#div_table .kv-pill-kva').allTextContents());
  await page.locator('.kv-tab:has-text("Kapsam Tipleri")').click();
  await page.waitForSelector('#div_scope_table tbody tr',{timeout:15000});
  await page.waitForTimeout(900);
  console.log('scope docs API:', await countScopeDocs());
  const combo = page.locator('#div_scope_table tbody tr', { hasText:'100 kVA Harici' });
  console.log('combo row exists:', await combo.count());

  const CATS = { 'TRF-33KV':'d0367b29-e6c0-48a5-b912-bb25ebc13270', 'AG-PANO':'dfcc1cb2-a75e-405a-bc18-0ed7e34c19ec' };
  for (const [code,qnid] of Object.entries(CATS)) {
    await page.goto(`${BASE}/coalpanel/product-categories/form/${qnid}`,{waitUntil:'networkidle'});
    await page.waitForSelector('.area-target[data-tag="op-doc-product-category-form"] .multiple-item-card',{timeout:20000});
    await page.waitForTimeout(1200);
    const kva = await pillsByLabel(page,'Geçerli Güçler');
    const types = await pillsByLabel(page,'Geçerli Tipler');
    console.log(`[${code}] row0 kva:`, (kva[0]||[]).join(' | ') || '—');
    console.log(`[${code}] row0 types:`, (types[0]||[]).join(' | ') || '—');
  }
  await page.screenshot({path:'/tmp/scope-model-final.png'});
} finally { await browser.close(); }
