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
  await page.goto(`${BASE}/coalpanel/users`,{waitUntil:'networkidle'});
  await page.waitForSelector('#div_table tbody tr',{timeout:20000});
  await page.waitForTimeout(1000);
  console.log('rows:', await page.locator('#div_table tbody tr').count());
  console.log('status chips:', await page.locator('#div_table .kv-chip--active, #div_table .kv-chip--passive, #div_table .kv-chip--pending').count());
  console.log('warn chips:', await page.locator('#div_table .kv-chip--warn').count());
  console.log('name cells:', await page.locator('#div_table .kv-cell-title').count());
  console.log('type chips:', await page.locator('#div_table .kv-chip--type').count());
  console.log('role chips:', await page.locator('#div_table .kv-chip--role').count());
  console.log('system chips:', await page.locator('#div_table .kv-chip--gdz, #div_table .kv-chip--adm, #div_table .kv-chip--both').count());
  console.log('usernames:', await page.locator('#div_table .kv-username').count());
  console.log('action btns:', await page.locator('#div_table .kv-act').count());
  console.log('btn size:', await page.locator('#div_table .kv-act').first().evaluate(el=>{const r=el.getBoundingClientRect();return r.width+'x'+r.height;}));
  console.log('sample:', (await page.locator('#div_table tbody tr').first().innerText()).replace(/\n/g,' | '));
  await page.screenshot({path:'/tmp/users-rows-new.png'});
} finally { await browser.close(); }
