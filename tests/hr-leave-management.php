<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/hr/functions.php';
require_once $root . '/company/admin/modules/shared/forms.php';

function hr_leave_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach ([
    'yovel_admin_hr_leave_form_targets',
    'yovel_admin_persist_leave_master',
    'yovel_admin_persist_leave_policy',
    'yovel_admin_persist_leave_policy_assignment',
    'yovel_admin_persist_holiday_list_assignment',
    'yovel_admin_persist_leave_allocation',
    'yovel_admin_bulk_leave_allocation',
    'yovel_admin_persist_leave_application',
    'yovel_admin_transition_leave_application',
    'yovel_admin_persist_leave_adjustment',
    'yovel_admin_persist_compensatory_leave_request',
    'yovel_admin_transition_compensatory_leave_request',
    'yovel_admin_persist_leave_encashment',
    'yovel_admin_calculate_leave_days',
    'yovel_admin_calculate_leave_balance',
    'yovel_admin_hr_calendar_read_contract',
    'yovel_admin_hr_leave_data',
    'yovel_admin_hr_handle_leave_post',
] as $function) {
    hr_leave_assert(function_exists($function), 'Missing HR-WP-03 interface: ' . $function);
}

$db = bx_db();
yovel_admin_hr_schema();

$requiredTables = [
    'project_company_hr_leave_type',
    'project_company_hr_leave_period',
    'project_company_hr_holiday_list',
    'project_company_hr_holiday',
    'project_company_hr_holiday_list_assignment',
    'project_company_hr_earned_leave_schedule',
    'project_company_hr_leave_policy',
    'project_company_hr_leave_policy_detail',
    'project_company_hr_leave_policy_assignment',
    'project_company_hr_leave_allocation',
    'project_company_hr_leave_application',
    'project_company_hr_leave_ledger_entry',
    'project_company_hr_leave_adjustment',
    'project_company_hr_compensatory_leave_request',
    'project_company_hr_leave_encashment',
    'project_company_hr_leave_block_list',
    'project_company_hr_leave_block_date',
    'project_company_hr_leave_block_allow',
    'project_company_hr_notification_intent',
];
foreach ($requiredTables as $table) {
    hr_leave_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    ) === 1, 'Missing HR-WP-03 table: ' . $table);
}

$scope = $db->GetRow("
    SELECT company_record.company_key, company_record.company_key_hash, company_record.company_name,
           admin_record.admin_key, branch_record.branch_key,
           COALESCE(department_record.department_key, '') AS department_key,
           COALESCE(position_record.job_position_key, '') AS job_position_key
    FROM project_company company_record
    INNER JOIN project_company_admin admin_record
      ON admin_record.company_key_hash = company_record.company_key_hash
     AND admin_record.company_key = company_record.company_key
     AND admin_record.admin_status = 'ACTIVE'
    INNER JOIN project_company_branch branch_record
      ON branch_record.company_key_hash = company_record.company_key_hash
     AND branch_record.branch_status <> 'DELETED'
    LEFT JOIN project_company_department department_record
      ON department_record.company_key_hash = company_record.company_key_hash
     AND department_record.department_status <> 'DELETED'
    LEFT JOIN project_company_hr_job_position position_record
      ON position_record.company_key_hash = company_record.company_key_hash
     AND position_record.job_position_status <> 'DELETED'
    WHERE company_record.company_status = 'ACTIVE'
    ORDER BY company_record.x_id, branch_record.x_id, department_record.x_id, position_record.x_id
    LIMIT 1
");
hr_leave_assert(is_array($scope) && $scope !== [], 'An active HR company fixture is required.');

$company = [
    'company_key' => (string) $scope['company_key'],
    'company_key_hash' => (string) $scope['company_key_hash'],
    'company_name' => (string) $scope['company_name'],
];
$admin = ['admin_key' => (string) $scope['admin_key']];
$companyKey = (string) $scope['company_key'];
$companyKeyHash = (string) $scope['company_key_hash'];
$adminKey = (string) $scope['admin_key'];
$token = strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 10));
$employeeKey = bx_uuid();
$secondEmployeeKey = bx_uuid();
$keys = [];
$auditKeys = [];

