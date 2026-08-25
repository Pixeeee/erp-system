const { execFileSync } = require('node:child_process');
const path = require('node:path');
const workerPath = require.main.filename;
const nodeModulesMarker = `${path.sep}node_modules${path.sep}`;
const markerIndex = workerPath.lastIndexOf(nodeModulesMarker);
if (markerIndex < 0) {
  throw new Error('Unable to locate the Playwright package root.');
}
const runnerNodeModules = workerPath.slice(0, markerIndex + `${path.sep}node_modules`.length);
const { test, expect } = require(path.join(runnerNodeModules, '@playwright', 'test'));

const root = path.resolve(__dirname, '..');

function renderWorkspace(formState = null) {
  const encodedState = formState ? Buffer.from(JSON.stringify(formState)).toString('base64') : '';
  const php = String.raw`
    require 'app/foundation.php';
    require 'company/admin/core/functions.php';
    require 'company/admin/modules/shared/registry.php';
    require 'company/admin/modules/shared/forms.php';
    require 'company/admin/modules/inventory-warehouse/functions.php';
    $scope = bx_db()->GetRow("SELECT c.company_key,c.company_key_hash,c.company_name,a.admin_key,a.admin_status FROM project_company c JOIN project_company_admin a ON a.company_key_hash=c.company_key_hash AND a.admin_status='ACTIVE' WHERE c.company_status='ACTIVE' ORDER BY c.x_id,a.x_id LIMIT 1");
    $company = ['company_key'=>(string)$scope['company_key'],'company_key_hash'=>(string)$scope['company_key_hash'],'company_name'=>(string)$scope['company_name']];
    $admin = ['admin_key'=>(string)$scope['admin_key'],'admin_status'=>(string)$scope['admin_status']];
    $activeModuleSections = yovel_admin_inventory_warehouse_sections();
    $activeModuleSection = 'form-builder';
    $activeModuleData = yovel_admin_inventory_warehouse_data($company, $admin, 'form-builder');
    $encodedState = getenv('INVENTORY_FORM_STATE');
    if (is_string($encodedState) && $encodedState !== '') { $activeModuleData['form_state'] = json_decode(base64_decode($encodedState), true, 512, JSON_THROW_ON_ERROR); }
    $companyName = (string)$company['company_name'];
    ob_start();
    require 'company/admin/modules/inventory-warehouse/views/workspace.php';
    require 'company/admin/views/partials/confirm-dialog.php';
    $body = ob_get_clean();
    $script = file_get_contents('company/admin/assets/js/admin-modal.js');
    echo '<!doctype html><html><head><meta charset="utf-8"><style>[hidden]{display:none!important}body{font-family:sans-serif}.grid{display:grid}.fixed{position:fixed}.inset-0{inset:0}.overflow-y-auto{overflow-y:auto}</style></head><body>'.$body.'<script>'.$script.'</script></body></html>';
  `;

  return execFileSync('php', ['-r', php], {
    cwd: root,
    encoding: 'utf8',
    env: { ...process.env, INVENTORY_FORM_STATE: encodedState },
  });
}

function renderItemsWorkspace(formState = null) {
  const encodedState = formState ? Buffer.from(JSON.stringify(formState)).toString('base64') : '';
  const php = String.raw`
    require 'app/foundation.php';
    require 'company/admin/core/functions.php';
    require 'company/admin/modules/shared/registry.php';
    require 'company/admin/modules/shared/forms.php';
    require 'company/admin/modules/inventory-warehouse/functions.php';
    $scope = bx_db()->GetRow("SELECT c.company_key,c.company_key_hash,c.company_name,a.admin_key,a.admin_status FROM project_company c JOIN project_company_admin a ON a.company_key_hash=c.company_key_hash AND a.admin_status='ACTIVE' WHERE c.company_status='ACTIVE' ORDER BY c.x_id,a.x_id LIMIT 1");
    $company = ['company_key'=>(string)$scope['company_key'],'company_key_hash'=>(string)$scope['company_key_hash'],'company_name'=>(string)$scope['company_name']];
    $admin = ['admin_key'=>(string)$scope['admin_key'],'admin_status'=>(string)$scope['admin_status']];
    $activeModuleSections = yovel_admin_inventory_warehouse_sections();
    $activeModuleSection = 'items';
    $activeModuleData = yovel_admin_inventory_warehouse_data($company, $admin, 'items');
    $encodedState = getenv('INVENTORY_FORM_STATE');
    $activeModuleFormState = is_string($encodedState) && $encodedState !== '' ? json_decode(base64_decode($encodedState), true, 512, JSON_THROW_ON_ERROR) : [];
    $companyName = (string)$company['company_name'];
    ob_start();
    require 'company/admin/modules/inventory-warehouse/views/workspace.php';
    require 'company/admin/views/partials/confirm-dialog.php';
    $body = ob_get_clean();
    $script = file_get_contents('company/admin/assets/js/admin-modal.js');
    echo '<!doctype html><html><head><meta charset="utf-8"><style>[hidden]{display:none!important}body{font-family:sans-serif}.grid{display:grid}.fixed{position:fixed}.inset-0{inset:0}.overflow-y-auto{overflow-y:auto}</style></head><body>'.$body.'<script>'.$script.'</script></body></html>';
  `;
  return execFileSync('php', ['-r', php], { cwd: root, encoding: 'utf8', env: { ...process.env, INVENTORY_FORM_STATE: encodedState } });
}

