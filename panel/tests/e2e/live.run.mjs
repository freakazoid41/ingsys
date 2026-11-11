import { chromium } from 'playwright';
const BASE='http://localhost:8000';
async function login(page,email,pass){
  await page.goto(`${BASE}/tedarik`,{waitUntil:'networkidle'});await page.waitForSelector('#email',{timeout:15000});
  await page.fill('#email',email);await page.fill('#password',pass);
  await page.evaluate(()=>{const el=document.querySelector('[name="g-recaptcha-response"]');if(el)el.value='test';});
  await page.click('#submit-button');await page.waitForTimeout(1800);
  for(let i=1;i<=6;i++){const s=`input[name="code_${i}"]`;if(await page.locator(s).count())await page.fill(s,'1');}
  const b=page.locator('button:has-text("Doğrula"),button:has-text("Onayla"),button[type="submit"]').first();
  if(await b.count())await b.first().click();else await page.keyboard.press('Enter');
  await page.waitForTimeout(2500);await page.waitForLoadState('networkidle').catch(()=>{});
  if(page.url().includes('/module-select')){const m=page.locator('a:has-text("Tedarik")').first();if(await m.count()){await m.click();await page.waitForTimeout(1000);} }
}
const b=await chromium.launch({headless:true});const ctx=await b.newContext({viewport:{width:1360,height:900}});const page=await ctx.newPage();
try{
  await login(page,'picklecanme@gmail.com','Kadir412.');
  await page.goto(`${BASE}/tedarikpanel/orders`,{waitUntil:'networkidle'});await page.waitForSelector('#div_table',{timeout:20000}).catch(()=>{});
  await page.waitForTimeout(3000);
  const txt=await page.locator('#div_table').innerText().catch(()=>'(no table)');
  console.log('LIST has 3510007499:',txt.includes('3510007499'));
  await page.screenshot({path:'/tmp/live-list.png'});
  const q='4db82438-42b9-43b1-90e1-d0161d722ad8';
  console.log('qnid:',q);
  await page.goto(`${BASE}/tedarikpanel/orders/form/${q}`,{waitUntil:'networkidle'});
  await page.waitForSelector('.tedarik-detail',{timeout:20000});await page.waitForTimeout(1500);
  await page.waitForSelector('.tedarik-file-wrap',{timeout:20000}).catch(()=>{});
  await page.waitForTimeout(2500);
  await page.waitForSelector('.oic-serial-table-input',{timeout:10000}).catch(()=>{});
  if(await page.locator('.oic-serial-table-input').count()){
    await page.locator('.oic-serial-table-input').first().fill('E2E-SRL-001');
    await page.evaluate(()=>{const a=document.querySelector('.oic-fp-month');if(a){a.removeAttribute('readonly');a.value='2026-11-01';a.dispatchEvent(new Event('input',{bubbles:true}));a.dispatchEvent(new Event('change',{bubbles:true}));}});
    const qty=page.locator('.oic-serial-table-qty').first();
    if(await qty.count()){await qty.fill('7000');}
    await page.waitForTimeout(800);
  }
  await page.fill('textarea[placeholder*="açıklama"]','Canlı veri E2E '+Date.now());
  await page.fill('input[placeholder*="İmalatçı"]','PANOBEL Test Ltd');
  const fis=page.locator('.tedarik-file-wrap input[type="file"]');console.log('file inputs:',await fis.count());
  if(await fis.count()>=2){await fis.nth(0).setInputFiles('/tmp/kabul.pdf');await page.waitForTimeout(2500);await fis.nth(1).setInputFiles('/tmp/cins.pdf');await page.waitForTimeout(2500);}
  else console.log('files already present, skipping upload');
  await page.locator('.tedarik-gonder-btn').first().scrollIntoViewIfNeeded().catch(()=>{});
  await page.locator('.tedarik-gonder-btn').first().click();await page.waitForTimeout(5000);
  const sw=await page.locator('.swal2-popup').isVisible().catch(()=>false);
  if(sw){console.log('swal:',(await page.locator('.swal2-popup').innerText()).slice(0,150));await page.locator('.swal2-confirm').first().click().catch(()=>{});await page.waitForTimeout(1500);}
  const st=await page.evaluate(async(qn)=>{const r=await fetch('/api/v1/document/'+qn);const j=await r.json();let s=j.data?.document?.status||'none';if(typeof s==='string'){try{s=JSON.parse(s);}catch(e){}}return Array.isArray(s)?s[s.length-1]?.op_key:s;},q);
  console.log('STATUS AFTER SEND:',st);
  if(st!=='doc_trans_order_transfer_sent')throw new Error('send failed: '+st);
  console.log('LIVE PIPELINE PASS');
}catch(e){console.error('FAIL:',e.message);try{await page.screenshot({path:'/tmp/live-FAIL.png',fullPage:true});}catch(x){}process.exitCode=1;}
finally{await b.close();}
