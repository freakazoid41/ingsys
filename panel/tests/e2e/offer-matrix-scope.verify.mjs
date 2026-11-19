import { chromium } from 'playwright';
const BASE='http://localhost:8000';
const EXE='/Users/freakazoid41/Library/Caches/ms-playwright/chromium-1155/chrome-mac/Chromium.app/Contents/MacOS/Chromium';
const browser=await chromium.launch({headless:true, executablePath:EXE});
const page=await (await browser.newContext({viewport:{width:1500,height:1100}})).newPage();
const errors=[];
page.on('pageerror', e=>errors.push(e.message));
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
  await page.goto(`${BASE}/coalpanel/ihaleler/teklifler`,{waitUntil:'networkidle'});
  await page.waitForSelector('#div_table tbody tr',{timeout:20000});
  await page.waitForTimeout(700);
  await page.locator('#div_table .ihl-act--edit').first().click();
  await page.waitForTimeout(3500);
  // open first product matrix
  const doldur = page.locator('button:has-text("Doldur")').first();
  if (await doldur.count()) { await doldur.click(); await page.waitForTimeout(1500); }
  const info = await page.evaluate(()=>{
    const heads = [...document.querySelectorAll('table th')].map(th=>th.textContent.trim()).filter(Boolean);
    return {
      headers: heads.slice(0,30),
      tables: document.querySelectorAll('table').length,
      cells: document.querySelectorAll('[class*="cell"]').length,
      bodySnippet: document.body.innerText.slice(-600),
    };
  });
  console.log(JSON.stringify(info,null,1));
  console.log('errors:', errors.slice(0,4));
  await page.screenshot({path:'/tmp/offer-matrix-scope.png', fullPage:true});
} finally { await browser.close(); }