function renderWarehouseWorkspace(section = 'warehouses', formState = null) {
  const encodedState = formState ? Buffer.from(JSON.stringify(formState)).toString('base64') : '';
  const php = String.raw`
    require 'app/foundation.php';
    require 'company/admin/core/functions.php';
    require 'company/admin/modules/shared/registry.php';
    require 'company/admin/modules/shared/forms.php';
    require 'company/admin/modules/inventory-warehouse/functions.php';
    $scope = bx_db()->GetRow("SELECT c.company_key,c.company_key_hash,c.company_name,a.admin_key,a.admin_status FROM project_company c JOIN project_company_admin a ON a.company_key_hash=c.company_key_hash AND a.admin_status='ACTIVE' WHERE c.company_status='ACTIVE' ORDER BY c.x_id,a.x_id LIMIT 1");
    $company = ['company_key'=>(string)$scope['company_key'],'company_key_hash'=>(string)$scope['company_key_hash'],'company_name'=>(string)$scope['company_name']];
    $admin = ['admin_key'=>(string)$scope['admin_key'],'admin_status'=>(string)$scope['admin_status']];
    $section = getenv('INVENTORY_SECTION') ?: 'warehouses';
    $activeModuleSections = yovel_admin_inventory_warehouse_sections();
    $activeModuleSection = $section;
    $activeModuleData = yovel_admin_inventory_warehouse_data($company, $admin, $section);
    $encodedState = getenv('INVENTORY_FORM_STATE');
    $activeModuleFormState = is_string($encodedState) && $encodedState !== '' ? json_decode(base64_decode($encodedState), true, 512, JSON_THROW_ON_ERROR) : [];
    $companyName = (string)$company['company_name'];
    ob_start();
    require 'company/admin/modules/inventory-warehouse/views/workspace.php';
    require 'company/admin/views/partials/confirm-dialog.php';
    $body = ob_get_clean();
    $script = file_get_contents('company/admin/assets/js/admin-modal.js');
    echo '<!doctype html><html><head><meta charset="utf-8"><style>[hidden]{display:none!important}body{font-family:sans-serif}.grid{display:grid}.fixed{position:fixed}.inset-0{inset:0}.overflow-y-auto{overflow-y:auto}</style></head><body>'.$body.'<script>'.$script.'</script></body></html>';
  `;
  return execFileSync('php', ['-r', php], { cwd: root, encoding: 'utf8', env: { ...process.env, INVENTORY_SECTION: section, INVENTORY_FORM_STATE: encodedState } });
}

test('Inventory Form Builder uses shared modal and confirmation behavior', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.setContent(renderWorkspace());

  const opener = page.getByRole('button', { name: /Insert field/i });
  await expect(opener).toBeVisible();
  await opener.click();

  const modal = page.locator('[data-record-modal]');
  const labelInput = modal.locator('input[name="new_field_label"]');
  const submit = modal.locator('[data-confirm-submit-action]');
  await expect(modal).toBeVisible();
  await labelInput.fill('Retained field value');

  await page.evaluate(() => {
    window.__inventorySubmitCount = 0;
    document.querySelector('[data-record-modal-form]').addEventListener('submit', (event) => {
      if (event.currentTarget.dataset.confirmed === 'true') {
        window.__inventorySubmitCount += 1;
        event.preventDefault();
      }
    }, true);
  });

  await submit.click();
  const confirmation = page.locator('[data-confirm-dialog]');
  await expect(confirmation).toBeVisible();
  await expect(modal).toHaveAttribute('inert', '');
  await expect.poll(() => page.evaluate(() => window.__inventorySubmitCount)).toBe(0);

  await confirmation.locator('[data-confirm-cancel]').click();
  await expect(confirmation).toBeHidden();
  await expect(modal).toBeVisible();
  await expect(labelInput).toHaveValue('Retained field value');
  await expect(submit).toBeFocused();

  await submit.click();
  await confirmation.locator('[data-confirm-action]').click();
  await expect.poll(() => page.evaluate(() => window.__inventorySubmitCount)).toBe(1);
});

