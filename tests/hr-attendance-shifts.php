<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/hr/functions.php';
require_once $root . '/company/admin/modules/shared/forms.php';

function hr_attendance_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach ([
    'yovel_admin_persist_hr_attendance_master',
    'yovel_admin_persist_employee_checkin',
    'yovel_admin_mark_attendance',
    'yovel_admin_process_auto_attendance',
    'yovel_admin_persist_attendance_request',
    'yovel_admin_transition_attendance_request',
    'yovel_admin_persist_shift_assignment',
    'yovel_admin_bulk_shift_assignment',
    'yovel_admin_persist_shift_request',
    'yovel_admin_transition_shift_request',
    'yovel_admin_persist_overtime_slip',
    'yovel_admin_bulk_mark_attendance',
    'yovel_admin_hr_attendance_data',
    'yovel_admin_hr_attendance_form_targets',
    'yovel_admin_hr_workforce_read_contract',
] as $function) {
    hr_attendance_assert(function_exists($function), 'Missing HR-WP-02 interface: ' . $function);
}

$db = bx_db();
yovel_admin_hr_schema();

$requiredTables = [
    'project_company_hr_shift_type',
    'project_company_hr_shift_location',
    'project_company_hr_shift_schedule',
    'project_company_hr_shift_assignment',
    'project_company_hr_shift_request',
    'project_company_hr_employee_checkin',
    'project_company_hr_attendance',
    'project_company_hr_attendance_request',
    'project_company_hr_overtime_type',
    'project_company_hr_overtime_salary_component',
    'project_company_hr_overtime_slip',
    'project_company_hr_overtime_detail',
];
foreach ($requiredTables as $table) {
    hr_attendance_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    ) === 1, 'Missing HR-WP-02 table: ' . $table);
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
hr_attendance_assert(is_array($scope) && $scope !== [], 'An active HR company fixture is required.');

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
$auditKeys = [];
$keys = [];

