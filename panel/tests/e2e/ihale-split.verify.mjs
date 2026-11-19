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
async function probe(path,label){
  await page.goto(`${BASE}${path}`,{waitUntil:'networkidle'});
  await page.waitForTimeout(2000);
  const info = await page.evaluate(()=>{
    const r = document.querySelector('#div_table');
    const root = document.querySelector('.rlist-card, .order-list-card');
    const th = r?.querySelector('thead th'); const td = r?.querySelector('tbody td');
    return {
      rootClass: root?.className,
      rlistCard: !!document.querySelector('.rlist-card'),
      tedarikCard: !!document.querySelector('.tedarik-card'),
      headerBg: th ? getComputedStyle(th).backgroundColor : null,
      headerPad: th ? getComputedStyle(th).padding : null,
      rowPad: td ? getComputedStyle(td).padding : null,
      rlistHeader: !!document.querySelector('.rlist-header'),
      tedarikTop: !!document.querySelector('.tedarik-list-top'),
      rows: r?.querySelectorAll('tbody tr').length || 0,
    };
  });
  console.log(`[${label}]`, JSON.stringify(info));
}
try{
  await login();
  await probe('/coalpanel/ihaleler','ADMIN ihaleler');
  await probe('/coalpanel/ihaleler/teklifler','ADMIN teklifler');
  await probe('/ihalepanel/ihaleler','PANEL ihaleler');
  await probe('/ihalepanel/ihaleler/teklifler','PANEL teklifler');
} finally { await browser.close(); }