test('Inventory server error reopens and rehydrates the record modal', async ({ page }) => {
  await page.setContent(renderWorkspace({
    open: true,
    record_type: 'ITEM',
    error: 'Field labels must be 180 characters or fewer.',
    new_field_label: 'Still here',
    schema_json: '{}',
  }));

  const modal = page.locator('[data-record-modal]');
  await expect(modal).toBeVisible();
  await expect(modal.locator('[role="alert"]')).toContainText('180 characters');
  await expect(modal.locator('input[name="new_field_label"]')).toHaveValue('Still here');
});

test('Inventory workspace keeps stable main-first 12/8 structure on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.setContent(renderWorkspace());

  const workspace = page.locator('[data-inventory-workspace]');
  await expect(workspace.locator('[data-inventory-main]')).toHaveAttribute('data-grid-span', '12');
  await expect(workspace.locator('[data-inventory-tools]')).toHaveAttribute('data-grid-span', '8');
  const order = await workspace.locator('[data-inventory-main], [data-inventory-tools]').evaluateAll((nodes) => nodes.map((node) => node.getAttribute('data-grid-span')));
  expect(order).toEqual(['12', '8']);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
});

test('Inventory workspace renders side-by-side at the desktop 12/8 ratio', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.setContent(renderWorkspace());

  const geometry = await page.locator('[data-inventory-workspace]').evaluate((workspace) => {
    const main = workspace.querySelector('[data-inventory-main]').getBoundingClientRect();
    const tools = workspace.querySelector('[data-inventory-tools]').getBoundingClientRect();
    return {
      sameRow: Math.abs(main.top - tools.top) < 1,
      mainShare: main.width / (main.width + tools.width),
      toolsShare: tools.width / (main.width + tools.width),
    };
  });
  expect(geometry.sameRow).toBe(true);
  expect(geometry.mainShare).toBeCloseTo(0.6, 1);
  expect(geometry.toolsShare).toBeCloseTo(0.4, 1);
});

test('Inventory catalogue create actions use modal then sibling confirmation', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.setContent(renderItemsWorkspace());
  for (const name of ['Add Item', 'Add Item Price']) {
    const opener = page.getByRole('button', { name, exact: true });
    await expect(opener).toBeVisible();
    await opener.click();
    const modal = page.locator('[data-record-modal]:visible');
    await expect(modal.locator('input[name="module_view"]')).toHaveValue('inventory-warehouse');
    await modal.getByRole('button', { name: 'Cancel', exact: true }).click();
  }

  await page.getByRole('button', { name: 'Add Item', exact: true }).click();
  const itemModal = page.locator('[data-record-modal]:visible');
  await itemModal.locator('input[name="item_code"]').fill('RETAINED-ITEM');
  await itemModal.locator('input[name="item_name"]').fill('Retained item name');
  await itemModal.locator('[data-confirm-submit-action]').click();
  const confirmation = page.locator('[data-confirm-dialog]');
  await expect(confirmation).toBeVisible();
  await expect(itemModal).toHaveAttribute('inert', '');
  expect(await page.evaluate(() => document.querySelector('form[data-record-modal-form]').dataset.confirmed || '')).toBe('');
  await confirmation.locator('[data-confirm-cancel]').click();
  await expect(itemModal.locator('input[name="item_code"]')).toHaveValue('RETAINED-ITEM');
  await expect(itemModal.locator('[data-confirm-submit-action]')).toBeFocused();
});

test('Inventory item price errors reopen and rehydrate the price modal', async ({ page }) => {
  await page.setContent(renderItemsWorkspace({
    section: 'items', action: 'save_inventory_item_price', error: 'Item price validity range is invalid.',
    input: { item_price_key: '', item_key: '', price_list_code: 'RETAINED-PRICE', price_list_name: 'Retained price list', currency_code: 'PHP', uom_code: 'EA', rate: '99.50', minimum_qty: '1', valid_from: '2026-12-31', valid_to: '2026-01-01', is_selling: '1' },
  }));
  const modal = page.locator('[data-record-modal]:visible');
  await expect(modal.locator('[role="alert"]')).toContainText('validity');
  await expect(modal.locator('input[name="price_list_code"]')).toHaveValue('RETAINED-PRICE');
  await expect(modal.locator('input[name="rate"]')).toHaveValue('99.50');
});

