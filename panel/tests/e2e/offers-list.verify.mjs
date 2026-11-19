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
  await page.goto(`${BASE}/coalpanel/ihaleler/teklifler`,{waitUntil:'networkidle'});
  await page.waitForSelector('#div_table tbody tr',{timeout:20000});
  await page.waitForTimeout(1200);
  const info = await page.evaluate(()=>{
    const rows = Array.from(document.querySelectorAll('#div_table tbody tr'));
    return {
      rows: rows.length,
      rlist: !!document.querySelector('.rlist-card'),
      tedarik: !!document.querySelector('.tedarik-card'),
      iconBtns: document.querySelectorAll('#div_table .ihl-act').length,
      textPills: Array.from(document.querySelectorAll('#div_table tbody button')).filter(b=>b.classList.contains('btn')).length,
      btnSize: (()=>{const b=document.querySelector('#div_table .ihl-act'); if(!b) return null; const r=b.getBoundingClientRect(); return r.width+'x'+r.height;})(),
      rowHeight: rows[0] ? Math.round(rows[0].getBoundingClientRect().height) : null,
      rowsText: rows.map(r=>r.innerText.replace(/\n/g,' | ')),
      statusChips: Array.from(document.querySelectorAll('#div_table tbody td:nth-child(5) span')).map(s=>[s.textContent.trim(), getComputedStyle(s).backgroundColor, getComputedStyle(s).color]),
    };
  });
  console.log(JSON.stringify(info,null,1));
  await page.screenshot({path:'/tmp/offers-admin-rows.png'});
  await page.goto(`${BASE}/ihalepanel/ihaleler/teklifler`,{waitUntil:'networkidle'});
  await page.waitForSelector('#div_table tbody tr',{timeout:20000});
  await page.waitForTimeout(1000);
  console.log('PANEL: rows=', await page.locator('#div_table tbody tr').count(), 'textPills=', await page.locator('#div_table tbody button.btn').count(), 'tedarik=', await page.locator('.tedarik-card').count());
} finally { await browser.close(); }
