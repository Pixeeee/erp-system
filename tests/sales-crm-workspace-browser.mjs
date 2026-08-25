import { chromium } from '../frontend/node_modules/playwright/index.mjs';

const baseUrl = process.env.SALES_CRM_BASE_URL || 'http://localhost/erpsystem/company/yovel-east/admin/?view=sales-crm&section=leads';
const login = process.env.SALES_CRM_ADMIN_LOGIN || 'admin';
const password = process.env.SALES_CRM_ADMIN_PASSWORD || 'admin12345';
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
const expect = (condition, message) => { if (!condition) throw new Error(message); };

try {
    await page.goto(baseUrl, { waitUntil: 'networkidle' });
    if (await page.locator('input[name="login"]').count()) {
        await page.locator('input[name="login"]').fill(login);
        await page.locator('input[name="password"]').fill(password);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.locator('button[type="submit"]').click(),
        ]);
        await page.goto(baseUrl, { waitUntil: 'networkidle' });
    }

    expect(await page.locator('[data-sales-crm-setup]').count() === 1, 'Sales / CRM setup workspace is missing.');
    expect(await page.locator('[data-sales-crm-shortcuts] a').count() >= 1, 'No operational workspace shortcut was rendered.');
    expect(await page.locator('[data-sales-crm-shortcuts] [aria-disabled="true"]').count() >= 1, 'Unavailable dependencies are not visibly disabled.');

    const tourTrigger = page.locator('[data-sales-crm-tour-open]');
    await tourTrigger.click();
    const tour = page.locator('[data-sales-crm-tour]');
    await tour.waitFor({ state: 'visible' });
    const firstTitle = await tour.locator('[data-sales-crm-tour-title]').textContent();
    await tour.locator('[data-sales-crm-tour-next]').click();
    expect(await tour.locator('[data-sales-crm-tour-title]').textContent() !== firstTitle, 'Tour did not advance.');
    await tour.locator('[data-sales-crm-tour-back]').click();
    expect(await tour.locator('[data-sales-crm-tour-title]').textContent() === firstTitle, 'Tour did not move back.');
    await page.keyboard.press('Escape');
    await tour.waitFor({ state: 'hidden' });
    expect(await tourTrigger.evaluate((element) => element === document.activeElement), 'Tour close did not return focus to Show Tour.');

    let postCount = 0;
    page.on('request', (request) => {
        if (request.method() === 'POST' && request.url().includes('/company/yovel-east/admin/')) postCount += 1;
    });
    const settingsOpen = page.locator('[data-record-modal-open="yovel-sales-crm-settings-modal"]');
    await settingsOpen.click();
    const settingsModal = page.locator('#yovel-sales-crm-settings-modal');
    await settingsModal.waitFor({ state: 'visible' });
    await settingsModal.locator('input[name="expected_version"]').evaluate((element) => { element.value = '999999'; });
    const settingsSubmit = settingsModal.locator('[data-confirm-submit-action]');
    await settingsSubmit.click();
    const confirmation = page.locator('[data-confirm-dialog]');
    await confirmation.waitFor({ state: 'visible' });
    expect(postCount === 0, 'Settings POST occurred before confirmation.');
    await confirmation.locator('[data-confirm-cancel]').click();
    expect(await settingsModal.locator('input[name="expected_version"]').inputValue() === '999999', 'Confirmation cancel discarded settings values.');
    expect(await settingsSubmit.evaluate((element) => element === document.activeElement), 'Confirmation cancel did not restore focus.');
    await settingsSubmit.click();
    await confirmation.waitFor({ state: 'visible' });
    await Promise.all([
        page.waitForLoadState('networkidle'),
        confirmation.locator('[data-confirm-action]').click(),
    ]);
    expect(postCount === 1, 'Settings Confirm did not issue exactly one POST.');
    await settingsModal.waitFor({ state: 'visible' });
    expect(await settingsModal.locator('input[name="expected_version"]').inputValue() === '999999', 'Settings server error did not rehydrate the submitted version.');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload({ waitUntil: 'networkidle' });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    expect(!overflow, 'SC-02 workspace has horizontal overflow on mobile.');
    const setupColumns = await page.locator('[data-sales-crm-setup-columns] > *').evaluateAll((elements) => elements.map((element) => element.getBoundingClientRect()));
    expect(setupColumns.length === 2 && setupColumns[1].top >= setupColumns[0].bottom - 1, 'Setup checklist and selected detail do not stack on mobile.');

    console.log('Sales / CRM SC-02 browser checks passed: tour keyboard flow, settings confirmation/rehydration, real destinations, disabled dependencies, and mobile stacking.');
} finally {
    await browser.close();
}

await import('./sales-crm-dashboard-browser.mjs');
await import('./sales-crm-leads-browser.mjs');