test('Inventory catalogue server errors reopen the matching populated modal', async ({ page }) => {
  await page.setContent(renderItemsWorkspace({
    section: 'items', action: 'save_inventory_item', error: 'Barcode checksum is invalid.',
    input: { item_code: 'ERROR-ITEM', item_name: 'Still populated', stock_uom_code: 'EA' },
  }));
  const modal = page.locator('[data-record-modal]:visible');
  await expect(modal.locator('[role="alert"]')).toContainText('checksum');
  await expect(modal.locator('input[name="item_code"]')).toHaveValue('ERROR-ITEM');
  await expect(modal.locator('input[name="item_name"]')).toHaveValue('Still populated');
});

test('Inventory warehouse mutation waits for sibling confirmation and cancel retains focus and values', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.setContent(renderWarehouseWorkspace());
  const opener = page.getByRole('button', { name: 'Add Warehouse', exact: true });
  await expect(opener).toBeVisible();
  await opener.click();
  const modal = page.locator('[data-record-modal]:visible');
  await expect(modal.locator('input[name="module_view"]')).toHaveValue('inventory-warehouse');
  await modal.locator('input[name="warehouse_code"]').fill('RETAINED-WH');
  await modal.locator('input[name="warehouse_name"]').fill('Retained warehouse');
  await page.evaluate(() => {
    window.__warehouseSubmitCount = 0;
    document.querySelector('[data-record-modal]:not([hidden]) form').addEventListener('submit', (event) => {
      if (event.currentTarget.dataset.confirmed === 'true') {
        window.__warehouseSubmitCount += 1;
        event.preventDefault();
      }
    }, true);
  });
  const submit = modal.locator('[data-confirm-submit-action]');
  await submit.click();
  const confirmation = page.locator('[data-confirm-dialog]');
  await expect(confirmation).toBeVisible();
  await expect.poll(() => page.evaluate(() => window.__warehouseSubmitCount)).toBe(0);
  await confirmation.locator('[data-confirm-cancel]').click();
  await expect(modal.locator('input[name="warehouse_code"]')).toHaveValue('RETAINED-WH');
  await expect(submit).toBeFocused();
  await submit.click();
  await confirmation.locator('[data-confirm-action]').click();
  await expect.poll(() => page.evaluate(() => window.__warehouseSubmitCount)).toBe(1);
});

test('Inventory warehouse tools expose modal-backed create actions across IW-03 sections', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.setContent(renderWarehouseWorkspace('warehouses'));
  for (const name of ['Add Warehouse', 'Add Warehouse Type', 'Add Inventory Dimension', 'Configure Stock Settings']) {
    const opener = page.getByRole('button', { name, exact: true });
    await expect(opener).toBeVisible();
    await opener.click();
    await expect(page.locator('[data-record-modal]:visible')).toBeVisible();
    await page.locator('[data-record-modal]:visible').getByRole('button', { name: 'Cancel', exact: true }).click();
  }
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);

  await page.setContent(renderWarehouseWorkspace('putaway'));
  await expect(page.getByRole('button', { name: 'Add Putaway Rule', exact: true })).toBeVisible();
  await page.setContent(renderWarehouseWorkspace('reorder-levels'));
  await expect(page.getByRole('button', { name: 'Add Reorder Rule', exact: true })).toBeVisible();
});

test('Inventory warehouse server errors reopen and rehydrate on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.setContent(renderWarehouseWorkspace('warehouses', {
    section: 'warehouses', action: 'save_inventory_warehouse', error: 'Warehouse capacity is invalid.',
    input: { warehouse_key: '', warehouse_code: 'RETAINED-WH', warehouse_name: 'Retained mobile warehouse', capacity_qty: 'bad', putaway_priority: '10' },
  }));
  const modal = page.locator('[data-record-modal]:visible');
  await expect(modal.locator('[role="alert"]')).toContainText('capacity');
  await expect(modal.locator('input[name="warehouse_code"]')).toHaveValue('RETAINED-WH');
  await expect(modal.locator('input[name="warehouse_name"]')).toHaveValue('Retained mobile warehouse');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
});
