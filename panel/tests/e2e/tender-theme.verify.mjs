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
  await page.waitForTimeout(1200);
  const stats = await page.evaluate(()=>{
    const rows = Array.from(document.querySelectorAll('#div_table tbody tr'));
    const pick = (sel) => { const el = document.querySelector(`#div_table tbody tr ${sel}`); return el ? [el.textContent.trim().slice(0,40), getComputedStyle(el).backgroundColor, getComputedStyle(el).color, getComputedStyle(el).padding] : null; };
    return {
      rows: rows.length,
      firstRowText: rows[0]?.innerText.replace(/\n/g,' | '),
      codePill: pick('span[style*="font-family"]'),
      statusChip: pick('td:nth-child(7) span'),
      actionBtns: document.querySelectorAll('#div_table tbody button').length,
      rowPad: rows[0] ? getComputedStyle(rows[0].querySelector('td')).padding : null,
      tedarikClass: !!document.querySelector('.tedarik-card'),
      rlistHeader: !!document.querySelector('.rlist-header'),
    };
  });
  console.log(JSON.stringify(stats,null,1));
  await page.screenshot({path:'/tmp/tender-admin-new.png'});

  // Panel shell sanity
  await page.goto(`${BASE}/ihalepanel/ihaleler`,{waitUntil:'networkidle'});
  await page.waitForSelector('#div_table tbody tr',{timeout:20000});
  await page.waitForTimeout(1000);
  console.log('panel rows:', await page.locator('#div_table tbody tr').count(), '| tedarik-card:', await page.locator('.tedarik-card').count());
} finally { await browser.close(); }
