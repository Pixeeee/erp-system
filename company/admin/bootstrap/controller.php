<?php
declare(strict_types=1);

$company = yovel_admin_company();
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    bx_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'login') {
        try {
            if ($company && yovel_admin_login($company, (string) ($_POST['login'] ?? ''), (string) ($_POST['password'] ?? ''))) {
                bx_flash('Signed in to ' . (string) $company['company_name'] . ' Company Admin.', 'success');
            } else {
                bx_flash('Invalid company admin login or password.', 'error');
            }
        } catch (Throwable) {
            bx_flash('Company admin login could not be completed. Try again.', 'error');
        }
        yovel_admin_redirect();
    }

    if ($action === 'logout') {
        yovel_admin_logout();
        bx_flash('Signed out from ' . ($company ? (string) $company['company_name'] : 'Company') . ' Company Admin.', 'success');
        yovel_admin_redirect();
    }

    $postAdmin = yovel_admin_current($company);
    if ($company && $postAdmin && (str_starts_with($action, 'save_company_') || $action === 'set_company_department_status')) {
        yovel_admin_platform_schema();
        yovel_admin_seed_company_modules($company, $postAdmin);
        $section = 'modules';
        try {
            $message = match ($action) {
                'save_company_module' => yovel_admin_save_module($company, $postAdmin),
                'save_company_department' => yovel_admin_save_department($company, $postAdmin),
                'set_company_department_status' => yovel_admin_set_department_status($company, $postAdmin),
                'save_company_user' => yovel_admin_save_user($company, $postAdmin),
                'save_company_role' => yovel_admin_save_role($company, $postAdmin),
                'save_company_permission' => yovel_admin_save_permission($company, $postAdmin),
                'save_company_group' => yovel_admin_save_group($company, $postAdmin),
                default => throw new InvalidArgumentException('Unknown platform action.'),
            };
            $section = match ($action) {
                'save_company_department', 'set_company_department_status' => 'departments',
                'save_company_user' => 'users',
                'save_company_role' => 'roles',
                'save_company_permission' => 'permissions',
                'save_company_group' => 'groups',
                default => 'modules',
            };
            bx_flash($message, 'success');
        } catch (Throwable $error) {
            $section = (string) ($_POST['section'] ?? $section);
            if (!array_key_exists($section, yovel_admin_platform_nav())) {
                $section = 'modules';
            }
            bx_flash($error->getMessage(), 'error');
        }
        yovel_admin_redirect_to('view=platform&section=' . rawurlencode($section));
    }

    if ($company && $postAdmin && (str_starts_with($action, 'save_hr_') || str_starts_with($action, 'set_hr_'))) {
        yovel_admin_hr_schema();
        $section = 'employee-profiles';
        try {
            $message = match ($action) {
                'save_hr_employee' => yovel_admin_save_hr_employee($company, $postAdmin),
                'set_hr_employee_status' => yovel_admin_set_hr_employee_status($company, $postAdmin),
                'save_hr_department' => yovel_admin_save_department($company, $postAdmin),
                'set_hr_department_status' => yovel_admin_set_department_status($company, $postAdmin),
                'save_hr_job_position' => yovel_admin_save_hr_job_position($company, $postAdmin),
                'set_hr_job_position_status' => yovel_admin_set_hr_job_position_status($company, $postAdmin),
                'save_hr_team' => yovel_admin_save_hr_team($company, $postAdmin),
                'set_hr_team_status' => yovel_admin_set_hr_team_status($company, $postAdmin),
                'save_hr_form_field' => yovel_admin_save_hr_form_field($company, $postAdmin),
                'save_hr_builder_form' => yovel_admin_save_hr_builder_form($company, $postAdmin),
                'save_hr_form_submission' => yovel_admin_save_hr_form_submission($company, $postAdmin),
                default => throw new InvalidArgumentException('Unknown HR action.'),
            };
            $section = match ($action) {
                'save_hr_department', 'set_hr_department_status' => 'departments',
                'save_hr_job_position', 'set_hr_job_position_status' => 'job-positions',
                'save_hr_team', 'set_hr_team_status' => 'teams',
                'save_hr_form_field' => yovel_admin_hr_form_key((string) ($_POST['form_key'] ?? 'employee-profiles')),
                'save_hr_builder_form' => 'dashboard',
                'save_hr_form_submission' => 'dashboard',
                default => 'employee-profiles',
            };
            bx_flash($message, 'success');
        } catch (Throwable $error) {
            $section = (string) ($_POST['section'] ?? $section);
            if (!array_key_exists($section, yovel_admin_hr_sections())) {
                $section = 'employee-profiles';
            }
            bx_flash($error->getMessage(), 'error');
        }
        $redirectQuery = 'view=hr&section=' . rawurlencode($section);
        if ($action === 'save_hr_form_field' && (string) ($_POST['return_to'] ?? '') === 'hr-dashboard-builder') {
            $returnTarget = yovel_admin_hr_builder_target_section((string) ($_POST['return_builder_target'] ?? 'employee-profiles'));
            $returnFields = yovel_admin_hr_form_fields($company, $returnTarget, $postAdmin);
            $returnSections = yovel_admin_hr_form_section_meta($returnTarget, $returnFields);
            $returnBuiltInSection = yovel_admin_slug((string) ($_POST['return_builtin_form'] ?? ''));
            if (array_key_exists($returnBuiltInSection, $returnSections)) {
                $redirectQuery = 'view=hr&section=dashboard&builder=1&builder_mode=existing&builder_target=' . rawurlencode($returnTarget) . '&builtin_form=' . rawurlencode($returnBuiltInSection);
            }
        }
        if ($action === 'save_hr_builder_form' && (string) ($_POST['return_to'] ?? '') === 'hr-dashboard-builder') {
            $returnTarget = yovel_admin_hr_builder_target_section((string) ($_POST['target_section'] ?? 'employee-profiles'));
            $redirectQuery = 'view=hr&section=dashboard&builder=1&builder_mode=existing&builder_target=' . rawurlencode($returnTarget);
            $returnBuilderFormKey = trim((string) ($GLOBALS['yovel_admin_saved_hr_builder_form_key'] ?? ($_POST['builder_form_key'] ?? '')));
            if (yovel_admin_is_uuid($returnBuilderFormKey)) {
                $redirectQuery .= '&form=' . rawurlencode($returnBuilderFormKey);
            }
        }
        if ($action === 'save_hr_form_submission') {
            $returnBuilderFormKey = trim((string) ($_POST['builder_form_key'] ?? ''));
            $returnSubmissionKey = trim((string) ($_POST['submission_key'] ?? ''));
            if ($returnSubmissionKey === '') {
                $returnSubmissionKey = (string) bx_db()->GetOne(
                    'SELECT submission_key FROM project_form_submission WHERE company_key_hash = ? AND builder_form_key = ? AND subject_key = ? ORDER BY updated_at DESC, x_id DESC LIMIT 1',
                    [(string) $company['company_key_hash'], $returnBuilderFormKey, (string) ($_POST['subject_key'] ?? '')]
                );
            }
            $redirectQuery = 'view=hr&section=dashboard&builder=1&builder_mode=submit&builder_target=employee-profiles';
            if (yovel_admin_is_uuid($returnBuilderFormKey)) {
                $redirectQuery .= '&form=' . rawurlencode($returnBuilderFormKey);
            }
            if (yovel_admin_is_uuid($returnSubmissionKey)) {
                $redirectQuery .= '&submission=' . rawurlencode($returnSubmissionKey);
            }
        }
        yovel_admin_redirect_to($redirectQuery);
    }

    if ($company && $postAdmin && (str_starts_with($action, 'save_sales_') || str_starts_with($action, 'set_sales_') || $action === 'reset_sales_form_schema')) {
        yovel_admin_sales_crm_schema();
        $section = 'leads';
        try {
            $message = match ($action) {
                'save_sales_lead' => yovel_admin_save_sales_lead($company, $postAdmin),
                'set_sales_lead_status' => yovel_admin_set_sales_lead_status($company, $postAdmin),
                'save_sales_form_schema' => yovel_admin_save_form_schema($company, $postAdmin),
                'reset_sales_form_schema' => yovel_admin_reset_form_schema($company, $postAdmin),
                default => throw new InvalidArgumentException('Unknown Sales/CRM action.'),
            };
            $section = match ($action) {
                'save_sales_lead', 'set_sales_lead_status' => 'leads',
                default => (string) ($_POST['section'] ?? 'leads'),
            };
            if (!array_key_exists($section, yovel_admin_sales_crm_sections())) {
                $section = 'leads';
            }
            bx_flash($message, 'success');
        } catch (Throwable $error) {
            $section = (string) ($_POST['section'] ?? $section);
            if (!array_key_exists($section, yovel_admin_sales_crm_sections())) {
                $section = 'leads';
            }
            bx_flash($error->getMessage(), 'error');
        }
        yovel_admin_redirect_to('view=sales-crm&section=' . rawurlencode($section));
    }

    if ($company && $postAdmin && (str_starts_with($action, 'save_accounting_') || str_starts_with($action, 'set_accounting_') || str_starts_with($action, 'save_finance_') || str_starts_with($action, 'set_finance_') || str_starts_with($action, 'submit_finance_') || str_starts_with($action, 'cancel_finance_') || str_starts_with($action, 'import_finance_') || str_starts_with($action, 'reconcile_finance_') || str_starts_with($action, 'unreconcile_finance_') || str_starts_with($action, 'close_finance_') || str_starts_with($action, 'reopen_finance_') || $action === 'reset_accounting_form_schema')) {
        yovel_admin_accounting_finance_schema();
        yovel_admin_finance_core_schema();
        $section = 'chart-of-accounts';
        $isFinanceBuilderAction = $action === 'save_finance_builder_form';
        try {
            $message = match ($action) {
                'save_accounting_account' => yovel_admin_save_accounting_account($company, $postAdmin),
                'set_accounting_account_status' => yovel_admin_set_accounting_account_status($company, $postAdmin),
                'save_accounting_form_schema' => yovel_admin_save_accounting_finance_form_schema($company, $postAdmin),
                'reset_accounting_form_schema' => yovel_admin_reset_accounting_finance_form_schema($company, $postAdmin),
                'save_finance_builder_form' => yovel_admin_save_finance_builder_form($company, $postAdmin),
                'save_finance_grid_view' => yovel_admin_save_finance_grid_view($company, $postAdmin),
                'save_finance_grid_formula' => yovel_admin_save_finance_grid_formula($company, $postAdmin),
                'save_finance_settings' => yovel_admin_save_finance_settings($company, $postAdmin),
                'save_finance_master' => yovel_admin_save_finance_master($company, $postAdmin),
                'set_finance_master_status' => yovel_admin_set_finance_master_status($company, $postAdmin),
                'save_finance_journal' => yovel_admin_save_finance_journal($company, $postAdmin),
                'submit_finance_journal' => yovel_admin_submit_finance_journal($company, $postAdmin),
                'cancel_finance_journal' => yovel_admin_cancel_finance_journal($company, $postAdmin),
                'save_finance_invoice' => yovel_admin_save_finance_invoice($company, $postAdmin),
                'save_finance_supplier' => yovel_admin_save_finance_supplier($company, $postAdmin),
                'submit_finance_invoice' => yovel_admin_submit_invoice_action($company, $postAdmin),
                'cancel_finance_invoice' => yovel_admin_cancel_invoice_action($company, $postAdmin),
                'save_finance_payment' => yovel_admin_save_payment_action($company, $postAdmin),
                'submit_finance_payment' => yovel_admin_submit_payment_action($company, $postAdmin),
                'cancel_finance_payment' => yovel_admin_cancel_payment_action($company, $postAdmin),
                'import_finance_bank_statement' => yovel_admin_import_bank_statement_action($company, $postAdmin),
                'reconcile_finance_bank' => yovel_admin_reconcile_bank_action($company, $postAdmin),
                'unreconcile_finance_bank' => yovel_admin_unreconcile_bank_action($company, $postAdmin),
                'save_finance_budget' => yovel_admin_save_budget_action($company, $postAdmin),
                'close_finance_period' => yovel_admin_close_period_action($company, $postAdmin),
                'reopen_finance_period' => yovel_admin_reopen_period_action($company, $postAdmin),
                'import_finance_accounts' => yovel_admin_import_finance_accounts($company, $postAdmin),
                default => throw new InvalidArgumentException('Unknown Accounting/Finance action.'),
            };
            $section = match ($action) {
                'save_accounting_account', 'set_accounting_account_status' => 'chart-of-accounts',
                'save_finance_settings' => 'dashboard',
                'save_finance_journal', 'submit_finance_journal', 'cancel_finance_journal' => 'journal-entries',
                'save_finance_invoice', 'submit_finance_invoice', 'cancel_finance_invoice' => strtoupper((string) ($_POST['document_type'] ?? 'SALES')) === 'PURCHASE' ? 'purchase-invoices' : 'sales-invoices',
                'save_finance_supplier' => 'purchase-invoices',
                'save_finance_payment', 'submit_finance_payment', 'cancel_finance_payment' => 'payment-entries',
                'import_finance_bank_statement', 'reconcile_finance_bank', 'unreconcile_finance_bank' => 'bank-reconciliation',
                'save_finance_budget' => 'budgets',
                'close_finance_period', 'reopen_finance_period' => 'period-closing',
                default => (string) ($_POST['section'] ?? 'chart-of-accounts'),
            };
            if (!array_key_exists($section, yovel_admin_accounting_finance_sections())) {
                $section = 'chart-of-accounts';
            }
            bx_flash($message, 'success');
        } catch (Throwable $error) {
            $section = (string) ($_POST['section'] ?? $section);
            if (!array_key_exists($section, yovel_admin_accounting_finance_sections())) {
                $section = 'chart-of-accounts';
            }
            bx_flash($error->getMessage(), 'error');
        }
        $redirectQuery = 'view=accounting-finance&section=' . rawurlencode($section);
        if ($isFinanceBuilderAction) {
            $target = yovel_admin_finance_builder_target_section((string) ($_POST['target_section'] ?? $section));
            $redirectQuery .= '&finance_builder=1&builder_mode=existing&builder_target=' . rawurlencode($target);
            $savedBuilderFormKey = (string) ($GLOBALS['yovel_admin_saved_finance_builder_form_key'] ?? ($_POST['builder_form_key'] ?? ''));
            if (yovel_admin_is_uuid($savedBuilderFormKey)) {
                $redirectQuery .= '&form=' . rawurlencode($savedBuilderFormKey);
            }
        } elseif ($action === 'save_finance_grid_view') {
            $savedGridViewKey = (string) ($GLOBALS['yovel_admin_saved_finance_grid_view_key'] ?? ($_POST['grid_view_key'] ?? ''));
            if (yovel_admin_is_uuid($savedGridViewKey)) {
                $redirectQuery .= '&grid_view=' . rawurlencode($savedGridViewKey);
            }
        }
        yovel_admin_redirect_to($redirectQuery);
    }

    $postView = strtolower(trim((string) ($_POST['module_view'] ?? $_GET['view'] ?? '')));
    $postRoute = yovel_admin_module_route($postView);
    $postProvider = (string) ($postRoute['action_provider'] ?? '');
    if ($company && $postAdmin && $postRoute && $postProvider !== '' && function_exists($postProvider)) {
        $postResult = null;
        try {
            $postResult = yovel_admin_module_post_result($postRoute, $company, $postAdmin, $action, $_POST);
            unset($_SESSION['builderx_module_form_state'][$postView]);
            bx_flash($postResult['message'], 'success');
        } catch (Throwable $error) {
            $section = yovel_admin_module_section(
                $postRoute,
                (string) ($_POST['section'] ?? $postRoute['default_section'])
            );
            $_SESSION['builderx_module_form_state'][$postView] = [
                'section' => $section,
                'action' => $action,
                'input' => yovel_admin_module_rehydration_input($_POST),
                'error' => $error->getMessage(),
            ];
            $postResult = ['message' => $error->getMessage(), 'section' => $section, 'query' => ['modal' => '1']];
            bx_flash($error->getMessage(), 'error');
        }
        $redirect = ['view' => $postView, 'section' => (string) $postResult['section']];
        foreach ($postResult['query'] as $key => $value) {
            $redirect[$key] = $value;
        }
        yovel_admin_redirect_to(http_build_query($redirect, '', '&', PHP_QUERY_RFC3986));
    }
}