try {
    yovel_admin_db_execute(
        $db,
        "INSERT INTO project_company_hr_employee (
            employee_key, company_key, company_key_hash, employee_code, first_name, employee_name,
            employee_status, branch_key, department_key, job_position_key, created_by_admin_key, updated_by_admin_key
         ) VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?, ?, ?, ?)",
        [
            $employeeKey, $companyKey, $companyKeyHash, 'WP02_' . $token, 'Attendance', 'Attendance Fixture',
            (string) $scope['branch_key'], ($scope['department_key'] ?: null), ($scope['job_position_key'] ?: null),
            $adminKey, $adminKey,
        ],
        'HR-WP-02 employee fixture'
    );

    $shiftLocation = yovel_admin_persist_hr_attendance_master($db, $company, $admin, 'SHIFT_LOCATION', [
        'location_name' => 'Manila Office ' . $token,
        'checkin_radius' => '150',
        'latitude' => '14.5995',
        'longitude' => '120.9842',
    ]);
    $keys['shift_location_key'] = (string) $shiftLocation['shift_location_key'];
    $auditKeys[] = $keys['shift_location_key'];

    $overtimeType = yovel_admin_persist_hr_attendance_master($db, $company, $admin, 'OVERTIME_TYPE', [
        'overtime_code' => 'OT_' . $token,
        'overtime_name' => 'Regular overtime',
        'calculation_method' => 'FIXED_HOURLY_RATE',
        'hourly_rate' => '250.00',
        'standard_multiplier' => '1.25',
        'maximum_hours' => '4',
        'salary_component_reference' => 'BASIC',
        'applicable_salary_components' => ['BASIC', 'ALLOWANCE'],
    ]);
    $keys['overtime_type_key'] = (string) $overtimeType['overtime_type_key'];
    $auditKeys[] = $keys['overtime_type_key'];
    hr_attendance_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_hr_overtime_salary_component WHERE company_key_hash = ? AND overtime_type_key = ?',
        [$companyKeyHash, $keys['overtime_type_key']]
    ) === 2, 'Overtime Salary Component rows were not retained without Payroll writes.');

    $shiftType = yovel_admin_persist_hr_attendance_master($db, $company, $admin, 'SHIFT_TYPE', [
        'shift_code' => 'NIGHT_' . $token,
        'shift_name' => 'Night Shift',
        'timezone_name' => 'Asia/Manila',
        'start_time' => '22:00:00',
        'end_time' => '06:00:00',
        'begin_checkin_before_minutes' => '60',
        'allow_checkout_after_minutes' => '90',
        'half_day_threshold_hours' => '4',
        'absent_threshold_hours' => '2',
        'enable_auto_attendance' => '1',
        'allow_overtime' => '1',
        'overtime_type_key' => $keys['overtime_type_key'],
    ]);
    $keys['shift_type_key'] = (string) $shiftType['shift_type_key'];
    $auditKeys[] = $keys['shift_type_key'];
    hr_attendance_assert((int) ($shiftType['crosses_midnight'] ?? 0) === 1, 'Cross-midnight shift window was not derived.');
    hr_attendance_assert((string) ($shiftType['timezone_name'] ?? '') === 'Asia/Manila', 'Shift timezone was not retained.');

    $schedule = yovel_admin_persist_hr_attendance_master($db, $company, $admin, 'SHIFT_SCHEDULE', [
        'schedule_code' => 'SCHED_' . $token,
        'schedule_name' => 'Weekly Night Rotation',
        'shift_type_key' => $keys['shift_type_key'],
        'frequency_weeks' => '1',
        'repeat_days' => ['MON', 'TUE', 'WED', 'THU', 'FRI'],
        'schedule_status' => 'ACTIVE',
    ]);
    $keys['shift_schedule_key'] = (string) $schedule['shift_schedule_key'];
    $auditKeys[] = $keys['shift_schedule_key'];

    $assignment = yovel_admin_persist_shift_assignment($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'shift_type_key' => $keys['shift_type_key'],
        'shift_schedule_key' => $keys['shift_schedule_key'],
        'shift_location_key' => $keys['shift_location_key'],
        'start_date' => '2026-08-24',
        'end_date' => '2026-08-31',
        'assignment_status' => 'ACTIVE',
        'source_reference' => 'TEST-ASSIGN-' . $token,
    ]);
    $keys['shift_assignment_key'] = (string) $assignment['shift_assignment_key'];
    $auditKeys[] = $keys['shift_assignment_key'];
    hr_attendance_assert((string) ($assignment['source_reference'] ?? '') === 'TEST-ASSIGN-' . $token, 'Shift assignment source reference was not retained.');

    $bulkAssignments = yovel_admin_bulk_shift_assignment($db, $company, $admin, [$employeeKey], [
        'shift_type_key' => $keys['shift_type_key'],
        'shift_schedule_key' => $keys['shift_schedule_key'],
        'shift_location_key' => $keys['shift_location_key'],
        'start_date' => '2026-09-02',
        'end_date' => '2026-09-05',
        'assignment_status' => 'ACTIVE',
        'source_reference' => 'TEST-TOOL-' . $token,
    ]);
    hr_attendance_assert(count($bulkAssignments) === 1, 'Shift Assignment Tool did not save the selected employee.');
    hr_attendance_assert((string) $bulkAssignments[0]['source_system'] === 'ASSIGNMENT_TOOL', 'Shift Assignment Tool source metadata is missing.');
    $auditKeys[] = (string) $bulkAssignments[0]['shift_assignment_key'];
    $bulkAssignmentsAgain = yovel_admin_bulk_shift_assignment($db, $company, $admin, [$employeeKey], [
        'shift_type_key' => $keys['shift_type_key'],
        'shift_schedule_key' => $keys['shift_schedule_key'],
        'shift_location_key' => $keys['shift_location_key'],
        'start_date' => '2026-09-02',
        'end_date' => '2026-09-05',
        'assignment_status' => 'ACTIVE',
        'source_reference' => 'TEST-TOOL-' . $token,
    ]);
    hr_attendance_assert((string) $bulkAssignmentsAgain[0]['shift_assignment_key'] === (string) $bulkAssignments[0]['shift_assignment_key'], 'Shift Assignment Tool is not idempotent for one batch source.');

    $shiftRequest = yovel_admin_persist_shift_request($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'shift_type_key' => $keys['shift_type_key'],
        'from_date' => '2026-09-01',
        'to_date' => '2026-09-07',
        'request_status' => 'DRAFT',
        'approver_admin_key' => $adminKey,
        'reason' => 'Coverage request',
    ]);
    $keys['shift_request_key'] = (string) $shiftRequest['shift_request_key'];
    $auditKeys[] = $keys['shift_request_key'];
    $approvedShiftRequest = yovel_admin_transition_shift_request($db, $company, $admin, $keys['shift_request_key'], 'APPROVED');
    hr_attendance_assert((string) $approvedShiftRequest['request_status'] === 'APPROVED', 'Shift request was not approved.');
    $shiftRequestMutationRejected = false;
    try {
        yovel_admin_transition_shift_request($db, $company, $admin, $keys['shift_request_key'], 'REJECTED');
    } catch (InvalidArgumentException) {
        $shiftRequestMutationRejected = true;
    }
    hr_attendance_assert($shiftRequestMutationRejected, 'A final shift request was mutated.');
    $rejectedShiftRequest = yovel_admin_persist_shift_request($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'shift_type_key' => $keys['shift_type_key'],
        'from_date' => '2026-09-08',
        'to_date' => '2026-09-09',
        'request_status' => 'DRAFT',
        'approver_admin_key' => $adminKey,
        'reason' => 'Rejected coverage fixture',
    ]);
    $auditKeys[] = (string) $rejectedShiftRequest['shift_request_key'];
    $rejectedShiftRequest = yovel_admin_transition_shift_request($db, $company, $admin, (string) $rejectedShiftRequest['shift_request_key'], 'REJECTED');
    hr_attendance_assert((string) $rejectedShiftRequest['request_status'] === 'REJECTED', 'Shift request rejection was not persisted.');

    $in = yovel_admin_persist_employee_checkin($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'log_type' => 'IN',
        'checkin_at' => '2026-08-24 21:55:00',
        'timezone_name' => 'Asia/Manila',
        'device_id' => 'DEVICE-' . $token,
        'source_system' => 'DEVICE',
        'source_reference' => 'LOG-IN-' . $token,
        'latitude' => '14.5995',
        'longitude' => '120.9842',
    ]);
    $keys['in_checkin_key'] = (string) $in['checkin_key'];
    $auditKeys[] = $keys['in_checkin_key'];
    $duplicateIn = yovel_admin_persist_employee_checkin($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'log_type' => 'IN',
        'checkin_at' => '2026-08-24 21:55:00',
        'timezone_name' => 'Asia/Manila',
        'device_id' => 'DEVICE-' . $token,
        'source_system' => 'DEVICE',
        'source_reference' => 'LOG-IN-' . $token,
        'latitude' => '14.5995',
        'longitude' => '120.9842',
    ]);
    hr_attendance_assert((string) $duplicateIn['checkin_key'] === $keys['in_checkin_key'], 'Duplicate check-in did not resolve idempotently.');
    hr_attendance_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_hr_employee_checkin WHERE company_key_hash = ? AND source_system = ? AND source_reference = ?',
        [$companyKeyHash, 'DEVICE', 'LOG-IN-' . $token]
    ) === 1, 'Duplicate raw check-in was inserted.');

    $out = yovel_admin_persist_employee_checkin($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'log_type' => 'OUT',
        'checkin_at' => '2026-08-25 06:30:00',
        'timezone_name' => 'Asia/Manila',
        'device_id' => 'DEVICE-' . $token,
        'source_system' => 'DEVICE',
        'source_reference' => 'LOG-OUT-' . $token,
    ]);
    $keys['out_checkin_key'] = (string) $out['checkin_key'];
    $auditKeys[] = $keys['out_checkin_key'];

    $auto = yovel_admin_process_auto_attendance($db, $company, $admin, $employeeKey, '2026-08-24');
    $keys['attendance_key'] = (string) $auto['attendance_key'];
    $auditKeys[] = $keys['attendance_key'];
    hr_attendance_assert((string) $auto['attendance_status'] === 'PRESENT', 'Auto attendance did not derive Present status.');
    hr_attendance_assert((string) $auto['source_mode'] === 'AUTO', 'Derived attendance is not marked as auto processed.');
    $sourceRefs = json_decode((string) $auto['source_references_json'], true, 512, JSON_THROW_ON_ERROR);
    hr_attendance_assert($sourceRefs === [$keys['in_checkin_key'], $keys['out_checkin_key']], 'Derived attendance did not retain deterministic raw check-in references.');
    $autoAgain = yovel_admin_process_auto_attendance($db, $company, $admin, $employeeKey, '2026-08-24');
    hr_attendance_assert((string) $autoAgain['attendance_key'] === $keys['attendance_key'], 'Auto attendance is not idempotent.');
    hr_attendance_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_hr_attendance WHERE company_key_hash = ? AND employee_key = ? AND attendance_date = ?',
        [$companyKeyHash, $employeeKey, '2026-08-24']
    ) === 1, 'Employee/date attendance uniqueness failed.');
    hr_attendance_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_hr_employee_checkin WHERE company_key_hash = ? AND employee_key = ?',
        [$companyKeyHash, $employeeKey]
    ) === 2, 'Raw check-ins were overwritten by derived attendance.');

    $manual = yovel_admin_mark_attendance($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'attendance_date' => '2026-08-26',
        'attendance_status' => 'WORK_FROM_HOME',
        'source_reference' => 'MANUAL-' . $token,
        'remarks' => 'Approved remote work.',
    ]);
    $keys['manual_attendance_key'] = (string) $manual['attendance_key'];
    $auditKeys[] = $keys['manual_attendance_key'];
    hr_attendance_assert((string) $manual['source_mode'] === 'MANUAL', 'Manual attendance source was not retained.');
    $manualUpdate = yovel_admin_mark_attendance($db, $company, $admin, [
        'attendance_key' => $keys['manual_attendance_key'],
        'employee_key' => $employeeKey,
        'attendance_date' => '2026-08-26',
        'attendance_status' => 'PRESENT',
        'source_reference' => 'MANUAL-' . $token,
        'remarks' => 'Corrected by administrator.',
    ]);
    hr_attendance_assert((string) $manualUpdate['attendance_status'] === 'PRESENT', 'Attendance update did not read back exactly.');

    $bulkAttendance = yovel_admin_bulk_mark_attendance($db, $company, $admin, [$employeeKey], [
        'attendance_date' => '2026-09-02',
        'attendance_status' => 'PRESENT',
        'source_reference' => 'TEST-ATTENDANCE-TOOL-' . $token,
        'remarks' => 'Bulk tool fixture.',
    ]);
    hr_attendance_assert(count($bulkAttendance) === 1, 'Employee Attendance Tool did not mark the selected employee.');
    hr_attendance_assert((string) $bulkAttendance[0]['attendance_date'] === '2026-09-02', 'Employee Attendance Tool read-back failed.');
    $auditKeys[] = (string) $bulkAttendance[0]['attendance_key'];

    $attendanceRequest = yovel_admin_persist_attendance_request($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'from_date' => '2026-08-27',
        'to_date' => '2026-08-28',
        'reason' => 'WORK_FROM_HOME',
        'explanation' => 'Approved field work.',
        'request_status' => 'DRAFT',
    ]);
    $keys['attendance_request_key'] = (string) $attendanceRequest['attendance_request_key'];
    $auditKeys[] = $keys['attendance_request_key'];
    $approvedRequest = yovel_admin_transition_attendance_request($db, $company, $admin, $keys['attendance_request_key'], 'APPROVED');
    hr_attendance_assert((string) $approvedRequest['request_status'] === 'APPROVED', 'Attendance request was not approved.');
    hr_attendance_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_hr_attendance WHERE company_key_hash = ? AND employee_key = ? AND attendance_request_key = ?',
        [$companyKeyHash, $employeeKey, $keys['attendance_request_key']]
    ) === 2, 'Approved attendance request did not create its dated attendance records.');
    $finalRequestRejected = false;
    try {
        yovel_admin_transition_attendance_request($db, $company, $admin, $keys['attendance_request_key'], 'REJECTED');
    } catch (InvalidArgumentException) {
        $finalRequestRejected = true;
    }
    hr_attendance_assert($finalRequestRejected, 'A final attendance request was mutated.');
    $rejectedAttendanceRequest = yovel_admin_persist_attendance_request($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'from_date' => '2026-09-10',
        'to_date' => '2026-09-10',
        'reason' => 'ON_DUTY',
        'explanation' => 'Rejected request fixture.',
        'request_status' => 'DRAFT',
    ]);
    $auditKeys[] = (string) $rejectedAttendanceRequest['attendance_request_key'];
    $rejectedAttendanceRequest = yovel_admin_transition_attendance_request($db, $company, $admin, (string) $rejectedAttendanceRequest['attendance_request_key'], 'REJECTED');
    hr_attendance_assert((string) $rejectedAttendanceRequest['request_status'] === 'REJECTED', 'Attendance request rejection was not persisted.');
    hr_attendance_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_hr_attendance WHERE company_key_hash = ? AND employee_key = ? AND attendance_request_key = ?',
        [$companyKeyHash, $employeeKey, (string) $rejectedAttendanceRequest['attendance_request_key']]
    ) === 0, 'Rejected Attendance Request created attendance rows.');

    $overtime = yovel_admin_persist_overtime_slip($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'posting_date' => '2026-08-29',
        'start_date' => '2026-08-24',
        'end_date' => '2026-08-29',
        'slip_status' => 'SUBMITTED',
        'details' => [[
            'reference_attendance_key' => $keys['attendance_key'],
            'overtime_date' => '2026-08-24',
            'overtime_type_key' => $keys['overtime_type_key'],
            'overtime_hours' => '2.5',
            'standard_working_hours' => '8',
        ]],
    ]);
    $keys['overtime_slip_key'] = (string) $overtime['overtime_slip_key'];
    $auditKeys[] = $keys['overtime_slip_key'];
    hr_attendance_assert((string) $overtime['total_overtime_hours'] === '2.50', 'Overtime total was not verified exactly.');
    hr_attendance_assert((string) $overtime['payroll_handoff_status'] === 'PENDING', 'Overtime did not stop at the Payroll handoff boundary.');
    hr_attendance_assert(empty($overtime['payroll_entry_key']) && empty($overtime['salary_slip_key']), 'HR fabricated a Payroll record reference.');

    $overtimeRejected = false;
    try {
        yovel_admin_persist_overtime_slip($db, $company, $admin, [
            'employee_key' => $employeeKey,
            'posting_date' => '2026-08-29',
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-29',
            'details' => [[
                'overtime_date' => '2026-08-24',
                'overtime_type_key' => $keys['overtime_type_key'],
                'overtime_hours' => '5',
                'standard_working_hours' => '8',
            ]],
        ]);
    } catch (InvalidArgumentException) {
        $overtimeRejected = true;
    }
    hr_attendance_assert($overtimeRejected, 'Overtime exceeded the configured maximum.');

    $beforeRollback = (int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_hr_employee_checkin WHERE company_key_hash = ? AND employee_key = ?',
        [$companyKeyHash, $employeeKey]
    );
    $rollbackTriggered = false;
    try {
        yovel_admin_persist_employee_checkin($db, $company, $admin, [
            'employee_key' => $employeeKey,
            'log_type' => 'IN',
            'checkin_at' => '2026-08-27 21:55:00',
            'timezone_name' => 'Asia/Manila',
            'source_system' => 'MANUAL',
            'source_reference' => 'ROLLBACK-' . $token,
        ], static function (): void {
            throw new RuntimeException('Injected check-in rollback.');
        });
    } catch (RuntimeException $error) {
        $rollbackTriggered = $error->getMessage() === 'Injected check-in rollback.';
    }
    hr_attendance_assert($rollbackTriggered, 'Attendance rollback injection did not execute.');
    hr_attendance_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_hr_employee_checkin WHERE company_key_hash = ? AND employee_key = ?',
        [$companyKeyHash, $employeeKey]
    ) === $beforeRollback, 'Attendance rollback left a raw check-in.');

    $foreignRejected = false;
    try {
        yovel_admin_persist_employee_checkin($db, [
            'company_key' => $companyKey,
            'company_key_hash' => str_repeat('f', 64),
        ], $admin, [
            'employee_key' => $employeeKey,
            'log_type' => 'IN',
            'checkin_at' => '2026-08-30 08:00:00',
            'timezone_name' => 'Asia/Manila',
            'source_reference' => 'FOREIGN-' . $token,
        ]);
    } catch (Throwable) {
        $foreignRejected = true;
    }
    hr_attendance_assert($foreignRejected, 'Attendance accepted a foreign company scope.');

    $contract = yovel_admin_hr_workforce_read_contract($company);
    hr_attendance_assert(($contract['contract'] ?? '') === 'hr.workforce.v1', 'HR workforce read contract signature changed.');
    $fixtureWorkforce = array_values(array_filter(
        $contract['employees'] ?? [],
        static fn (array $row): bool => ($row['employee_key'] ?? '') === $employeeKey
    ));
    hr_attendance_assert(count($fixtureWorkforce) === 1, 'Workforce read contract omitted the fixture employee.');
    foreach (['employee_key', 'department_key', 'job_position_key'] as $stableKey) {
        hr_attendance_assert(array_key_exists($stableKey, $fixtureWorkforce[0]), 'Workforce read contract omitted ' . $stableKey . '.');
    }
    foreach (['education', 'employment_history', 'employee_group', 'calendar'] as $capability) {
        hr_attendance_assert(($contract['availability'][$capability]['available'] ?? true) === false, 'Unavailable HR capability was fabricated: ' . $capability);
    }

    foreach ($auditKeys as $recordKey) {
        hr_attendance_assert((int) $db->GetOne(
            'SELECT COUNT(*) FROM builder_audit_log WHERE record_key = ?',
            [$recordKey]
        ) >= 1, 'HR-WP-02 write has no audit evidence: ' . $recordKey);
    }
} finally {
    $db->Execute('DELETE FROM project_company_hr_overtime_detail WHERE company_key_hash = ? AND employee_key = ?', [$companyKeyHash, $employeeKey]);
    $db->Execute('DELETE FROM project_company_hr_overtime_slip WHERE company_key_hash = ? AND employee_key = ?', [$companyKeyHash, $employeeKey]);
    $db->Execute('DELETE FROM project_company_hr_attendance WHERE company_key_hash = ? AND employee_key = ?', [$companyKeyHash, $employeeKey]);
    $db->Execute('DELETE FROM project_company_hr_attendance_request WHERE company_key_hash = ? AND employee_key = ?', [$companyKeyHash, $employeeKey]);
    $db->Execute('DELETE FROM project_company_hr_employee_checkin WHERE company_key_hash = ? AND employee_key = ?', [$companyKeyHash, $employeeKey]);
    $db->Execute('DELETE FROM project_company_hr_shift_request WHERE company_key_hash = ? AND employee_key = ?', [$companyKeyHash, $employeeKey]);
    $db->Execute('DELETE FROM project_company_hr_shift_assignment WHERE company_key_hash = ? AND employee_key = ?', [$companyKeyHash, $employeeKey]);
    $db->Execute('DELETE FROM project_company_hr_shift_schedule WHERE company_key_hash = ? AND schedule_code = ?', [$companyKeyHash, 'SCHED_' . $token]);
    $db->Execute('DELETE FROM project_company_hr_shift_type WHERE company_key_hash = ? AND shift_code = ?', [$companyKeyHash, 'NIGHT_' . $token]);
    $db->Execute('DELETE FROM project_company_hr_shift_location WHERE company_key_hash = ? AND location_name = ?', [$companyKeyHash, 'Manila Office ' . $token]);
    $db->Execute('DELETE FROM project_company_hr_overtime_salary_component WHERE company_key_hash = ? AND overtime_type_key IN (SELECT overtime_type_key FROM project_company_hr_overtime_type WHERE company_key_hash = ? AND overtime_code = ?)', [$companyKeyHash, $companyKeyHash, 'OT_' . $token]);
    $db->Execute('DELETE FROM project_company_hr_overtime_type WHERE company_key_hash = ? AND overtime_code = ?', [$companyKeyHash, 'OT_' . $token]);
    $db->Execute('DELETE FROM project_company_hr_employee WHERE company_key_hash = ? AND employee_key = ?', [$companyKeyHash, $employeeKey]);
    if ($auditKeys !== []) {
        $placeholders = implode(',', array_fill(0, count($auditKeys), '?'));
        $db->Execute("DELETE FROM builder_audit_log WHERE record_key IN ($placeholders)", $auditKeys);
    }
}

