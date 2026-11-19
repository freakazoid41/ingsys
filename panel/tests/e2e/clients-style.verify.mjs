import { chromium } from 'playwright';
const BASE='http://localhost:8000';
const EXE='/Users/freakazoid41/Library/Caches/ms-playwright/chromium-1155/chrome-mac/Chromium.app/Contents/MacOS/Chromium';
const browser=await chromium.launch({headless:true, executablePath:EXE});
const page=await (await browser.newContext({viewport:{width:1440,height:950}})).newPage();
try{
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
  await page.goto(`${BASE}/coalpanel/client`,{waitUntil:'networkidle'});
  await page.waitForSelector('#div_table tbody tr',{timeout:20000});
  await page.waitForTimeout(1000);
  console.log('rows:', await page.locator('#div_table tbody tr').count());
  console.log('title cells:', await page.locator('#div_table .kv-cell-title').count());
  console.log('code pills:', await page.locator('#div_table .kv-pill-code').count());
  console.log('system chips:', await page.locator('#div_table .kv-chip').count(), '(adm:', await page.locator('#div_table .kv-chip--adm').count(), '/ gdz:', await page.locator('#div_table .kv-chip--gdz').count(), ')');
  console.log('cari pills:', await page.locator('#div_table .kv-pill-cari').count());
  console.log('btn size:', await page.locator('#div_table .action-icon-btn').first().evaluate(el=>{const r=el.getBoundingClientRect();return r.width+'x'+r.height;}));
  console.log('row padding:', await page.locator('#div_table tbody td').first().evaluate(el=>getComputedStyle(el).padding));
  console.log('sample:', (await page.locator('#div_table tbody tr').first().innerText()).replace(/\n/g,' | '));
  await page.screenshot({path:'/tmp/clients-rows-new.png'});
} finally { await browser.close(); }