$flash = bx_take_flash();
$admin = yovel_admin_current($company);
$assets = yovel_admin_asset_entry();
$activeView = $admin ? yovel_admin_view() : 'dashboard';
$activeModuleRoute = $admin ? yovel_admin_module_route($activeView) : null;
$activeModuleFormState = $activeModuleRoute ? yovel_admin_take_module_form_state($activeView) : [];
$activeModuleSections = $activeModuleRoute ? yovel_admin_module_sections($activeModuleRoute) : [];
$activeModuleSection = $activeModuleRoute
    ? yovel_admin_module_section($activeModuleRoute, (string) ($_GET['section'] ?? $activeModuleRoute['default_section']))
    : '';
$activeModuleMeta = $activeModuleSections[$activeModuleSection] ?? [];
$activeModuleData = ($company && $admin && $activeModuleRoute && !in_array($activeView, ['hr', 'sales-crm', 'accounting-finance'], true))
    ? yovel_admin_module_data($activeModuleRoute, $company, $admin)
    : [];
$platformNav = $admin ? yovel_admin_platform_nav() : [];
$activePlatformSection = $activeView === 'platform' ? yovel_admin_platform_section() : 'modules';
$hrSections = $admin ? yovel_admin_hr_sections() : [];
$activeHrSection = $activeView === 'hr' ? yovel_admin_hr_section() : 'employee-profiles';
$salesCrmSections = $admin ? yovel_admin_sales_crm_sections() : [];
$activeSalesCrmSection = $activeView === 'sales-crm' ? yovel_admin_sales_crm_section() : 'leads';
$accountingFinanceSections = $admin ? yovel_admin_accounting_finance_sections() : [];
$activeAccountingFinanceSection = $activeView === 'accounting-finance' ? yovel_admin_accounting_finance_section() : 'dashboard';
$erpGroups = $admin ? yovel_admin_erp_groups() : [];
$companyName = $company ? (string) $company['company_name'] : 'Company';
$companyCode = $company ? (string) $company['company_code'] : '';
$companySlug = $company ? (string) $company['company_slug'] : yovel_admin_requested_slug();
$companyInitials = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($companyCode !== '' ? $companyCode : $companyName)) ?: 'CO', 0, 2));
$companyThemeKey = 'builderx:company-admin:' . ($companySlug !== '' ? $companySlug : 'company') . ':theme';
$companySidebarKey = 'builderx:company-admin:' . ($companySlug !== '' ? $companySlug : 'company') . ':sidebar';
$platformSections = $admin ? yovel_admin_platform_sections($companyName) : [];
$dashboardMetrics = ($company && $admin) ? yovel_admin_dashboard_metrics($company, $admin) : [];
$platformData = ($company && $admin && $activeView === 'platform') ? yovel_admin_platform_data($company, $admin) : [];
$hrData = ($company && $admin && $activeView === 'hr') ? yovel_admin_hr_data($company) : [];
$salesCrmData = ($company && $admin && $activeView === 'sales-crm') ? yovel_admin_sales_crm_data($company, $admin) : [];
$accountingFinanceData = ($company && $admin && $activeView === 'accounting-finance') ? yovel_admin_accounting_finance_data($company, $admin) : [];
$editKey = trim((string) ($_GET['edit'] ?? ''));
$editModule = $editKey !== '' ? yovel_admin_find_record($platformData['modules'] ?? [], 'module_key', $editKey) : null;
$editDepartment = $editKey !== '' ? yovel_admin_find_record($platformData['departments'] ?? [], 'department_key', $editKey) : null;
$editUser = $editKey !== '' ? yovel_admin_find_record($platformData['users'] ?? [], 'user_key', $editKey) : null;
$editRole = $editKey !== '' ? yovel_admin_find_record($platformData['roles'] ?? [], 'role_key', $editKey) : null;
$editPermission = $editKey !== '' ? yovel_admin_find_record($platformData['permissions'] ?? [], 'permission_key', $editKey) : null;
$editGroup = $editKey !== '' ? yovel_admin_find_record($platformData['groups'] ?? [], 'group_key', $editKey) : null;
$editHrEmployee = $editKey !== '' ? yovel_admin_find_record($hrData['employees'] ?? [], 'employee_key', $editKey) : null;
$editHrDepartment = $editKey !== '' ? yovel_admin_find_record($hrData['departments'] ?? [], 'department_key', $editKey) : null;
$editHrJobPosition = $editKey !== '' ? yovel_admin_find_record($hrData['jobPositions'] ?? [], 'job_position_key', $editKey) : null;
$editHrTeam = $editKey !== '' ? yovel_admin_find_record($hrData['teams'] ?? [], 'team_key', $editKey) : null;
$editSalesLead = $editKey !== '' ? yovel_admin_find_record($salesCrmData['leads'] ?? [], 'lead_key', $editKey) : null;
$editAccountingAccount = $editKey !== '' ? yovel_admin_find_record($accountingFinanceData['accounts'] ?? [], 'account_key', $editKey) : null;
$hrEmployees = $hrData['employees'] ?? [];
$activeHrEmployees = array_values(array_filter($hrEmployees, static fn (array $employee): bool => (string) ($employee['employee_status'] ?? '') === 'ACTIVE'));
$incompleteHrEmployees = array_values(array_filter($hrEmployees, static fn (array $employee): bool => (string) ($employee['department_key'] ?? '') === '' || (string) ($employee['branch_key'] ?? '') === ''));
$salesLeads = $salesCrmData['leads'] ?? [];
$openSalesLeads = array_values(array_filter($salesLeads, static fn (array $lead): bool => in_array((string) ($lead['lead_status'] ?? ''), ['OPEN', 'QUALIFIED'], true)));
$convertedSalesLeads = array_values(array_filter($salesLeads, static fn (array $lead): bool => (string) ($lead['lead_status'] ?? '') === 'CONVERTED'));
$accountingAccounts = $accountingFinanceData['accounts'] ?? [];
$activeAccountingAccounts = array_values(array_filter($accountingAccounts, static fn (array $account): bool => (string) ($account['account_status'] ?? '') === 'ACTIVE'));
$frozenAccountingAccounts = array_values(array_filter($accountingAccounts, static fn (array $account): bool => (string) ($account['account_status'] ?? '') === 'FROZEN' || (int) ($account['freeze_account'] ?? 0) === 1));
$generalLedgerFilters = $accountingFinanceData['ledgerFilters'] ?? yovel_admin_general_ledger_filters([]);
$generalLedgerEntries = $accountingFinanceData['ledgerEntries'] ?? [];
$generalLedgerSummary = $accountingFinanceData['ledgerSummary'] ?? ['opening_balance' => '0.000000', 'period_debit' => '0.000000', 'period_credit' => '0.000000', 'closing_balance' => '0.000000', 'entry_count' => 0];
$generalLedgerEntryCount = (int) ($accountingFinanceData['ledgerEntryCount'] ?? 0);
$employeeFormFields = $hrData['formFields']['employee-profiles'] ?? [];
$departmentFormFields = $hrData['formFields']['departments'] ?? [];
$jobPositionFormFields = $hrData['formFields']['job-positions'] ?? [];
$teamFormFields = $hrData['formFields']['teams'] ?? [];
$activeHrFormFields = ($company && $admin && $activeView === 'hr') ? ($hrData['formFields'][$activeHrSection] ?? yovel_admin_hr_form_fields($company, $activeHrSection, $admin)) : [];
$employeeCustomValues = ($company && $editHrEmployee) ? yovel_admin_hr_custom_values($company, 'employee-profiles', (string) $editHrEmployee['employee_key']) : [];
$employeeModalSectionKeys = ['overview', 'joining', 'address-contacts', 'attendance-leaves', 'salary', 'personal', 'profile', 'exit'];
$activeEmployeeModalSection = yovel_admin_slug((string) ($_GET['employee_section'] ?? 'overview'));
$activeEmployeeModalSection = in_array($activeEmployeeModalSection, $employeeModalSectionKeys, true) ? $activeEmployeeModalSection : 'overview';
$departmentModalSectionKeys = ['overview', 'details'];
$activeDepartmentModalSection = yovel_admin_slug((string) ($_GET['department_section'] ?? 'overview'));
$activeDepartmentModalSection = in_array($activeDepartmentModalSection, $departmentModalSectionKeys, true) ? $activeDepartmentModalSection : 'overview';
$departmentCustomValues = ($company && $editHrDepartment) ? yovel_admin_hr_custom_values($company, 'departments', (string) $editHrDepartment['department_key']) : [];
$jobPositionModalSectionKeys = ['overview', 'details'];
$activeJobPositionModalSection = yovel_admin_slug((string) ($_GET['job_position_section'] ?? 'overview'));
$activeJobPositionModalSection = in_array($activeJobPositionModalSection, $jobPositionModalSectionKeys, true) ? $activeJobPositionModalSection : 'overview';
$jobPositionCustomValues = ($company && $editHrJobPosition) ? yovel_admin_hr_custom_values($company, 'job-positions', (string) $editHrJobPosition['job_position_key']) : [];
$teamModalSectionKeys = ['overview', 'details'];
$activeTeamModalSection = yovel_admin_slug((string) ($_GET['team_section'] ?? 'overview'));
$activeTeamModalSection = in_array($activeTeamModalSection, $teamModalSectionKeys, true) ? $activeTeamModalSection : 'overview';
$teamCustomValues = ($company && $editHrTeam) ? yovel_admin_hr_custom_values($company, 'teams', (string) $editHrTeam['team_key']) : [];
$hrBuilderTargetSections = $admin ? yovel_admin_hr_builder_target_sections() : [];
$hrBuilderForms = $hrData['builderForms'] ?? [];
$hrFormSubmissions = $hrData['formSubmissions'] ?? [];
$activeHrBuilderTarget = yovel_admin_hr_builder_target_section((string) ($_GET['builder_target'] ?? 'employee-profiles'));
$activeHrBuilderFormKey = trim((string) ($_GET['form'] ?? ''));
$activeHrBuilderForm = ($activeHrBuilderFormKey !== '') ? yovel_admin_find_record($hrBuilderForms, 'builder_form_key', $activeHrBuilderFormKey) : null;
$activeHrBuilderMode = yovel_admin_slug((string) ($_GET['builder_mode'] ?? ($activeHrBuilderForm ? 'existing' : 'new')));
$activeHrBuilderMode = in_array($activeHrBuilderMode, ['new', 'existing', 'submit'], true) ? $activeHrBuilderMode : 'new';
if ($activeHrBuilderForm) {
    $activeHrBuilderTarget = yovel_admin_hr_builder_target_section((string) ($activeHrBuilderForm['target_section'] ?? $activeHrBuilderTarget));
    if ($activeHrBuilderMode !== 'submit') {
        $activeHrBuilderMode = 'existing';
    }
}
$activeHrSubmissionKey = trim((string) ($_GET['submission'] ?? ''));
$activeHrSubmission = $activeHrSubmissionKey !== '' ? yovel_admin_find_record($hrFormSubmissions, 'submission_key', $activeHrSubmissionKey) : null;
if ($activeHrSubmission && (!$activeHrBuilderForm || (string) $activeHrSubmission['builder_form_key'] !== (string) $activeHrBuilderForm['builder_form_key'])) {
    $activeHrSubmission = null;
    $activeHrSubmissionKey = '';
}
if ($activeHrBuilderMode === 'submit' && (!$activeHrBuilderForm || $activeHrBuilderTarget !== 'employee-profiles')) {
    $activeHrBuilderMode = 'existing';
}
$activeHrBuilderOpen = $activeHrSection === 'dashboard' && (
    (string) ($_GET['builder'] ?? '') === '1'
    || isset($_GET['builder_mode'])
    || isset($_GET['form'])
    || isset($_GET['builtin_form'])
    || isset($_GET['submission'])
);
$activeHrBuilderFields = ($company && $admin)
    ? ($hrData['formFields'][$activeHrBuilderTarget] ?? yovel_admin_hr_form_fields($company, $activeHrBuilderTarget, $admin))
    : [];
