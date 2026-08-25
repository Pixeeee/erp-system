import { chromium } from '../frontend/node_modules/playwright/index.mjs';

const baseUrl = process.env.SALES_CRM_BASE_URL || 'http://localhost/erpsystem/company/yovel-east/admin/?view=sales-crm&section=dashboard';
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

    for (const marker of ['summary', 'queue', 'activity', 'setup', 'alerts', 'shortcuts', 'directory']) {
        expect(await page.locator(`[data-sales-dashboard-${marker}]`).count() === 1, `Dashboard ${marker} region is missing.`);
    }
    expect(await page.locator('[data-sales-dashboard-metric="open-leads"]').count() === 1, 'Live Lead metric is missing.');
    expect(await page.locator('[data-sales-dashboard-summary]').getByText('Unavailable', { exact: true }).count() === 3, 'Downstream metrics do not render explicit unavailable states.');
    expect(await page.locator('[data-sales-dashboard-form-builder]').count() >= 1, 'Sales Form Builder access is missing.');

    const panelBoxes = await page.locator('.yovel-sales-crm-two-panel > main, .yovel-sales-crm-two-panel > aside').evaluateAll((elements) => elements.map((element) => element.getBoundingClientRect()));
    const panelRatio = panelBoxes[0]?.width / panelBoxes[1]?.width;
    expect(panelBoxes.length === 2 && panelRatio > 1.4 && panelRatio < 1.6, 'Dashboard desktop panels do not render the approved 12/8 ratio.');

    const tourTrigger = page.locator('[data-sales-dashboard-tour-open]');
    await tourTrigger.click();
    const tour = page.locator('[data-sales-dashboard-tour]');
    await tour.waitFor({ state: 'visible' });
    const firstTourTitle = await tour.locator('[data-sales-dashboard-tour-title]').textContent();
    await tour.locator('[data-sales-dashboard-tour-next]').click();
    expect(await tour.locator('[data-sales-dashboard-tour-title]').textContent() !== firstTourTitle, 'Dashboard tour did not advance.');
    await tour.locator('[data-sales-dashboard-tour-back]').click();
    expect(await tour.locator('[data-sales-dashboard-tour-title]').textContent() === firstTourTitle, 'Dashboard tour did not move back.');
    await page.keyboard.press('Escape');
    await tour.waitFor({ state: 'hidden' });
    expect(await tourTrigger.evaluate((element) => element === document.activeElement), 'Dashboard tour did not return focus to its trigger.');

    let postCount = 0;
    page.on('request', (request) => {
        if (request.method() === 'POST' && request.url().includes('/company/yovel-east/admin/')) postCount += 1;
    });
    const leadOpener = page.locator('[data-record-modal-open="yovel-sales-dashboard-lead-modal"]').first();
    expect(await leadOpener.count() === 1, 'Dashboard Add Lead does not use the record modal controller.');
    await leadOpener.click();
    const leadModal = page.locator('#yovel-sales-dashboard-lead-modal');
    await leadModal.waitFor({ state: 'visible' });
    await leadModal.locator('#sales_lead_code').fill('!');
    await leadModal.locator('#sales_lead_name').fill('Dashboard retained Lead');
    await leadModal.locator('#sales_lead_status').selectOption('OPEN');
    const leadSubmit = leadModal.locator('[data-confirm-submit-action]');
    await leadSubmit.click();
    const confirmation = page.locator('[data-confirm-dialog]');
    await confirmation.waitFor({ state: 'visible' });
    expect(postCount === 0, 'Dashboard Lead POST occurred before Confirm.');
    await confirmation.locator('[data-confirm-cancel]').click();
    expect(await leadModal.locator('#sales_lead_name').inputValue() === 'Dashboard retained Lead', 'Dashboard confirmation Cancel discarded Lead values.');
    expect(await leadSubmit.evaluate((element) => element === document.activeElement), 'Dashboard confirmation Cancel did not restore submit focus.');
    await leadSubmit.click();
    await confirmation.waitFor({ state: 'visible' });
    await Promise.all([
        page.waitForLoadState('networkidle'),
        confirmation.locator('[data-confirm-action]').click(),
    ]);
    expect(postCount === 1, 'Dashboard Lead Confirm did not issue exactly one POST.');
    await page.locator('#yovel-sales-dashboard-lead-modal').waitFor({ state: 'visible' });
    expect(await page.locator('#sales_lead_name').inputValue() === 'Dashboard retained Lead', 'Dashboard Lead server validation did not rehydrate values.');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(baseUrl, { waitUntil: 'networkidle' });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    expect(!overflow, 'Sales dashboard has horizontal overflow on mobile.');
    const mobileBoxes = await page.locator('.yovel-sales-crm-two-panel > main, .yovel-sales-crm-two-panel > aside').evaluateAll((elements) => elements.map((element) => element.getBoundingClientRect()));
    expect(mobileBoxes.length === 2 && mobileBoxes[1].top >= mobileBoxes[0].bottom - 1, 'Sales dashboard does not stack main-first on mobile.');

    console.log(`Sales / CRM dashboard browser checks passed: authenticated live regions, 12/8 ratio ${panelRatio.toFixed(3)}, tour focus, confirmation/rehydration, dependency states, Form Builder, and mobile stacking.`);
} finally {
    await browser.close();
}