try {
    foreach ([[$employeeKey, 'Leave Fixture'], [$secondEmployeeKey, 'Leave Fixture Two']] as $index => [$fixtureKey, $fixtureName]) {
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_employee (
            employee_key, company_key, company_key_hash, employee_code, first_name, employee_name,
            employee_status, branch_key, department_key, job_position_key, created_by_admin_key, updated_by_admin_key
        ) VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?, ?, ?, ?)", [
            $fixtureKey, $companyKey, $companyKeyHash, 'WP03_' . $token . '_' . $index,
            'Leave', $fixtureName, (string) $scope['branch_key'], ($scope['department_key'] ?: null),
            ($scope['job_position_key'] ?: null), $adminKey, $adminKey,
        ], 'HR-WP-03 employee fixture');
    }

    $leaveType = yovel_admin_persist_leave_master($db, $company, $admin, 'LEAVE_TYPE', [
        'leave_type_code' => 'ANNUAL_' . $token,
        'leave_type_name' => 'Annual Leave',
        'maximum_days' => '30',
        'allow_carry_forward' => '1',
        'allow_negative_balance' => '0',
        'include_holidays' => '0',
        'is_paid' => '1',
    ]);
    $keys['leave_type_key'] = (string) $leaveType['leave_type_key'];
    $auditKeys[] = $keys['leave_type_key'];

    $period = yovel_admin_persist_leave_master($db, $company, $admin, 'LEAVE_PERIOD', [
        'period_code' => 'FY26_' . $token,
        'period_name' => 'FY 2026',
        'from_date' => '2026-01-01',
        'to_date' => '2026-12-31',
    ]);
    $keys['leave_period_key'] = (string) $period['leave_period_key'];

    $holidayList = yovel_admin_persist_leave_master($db, $company, $admin, 'HOLIDAY_LIST', [
        'holiday_list_code' => 'PH_' . $token,
        'holiday_list_name' => 'Philippines 2026',
        'from_date' => '2026-01-01',
        'to_date' => '2026-12-31',
        'timezone_name' => 'Asia/Manila',
    ]);
    $keys['holiday_list_key'] = (string) $holidayList['holiday_list_key'];
    $holiday = yovel_admin_persist_leave_master($db, $company, $admin, 'HOLIDAY', [
        'holiday_list_key' => $keys['holiday_list_key'],
        'holiday_date' => '2026-09-02',
        'description' => 'Fixture Holiday',
    ]);
    $keys['holiday_key'] = (string) $holiday['holiday_key'];

    $schedule = yovel_admin_persist_leave_master($db, $company, $admin, 'EARNED_LEAVE_SCHEDULE', [
        'schedule_code' => 'MONTHLY_' . $token,
        'schedule_name' => 'Monthly Earned Leave',
        'leave_type_key' => $keys['leave_type_key'],
        'frequency' => 'MONTHLY',
        'earned_quantity' => '1.0000',
    ]);
    $keys['earned_leave_schedule_key'] = (string) $schedule['earned_leave_schedule_key'];

    $policy = yovel_admin_persist_leave_policy($db, $company, $admin, [
        'policy_code' => 'POL_' . $token,
        'policy_name' => 'Standard Leave Policy',
        'details' => [[
            'leave_type_key' => $keys['leave_type_key'],
            'annual_allocation' => '12.0000',
            'earned_leave_schedule_key' => $keys['earned_leave_schedule_key'],
        ]],
    ]);
    $keys['leave_policy_key'] = (string) $policy['leave_policy_key'];

    $policyAssignment = yovel_admin_persist_leave_policy_assignment($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'leave_policy_key' => $keys['leave_policy_key'],
        'leave_period_key' => $keys['leave_period_key'],
        'effective_from' => '2026-01-01',
        'effective_until' => '2026-12-31',
    ]);
    $keys['leave_policy_assignment_key'] = (string) $policyAssignment['leave_policy_assignment_key'];

    $holidayAssignment = yovel_admin_persist_holiday_list_assignment($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'holiday_list_key' => $keys['holiday_list_key'],
        'effective_from' => '2026-01-01',
        'effective_until' => '2026-12-31',
    ]);
    $keys['holiday_list_assignment_key'] = (string) $holidayAssignment['holiday_list_assignment_key'];

    $allocation = yovel_admin_persist_leave_allocation($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'leave_type_key' => $keys['leave_type_key'],
        'leave_period_key' => $keys['leave_period_key'],
        'from_date' => '2026-01-01',
        'to_date' => '2026-12-31',
        'allocated_quantity' => '10.0000',
        'carry_forward_quantity' => '2.0000',
        'allocation_status' => 'SUBMITTED',
        'source_reference' => 'ALLOC-' . $token,
    ]);
    $keys['leave_allocation_key'] = (string) $allocation['leave_allocation_key'];
    $auditKeys[] = $keys['leave_allocation_key'];
    $balance = yovel_admin_calculate_leave_balance($db, $companyKeyHash, $employeeKey, $keys['leave_type_key'], '2026-08-31');
    hr_leave_assert(($balance['available'] ?? '') === '12.0000', 'Allocation and carry-forward did not produce the expected balance.');

    $days = yovel_admin_calculate_leave_days($db, $companyKeyHash, $employeeKey, $keys['leave_type_key'], '2026-09-01', '2026-09-03', false, '');
    hr_leave_assert($days === '2.0000', 'Holiday exclusion changed leave-day calculation.');
    $halfDay = yovel_admin_calculate_leave_days($db, $companyKeyHash, $employeeKey, $keys['leave_type_key'], '2026-09-01', '2026-09-01', true, '2026-09-01');
    hr_leave_assert($halfDay === '0.5000', 'Half-day leave calculation changed.');

    $application = yovel_admin_persist_leave_application($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'leave_type_key' => $keys['leave_type_key'],
        'from_date' => '2026-09-01',
        'to_date' => '2026-09-03',
        'reason' => 'Planned leave',
        'application_status' => 'SUBMITTED',
        'source_reference' => 'APP-' . $token,
    ]);
    $keys['leave_application_key'] = (string) $application['leave_application_key'];
    hr_leave_assert((string) $application['total_leave_days'] === '2.0000', 'Leave Application stored the wrong quantity.');
    $approved = yovel_admin_transition_leave_application($db, $company, $admin, $keys['leave_application_key'], 'APPROVED');
    hr_leave_assert((string) $approved['application_status'] === 'APPROVED', 'Leave Application approval failed.');
    $balance = yovel_admin_calculate_leave_balance($db, $companyKeyHash, $employeeKey, $keys['leave_type_key'], '2026-09-03');
    hr_leave_assert(($balance['available'] ?? '') === '10.0000', 'Approved leave did not debit the append-only ledger.');

    $overlapRejected = false;
    try {
        yovel_admin_persist_leave_application($db, $company, $admin, [
            'employee_key' => $employeeKey,
            'leave_type_key' => $keys['leave_type_key'],
            'from_date' => '2026-09-03',
            'to_date' => '2026-09-04',
            'application_status' => 'SUBMITTED',
        ]);
    } catch (InvalidArgumentException) {
        $overlapRejected = true;
    }
    hr_leave_assert($overlapRejected, 'Overlapping leave was accepted.');

    $adjustment = yovel_admin_persist_leave_adjustment($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'leave_type_key' => $keys['leave_type_key'],
        'posting_date' => '2026-09-04',
        'quantity' => '1.5000',
        'reason' => 'Verified correction',
    ]);
    $keys['leave_adjustment_key'] = (string) $adjustment['leave_adjustment_key'];

    $compensatory = yovel_admin_persist_compensatory_leave_request($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'leave_type_key' => $keys['leave_type_key'],
        'work_date' => '2026-09-05',
        'quantity' => '1.0000',
        'request_status' => 'SUBMITTED',
        'reason' => 'Weekend support',
    ]);
    $keys['compensatory_leave_request_key'] = (string) $compensatory['compensatory_leave_request_key'];
    yovel_admin_transition_compensatory_leave_request($db, $company, $admin, $keys['compensatory_leave_request_key'], 'APPROVED');

    $encashment = yovel_admin_persist_leave_encashment($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'leave_type_key' => $keys['leave_type_key'],
        'posting_date' => '2026-09-06',
        'quantity' => '1.0000',
        'encashment_status' => 'SUBMITTED',
    ]);
    $keys['leave_encashment_key'] = (string) $encashment['leave_encashment_key'];
    $balance = yovel_admin_calculate_leave_balance($db, $companyKeyHash, $employeeKey, $keys['leave_type_key'], '2026-09-06');
    hr_leave_assert(($balance['available'] ?? '') === '11.5000', 'Adjustment, compensatory leave, and encashment ledger effects are wrong.');

    $blockList = yovel_admin_persist_leave_master($db, $company, $admin, 'LEAVE_BLOCK_LIST', [
        'block_list_code' => 'BLOCK_' . $token,
        'block_list_name' => 'Quarter Close',
        'blocked_dates' => [['blocked_date' => '2026-09-10', 'description' => 'Quarter close']],
        'allowed_employee_keys' => [$employeeKey],
    ]);
    $keys['leave_block_list_key'] = (string) $blockList['leave_block_list_key'];

    $bulk = yovel_admin_bulk_leave_allocation($db, $company, $admin, [$employeeKey, $secondEmployeeKey], [
        'leave_type_key' => $keys['leave_type_key'],
        'leave_period_key' => $keys['leave_period_key'],
        'from_date' => '2026-10-01',
        'to_date' => '2026-12-31',
        'allocated_quantity' => '1.0000',
        'source_reference' => 'CONTROL-' . $token,
    ]);
    hr_leave_assert(count($bulk) === 2, 'Leave Control Panel did not allocate to both employees.');

    $calendar = yovel_admin_hr_calendar_read_contract($company, ['from_date' => '2026-01-01', 'to_date' => '2026-12-31']);
    hr_leave_assert(($calendar['contract'] ?? '') === 'hr.calendar-directory.v1', 'HR calendar contract signature changed.');
    hr_leave_assert(($calendar['company_key_hash'] ?? '') === $companyKeyHash, 'HR calendar contract changed company scope.');
    hr_leave_assert(count($calendar['holiday_lists'] ?? []) >= 1 && count($calendar['holidays'] ?? []) >= 1, 'HR calendar contract omitted authoritative records.');
    foreach (['holiday_list_key', 'holiday_list_name', 'from_date', 'to_date', 'holiday_list_status'] as $field) {
        hr_leave_assert(array_key_exists($field, $calendar['holiday_lists'][0]), 'Holiday List contract omitted ' . $field . '.');
    }
    foreach (['holiday_key', 'holiday_list_key', 'holiday_date', 'description', 'holiday_status'] as $field) {
        hr_leave_assert(array_key_exists($field, $calendar['holidays'][0]), 'Holiday contract omitted ' . $field . '.');
    }

    $beforeRollback = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_leave_adjustment WHERE company_key_hash = ?', [$companyKeyHash]);
    try {
        yovel_admin_persist_leave_adjustment($db, $company, $admin, [
            'employee_key' => $employeeKey,
            'leave_type_key' => $keys['leave_type_key'],
            'posting_date' => '2026-09-07',
            'quantity' => '1.0000',
            'reason' => 'Rollback fixture',
        ], static function (): void {
            throw new RuntimeException('Injected rollback.');
        });
    } catch (RuntimeException) {
    }
    hr_leave_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_leave_adjustment WHERE company_key_hash = ?', [$companyKeyHash]) === $beforeRollback, 'Leave adjustment rollback left a record.');

    $foreignRejected = false;
    try {
        yovel_admin_persist_leave_allocation($db, ['company_key' => $companyKey, 'company_key_hash' => str_repeat('f', 64)], $admin, [
            'employee_key' => $employeeKey,
            'leave_type_key' => $keys['leave_type_key'],
            'leave_period_key' => $keys['leave_period_key'],
            'from_date' => '2026-01-01',
            'to_date' => '2026-12-31',
            'allocated_quantity' => '1',
        ]);
    } catch (Throwable) {
        $foreignRejected = true;
    }
    hr_leave_assert($foreignRejected, 'Leave allocation accepted a foreign company scope.');

    hr_leave_assert((int) $db->GetOne("SELECT COUNT(*) FROM project_company_hr_leave_ledger_entry WHERE company_key_hash = ? AND employee_key = ? AND entry_status = 'POSTED'", [$companyKeyHash, $employeeKey]) >= 5, 'Leave ledger entries were not appended.');
    hr_leave_assert((int) $db->GetOne("SELECT COUNT(*) FROM project_company_hr_notification_intent WHERE company_key_hash = ? AND record_key = ? AND intent_status = 'PENDING'", [$companyKeyHash, $keys['leave_application_key']]) >= 1, 'Leave approval did not persist a notification intent in its owner transaction.');
} finally {
    $tables = [
        'project_company_hr_notification_intent', 'project_company_hr_leave_ledger_entry',
        'project_company_hr_leave_block_allow', 'project_company_hr_leave_block_date',
        'project_company_hr_leave_block_list', 'project_company_hr_leave_encashment',
        'project_company_hr_compensatory_leave_request', 'project_company_hr_leave_adjustment',
        'project_company_hr_leave_application', 'project_company_hr_leave_allocation',
        'project_company_hr_holiday_list_assignment', 'project_company_hr_leave_policy_assignment',
        'project_company_hr_leave_policy_detail', 'project_company_hr_leave_policy',
        'project_company_hr_earned_leave_schedule', 'project_company_hr_holiday',
        'project_company_hr_holiday_list', 'project_company_hr_leave_period',
        'project_company_hr_leave_type',
    ];
    foreach ($tables as $table) {
        $db->Execute("DELETE FROM {$table} WHERE company_key_hash = ?", [$companyKeyHash]);
    }
    $db->Execute('DELETE FROM project_company_hr_employee WHERE company_key_hash = ? AND employee_key IN (?, ?)', [$companyKeyHash, $employeeKey, $secondEmployeeKey]);
    if ($auditKeys !== []) {
        $placeholders = implode(',', array_fill(0, count($auditKeys), '?'));
        $db->Execute("DELETE FROM builder_audit_log WHERE record_key IN ($placeholders)", $auditKeys);
    }
}