$activeHrBuiltInSections = yovel_admin_hr_form_section_meta($activeHrBuilderTarget, $activeHrBuilderFields);
$activeHrBuiltInSection = yovel_admin_slug((string) ($_GET['builtin_form'] ?? ''));
if (!array_key_exists($activeHrBuiltInSection, $activeHrBuiltInSections) || $activeHrBuilderForm) {
    $activeHrBuiltInSection = '';
}
if ($activeHrBuiltInSection !== '') {
    $activeHrBuilderMode = 'existing';
}
$activeHrBuiltInSectionFields = $activeHrBuiltInSection === '' ? [] : array_values(array_filter(
    $activeHrBuilderFields,
    static fn (array $field): bool => yovel_admin_slug((string) ($field['field_section'] ?? 'overview')) === $activeHrBuiltInSection
));
$activeHrBuilderSchema = ['version' => 2, 'questions' => [], 'rows' => []];
if ($activeHrBuilderForm && (string) ($activeHrBuilderForm['schema_json'] ?? '') !== '') {
    $decodedBuilderSchema = json_decode((string) $activeHrBuilderForm['schema_json'], true);
    if (is_array($decodedBuilderSchema)) {
        $activeHrBuilderSchema = yovel_admin_normalize_hr_builder_schema(json_encode($decodedBuilderSchema, JSON_UNESCAPED_SLASHES) ?: '{}');
    }
}
$activeHrFormSubmissions = $activeHrBuilderForm ? array_values(array_filter(
    $hrFormSubmissions,
    static fn (array $submission): bool => (string) ($submission['builder_form_key'] ?? '') === (string) $activeHrBuilderForm['builder_form_key']
)) : [];
$activeHrSubmissionVersionKey = (string) ($activeHrSubmission['form_version_key'] ?? ($activeHrBuilderForm['current_form_version_key'] ?? ''));
$activeHrSubmissionVersion = ($company && $activeHrBuilderForm)
    ? yovel_admin_hr_builder_form_version($company, (string) $activeHrBuilderForm['builder_form_key'], $activeHrSubmissionVersionKey)
    : null;
