import { chromium } from '../frontend/node_modules/playwright/index.mjs';

const rootUrl = process.env.SALES_CRM_BASE_URL || 'http://localhost/erpsystem/company/yovel-east/admin/?view=sales-crm';
const login = process.env.SALES_CRM_ADMIN_LOGIN || 'admin';
const password = process.env.SALES_CRM_ADMIN_PASSWORD || 'admin12345';
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
const expect = (condition, message) => { if (!condition) throw new Error(message); };
const sectionUrl = (section) => `${rootUrl}&section=${section}`;
let createdLeadCode = '';

try {
    await page.goto(sectionUrl('leads'), { waitUntil: 'networkidle' });
    if (await page.locator('input[name="login"]').count()) {
        await page.locator('input[name="login"]').fill(login);
        await page.locator('input[name="password"]').fill(password);
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.locator('button[type="submit"]').click()]);
        await page.goto(sectionUrl('leads'), { waitUntil: 'networkidle' });
    }

    let postCount = 0;
    page.on('request', (request) => { if (request.method() === 'POST' && request.url().includes('/company/yovel-east/admin/')) postCount += 1; });
    if (await page.locator('#yovel-sales-lead-note-modal select[name="subject_key"] option').count() === 0) {
        createdLeadCode = `SC03_BROWSER_${Date.now()}`;
        await page.locator('[data-record-modal-open="yovel-sales-lead-modal"]').click();
        const leadModal = page.locator('#yovel-sales-lead-modal');
        await leadModal.waitFor({ state: 'visible' });
        await leadModal.locator('#sales_lead_code').fill(createdLeadCode);
        await leadModal.locator('#sales_lead_name').fill('SC-03 Browser Fixture');
        await leadModal.locator('#sales_lead_status').selectOption('OPEN');
        await leadModal.locator('[data-confirm-submit-action]').click();
        const seedConfirmation = page.locator('[data-confirm-dialog]');
        await seedConfirmation.waitFor({ state: 'visible' });
        expect(postCount === 0, 'Lead fixture POST occurred before Confirm.');
        await Promise.all([page.waitForLoadState('networkidle'), seedConfirmation.locator('[data-confirm-action]').click()]);
        expect(postCount === 1, 'Lead fixture Confirm did not issue exactly one POST.');
        postCount = 0;
    }

    expect(await page.locator('[data-sales-lead-operations]').count() === 1, 'Lead operations surface is missing.');
    expect(await page.locator('[data-sales-lead-timeline]').count() <= 1, 'Lead timeline duplicated.');
    expect(await page.locator('#yovel-sales-form-builder').count() === 1, 'Lead Form Builder is missing.');
    const desktopBoxes = await page.locator('.yovel-sales-crm-two-panel > section, .yovel-sales-crm-two-panel > aside').evaluateAll((elements) => elements.map((element) => element.getBoundingClientRect()));
    const desktopRatio = desktopBoxes[0]?.width / desktopBoxes[1]?.width;
    expect(desktopBoxes.length === 2 && desktopRatio > 1.4 && desktopRatio < 1.6, 'SC-03 Leads workspace lost the 12/8 layout.');
    expect(await page.locator('#yovel-sales-lead-note-modal select[name="subject_key"] option').count() > 0, 'SC-03 browser test requires an existing Lead.');
    const editLead = page.locator('a[href*="section=leads"][href*="edit="]').first();
    if (await editLead.count()) {
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), editLead.click()]);
        await page.locator('#yovel-sales-lead-modal').waitFor({ state: 'visible' });
        expect(await page.locator('#yovel-sales-lead-modal input[name="lead_key"]').inputValue() !== '', 'Existing Lead Edit did not bind its stable key.');
        await page.locator('#yovel-sales-lead-modal [data-record-modal-close]').last().click();
    }

    const confirmation = page.locator('[data-confirm-dialog]');
    const noteOpen = page.locator('[data-record-modal-open="yovel-sales-lead-note-modal"]');
    await noteOpen.click();
    const noteModal = page.locator('#yovel-sales-lead-note-modal');
    await noteModal.waitFor({ state: 'visible' });
    await noteModal.locator('textarea[name="note_text"]').fill('SC-03 retained browser Note');
    const noteSubmit = noteModal.locator('[data-confirm-submit-action]');
    await noteSubmit.click();
    await confirmation.waitFor({ state: 'visible' });
    expect(postCount === 0, 'CRM Note POST occurred before Confirm.');
    await confirmation.locator('[data-confirm-cancel]').click();
    expect(await noteModal.locator('textarea[name="note_text"]').inputValue() === 'SC-03 retained browser Note', 'CRM Note Cancel discarded values.');
    expect(await noteSubmit.evaluate((element) => element === document.activeElement), 'CRM Note Cancel did not restore submit focus.');
    await noteModal.locator('select[name="subject_key"]').evaluate((element) => { element.innerHTML = '<option value="00000000-0000-4000-8000-000000000003">Missing Lead</option>'; });
    await noteSubmit.click();
    await confirmation.waitFor({ state: 'visible' });
    await Promise.all([page.waitForLoadState('networkidle'), confirmation.locator('[data-confirm-action]').click()]);
    expect(postCount === 1, 'CRM Note Confirm did not issue exactly one POST.');
    await page.locator('#yovel-sales-lead-note-modal').waitFor({ state: 'visible' });
    expect(await page.locator('#yovel-sales-lead-note-modal textarea[name="note_text"]').inputValue() === 'SC-03 retained browser Note', 'CRM Note server validation did not rehydrate values.');
    await page.locator('#yovel-sales-lead-note-modal [data-record-modal-close]').last().click();

    const convertOpen = page.locator('[data-record-modal-open="yovel-sales-lead-convert-modal"]');
    await convertOpen.click();
    const convertModal = page.locator('#yovel-sales-lead-convert-modal');
    await convertModal.waitFor({ state: 'visible' });
    await convertModal.locator('select[name="lead_key"]').evaluate((element) => { element.innerHTML = '<option value="00000000-0000-4000-8000-000000000004">Missing Lead</option>'; });
    const convertSubmit = convertModal.locator('[data-confirm-submit-action]');
    await convertSubmit.click();
    await confirmation.waitFor({ state: 'visible' });
    expect(postCount === 1, 'Lead conversion POST occurred before Confirm.');
    await Promise.all([page.waitForLoadState('networkidle'), confirmation.locator('[data-confirm-action]').click()]);
    expect(postCount === 2, 'Lead conversion Confirm did not issue exactly one POST.');
    await page.locator('#yovel-sales-lead-convert-modal').waitFor({ state: 'visible' });
    expect(await page.locator('#yovel-sales-lead-convert-modal select[name="target_type"]').inputValue() === 'BOTH', 'Lead conversion server validation did not rehydrate the target.');

    await page.goto(sectionUrl('prospects'), { waitUntil: 'networkidle' });
    expect(await page.locator('[data-record-modal-open="yovel-sales-prospect-modal"]').count() === 1, 'Add Prospect modal opener is missing.');
    expect(await page.locator('#yovel-sales-form-builder').count() === 1, 'Prospect Form Builder is missing.');
    await page.locator('[data-record-modal-open="yovel-sales-prospect-modal"]').click();
    const prospectModal = page.locator('#yovel-sales-prospect-modal');
    await prospectModal.waitFor({ state: 'visible' });
    await prospectModal.locator('#sales_prospect_code').fill('!');
    await prospectModal.locator('#sales_prospect_name').fill('SC-03 retained Prospect');
    await prospectModal.locator('#sales_prospect_status').selectOption('OPEN');
    const prospectSubmit = prospectModal.locator('[data-confirm-submit-action]');
    const prospectBaseline = postCount;
    await prospectSubmit.click();
    await confirmation.waitFor({ state: 'visible' });
    expect(postCount === prospectBaseline, 'Prospect POST occurred before Confirm.');
    await Promise.all([page.waitForLoadState('networkidle'), confirmation.locator('[data-confirm-action]').click()]);
    expect(postCount === prospectBaseline + 1, 'Prospect Confirm did not issue exactly one POST.');
    await page.locator('#yovel-sales-prospect-modal').waitFor({ state: 'visible' });
    expect(await page.locator('#sales_prospect_name').inputValue() === 'SC-03 retained Prospect', 'Prospect server validation did not rehydrate values.');

    await page.goto(sectionUrl('appointments'), { waitUntil: 'networkidle' });
    expect(await page.locator('[data-record-modal-open="yovel-sales-appointment-modal"]').count() === 1, 'Add Appointment modal opener is missing.');
    expect(await page.locator('[data-record-modal-open="yovel-sales-appointment-slot-modal"]').count() === 1, 'Add booking slot modal opener is missing.');
    expect(await page.locator('#yovel-sales-form-builder').count() === 1, 'Appointment Form Builder is missing.');
    await page.locator('[data-record-modal-open="yovel-sales-appointment-modal"]').click();
    const appointmentModal = page.locator('#yovel-sales-appointment-modal');
    await appointmentModal.waitFor({ state: 'visible' });
    await appointmentModal.locator('#sales_appointment_code').fill('!');
    expect(await appointmentModal.locator('#sales_appointment_status').inputValue() === 'SCHEDULED', 'New Appointment did not default to SCHEDULED.');
    await appointmentModal.locator('#sales_starts_at').fill('2026-09-15T09:00');
    await appointmentModal.locator('#sales_ends_at').fill('2026-09-15T09:30');
    if (await appointmentModal.locator('#sales_lead_key option').count() > 1) await appointmentModal.locator('#sales_lead_key').selectOption({ index: 1 });
    const appointmentSubmit = appointmentModal.locator('[data-confirm-submit-action]');
    const appointmentBaseline = postCount;
    await appointmentSubmit.click();
    await confirmation.waitFor({ state: 'visible' });
    expect(postCount === appointmentBaseline, 'Appointment POST occurred before Confirm.');
    await confirmation.locator('[data-confirm-cancel]').click();
    expect(await appointmentModal.locator('#sales_starts_at').inputValue() === '2026-09-15T09:00', 'Appointment Cancel discarded values.');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload({ waitUntil: 'networkidle' });
    expect(!await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth), 'SC-03 Appointment workspace has mobile horizontal overflow.');
    const mobileBoxes = await page.locator('.yovel-sales-crm-two-panel > section, .yovel-sales-crm-two-panel > aside').evaluateAll((elements) => elements.map((element) => element.getBoundingClientRect()));
    expect(mobileBoxes.length === 2 && mobileBoxes[1].top >= mobileBoxes[0].bottom - 1, 'SC-03 panels do not stack main-first on mobile.');

    console.log(`Sales / CRM SC-03 browser checks passed: 12/8 ratio ${desktopRatio.toFixed(3)}, Lead Note and conversion confirmations/rehydration, Prospect and Appointment schema modals, Form Builder access, and mobile stacking.`);
} finally {
    if (createdLeadCode !== '') {
        try {
            await page.setViewportSize({ width: 1440, height: 1000 });
            await page.goto(sectionUrl('leads'), { waitUntil: 'networkidle' });
            const fixtureRow = page.locator('tbody tr').filter({ hasText: createdLeadCode });
            if (await fixtureRow.count()) {
                await fixtureRow.getByRole('button', { name: 'Delete', exact: true }).click();
                const cleanupConfirmation = page.locator('[data-confirm-dialog]');
                await cleanupConfirmation.waitFor({ state: 'visible' });
                await Promise.all([page.waitForLoadState('networkidle'), cleanupConfirmation.locator('[data-confirm-action]').click()]);
            }
        } catch {
            console.error(`SC-03 browser fixture cleanup failed for ${createdLeadCode}.`);
        }
    }
    await browser.close();
}
