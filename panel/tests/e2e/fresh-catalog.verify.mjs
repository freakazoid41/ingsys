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
async function countDocs(formType,type){
  return page.evaluate(async({formType,type})=>{
    const fd=new FormData();
    fd.append('tableReq', JSON.stringify({ filter:[{key:'form-type',type:'=',value:formType},{key:'type',type:'=',value:type}], scale:{page:1,limit:200} }));
    const r=await fetch('/api/v1/table/documents',{method:'POST',credentials:'include',headers:{'X-Requested-With':'XMLHttpRequest',...(localStorage.getItem('token')?{Authorization:'Bearer '+localStorage.getItem('token')}:{})},body:fd});
    const j=await r.json(); return (j?.data?.data||j?.data||[]).length;
  },{formType,type});
}
try{
  await login();
  console.log('kVA:', await countDocs('op-doc-kva-form','op-doc-kva'));
  console.log('scope:', await countDocs('op-doc-scope-value-form','op-doc-scope-value'));
  console.log('categories:', await countDocs('op-doc-product-category-form','op-doc-product-category'));
  console.log('products:', await countDocs('op-doc-product-form','op-doc-product'));
  console.log('tenders:', await countDocs('op-doc-ihale-form','op-doc-ihale'));
  console.log('offers:', await countDocs('op-doc-ihale-offer-form','op-doc-ihale-offer'));
  await page.goto(`${BASE}/coalpanel/ihaleler`,{waitUntil:'networkidle'});
  await page.waitForSelector('#div_table tbody tr',{timeout:20000});
  await page.waitForTimeout(800);
  console.log('admin tender rows:', await page.locator('#div_table tbody tr').count(), '| icon buttons:', await page.locator('#div_table .ihl-act').count());
  await page.goto(`${BASE}/coalpanel/ihaleler/teklifler`,{waitUntil:'networkidle'});
  await page.waitForSelector('#div_table tbody tr',{timeout:20000});
  await page.waitForTimeout(800);
  console.log('admin offer rows:', await page.locator('#div_table tbody tr').count(), '| icon buttons:', await page.locator('#div_table .ihl-act').count());
} finally { await browser.close(); }
