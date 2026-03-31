const puppeteer = require('puppeteer');

(async () => {
  console.log('Testing WebAuthn (Passkey) flow...');
  const browser = await puppeteer.launch({
    headless: "new",
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const page = await browser.newPage();
  
  page.on('console', msg => console.log('BROWSER CONSOLE:', msg.text()));
  page.on('pageerror', error => console.error('BROWSER ERROR:', error.message));
  page.on('requestfailed', request => console.log('BROWSER REQUEST FAILED:', request.url(), request.failure().errorText));

  page.on('response', async res => {
    if (res.status() >= 400) {
      console.log(`RESPONSE ERROR (${res.status()}): ${res.url()}`);
      try { console.log('Body:', await res.text()); } catch (e) {}
    }
  });

  // Set up the CDP Session to simulate a physical WebAuthn device (fingerprint/touch/yubikey)
  const client = await page.createCDPSession();
  await client.send('WebAuthn.enable');
  await client.send('WebAuthn.addVirtualAuthenticator', {
    options: {
      protocol: 'ctap2',
      transport: 'internal',
      hasResidentKey: true,
      hasUserVerification: true,
      isUserVerified: true
    }
  });

  const uniqueEmail = `test${Date.now()}@example.com`;
  
  try {
    console.log('\n--- 1. REGISTRATION ---');
    await page.goto('http://localhost:8088/register');
    
    // Switch to Passkey tab
    await page.waitForSelector('button[data-method="passkey"]');
    await page.click('button[data-method="passkey"]');
    
    // Fill the registration form
    await page.waitForSelector('#pk-username', { visible: true });
    await page.type('#pk-username', 'PasskeyTestUser');
    await page.type('#pk-email', uniqueEmail);
    
    console.log(`Submitting registration for ${uniqueEmail}...`);
    // Click submit and wait for the fetch + redirect
    const [response] = await Promise.all([
      page.waitForNavigation({ timeout: 15000 }), 
      page.click('#passkey-form button[type="submit"]')
    ]);
    
    if (page.url() === 'http://localhost:8088/') {
        console.log('✅ Registration with passkey successful.');
    } else {
        console.log('❌ Registration redirect failed. Current URL:', page.url());
        const status = await page.$eval('#status-message', el => el.innerText).catch(() => 'No status message');
        console.log('Status message:', status);
        process.exit(1);
    }
    
    console.log('\n--- 2. LOGOUT ---');
    // We hit the logout route and ignore if it throws
    await page.goto('http://localhost:8088/logout').catch(() => {});
    console.log('Logged out.');
    
    console.log('\n--- 3. LOGIN ---');
    await page.goto('http://localhost:8088/login');
    
    // Wait for the login Passkey tab to be visible
    await page.waitForSelector('button[data-method="passkey"]');
    await page.click('button[data-method="passkey"]');
    
    // Wait for form, type the exact same email
    await page.waitForSelector('#pk-email', { visible: true });
    await page.type('#pk-email', uniqueEmail);
    
    console.log(`Submitting login for ${uniqueEmail}...`);
    const [loginResponse] = await Promise.all([
      page.waitForNavigation({ timeout: 15000 }),
      page.click('#passkey-form button[type="submit"]')
    ]);
    
    if (page.url() === 'http://localhost:8088/') {
        console.log('✅ Login with passkey successful.');
    } else {
        console.log('❌ Login redirect failed. Current URL:', page.url());
        const status = await page.$eval('#status-message', el => el.innerText).catch(() => 'No status message');
        console.log('Status message:', status);
        process.exit(1);
    }

    console.log('\n--- 🎉 ALL TESTS PASSED! ---');

  } catch (err) {
    console.error('Error during test:', err);
  } finally {
    await browser.close();
  }
})();
