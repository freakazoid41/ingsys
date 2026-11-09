// helpers/auth.js — real browser login like a human (IS_TEST=true => 111111)
export async function login(page, email, password) {
  // go to canonical tedarik login (single login page)
  await page.goto('/tedarik', { waitUntil: 'networkidle' });
  // tedarik login form: #login-form with #email, #password, #submit-button
  await page.waitForSelector('#email', { timeout: 10000 });
  await page.fill('#email', email);
  await page.fill('#password', password);
  // recaptcha bypass for IS_TEST — if there's a hidden input, set it
  await page.evaluate(() => {
    const el = document.querySelector('[name="g-recaptcha-response"]');
    if (el) el.value = 'test-bypass';
    const ta = document.querySelector('textarea[name="g-recaptcha-response"]');
    if (ta) ta.value = 'test-bypass';
  }).catch(()=>{});
  await page.click('#submit-button');
  // either goes to /login-sms or shows error
  await page.waitForTimeout(1500);
  const url = page.url();
  if (url.includes('/login-sms') || await page.locator('input[name^="code_"]').first().isVisible().catch(()=>false)) {
    // SMS page has 6 inputs code_1..code_6
    for (let i=1;i<=6;i++) {
      const sel = `input[name="code_${i}"]`;
      if (await page.locator(sel).count()) {
        await page.fill(sel, '1');
      }
    }
    // submit SMS — button is usually #sms-submit or form submit
    const smsBtn = page.locator('button:has-text("Doğrula"), button:has-text("Onayla"), button[type="submit"], #sms-submit').first();
    if (await smsBtn.count()) await smsBtn.click();
    else {
      // fallback: press Enter
      await page.keyboard.press('Enter');
    }
    await page.waitForTimeout(2000);
    // After SMS, server redirects to /tedarik-login with token, then to target module
    // Wait for either tedarik login token page or module-select or planlama
    await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(()=>{});
    // If we're on /tedarik with token param, page.js will auto redirect to planlamapanel/coalpanel
    // Wait for redirect
    for(let k=0;k<10;k++){
      const u = page.url();
      if(u.includes('/planlamapanel') || u.includes('/coalpanel') || u.includes('/tedarikpanel') || u.includes('/module-select')) break;
      await page.waitForTimeout(500);
    }
    // If module-select (user has multiple modules), auto-pick first or go to planlamapanel
    if(page.url().includes('/module-select')){
      // try to click Malzeme Planlama card
      const planCard = page.locator('a:has-text("Malzeme Planlama"), a:has-text("Planlama")').first();
      if(await planCard.count()) await planCard.click();
      else await page.goto('/planlamapanel');
      await page.waitForLoadState('networkidle');
    }
    // If still on /tedarik, use direct nav to planlamapanel (auth cookie already set)
    const finalUrl = page.url();
    if(finalUrl.includes('/tedarik') && !finalUrl.includes('planlama')){
      // Check if we have XSRF token — try direct goto
      await page.goto('/planlamapanel');
      await page.waitForLoadState('networkidle');
    }
  }
  // Verify logged in by checking for header user or planlama sidebar
  // Wait for planlama sidebar to appear if we are on planlama
  try {
    await page.waitForSelector('.tedarik-sidebar, .tedarik-menu, #div_table', { timeout: 5000 });
  } catch(e){
    // still log url for debug
    console.log(`[auth] after login url=${page.url()}`);
  }
  return page.url();
}

export async function logout(page){
  try{
    await page.goto('/logout');
    await page.waitForLoadState('networkidle');
  }catch(e){}
  await page.context().clearCookies();
}
