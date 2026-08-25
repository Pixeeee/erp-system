<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/foundation.php';
require_once dirname(__DIR__) . '/core/functions.php';
require_once dirname(__DIR__) . '/modules/shared/registry.php';
require_once dirname(__DIR__) . '/modules/shared/forms.php';
require_once dirname(__DIR__) . '/modules/platform/functions.php';
require_once dirname(__DIR__) . '/modules/hr/functions.php';
require_once dirname(__DIR__) . '/modules/sales-crm/functions.php';
require_once dirname(__DIR__) . '/modules/accounting-finance/functions.php';
require_once dirname(__DIR__) . '/modules/accounting-finance/core.php';
require_once dirname(__DIR__) . '/modules/accounting-finance/tax.php';
require_once dirname(__DIR__) . '/modules/accounting-finance/forms.php';
require_once dirname(__DIR__) . '/modules/accounting-finance/grid.php';
require_once dirname(__DIR__) . '/modules/accounting-finance/ledger.php';
require_once dirname(__DIR__) . '/modules/accounting-finance/journals.php';
require_once dirname(__DIR__) . '/modules/accounting-finance/invoices.php';
require_once dirname(__DIR__) . '/modules/accounting-finance/payments.php';
require_once dirname(__DIR__) . '/modules/accounting-finance/banking.php';
require_once dirname(__DIR__) . '/modules/accounting-finance/planning.php';
require_once dirname(__DIR__) . '/modules/accounting-finance/reports.php';
foreach (yovel_admin_module_registry() as $moduleRoute) {
    $moduleFunctionFile = dirname(__DIR__) . '/' . (string) $moduleRoute['function_file'];
    if (is_file($moduleFunctionFile)) {
        require_once $moduleFunctionFile;
    }
}
require dirname(__DIR__) . '/bootstrap/controller.php';
