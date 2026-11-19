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
  await page.waitForTimeout(2500); await page.waitForLoadState('networkidle').catch(()=>{});
  if(page.url().includes('/module-select')){const m=page.locator('a:has-text("Yönetim")').first();if(await m.count()){await m.first().click();await page.waitForLoadState('networkidle').catch(()=>{});}}
}
try{
  await login();
  await page.goto(`${BASE}/coalpanel/kva`,{waitUntil:'networkidle'});
  await page.waitForSelector('#div_table tbody tr',{timeout:15000});
  await page.waitForTimeout(800);
  console.log('kva pills:', await page.locator('#div_table .kv-pill-kva').count());
  console.log('kva dates:', await page.locator('#div_table .kv-date').count());
  console.log('action btn size:', await page.locator('#div_table .action-icon-btn').first().evaluate(el=>{const r=el.getBoundingClientRect();return r.width+'x'+r.height;}));
  await page.screenshot({path:'/tmp/kva-rows-new.png'});
  await page.locator('.kv-tab:has-text("Kapsam Tipleri")').click();
  await page.waitForSelector('#div_scope_table tbody tr',{timeout:15000});
  await page.waitForTimeout(800);
  console.log('scope code pills:', await page.locator('#div_scope_table .kv-pill-code').count());
  console.log('scope chips:', await page.locator('#div_scope_table .kv-chip').count());
  console.log('sidebar title:', (await page.locator('.sub-link:has-text("Kapsam")').first().textContent().catch(()=>'')) || 'n/a');
  await page.screenshot({path:'/tmp/scope-rows-new.png'});
} finally { await browser.close(); }
