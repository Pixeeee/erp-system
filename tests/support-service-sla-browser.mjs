import { chromium } from '../frontend/node_modules/playwright/index.mjs';

const baseUrl = process.env.SUPPORT_SERVICE_BASE_URL || 'http://localhost/erpsystem/company/yovel-east/admin/?view=support-service&section=sla-rules';
const login = process.env.SUPPORT_SERVICE_ADMIN_LOGIN || 'admin';
const password = process.env.SUPPORT_SERVICE_ADMIN_PASSWORD || 'admin12345';
const assert = (condition, message) => { if (!condition) throw new Error(message); };

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });

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

    assert(await page.locator('#support-sla-directory-heading').count() === 1, 'Authenticated SLA directory is missing.');
    const desktopPanels = await page.locator('[data-support-service-main], [data-support-service-tools]').evaluateAll(
        (elements) => elements.map((element) => element.getBoundingClientRect())
    );
    const desktopRatio = desktopPanels[0]?.width / desktopPanels[1]?.width;
    assert(desktopPanels.length === 2 && desktopRatio > 1.4 && desktopRatio < 1.6, 'Support SLA panels do not render the approved 12/8 ratio.');

    let postCount = 0;
    page.on('request', (request) => {
        if (request.method() === 'POST' && request.url().includes('/company/yovel-east/admin/')) postCount += 1;
    });
    await page.locator('[data-record-modal-open="support-sla-modal"]').click();
    const modal = page.locator('#support-sla-modal');
    await modal.locator('input[name="sla_code"]').fill('BROWSER-SLA');
    await modal.locator('input[name="service_level_name"]').fill('Browser retained SLA');
    await modal.locator('textarea[name="service_days_json"]').fill('[{"weekday":1,"start_time":"17:00:00","end_time":"09:00:00","sort_order":10}]');
    const submit = modal.locator('[data-confirm-submit-action]');
    const confirmation = page.locator('[data-confirm-dialog]');
    await submit.click();
    await confirmation.waitFor({ state: 'visible' });
    assert(postCount === 0, 'SLA POST occurred before Confirm.');
    assert(await modal.isVisible(), 'SLA confirmation displaced the body-owned modal.');
    await confirmation.locator('[data-confirm-cancel]').click();
    assert(await modal.locator('input[name="sla_code"]').inputValue() === 'BROWSER-SLA', 'Confirmation Cancel discarded SLA values.');
    assert(await submit.evaluate((element) => element === document.activeElement), 'Confirmation Cancel did not restore focus to SLA Submit.');

    await submit.click();
    await confirmation.waitFor({ state: 'visible' });
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        confirmation.locator('[data-confirm-action]').click(),
    ]);
    assert(postCount === 1, 'SLA Confirm did not issue exactly one POST.');
    await page.locator('#support-sla-modal').waitFor({ state: 'visible' });
    assert(await page.locator('#support-sla-modal').getAttribute('data-record-modal-open-on-load') !== null, 'SLA validation failure did not reopen the modal.');
    assert(await page.locator('#support-sla-modal input[name="sla_code"]').inputValue() === 'BROWSER-SLA', 'Server rejection lost the SLA code.');
    assert((await page.locator('#support-sla-modal [role="alert"]').textContent()).includes('start must be before end'), 'Server rejection did not expose the service-window error.');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(baseUrl, { waitUntil: 'networkidle' });
    const mobileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    const mobilePanels = await page.locator('[data-support-service-main], [data-support-service-tools]').evaluateAll(
        (elements) => elements.map((element) => element.getBoundingClientRect())
    );
    assert(!mobileOverflow, 'Support SLA workspace overflows horizontally on mobile.');
    assert(mobilePanels.length === 2 && mobilePanels[1].top >= mobilePanels[0].bottom - 1, 'Support SLA workspace does not stack main-first on mobile.');

    console.log(`Support SLA browser checks passed: authenticated 12/8 ratio ${desktopRatio.toFixed(3)}, modal confirmation, zero pre-confirm POSTs, single Confirm POST, server rehydration, focus retention, and mobile stacking.`);
} finally {
    await browser.close();
}
