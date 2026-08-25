<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$workspace = (string) file_get_contents($root . '/company/admin/modules/accounting-finance/views/workspace.php');
$workspaceStylesPath = $root . '/company/admin/modules/accounting-finance/views/workspace-styles.php';

function workspace_assert(bool $value, string $message): void
{
    if (!$value) {
        throw new RuntimeException($message);
    }
}

$views = [
    'dashboard.php',
    'workspace.php',
    'masters.php',
    'invoices.php',
    'journal-entries.php',
    'payment-entries.php',
    'bank-reconciliation.php',
    'budgets.php',
    'period-closing.php',
    'general-ledger.php',
    'financial-report.php',
    'tax-reports.php',
];
$approvedLayout = 'xl:grid-cols-[minmax(0,12fr)_minmax(16rem,8fr)]';
$legacyLayout = 'xl:grid-cols-[minmax(0,12fr)_minmax(16rem,4fr)]';
foreach ($views as $view) {
    $path = $root . '/company/admin/modules/accounting-finance/views/' . $view;
    workspace_assert(is_file($path), 'Missing dedicated Finance view: ' . $view);
    $source = (string) file_get_contents($path);
    workspace_assert(str_contains($source, $approvedLayout), 'Finance view does not use the approved 12/8 layout: ' . $view);
    workspace_assert(!str_contains($source, $legacyLayout), 'Legacy 12/4 Finance layout remains: ' . $view);
}

workspace_assert(!str_contains($workspace, "'value' => 'Queued'"), 'Queued Finance placeholder remains.');
workspace_assert(str_contains($workspace, "'general-ledger'"), 'General Ledger route is missing.');
workspace_assert(is_file($root . '/company/admin/modules/accounting-finance/views/settings-modal.php'), 'Finance Settings modal is missing.');
workspace_assert(is_file($root . '/company/admin/modules/accounting-finance/views/lifecycle-modal.php'), 'Finance lifecycle modal is missing.');
workspace_assert(str_contains((string) file_get_contents($root . '/company/admin/modules/accounting-finance/views/payment-entries.php'), 'data-finance-allocation-row'), 'Payment allocation workspace is missing.');
workspace_assert(str_contains((string) file_get_contents($root . '/company/admin/modules/accounting-finance/views/bank-reconciliation.php'), 'data-finance-bank-match-open'), 'Bank match workspace is missing.');
workspace_assert(is_file($workspaceStylesPath), 'Finance runtime layout override is missing.');
$workspaceStyles = is_file($workspaceStylesPath) ? (string) file_get_contents($workspaceStylesPath) : '';
workspace_assert(str_contains($workspace, "require __DIR__ . '/workspace-styles.php'"), 'Finance workspace does not load its module-local layout boundary.');
workspace_assert(str_contains($workspaceStyles, 'grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr)'), 'Finance runtime CSS does not enforce the approved 12/8 desktop layout.');
workspace_assert(str_contains($workspaceStyles, '.yovel-finance-sticky-nav'), 'Finance runtime CSS does not make the section header sticky.');
workspace_assert(str_contains($workspaceStyles, '.yovel-finance-sticky-modal-header'), 'Finance runtime CSS does not keep modal headers sticky.');
workspace_assert(str_contains($workspaceStyles, '.yovel-finance-sticky-table-header'), 'Finance runtime CSS does not keep table headers sticky.');

foreach (['masters.php', 'invoices.php', 'journal-entries.php', 'payment-entries.php', 'bank-reconciliation.php', 'budgets.php', 'period-closing.php'] as $view) {
    $source = (string) file_get_contents($root . '/company/admin/modules/accounting-finance/views/' . $view);
    workspace_assert(str_contains($source, 'data-record-modal'), 'Operational form does not consume the shared record-modal contract: ' . $view);
    workspace_assert(str_contains($source, 'data-record-modal-open='), 'Operational Add/New command does not open a shared record modal: ' . $view);
    workspace_assert(str_contains($source, 'yovel-finance-sticky-modal-header'), 'Modal header is not sticky/opaque: ' . $view);
    workspace_assert(!str_contains($source, 'data-finance-modal-open='), 'Legacy Finance modal opener remains: ' . $view);
}

echo "Accounting/Finance workspace checks passed.\n";
