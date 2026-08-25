import { chromium } from '../frontend/node_modules/playwright/index.mjs';

const baseUrl = process.env.ERP_DASHBOARD_BASE_URL || 'http://localhost/erpsystem/company/yovel-east/admin/';
const login = process.env.ERP_DASHBOARD_ADMIN_LOGIN || 'admin';
const password = process.env.ERP_DASHBOARD_ADMIN_PASSWORD || 'admin12345';
const modules = [
    ['hr', '.yovel-hr-dashboard-setup'],
    ['accounting-finance', '.yovel-finance-dashboard-setup'],
    ['sales-crm', '[data-sales-dashboard-summary]'],
    ['buying-procurement', '[data-buying-dashboard]'],
    ['inventory-warehouse', '[data-inventory-dashboard]'],
    ['manufacturing', '[data-manufacturing-dashboard-summary]'],
    ['projects', '[data-projects-dashboard]'],
    ['support-service', '[data-support-dashboard]'],
    ['assets-maintenance', '[data-assets-dashboard]'],
    ['operations', '[data-operations-dashboard]'],
    ['compliance-localization', '[data-compliance-live-dashboard]'],
];

const assert = (condition, message) => {
    if (!condition) throw new Error(message);
};

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });

try {
    await page.goto(`${baseUrl}?view=dashboard`, { waitUntil: 'networkidle' });
    if (await page.locator('input[name="login"]').count()) {
        await page.locator('input[name="login"]').fill(login);
        await page.locator('input[name="password"]').fill(password);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.locator('button[type="submit"]').click(),
        ]);
    }

    const failures = [];
    for (const [view, marker] of modules) {
        await page.setViewportSize({ width: 1440, height: 1000 });
        const response = await page.goto(`${baseUrl}?view=${view}`, { waitUntil: 'networkidle' });
        const body = await page.locator('body').innerText();
        if (!response || response.status() !== 200) failures.push(`${view} dashboard did not return HTTP 200.`);
        if (/Fatal error|Parse error|Uncaught (?:Error|Exception)/i.test(body)) failures.push(`${view} dashboard rendered a PHP failure.`);
        if (await page.locator(marker).count() !== 1) failures.push(`${view} did not open its dashboard by default.`);
        if (!await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)) failures.push(`${view} dashboard overflows horizontally on desktop.`);

        await page.setViewportSize({ width: 390, height: 844 });
        await page.reload({ waitUntil: 'networkidle' });
        if (await page.locator(marker).count() !== 1) failures.push(`${view} dashboard disappeared on mobile.`);
        if (!await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)) failures.push(`${view} dashboard overflows horizontally on mobile.`);
    }

    assert(failures.length === 0, failures.join('\n'));

    console.log(`Company admin dashboard browser matrix passed: ${modules.length} desktop and mobile routes.`);
} finally {
    await browser.close();
}