$activeHrSubmissionSchema = ['version' => 2, 'questions' => [], 'rows' => []];
if ($activeHrSubmissionVersion && (string) ($activeHrSubmissionVersion['schema_json'] ?? '') !== '') {
    $decodedSubmissionSchema = json_decode((string) $activeHrSubmissionVersion['schema_json'], true);
    if (is_array($decodedSubmissionSchema)) {
        $activeHrSubmissionSchema = yovel_admin_normalize_hr_builder_schema(json_encode($decodedSubmissionSchema, JSON_UNESCAPED_SLASHES) ?: '{}');
    }
}
$activeHrSubmissionValues = ($company && $activeHrSubmission)
    ? yovel_admin_hr_submission_values($company, (string) $activeHrSubmission['submission_key'])
    : [];
$activeHrSubmissionEmployeeKey = (string) ($activeHrSubmission['subject_key'] ?? ($_GET['subject'] ?? ''));
$isModulesView = $activeView === 'platform' && $activePlatformSection === 'modules';
$activeHrMeta = $hrSections[$activeHrSection] ?? ['label' => 'Employee profiles', 'description' => 'Create and maintain Yovel East employee master records.'];
$activeSalesCrmMeta = $salesCrmSections[$activeSalesCrmSection] ?? ['label' => 'Leads', 'description' => 'Capture and qualify prospects before they become opportunities or customers.', 'record_type' => 'lead'];
$activeSalesCrmRecordType = (string) ($activeSalesCrmMeta['record_type'] ?? '');
$activeSalesCrmSchema = $activeSalesCrmRecordType !== '' ? ($salesCrmData['schemas'][$activeSalesCrmRecordType] ?? yovel_admin_sales_crm_default_form_schemas()[$activeSalesCrmRecordType] ?? []) : [];
$activeAccountingFinanceMeta = $accountingFinanceSections[$activeAccountingFinanceSection] ?? ['label' => 'Chart of accounts', 'description' => 'Build and maintain the account tree used by Accounting/Finance records.', 'record_type' => 'account'];
$activeAccountingFinanceRecordType = (string) ($activeAccountingFinanceMeta['record_type'] ?? '');
$activeAccountingFinanceSchema = $activeAccountingFinanceRecordType !== '' ? ($accountingFinanceData['schemas'][$activeAccountingFinanceRecordType] ?? yovel_admin_accounting_finance_default_form_schemas()[$activeAccountingFinanceRecordType] ?? []) : [];
$financeGridColumnRegistry = yovel_admin_finance_grid_column_registry($activeAccountingFinanceSchema);
$financeGridViews = $accountingFinanceData['gridViews'] ?? [];
$financeGridFormulas = $accountingFinanceData['gridFormulas'] ?? [];
$activeFinanceGridViewKey = trim((string) ($_GET['grid_view'] ?? ''));
$activeFinanceGridView = $activeFinanceGridViewKey !== '' ? yovel_admin_find_record($financeGridViews, 'grid_view_key', $activeFinanceGridViewKey) : null;
if (!$activeFinanceGridView) {
    $activeFinanceGridView = yovel_admin_find_record($financeGridViews, 'is_default', '1') ?? ($financeGridViews[0] ?? null);
}
$financeBuilderTargetSections = $admin ? yovel_admin_finance_builder_target_sections() : [];
$financeBuilderForms = $accountingFinanceData['builderForms'] ?? [];
$activeFinanceBuilderTarget = yovel_admin_finance_builder_target_section((string) ($_GET['builder_target'] ?? 'chart-of-accounts'));
$activeFinanceBuilderFormKey = trim((string) ($_GET['form'] ?? ''));
$activeFinanceBuilderForm = ($activeView === 'accounting-finance' && $activeFinanceBuilderFormKey !== '')
    ? yovel_admin_find_record($financeBuilderForms, 'builder_form_key', $activeFinanceBuilderFormKey)
    : null;
