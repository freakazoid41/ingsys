import { chromium } from 'playwright';
const BASE='http://localhost:8000';
const b=await chromium.launch({headless:true});const ctx=await b.newContext({viewport:{width:1360,height:900}});const p=await ctx.newPage();
const t0=Date.now();
await p.goto(`${BASE}/tedarik`,{waitUntil:'networkidle'});await p.waitForSelector('#email',{timeout:15000});
await p.fill('#email','kadir@kontent.com.tr');await p.fill('#password','Kadir412.');
await p.evaluate(()=>{const el=document.querySelector('[name="g-recaptcha-response"]');if(el)el.value='test';});
await p.click('#submit-button');await p.waitForTimeout(1800);
for(let i=1;i<=6;i++){const s=`input[name="code_${i}"]`;if(await p.locator(s).count())await p.fill(s,'1');}
await p.locator('button:has-text("Kodu Onayla")').first().click().catch(()=>{});
await p.waitForTimeout(3000);await p.waitForLoadState('networkidle').catch(()=>{});
if(p.url().includes('/module-select')){const m=p.locator('a:has-text("Yönetim")').first();if(await m.count())await m.first().click();}
await p.goto(`${BASE}/coalpanel/documents`,{waitUntil:'domcontentloaded'});
await p.waitForSelector('#div_table',{timeout:20000});await p.waitForTimeout(4000);
console.log('docs list ms:',Date.now()-t0);
const files=await p.evaluate(async()=>{
  const fd=new FormData();fd.append('tableReq',JSON.stringify({filter:[{key:'siparis_kodu',type:'=',value:'3510011686'}],scale:{page:1,limit:20}}));
  const r=await fetch('/api/v1/table/document_files',{method:'POST',body:fd});const j=await r.json();
  const rows=j.data?.data||j.data||[];return rows.map(x=>({qnid:x.id,file:String(x.file).slice(0,30)}));
});
console.log('files for 3510011686:',JSON.stringify(files));
let ok=0;
for(const f of files){
  const r=await p.evaluate(async(fq)=>{const fd=new FormData();fd.append('id',fq);fd.append('op_key','doc_file_accepted');fd.append('note','canli e2e onay');const r=await fetch('/api/v1/trans/set-file-status',{method:'POST',body:fd});return r.status;},f.qnid);
  if(r===200)ok++;
}
console.log('approved:',ok,'/',files.length);
const kal=await p.evaluate(async()=>{const fd=new FormData();fd.append('tableReq',JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-order-form'},{key:'type',type:'=',value:'op-doc-order'},{key:'siparis_kodu',type:'=',value:'3510011686'}],scale:{page:1,limit:5}}));const r=await fetch('/api/v1/table/documents',{method:'POST',body:fd});const j=await r.json();const rows=j.data?.data||j.data||[];return rows.map(x=>x.id);});
console.log('order qnids:',JSON.stringify(kal));
const k2=await p.evaluate(async(q)=>{const fd=new FormData();fd.append('id',q);fd.append('op_key','doc_trans_order_approved');fd.append('note','canli e2e kalite');const r=await fetch('/api/v1/trans/set-status',{method:'POST',body:fd});return {s:r.status,b:(await r.text()).slice(0,120)};},kal[0]);
console.log('kalite:',JSON.stringify(k2));
const st=await p.evaluate(async(q)=>{const r=await fetch('/api/v1/document/'+q);const j=await r.json();let s=j.data?.document?.status||'none';if(typeof s==='string'){try{s=JSON.parse(s);}catch(e){}}return Array.isArray(s)?s[s.length-1]?.op_key:s;},kal[0]);
console.log('FINAL STATUS:',st,'total ms:',Date.now()-t0);
if(st!=='doc_trans_order_approved')throw new Error('kalite failed: '+st);
console.log('ADMIN CLOSE PASS');
await b.close();
