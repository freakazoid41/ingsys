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
  await page.goto(`${BASE}/coalpanel/ihaleler`,{waitUntil:'networkidle'});
  await page.waitForSelector('#div_table tbody tr',{timeout:20000});
  await page.waitForTimeout(900);
  const admin = await page.evaluate(()=>{
    const btns = Array.from(document.querySelectorAll('#div_table .ihl-act'));
    const row = document.querySelector('#div_table tbody tr');
    return {
      rows: document.querySelectorAll('#div_table tbody tr').length,
      iconBtns: btns.length,
      sizes: btns.slice(0,3).map(b=>{const r=b.getBoundingClientRect();return r.width+'x'+r.height;}),
      colors: btns.slice(0,3).map(b=>[b.title, getComputedStyle(b).backgroundColor, getComputedStyle(b).color]),
      textPills: Array.from(document.querySelectorAll('#div_table tbody button')).filter(b=>b.classList.contains('btn')).length,
      rowHeight: row ? Math.round(row.getBoundingClientRect().height) : null,
    };
  });
  console.log('ADMIN:', JSON.stringify(admin,null,1));
  await page.goto(`${BASE}/ihalepanel/ihaleler`,{waitUntil:'networkidle'});
  await page.waitForSelector('#div_table tbody tr',{timeout:20000});
  await page.waitForTimeout(900);
  const panel = await page.evaluate(()=>({
    rows: document.querySelectorAll('#div_table tbody tr').length,
    iconBtns: document.querySelectorAll('#div_table .ihl-act').length,
    textPills: Array.from(document.querySelectorAll('#div_table tbody button')).filter(b=>b.classList.contains('btn')).length,
  }));
  console.log('PANEL:', JSON.stringify(panel));
  await page.screenshot({path:'/tmp/tender-panel-actions.png'});
  await page.goto(`${BASE}/coalpanel/ihaleler`,{waitUntil:'networkidle'});
  await page.waitForTimeout(1200);
  await page.screenshot({path:'/tmp/tender-admin-actions.png'});
} finally { await browser.close(); }