$targets = yovel_admin_hr_leave_form_targets();
$schemas = yovel_admin_hr_default_form_fields();
$adapter = yovel_admin_shared_form_adapter('hr');
foreach ([
    'leave-type', 'leave-period', 'leave-policy', 'leave-policy-assignment', 'leave-allocation',
    'leave-application', 'leave-adjustment', 'compensatory-leave-request', 'leave-encashment',
    'earned-leave-schedule', 'holiday-list-assignment', 'leave-block-list',
] as $target) {
    hr_leave_assert(isset($targets[$target]), 'Leave Form Builder target is missing: ' . $target);
    hr_leave_assert(($targets[$target]['versioned'] ?? false) === true, 'Leave target is not versioned: ' . $target);
    hr_leave_assert(($targets[$target]['protected_fields'] ?? []) !== [], 'Leave target has no protected fields: ' . $target);
    hr_leave_assert(isset($schemas[$target]), 'Leave target has no default schema: ' . $target);
    hr_leave_assert(isset($adapter['protected_fields'][$target]), 'Shared Form Builder adapter omitted leave protections: ' . $target);
}

$workspacePath = $root . '/company/admin/modules/hr/views/leave.php';
hr_leave_assert(is_file($workspacePath), 'Leaves workspace is missing.');
$workspace = (string) file_get_contents($workspacePath);
foreach ([
    'grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr)',
    'data-leave-mode="applications"',
    'data-leave-mode="ledger"',
    'data-leave-mode="calendar"',
    'data-record-modal-open="hr-leave-application-modal"',
    'data-record-modal-open="hr-leave-allocation-modal"',
    'data-record-modal-open="hr-leave-master-modal"',
    'data-record-modal-open="hr-leave-control-modal"',
    'data-record-modal',
    'data-confirm-submit',
] as $marker) {
    hr_leave_assert(str_contains($workspace, $marker), 'Leaves workspace is missing contract marker: ' . $marker);
}
hr_leave_assert(substr_count($workspace, 'data-confirm-submit') >= 8, 'Every Leave action must cross a confirmation boundary.');
hr_leave_assert(!str_contains($workspace, 'data-confirm-dialog'), 'Leaves workspace copied or nested the shared confirmation dialog.');

echo "HR leave management checks passed.\n";
