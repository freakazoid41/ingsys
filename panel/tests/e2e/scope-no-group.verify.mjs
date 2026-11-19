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
try{
  await login();
  await page.goto(`${BASE}/coalpanel/kva`,{waitUntil:'networkidle'});
  await page.waitForSelector('#div_table tbody tr',{timeout:20000});
  await page.locator('.kv-tab:has-text("Kapsam Tipleri")').click();
  await page.waitForSelector('#div_scope_table tbody tr',{timeout:15000});
  await page.waitForTimeout(800);
  const heads = await page.locator('#div_scope_table thead th').allTextContents();
  console.log('scope headers:', heads.map(h=>h.trim()).filter(Boolean).join(' | '));
  // form
  await page.goto(`${BASE}/coalpanel/kva/scope-form`,{waitUntil:'networkidle'});
  await page.waitForSelector('.area-target[data-tag="op-doc-scope-value-form"]',{timeout:15000});
  await page.waitForTimeout(800);
  const fields = await page.evaluate(()=>[...document.querySelectorAll('.area-target[data-tag="op-doc-scope-value-form"] .input-label')].map(l=>l.textContent.trim()).filter(Boolean));
  console.log('form fields:', fields.join(' | '));
  // category form tagbox labels
  await page.goto(`${BASE}/coalpanel/product-categories/form/87025202-9a8d-4cec-a9ec-33fe7d3d0ada`,{waitUntil:'networkidle'});
  await page.waitForSelector('.area-target[data-tag="op-doc-product-category-form"] .multiple-item-card',{timeout:20000});
  await page.waitForTimeout(1000);
  const pills = await page.evaluate(()=>{
    const cards=[...document.querySelectorAll('.area-target[data-tag="op-doc-product-category-form"] .multiple-item-card')];
    const card=cards[0];
    const lab=[...card.querySelectorAll('label')].find(l=>l.textContent.trim().startsWith('Geçerli Tipler'));
    const box=lab?.parentElement.querySelector('.tag-typeahead');
    return box?[...box.querySelectorAll('span[style*="border-radius: 999px"]')].map(p=>p.textContent.replace('×','').trim()):[];
  });
  console.log('pano type pills:', pills.join(' | '));
} finally { await browser.close(); }
