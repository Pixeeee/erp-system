import { chromium } from '../frontend/node_modules/playwright/index.mjs';

const baseUrl = process.env.SALES_CRM_BASE_URL || 'http://localhost/erpsystem/company/yovel-east/admin/?view=sales-crm&section=leads';
const login = process.env.SALES_CRM_ADMIN_LOGIN || 'admin';
const password = process.env.SALES_CRM_ADMIN_PASSWORD || 'admin12345';
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });

const expect = (condition, message) => {
    if (!condition) throw new Error(message);
};

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

    const opener = page.locator('[data-record-modal-open="yovel-sales-lead-modal"]');
    expect(await opener.count() === 1, 'Lead Add action does not use the shared record-modal opener.');
    const layout = page.locator('.xl\\:grid-cols-\\[minmax\\(0\\,12fr\\)_minmax\\(20rem\\,8fr\\)\\]');
    expect(await layout.count() === 1, 'Desktop Sales / CRM workspace is not using the 12/8 composition.');
    const desktopPanelBoxes = await page.locator('.yovel-sales-crm-two-panel > section, .yovel-sales-crm-two-panel > aside').evaluateAll((elements) => elements.map((element) => element.getBoundingClientRect()));
    const desktopPanelRatio = desktopPanelBoxes[0]?.width / desktopPanelBoxes[1]?.width;
    expect(desktopPanelBoxes.length === 2 && desktopPanelRatio > 1.4 && desktopPanelRatio < 1.6, 'Rendered desktop panels do not have the approved 12/8 width ratio.');

    let postCount = 0;
    page.on('request', (request) => {
        if (request.method() === 'POST' && request.url().includes('/company/yovel-east/admin/')) postCount += 1;
    });

    await opener.click();
    const modal = page.locator('#yovel-sales-lead-modal');
    await modal.waitFor({ state: 'visible' });
    expect(await modal.getAttribute('data-record-modal') !== null, 'Lead modal is missing the shared record-modal marker.');
    await page.locator('#sales_lead_code').fill('!');
    await page.locator('#sales_lead_name').fill('Retained after server validation');
    await page.locator('#sales_lead_status').selectOption('OPEN');
    const submit = modal.locator('[data-confirm-submit-action]');
    await submit.click();
    const confirmation = page.locator('[data-confirm-dialog]');
    await confirmation.waitFor({ state: 'visible' });
    expect(postCount === 0, 'A POST request occurred before confirmation.');
    await confirmation.locator('[data-confirm-cancel]').click();
    await modal.waitFor({ state: 'visible' });
    expect(await page.locator('#sales_lead_name').inputValue() === 'Retained after server validation', 'Cancel discarded Lead form values.');
    expect(await submit.evaluate((element) => element === document.activeElement), 'Cancel did not return focus to the Lead submit action.');

    await submit.click();
    await confirmation.waitFor({ state: 'visible' });
    await Promise.all([
        page.waitForLoadState('networkidle'),
        confirmation.locator('[data-confirm-action]').click(),
    ]);
    expect(postCount === 1, 'Confirm did not issue exactly one POST request.');
    await modal.waitFor({ state: 'visible' });
    expect(await page.locator('#sales_lead_name').inputValue() === 'Retained after server validation', 'Server validation did not rehydrate the Lead form.');

    const campaignUrl = baseUrl.replace(/section=[^&]+/, 'section=campaigns');
    await page.goto(campaignUrl, { waitUntil: 'networkidle' });
    const campaignOpener = page.locator('[data-record-modal-open="yovel-sales-campaign-modal"]');
    expect(await campaignOpener.count() === 1, 'Campaign Add action does not use the shared record-modal opener.');
    const campaignPostBaseline = postCount;
    await campaignOpener.click();
    const campaignModal = page.locator('#yovel-sales-campaign-modal');
    await campaignModal.waitFor({ state: 'visible' });
    await page.locator('#sales_campaign_code').fill('!');
    await page.locator('#sales_campaign_name').fill('Retained Campaign form');
    await page.locator('#sales_start_date').fill('2026-09-30');
    await page.locator('#sales_end_date').fill('2026-09-01');
    await page.locator('[data-campaign-schedule-row-insert]').click();
    expect(await page.locator('[data-campaign-schedule-row]').count() === 1, 'Campaign schedule control did not append a child row.');
    await campaignModal.getByLabel('Schedule code').fill('BROWSER_CHECK');
    await campaignModal.getByLabel('Email subject').fill('Browser confirmation check');
    await campaignModal.getByLabel('Scheduled at').fill('2026-09-15T09:30');
    const campaignSubmit = campaignModal.locator('[data-confirm-submit-action]');
    await campaignSubmit.click();
    await confirmation.waitFor({ state: 'visible' });
    expect(postCount === campaignPostBaseline, 'A Campaign POST occurred before confirmation.');
    await confirmation.locator('[data-confirm-cancel]').click();
    await campaignModal.waitFor({ state: 'visible' });
    expect(await page.locator('#sales_campaign_name').inputValue() === 'Retained Campaign form', 'Campaign confirmation cancel discarded values.');
    expect(await campaignSubmit.evaluate((element) => element === document.activeElement), 'Campaign confirmation cancel did not restore submit focus.');
    await campaignSubmit.click();
    await confirmation.waitFor({ state: 'visible' });
    await Promise.all([
        page.waitForLoadState('networkidle'),
        confirmation.locator('[data-confirm-action]').click(),
    ]);
    expect(postCount === campaignPostBaseline + 1, 'Campaign Confirm did not issue exactly one POST request.');
    await page.locator('#yovel-sales-campaign-modal').waitFor({ state: 'visible' });
    expect(await page.locator('#sales_campaign_name').inputValue() === 'Retained Campaign form', 'Campaign server validation did not rehydrate values.');
    expect(await page.locator('[data-campaign-transition-modal] [data-record-modal-form][data-confirm-submit]').count() === 1, 'Campaign lifecycle action lacks its modal and confirmation contract.');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload({ waitUntil: 'networkidle' });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    expect(!overflow, 'Sales / CRM mobile workspace has horizontal overflow.');
    const gridBoxes = await page.locator('.yovel-sales-crm-two-panel > section, .yovel-sales-crm-two-panel > aside').evaluateAll((elements) => elements.map((element) => element.getBoundingClientRect()));
    expect(gridBoxes.length === 2 && gridBoxes[1].top >= gridBoxes[0].bottom - 1, 'Sales / CRM panels do not stack main-first on mobile.');

    console.log(`Sales / CRM browser checks passed: rendered 12/8 ratio ${desktopPanelRatio.toFixed(3)}, Lead and Campaign confirmation/Cancel/server rehydration, Campaign schedules/lifecycle modal, and mobile panels stacked main-first.`);
} finally {
    await browser.close();
}
