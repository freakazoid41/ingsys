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
  await page.waitForSelector('.area-target[data-tag="op-doc-ihale-form"]',{timeout:20000});
  await page.waitForTimeout(1500);
  const info = await page.evaluate(()=>{
    const head = document.querySelector('.ihf-head');
    const sec = document.querySelector('.form-section-heading');
    const btn = document.querySelector('.ihf-btn--primary');
    const root = document.querySelector('.ihf-page');
    const cs = el => el ? getComputedStyle(el) : {};
    return {
      bodyClass: document.body.className.split(' ').filter(c=>c.startsWith('ihf-')).join(','),
      rootClass: root?.className,
      headBg: cs(head).backgroundImage !== 'none' ? 'gradient' : cs(head).backgroundColor,
      headBorder: cs(head).borderColor,
      sectionBg: cs(sec).backgroundColor,
      sectionTop: cs(sec).borderTopColor,
      primaryBtn: btn ? cs(btn).backgroundColor : null,
      fields: document.querySelectorAll('.area-target[data-tag="op-doc-ihale-form"] .form-control').length,
    };
  });
  console.log(`[${label}]`, JSON.stringify(info));
}
try{
  await login();
  await probe('/coalpanel/ihaleler/form','ADMIN new form');
  await probe('/ihalepanel/ihaleler/form','PANEL new form');
  await page.screenshot({path:'/tmp/ihale-panel-form.png'});
  await page.goto(`${BASE}/coalpanel/ihaleler/form`,{waitUntil:'networkidle'});
  await page.waitForTimeout(1500);
  await page.screenshot({path:'/tmp/ihale-admin-form.png'});
} finally { await browser.close(); }
