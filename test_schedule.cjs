const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 500 });
  const page = await browser.newPage();

  await page.goto('http://127.0.0.1:8000/login');
  await page.fill('input[name="email"]', 'test@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 8000 }).catch(() => {});
  console.log('After login URL:', page.url());

  await page.goto('http://127.0.0.1:8000/tournaments/1');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test_tournament.png' });
  console.log('Tournament page screenshot saved');

  await browser.close();
})();
