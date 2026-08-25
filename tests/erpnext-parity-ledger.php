<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require $projectRoot . '/tools/erpnext-parity.php';

function parity_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$baseline = erpnext_parity_load_baseline($projectRoot . '/docs/erpnext-parity/baseline.json');
$owners = erpnext_parity_load_owners($projectRoot . '/docs/erpnext-parity/owners.json');

parity_assert(
    ($baseline['erpnext']['commit'] ?? '') === '11e0ba0a1c45f217e2e73e885f699102d06da325',
    'ERPNext baseline commit is not pinned.'
);
parity_assert(
    ($baseline['hrms']['commit'] ?? '') === 'f281e8b172ac8836ad89c59df65a922101103097',
    'Frappe HR baseline commit is not pinned.'
);
parity_assert(count($owners['owners'] ?? []) === 11, 'Parity ownership does not cover eleven module tasks.');

$requiredOwners = [
    'hr',
    'accounting-finance',
    'sales-crm',
    'buying-procurement',
    'inventory-warehouse',
    'manufacturing',
    'projects',
    'support-service',
    'assets-maintenance',
    'operations',
    'compliance-localization',
];
parity_assert(
    array_keys($owners['owners']) === $requiredOwners,
    'Parity owners are missing or out of deterministic order.'
);

$validRow = [
    'source_id' => 'erpnext:erpnext/accounts/doctype/sales_invoice/sales_invoice.json:doctype:Sales Invoice',
    'source_repository' => 'erpnext',
    'source_commit' => (string) $baseline['erpnext']['commit'],
    'source_path' => 'erpnext/accounts/doctype/sales_invoice/sales_invoice.json',
    'source_kind' => 'doctype',
    'module_owner' => 'accounting-finance',
    'builderx_target' => 'accounting-finance/sales-invoices',
    'dependencies' => ['sales-crm', 'inventory-warehouse'],
    'status' => 'MISSING',
    'evidence' => [],
    'reason' => '',
];

$validErrors = erpnext_parity_validate_ledger([$validRow], $owners);
parity_assert($validErrors === [], 'A valid parity ledger row was rejected: ' . implode(' ', $validErrors));

$invalidStatus = $validRow;
$invalidStatus['status'] = 'QUEUED';
$invalidStatusErrors = erpnext_parity_validate_ledger([$invalidStatus], $owners);
parity_assert(
    count(array_filter($invalidStatusErrors, static fn (string $error): bool => str_contains($error, 'unsupported status'))) === 1,
    'Unsupported parity status was not rejected.'
);

$invalidDeferred = $validRow;
$invalidDeferred['status'] = 'DEFERRED';
$invalidDeferred['reason'] = 'Maybe later';
$invalidDeferredErrors = erpnext_parity_validate_ledger([$invalidDeferred], $owners);
parity_assert(
    count(array_filter($invalidDeferredErrors, static fn (string $error): bool => str_contains($error, 'Approved Android exclusion'))) === 1,
    'Unapproved non-Android deferral was not rejected.'
);

$duplicateErrors = erpnext_parity_validate_ledger([$validRow, $validRow], $owners);
parity_assert(
    count(array_filter($duplicateErrors, static fn (string $error): bool => str_contains($error, 'duplicate source_id'))) === 1,
    'Duplicate parity source identifiers were not rejected.'
);

function parity_remove_fixture(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}

function parity_write_fixture_json(string $root, string $path, array $payload): void
{
    $target = $root . '/' . $path;
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0777, true) && !is_dir(dirname($target))) {
        throw new RuntimeException('Could not create parity fixture directory.');
    }
    file_put_contents($target, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

$fixtureRoot = sys_get_temp_dir() . '/builderx-parity-' . bin2hex(random_bytes(6));
$erpnextFixture = $fixtureRoot . '/erpnext';
$hrmsFixture = $fixtureRoot . '/hrms';

try {
    parity_write_fixture_json($erpnextFixture, 'erpnext/accounts/doctype/sales_invoice/sales_invoice.json', [
        'doctype' => 'DocType',
        'name' => 'Sales Invoice',
        'module' => 'Accounts',
    ]);
    parity_write_fixture_json($erpnextFixture, 'erpnext/selling/workspace/selling/selling.json', [
        'doctype' => 'Workspace',
        'label' => 'Selling',
        'module' => 'Selling',
    ]);
    parity_write_fixture_json($erpnextFixture, 'erpnext/stock/report/stock_balance/stock_balance.json', [
        'doctype' => 'Report',
        'name' => 'Stock Balance',
        'module' => 'Stock',
    ]);
    parity_write_fixture_json($erpnextFixture, 'erpnext/manufacturing/page/production_analytics/production_analytics.json', [
        'doctype' => 'Page',
        'name' => 'Production Analytics',
        'module' => 'Manufacturing',
    ]);
    $reportPython = $erpnextFixture . '/erpnext/projects/report/project_profitability/project_profitability.py';
    if (!is_dir(dirname($reportPython)) && !mkdir(dirname($reportPython), 0777, true) && !is_dir(dirname($reportPython))) {
        throw new RuntimeException('Could not create parity Python fixture directory.');
    }
    file_put_contents($reportPython, "# metadata filename fixture only\n");
    parity_write_fixture_json($hrmsFixture, 'hrms/payroll/doctype/salary_slip/salary_slip.json', [
        'doctype' => 'DocType',
        'name' => 'Salary Slip',
        'module' => 'Payroll',
    ]);

    $discovered = erpnext_parity_discover($erpnextFixture, $hrmsFixture, $baseline);
    parity_assert(count($discovered) === 6, 'Parity discovery did not find the six structured fixture artifacts.');
    parity_assert(
        $discovered[0]['source_id'] === 'erpnext:erpnext/accounts/doctype/sales_invoice/sales_invoice.json:doctype:Sales Invoice',
        'Parity discovery did not produce deterministic source identifiers.'
    );
    parity_assert(
        count(array_filter(
            $discovered,
            static fn (array $row): bool => $row['source_id'] === 'hrms:hrms/payroll/doctype/salary_slip/salary_slip.json:doctype:Salary Slip'
        )) === 1,
        'Frappe HR source paths were not normalized relative to the repository root.'
    );

    $partitions = erpnext_parity_partition($discovered, $owners);
    parity_assert(count($partitions) === 11, 'Parity partitioning did not create all eleven owner buckets.');
    parity_assert(count($partitions['accounting-finance']) === 1, 'Accounts metadata was not assigned to Finance.');
    parity_assert(count($partitions['sales-crm']) === 1, 'Selling metadata was not assigned to Sales/CRM.');
    parity_assert(count($partitions['inventory-warehouse']) === 1, 'Stock metadata was not assigned to Inventory.');
    parity_assert(count($partitions['manufacturing']) === 1, 'Manufacturing metadata was not assigned to Manufacturing.');
    parity_assert(count($partitions['projects']) === 1, 'Project report metadata was not assigned to Projects.');
    parity_assert(count($partitions['hr']) === 1, 'Frappe HR metadata was not assigned to HR.');
} finally {
    parity_remove_fixture($fixtureRoot);
}

echo "ERPNext parity baseline tests passed.\n";
