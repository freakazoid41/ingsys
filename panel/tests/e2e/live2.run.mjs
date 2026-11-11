import { chromium } from 'playwright';
const BASE='http://localhost:8000';
const b=await chromium.launch({headless:true});const ctx=await b.newContext({viewport:{width:1360,height:900}});const p=await ctx.newPage();
await p.goto(`${BASE}/tedarik`,{waitUntil:'networkidle'});await p.waitForSelector('#email',{timeout:15000});
await p.fill('#email','kbbozat41@hotmail.com');await p.fill('#password','Kadir412.');
await p.evaluate(()=>{const el=document.querySelector('[name="g-recaptcha-response"]');if(el)el.value='test';});
await p.click('#submit-button');await p.waitForTimeout(1800);
for(let i=1;i<=6;i++){const s=`input[name="code_${i}"]`;if(await p.locator(s).count())await p.fill(s,'1');}
await p.locator('button:has-text("Kodu Onayla"),button[type="submit"]').first().click().catch(()=>{});
await p.waitForTimeout(3000);await p.waitForLoadState('networkidle').catch(()=>{});
await p.goto('http://localhost:8000/tedarikpanel/orders/form/26ff4700-c674-41cc-8f3b-0ff1828be53e',{waitUntil:'domcontentloaded'});
await p.waitForSelector('.tedarik-detail',{timeout:20000});await p.waitForTimeout(4000);
// ST serial: tick checkbox, fill auto row
const tgl=p.locator('.oic-toggle-cb').first();
if(await tgl.count()){await tgl.scrollIntoViewIfNeeded().catch(()=>{});await tgl.click().catch(()=>{});await p.waitForTimeout(1200);}
console.log('serial inputs:',await p.locator('.oic-serial-input').count());
const si=p.locator('.oic-serial-input').first();
if(await si.count()){await si.fill('E2E-SN-001');}
await p.evaluate(()=>{const a=document.querySelector('.tedarik-step-card .oic-fp-month');if(a){a.removeAttribute('readonly');a.value='2026-02-01';a.dispatchEvent(new Event('input',{bubbles:true}));a.dispatchEvent(new Event('change',{bubbles:true}));}});
await p.waitForTimeout(800);
await p.fill('textarea[placeholder*="açıklama"]','Canlı veri E2E ST '+Date.now());
await p.fill('input[placeholder*="İmalatçı"]','PANOBEL ST Ltd');
await p.waitForSelector('.tedarik-file-wrap',{timeout:20000}).catch(()=>{});
const fis=p.locator('.tedarik-file-wrap input[type="file"]');console.log('file inputs:',await fis.count());
if(await fis.count()>=2){await fis.nth(0).setInputFiles('/tmp/kabul.pdf');await p.waitForTimeout(2500);await fis.nth(1).setInputFiles('/tmp/cins.pdf');await p.waitForTimeout(2500);}
else console.log('files already present, skipping upload');
await p.locator('.tedarik-gonder-btn').first().scrollIntoViewIfNeeded().catch(()=>{});
await p.locator('.tedarik-gonder-btn').first().click();await p.waitForTimeout(6000);
const sw=await p.locator('.swal2-popup').isVisible().catch(()=>false);
if(sw){console.log('swal:',(await p.locator('.swal2-popup').innerText()).slice(0,200));await p.locator('.swal2-confirm').first().click().catch(()=>{});await p.waitForTimeout(1500);}
const st=await p.evaluate(async()=>{const r=await fetch('/api/v1/document/26ff4700-c674-41cc-8f3b-0ff1828be53e');const j=await r.json();let s=j.data?.document?.status||'none';if(typeof s==='string'){try{s=JSON.parse(s);}catch(e){}}return Array.isArray(s)?s[s.length-1]?.op_key:s;});
console.log('STATUS AFTER SEND:',st);
if(st!=='doc_trans_order_transfer_sent')throw new Error('send failed: '+st);
console.log('LIVE ST PIPELINE PASS');
await p.screenshot({path:'/tmp/live2-pass.png',fullPage:true});
await b.close();
