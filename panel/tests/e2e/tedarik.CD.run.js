import { chromium } from 'playwright';
const BASE='http://localhost:8000', PASS='Kadir412.';
async function login(page,email){
  await page.goto(`${BASE}/tedarik`,{waitUntil:'networkidle'});
  await page.waitForSelector('#email',{timeout:15000});
  await page.fill('#email',email); await page.fill('#password',PASS);
  await page.evaluate(()=>{const el=document.querySelector('[name="g-recaptcha-response"]');if(el)el.value='test';});
  await page.click('#submit-button'); await page.waitForTimeout(1800);
  for(let i=1;i<=6;i++){const s=`input[name="code_${i}"]`;if(await page.locator(s).count())await page.fill(s,'1');}
  const b=page.locator('button:has-text("Doğrula"),button:has-text("Onayla"),button[type="submit"]').first();
  if(await b.count())await b.first().click(); else await page.keyboard.press('Enter');
  await page.waitForTimeout(2500); await page.waitForLoadState('networkidle').catch(()=>{});
  if(page.url().includes('/module-select')){const t=page.locator('a:has-text("Tedarik")').first();if(await t.count()){await t.click();await page.waitForLoadState('networkidle').catch(()=>{});}}
}
async function apiOrders(page){
  return await page.evaluate(async()=>{
    const fd=new FormData(); fd.append('tableReq',JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-order-form'},{key:'type',type:'=',value:'op-doc-order'}],scale:{page:1,limit:50}}));
    const r=await fetch('/api/v1/table/documents',{method:'POST',body:fd}); const j=await r.json();
    const rows=j.data?.data||j.data||[];
    return rows.map(row=>{try{const a=JSON.parse(row.main_attr||'[]');const no=(a.find(x=>x.Key==='order_no')||{}).Value||'?';return {no,qnid:row.id||row.qnid};}catch(e){return {no:'?',qnid:row.id};}});
  });
}
async function lastStatus(page,qnid){
  return await page.evaluate(async(q)=>{
    const r=await fetch('/api/v1/document/'+q); const j=await r.json();
    let st=j.data?.document?.status||'none';
    if(typeof st==='string'){try{st=JSON.parse(st);}catch(e){return st.slice(0,120);}}
    if(Array.isArray(st)){const l=st[st.length-1];return l?.op_key||JSON.stringify(l).slice(0,120);}
    return JSON.stringify(st).slice(0,120);
  },qnid);
}
(async()=>{
  const browser=await chromium.launch({headless:true});
  const ctx=await browser.newContext({viewport:{width:1360,height:900}});
  const page=await ctx.newPage();
  const sum={};
  try{
    console.log('=== C kbbozat guard ===');
    await login(page,'kbbozat41@hotmail.com');
    await page.goto(`${BASE}/tedarikpanel/orders`,{waitUntil:'networkidle'});
    await page.waitForSelector('#div_table',{timeout:15000}).catch(()=>{});
    await page.waitForTimeout(2000);
    await page.screenshot({path:'/tmp/tedarik-C-kbbozat-list.png',fullPage:true});
    let orders=await apiOrders(page);
    console.log('[kbbozat]',orders.map(o=>o.no).join(','));
    sum.kbbozat=orders.map(o=>o.no);
    if(orders.some(o=>o.no==='3510004200'))throw new Error('ISOLATION FAIL kbbozat sees 4200');
    if(!orders.some(o=>o.no.startsWith('3510004400')))throw new Error('kbbozat must see 4400');
    console.log('PASS isolation');
    const o4400=orders.find(o=>o.no==='3510004400');
    await page.goto(`${BASE}/tedarikpanel/orders/form/${o4400.qnid}`,{waitUntil:'networkidle'});
    await page.waitForSelector('.tedarik-detail',{timeout:20000});
    await page.waitForTimeout(1500);
    const dis=await page.locator('input[value="at_once"]').isDisabled().catch(()=>false);
    console.log('[4400] at_once disabled=',dis);
    await page.screenshot({path:'/tmp/tedarik-C-4400-guard.png',fullPage:true});
    if(!dis)throw new Error('4400 at_once must be disabled');
    console.log('PASS guard');
    console.log('=== D admin ===');
    await page.goto(`${BASE}/logout`,{waitUntil:'networkidle'}).catch(()=>{});
    await ctx.clearCookies();
    await login(page,'kadir@kontent.com.tr');
    await page.goto(`${BASE}/coalpanel/documents`,{waitUntil:'networkidle'});
    await page.waitForTimeout(2500);
    await page.screenshot({path:'/tmp/tedarik-D-admin-docs.png',fullPage:true});
    const q4200='6d2cec19-a592-4632-82d9-2653275c3598';
    console.log('[4200 status]',await lastStatus(page,q4200));
    const files=await page.evaluate(async()=>{
      const fd=new FormData(); fd.append('tableReq',JSON.stringify({filter:[],scale:{page:1,limit:100}}));
      const r=await fetch('/api/v1/table/document_files',{method:'POST',body:fd}); const j=await r.json();
      const rows=j.data?.data||j.data||[];
      return rows.filter(x=>x.group_key==='3510004200').map(x=>({qnid:x.id,file:String(x.file).slice(0,60),group:x.group_key}));
    });
    console.log('[4200 files]',files.length,JSON.stringify(files).slice(0,600));
    sum.files=files.length;
    if(!files.length)throw new Error('no files for 4200');
    const f1=files[0].qnid;
    let res=await page.evaluate(async(fq)=>{const fd=new FormData();fd.append('id',fq);fd.append('op_key','doc_file_accepted');fd.append('note','browser e2e approve');const r=await fetch('/api/v1/trans/set-file-status',{method:'POST',body:fd});return {status:r.status,body:(await r.text()).slice(0,400)};},f1);
    console.log('[approve]',JSON.stringify(res)); sum.approve=res.status;
    if(files.length>1){
      const f2=files[1].qnid;
      res=await page.evaluate(async(fq)=>{const fd=new FormData();fd.append('id',fq);fd.append('op_key','doc_file_rejected');fd.append('note','browser e2e kalite uygun degil');const r=await fetch('/api/v1/trans/set-file-status',{method:'POST',body:fd});return {status:r.status,body:(await r.text()).slice(0,600)};},f2);
      console.log('[reject]',JSON.stringify(res)); sum.reject=res.status;
      const stR=await lastStatus(page,q4200); console.log('[after reject]',stR); sum.afterReject=stR;
      if(stR!=='doc_trans_order_files_rejected')throw new Error('expected files_rejected got '+stR);
      console.log('PASS files_rejected');
    }
    const logs=await page.evaluate(async()=>{const fd=new FormData();fd.append('tableReq',JSON.stringify({filter:[],scale:{page:1,limit:5}}));const r=await fetch('/api/v1/table/userlog',{method:'POST',body:fd});const j=await r.json();const rows=j.data?.data||j.data||[];return rows.map(x=>{try{const d=JSON.parse(x.description||'{}');return {keys:Object.keys(d),a:!!d.actor,d:!!d.document,f:!!d.file};}catch(e){return {err:1}}});});
    console.log('[logs]',JSON.stringify(logs)); sum.logs=logs;
    const kal=await page.evaluate(async(q)=>{const fd=new FormData();fd.append('id',q);fd.append('op_key','doc_trans_order_approved');fd.append('note','browser e2e kalite');const r=await fetch('/api/v1/trans/set-status',{method:'POST',body:fd});return {status:r.status,body:(await r.text()).slice(0,600)};},q4200);
    console.log('[kalite]',JSON.stringify(kal)); sum.kalite=kal.status;
    console.log('[after kalite]',await lastStatus(page,q4200)); sum.afterKalite=await lastStatus(page,q4200);
    console.log('SUMMARY',JSON.stringify(sum,null,2));
    console.log('E2E CD DONE');
  }catch(e){console.error('FAIL',e.message);try{await page.screenshot({path:'/tmp/tedarik-CD-FAIL.png',fullPage:true});}catch(x){}process.exitCode=1;}
  finally{await browser.close();}
})();
