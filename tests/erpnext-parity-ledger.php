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

echo "ERPNext parity baseline tests passed.\n";