$activeFinanceBuilderMode = yovel_admin_slug((string) ($_GET['builder_mode'] ?? ($activeFinanceBuilderForm ? 'existing' : 'new')));
$activeFinanceBuilderMode = in_array($activeFinanceBuilderMode, ['new', 'existing'], true) ? $activeFinanceBuilderMode : 'new';
if ($activeFinanceBuilderForm) {
    $activeFinanceBuilderTarget = yovel_admin_finance_builder_target_section((string) ($activeFinanceBuilderForm['target_section'] ?? $activeFinanceBuilderTarget));
    $activeFinanceBuilderMode = 'existing';
}
$activeFinanceBuilderOpen = $activeView === 'accounting-finance' && (
    (string) ($_GET['finance_builder'] ?? '') === '1'
    || isset($_GET['builder_mode'])
    || ($activeFinanceBuilderForm !== null)
    || isset($_GET['builtin_form'])
);
$activeFinanceBuilderRecordType = (string) ($accountingFinanceSections[$activeFinanceBuilderTarget]['record_type'] ?? 'account');
$activeFinanceBuiltInSchema = $accountingFinanceData['schemas'][$activeFinanceBuilderRecordType]
    ?? yovel_admin_accounting_finance_default_form_schemas()[$activeFinanceBuilderRecordType]
    ?? [];
