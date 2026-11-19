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
  await page.waitForTimeout(1800);
  const info = await page.evaluate(()=>{
    const root = document.querySelector('.order-list-card');
    const cs = el => el ? getComputedStyle(el) : null;
    const sheets = Array.from(document.styleSheets).map(s=>s.href||'inline').filter(h=>!h.includes('fonts'));
    const hasTedarikPanel = !!document.querySelector('.tedarik-panel');
    const hasKtContent = !!document.querySelector('#kt_content');
    return {
      rootClass: root?.className,
      hasTedarikPanel, hasKtContent,
      topTitleColor: root ? cs(root.querySelector('.tedarik-list-title'))?.color : null,
      tableHeaderBg: root ? cs(root.querySelector('thead th'))?.backgroundColor : null,
      tableHeaderPad: root ? cs(root.querySelector('thead th'))?.padding : null,
      rowPad: root ? cs(root.querySelector('tbody td'))?.padding : null,
      filterColor: root ? cs(root.querySelector('.tedarik-filter'))?.color : null,
      sheets: sheets.slice(0,12),
      bodyClass: document.body.className,
    };
  });
  console.log(`\n== ${label} ==`);
  console.log(JSON.stringify(info,null,1));
}
try{
  await login();
  await probe('/coalpanel/ihaleler','ADMIN /coalpanel/ihaleler');
  await probe('/ihalepanel/ihaleler','PANEL /ihalepanel/ihaleler');
} finally { await browser.close(); }