$targets = yovel_admin_hr_attendance_form_targets();
$schemas = yovel_admin_hr_default_form_fields();
$adapter = yovel_admin_shared_form_adapter('hr');
foreach ([
    'attendance', 'attendance-request', 'employee-checkin', 'shift-type', 'shift-location',
    'shift-assignment', 'shift-schedule', 'shift-request', 'overtime-type', 'overtime-slip',
] as $target) {
    hr_attendance_assert(isset($targets[$target]), 'Attendance Form Builder target is missing: ' . $target);
    hr_attendance_assert(($targets[$target]['versioned'] ?? false) === true, 'Attendance Form Builder target is not versioned: ' . $target);
    hr_attendance_assert(($targets[$target]['protected_fields'] ?? []) !== [], 'Attendance target has no protected workflow fields: ' . $target);
    hr_attendance_assert(isset($schemas[$target]), 'Attendance target has no default form schema: ' . $target);
    hr_attendance_assert(isset($adapter['protected_fields'][$target]), 'Shared Form Builder adapter omitted attendance protections: ' . $target);
}

$workspacePath = $root . '/company/admin/modules/hr/views/attendance.php';
hr_attendance_assert(is_file($workspacePath), 'The Shift & Attendance workspace is missing.');
$workspace = (string) file_get_contents($workspacePath);
foreach ([
    'grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr)',
    'data-attendance-mode="list"',
    'data-attendance-mode="calendar"',
    'data-attendance-mode="tool"',
    'data-record-modal-open="hr-checkin-modal"',
    'data-record-modal-open="hr-attendance-modal"',
    'data-record-modal-open="hr-attendance-request-modal"',
    'data-record-modal-open="hr-attendance-tool-modal"',
    'data-record-modal-open="hr-shift-assignment-modal"',
    'data-record-modal-open="hr-shift-assignment-tool-modal"',
    'data-record-modal-open="hr-shift-schedule-modal"',
    'data-record-modal-open="hr-overtime-modal"',
    'name="module_view" value="hr"',
    'name="section" value="attendance"',
    'data-record-modal',
    'data-confirm-submit',
] as $marker) {
    hr_attendance_assert(str_contains($workspace, $marker), 'Attendance workspace is missing contract marker: ' . $marker);
}
hr_attendance_assert(substr_count($workspace, 'data-confirm-submit') >= 12, 'Every attendance action must cross a confirmation boundary.');
hr_attendance_assert(!str_contains($workspace, 'data-confirm-dialog'), 'Attendance copied or nested the shared confirmation dialog.');

$attendanceSource = (string) file_get_contents($root . '/company/admin/modules/hr/attendance.php');
hr_attendance_assert(!preg_match('/(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+)?[^\n]*(?:payroll|salary_slip)/i', $attendanceSource), 'HR attendance writes a Payroll-owned table.');

echo "HR attendance, shifts, and overtime checks passed.\n";
