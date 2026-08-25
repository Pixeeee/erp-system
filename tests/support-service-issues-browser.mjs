import { chromium } from '../frontend/node_modules/playwright/index.mjs';

const baseUrl = process.env.SUPPORT_SERVICE_BASE_URL || 'http://localhost/erpsystem/company/yovel-east/admin/?view=support-service&section=issues-tickets';
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

    assert(await page.locator('[data-support-issue-directory]').count() === 1, 'Authenticated Issue directory is missing.');
    const desktopPanels = await page.locator('[data-support-service-main], [data-support-service-tools]').evaluateAll(
        (elements) => elements.map((element) => element.getBoundingClientRect())
    );
    const desktopRatio = desktopPanels[0]?.width / desktopPanels[1]?.width;
    assert(desktopPanels.length === 2 && desktopRatio > 1.4 && desktopRatio < 1.6, 'Support desktop panels do not render the approved 12/8 ratio.');

    let postCount = 0;
    page.on('request', (request) => {
        if (request.method() === 'POST' && request.url().includes('/company/yovel-east/admin/')) postCount += 1;
    });
    const opener = page.locator('[data-record-modal-open="support-issue-modal"]');
    await opener.click();
    const modal = page.locator('#support-issue-modal');
    await modal.locator('input[name="subject"]').fill('Browser retained Support issue');
    await modal.locator('textarea[name="description"]').fill('Browser confirmation and rehydration evidence.');
    await modal.locator('input[name="customer_key"]').fill('external-browser-customer');
    const submit = modal.locator('[data-confirm-submit-action]');
    const confirmation = page.locator('[data-confirm-dialog]');
    await submit.click();
    await confirmation.waitFor({ state: 'visible' });
    assert(postCount === 0, 'Issue POST occurred before Confirm.');
    assert(await modal.isVisible(), 'Opening confirmation displaced the body-owned Issue modal.');

    await confirmation.locator('[data-confirm-cancel]').click();
    assert(await modal.locator('input[name="subject"]').inputValue() === 'Browser retained Support issue', 'Confirmation Cancel discarded Issue values.');
    assert(await submit.evaluate((element) => element === document.activeElement), 'Confirmation Cancel did not restore focus to Submit.');

    await submit.click();
    await confirmation.waitFor({ state: 'visible' });
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        confirmation.locator('[data-confirm-action]').click(),
    ]);
    assert(postCount === 1, 'Issue Confirm did not issue exactly one POST.');
    await page.locator('#support-issue-modal').waitFor({ state: 'visible' });
    assert(await page.locator('#support-issue-modal').getAttribute('data-record-modal-open-on-load') !== null, 'Dependency failure did not reopen the Issue modal.');
    assert(await page.locator('#support-issue-modal input[name="subject"]').inputValue() === 'Browser retained Support issue', 'Server rejection lost the Issue subject.');
    assert(await page.locator('#support-issue-modal input[name="customer_key"]').inputValue() === 'external-browser-customer', 'Server rejection lost the owner reference.');
    assert((await page.locator('#support-issue-modal [role="alert"]').textContent()).includes('UNAVAILABLE_DEPENDENCY: sales-crm.customer-reference.v1'), 'Server rejection did not expose the exact unavailable contract.');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(baseUrl, { waitUntil: 'networkidle' });
    const mobileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    const mobilePanels = await page.locator('[data-support-service-main], [data-support-service-tools]').evaluateAll(
        (elements) => elements.map((element) => element.getBoundingClientRect())
    );
    assert(!mobileOverflow, 'Support Issue workspace overflows horizontally on mobile.');
    assert(mobilePanels.length === 2 && mobilePanels[1].top >= mobilePanels[0].bottom - 1, 'Support Issue workspace does not stack main-first on mobile.');

    console.log(`Support Issue browser checks passed: authenticated 12/8 ratio ${desktopRatio.toFixed(3)}, modal confirmation, zero pre-confirm POSTs, single Confirm POST, dependency rehydration, focus retention, and mobile stacking.`);
} finally {
    await browser.close();
}
