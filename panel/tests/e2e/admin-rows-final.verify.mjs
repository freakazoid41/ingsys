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
async function audit(path,label){
  await page.goto(`${BASE}${path}`,{waitUntil:'networkidle'});
  await page.waitForSelector('#div_table tbody tr',{timeout:20000});
  await page.waitForTimeout(600);
  const td = page.locator('#div_table tbody td:visible').first();
  const th = page.locator('#div_table thead th:visible').first();
  const row = page.locator('#div_table tbody tr').first();
  await row.hover();
  await page.waitForTimeout(250);
  const mouse = await row.evaluate(el=>getComputedStyle(el).backgroundColor);
  console.log(`[${label}] td pad=${await td.evaluate(el=>getComputedStyle(el).padding)} | th pad=${await th.evaluate(el=>getComputedStyle(el).padding)} | row hover=${mouse}`);
  return { td, th };
}
try{
  await login();
  await audit('/coalpanel/client','client');
  await audit('/coalpanel/products','products');
  await audit('/coalpanel/product-categories','categories');
  await audit('/coalpanel/kva','kva');
  await page.goto(`${BASE}/coalpanel/client`,{waitUntil:'networkidle'});
  await page.waitForSelector('#div_table tbody tr',{timeout:20000});
  await page.waitForTimeout(900);
  await page.screenshot({path:'/tmp/clients-rows-final.png'});
} finally { await browser.close(); }