$activeFinanceBuiltInSections = yovel_admin_finance_builtin_sections($activeFinanceBuiltInSchema);
$activeFinanceBuiltInSection = yovel_admin_slug((string) ($_GET['builtin_form'] ?? ''));
if (!array_key_exists($activeFinanceBuiltInSection, $activeFinanceBuiltInSections) || $activeFinanceBuilderForm) {
    $activeFinanceBuiltInSection = '';
}
if ($activeFinanceBuiltInSection !== '') {
    $activeFinanceBuilderMode = 'existing';
}
$activeFinanceBuilderSchema = ['version' => 1, 'questions' => []];
if ($activeFinanceBuilderForm && (string) ($activeFinanceBuilderForm['schema_json'] ?? '') !== '') {
    $activeFinanceBuilderSchema = yovel_admin_normalize_finance_builder_schema((string) $activeFinanceBuilderForm['schema_json']);
}
$accountCustomValues = ($company && $editAccountingAccount) ? yovel_admin_accounting_finance_custom_values($company, 'account', (string) $editAccountingAccount['account_key']) : [];
$viewTitle = $activeView === 'accounting-finance' ? 'Accounting / Finance' : ($activeView === 'sales-crm' ? 'Sales / CRM' : ($activeView === 'hr' ? 'HR Department' : ($isModulesView ? 'Company Modules' : ($activeView === 'platform' ? 'Company Platform' : $companyName . ' Dashboard'))));
$viewCrumb = $activeView === 'accounting-finance' ? 'Accounting / Finance' : ($activeView === 'sales-crm' ? 'Sales / CRM' : ($activeView === 'hr' ? 'HR Department' : ($isModulesView ? 'Modules' : ($activeView === 'platform' ? 'Platform' : 'Dashboard'))));
$viewEyebrow = $activeView === 'accounting-finance' ? 'Accounting / Finance' : ($activeView === 'sales-crm' ? 'Sales / CRM' : ($activeView === 'hr' ? 'HR Department' : ($isModulesView ? 'Modules' : ($activeView === 'platform' ? 'Platform' : 'Dashboard'))));
$viewHeading = $activeView === 'accounting-finance' ? (string) $activeAccountingFinanceMeta['label'] : ($activeView === 'sales-crm' ? (string) $activeSalesCrmMeta['label'] : ($activeView === 'hr' ? (string) $activeHrMeta['label'] : ($isModulesView ? 'Modules' : ($activeView === 'platform' ? 'Company Platform' : $companyName . ' Dashboard'))));
$viewDescription = $isModulesView
    ? 'Enable, describe, and organize the ERP workspaces available to this company.'
    : ($activeView === 'accounting-finance'
        ? (string) $activeAccountingFinanceMeta['description']
        : ($activeView === 'sales-crm'
        ? (string) $activeSalesCrmMeta['description']
        : ($activeView === 'hr'
        ? (string) $activeHrMeta['description']
        : ($activeView === 'platform'
        ? 'Create and assign company users, roles, access rules, and audited authority boundaries.'
        : 'Review ' . $companyName . ' company scope, platform readiness, and the ERP system workspaces.'))));
if ($activeModuleRoute && !in_array($activeView, ['hr', 'sales-crm', 'accounting-finance'], true)) {
    $moduleLabel = (string) $activeModuleRoute['label'];
    $viewTitle = $moduleLabel;
    $viewCrumb = $moduleLabel;
    $viewEyebrow = $moduleLabel;
    $viewHeading = (string) ($activeModuleMeta['label'] ?? ucwords(str_replace('-', ' ', $activeModuleSection)));
    $viewDescription = (string) ($activeModuleMeta['description'] ?? 'Manage ' . $moduleLabel . ' records and workflows.');
}
$nextThemeLabel = 'Toggle theme';
$pageTitle = $companyName . ' Company Admin';

require dirname(__DIR__) . '/views/layout.php';
