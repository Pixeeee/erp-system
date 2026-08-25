<?php
declare(strict_types=1);

require_once __DIR__ . '/operations-test-helper.php';

$mode = (string) ($argv[1] ?? '');
if ($mode === 'missing-owner-contract') {
    $projection = yovel_admin_operations_workforce_calendar_projection(operations_test_company('Missing HR Contract'));
    echo json_encode($projection, JSON_THROW_ON_ERROR);
    exit(0);
}

if (!function_exists('yovel_admin_hr_workforce_read_contract')) {
    function yovel_admin_hr_workforce_read_contract(array $company, array $options = []): array
    {
        $provider = $GLOBALS['operations_hr_workforce_contract'] ?? null;
        if (!is_callable($provider)) {
            throw new RuntimeException('HR workforce fixture is unavailable.');
        }
        return $provider($company, $options);
    }
}

if (!function_exists('yovel_admin_hr_calendar_read_contract')) {
    function yovel_admin_hr_calendar_read_contract(array $company, array $options = []): array
    {
        $provider = $GLOBALS['operations_hr_calendar_contract'] ?? null;
        if (!is_callable($provider)) {
            throw new RuntimeException('HR calendar fixture is unavailable.');
        }
        return $provider($company, $options);
    }
}

$company = operations_test_company('Operations Workforce Company');
$otherCompany = operations_test_company('Other Workforce Company');
$employeeKey = bx_uuid();
$departmentKey = bx_uuid();
$positionKey = bx_uuid();
$holidayListKey = bx_uuid();
$holidayKey = bx_uuid();
$capturedOptions = [];
$capturedCalendarOptions = [];
$GLOBALS['operations_hr_workforce_contract'] = static function (array $scope, array $options) use (
    $company,
    $employeeKey,
    $departmentKey,
    $positionKey,
    &$capturedOptions
): array {
    $capturedOptions = $options;
    return [
        'contract' => 'hr.workforce.v1',
        'company_key_hash' => (string) $scope['company_key_hash'],
        'employees' => [[
            'employee_key' => $employeeKey,
            'employee_code' => 'EMP-0001',
            'employee_name' => 'Ada Operator',
            'employee_status' => 'ACTIVE',
            'department_key' => $departmentKey,
            'job_position_key' => $positionKey,
            'api_token' => 'must-not-leak',
        ]],
        'availability' => [
            'education' => ['available' => false, 'reason' => 'Deferred to HR-WP-10.'],
            'employment_history' => ['available' => false, 'reason' => 'Deferred to HR-WP-10.'],
            'employee_group' => ['available' => false, 'reason' => 'Deferred to HR-WP-10.'],
            'calendar' => ['available' => false, 'reason' => 'Deferred to HR-WP-10.'],
        ],
    ];
};
$GLOBALS['operations_hr_calendar_contract'] = static function (array $scope, array $options) use (
    $holidayListKey,
    $holidayKey,
    &$capturedCalendarOptions
): array {
    $capturedCalendarOptions = $options;
    return [
        'contract' => 'hr.calendar-directory.v1',
        'company_key_hash' => (string) $scope['company_key_hash'],
        'range' => ['from_date' => '2026-01-01', 'to_date' => '2026-12-31'],
        'holiday_lists' => [[
            'holiday_list_key' => $holidayListKey,
            'holiday_list_code' => 'PH-2026',
            'holiday_list_name' => 'Philippines 2026',
            'from_date' => '2026-01-01',
            'to_date' => '2026-12-31',
            'timezone_name' => 'Asia/Manila',
            'holiday_list_status' => 'ACTIVE',
            'credential' => 'must-not-leak',
        ]],
        'holidays' => [[
            'holiday_key' => $holidayKey,
            'holiday_list_key' => $holidayListKey,
            'holiday_date' => '2026-06-12',
            'description' => 'Independence Day',
            'holiday_status' => 'ACTIVE',
            'api_token' => 'must-not-leak',
        ]],
        'assignments' => [],
        'leave_ranges' => [],
        'availability' => ['status' => 'AVAILABLE', 'authoritative' => true],
    ];
};

$projection = yovel_admin_operations_workforce_calendar_projection($company, [
    'status' => 'ACTIVE',
    'from_date' => '2026-01-01',
    'to_date' => '2026-12-31',
]);
operations_test_assert(($projection['contract_status'] ?? '') === 'AVAILABLE', 'Verified HR workforce envelope was not available.');
operations_test_assert(($projection['contract'] ?? '') === 'hr.workforce.v1', 'Operations changed the verified HR contract identity.');
operations_test_assert(($projection['company_key_hash'] ?? '') === $company['company_key_hash'], 'Workforce projection changed company scope.');
operations_test_assert($capturedOptions === ['status' => 'ACTIVE'], 'Operations did not pass the allow-listed status filter to HR.');
operations_test_assert($capturedCalendarOptions === ['from_date' => '2026-01-01', 'to_date' => '2026-12-31'], 'Operations did not pass the allow-listed calendar range to HR.');

$directories = $projection['directories'] ?? [];
$expectedDirectories = [
    'department', 'designation', 'employee', 'education', 'external_work_history',
    'employee_group', 'employee_group_table', 'internal_work_history', 'holiday', 'holiday_list',
];
operations_test_assert(array_keys($directories) === $expectedDirectories, 'Workforce projection does not trace all ten OP-03 rows.');
operations_test_assert(($directories['employee']['status'] ?? '') === 'AVAILABLE', 'Employee projection was not available.');
operations_test_assert(($directories['employee']['records'][0] ?? null) === [
    'employee_key' => $employeeKey,
    'employee_code' => 'EMP-0001',
    'employee_name' => 'Ada Operator',
    'employee_status' => 'ACTIVE',
    'department_key' => $departmentKey,
    'job_position_key' => $positionKey,
], 'Employee projection did not retain only the verified stable HR fields.');
operations_test_assert(!str_contains(json_encode($projection, JSON_THROW_ON_ERROR), 'must-not-leak'), 'Workforce projection exposed an owner secret.');

operations_test_assert(($directories['department']['status'] ?? '') === 'PARTIAL', 'Department references were presented as a complete directory.');
operations_test_assert(($directories['department']['records'] ?? []) === [['department_key' => $departmentKey]], 'Department stable reference was lost or embellished.');
operations_test_assert(in_array('department_name', $directories['department']['unavailable_fields'] ?? [], true), 'Unavailable department labels were not explicit.');
operations_test_assert(($directories['designation']['status'] ?? '') === 'PARTIAL', 'Designation references were presented as a complete directory.');
operations_test_assert(($directories['designation']['records'] ?? []) === [['job_position_key' => $positionKey]], 'Job-position stable reference was lost or embellished.');
operations_test_assert(in_array('designation_name', $directories['designation']['unavailable_fields'] ?? [], true), 'Unavailable designation labels were not explicit.');

foreach (['education', 'external_work_history', 'employee_group', 'employee_group_table', 'internal_work_history'] as $directory) {
    operations_test_assert(($directories[$directory]['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', $directory . ' did not expose the HR dependency gap.');
    operations_test_assert(($directories[$directory]['records'] ?? null) === [], $directory . ' fabricated owner records.');
    operations_test_assert(str_contains((string) ($directories[$directory]['reason'] ?? ''), 'HR-WP-10'), $directory . ' lost the exact owner reason.');
    operations_test_assert(($directories[$directory]['blocking'] ?? true) === false, $directory . ' incorrectly blocks Operations.');
}
operations_test_assert(($directories['holiday_list']['status'] ?? '') === 'AVAILABLE', 'Verified Holiday List projection was not available.');
operations_test_assert(($directories['holiday_list']['records'][0] ?? null) === [
    'holiday_list_key' => $holidayListKey,
    'holiday_list_code' => 'PH-2026',
    'holiday_list_name' => 'Philippines 2026',
    'from_date' => '2026-01-01',
    'to_date' => '2026-12-31',
    'timezone_name' => 'Asia/Manila',
    'holiday_list_status' => 'ACTIVE',
], 'Holiday List projection did not retain only exact stable HR fields.');
operations_test_assert(($directories['holiday']['status'] ?? '') === 'AVAILABLE', 'Verified Holiday projection was not available.');
operations_test_assert(($directories['holiday']['records'][0] ?? null) === [
    'holiday_key' => $holidayKey,
    'holiday_list_key' => $holidayListKey,
    'holiday_date' => '2026-06-12',
    'description' => 'Independence Day',
    'holiday_status' => 'ACTIVE',
], 'Holiday projection did not retain only exact stable HR fields.');
operations_test_assert(!str_contains(json_encode($projection, JSON_THROW_ON_ERROR), 'must-not-leak'), 'Calendar projection exposed an owner secret.');

$GLOBALS['operations_hr_calendar_contract'] = static fn (array $scope, array $options): array => [
    'contract' => 'hr.calendar-directory.v1',
    'company_key_hash' => (string) $otherCompany['company_key_hash'],
    'range' => $options,
    'holiday_lists' => [['holiday_list_key' => bx_uuid()]],
    'holidays' => [],
    'availability' => ['status' => 'AVAILABLE', 'authoritative' => true],
];
$calendarMismatch = yovel_admin_operations_workforce_calendar_projection($company, ['from_date' => '2026-01-01', 'to_date' => '2026-12-31']);
operations_test_assert(($calendarMismatch['contract_status'] ?? '') === 'AVAILABLE', 'Invalid calendar envelope erased a valid workforce projection.');
operations_test_assert(($calendarMismatch['directories']['employee']['status'] ?? '') === 'AVAILABLE', 'Calendar failure erased valid employee records.');
foreach (['holiday', 'holiday_list'] as $directory) {
    operations_test_assert(($calendarMismatch['directories'][$directory]['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Cross-company calendar records were accepted.');
    operations_test_assert(($calendarMismatch['directories'][$directory]['records'] ?? null) === [], 'Cross-company calendar records leaked into Operations.');
    operations_test_assert(($calendarMismatch['directories'][$directory]['blocking'] ?? true) === false, 'Invalid calendar response blocked Operations.');
}

$GLOBALS['operations_hr_workforce_contract'] = static fn (array $scope, array $options): array => [
    'contract' => 'hr.workforce.v1',
    'company_key_hash' => (string) $otherCompany['company_key_hash'],
    'employees' => [['employee_key' => bx_uuid(), 'employee_code' => 'LEAK', 'employee_name' => 'Wrong Company']],
    'availability' => [],
];
$mismatch = yovel_admin_operations_workforce_calendar_projection($company);
operations_test_assert(($mismatch['contract_status'] ?? '') === 'UNAVAILABLE', 'Cross-company HR envelope was accepted.');
operations_test_assert(($mismatch['directories']['employee']['records'] ?? null) === [], 'Cross-company HR records leaked into Operations.');
operations_test_assert(($mismatch['blocking'] ?? true) === false, 'Invalid HR envelope incorrectly blocked the workspace.');

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' missing-owner-contract';
$missingJson = shell_exec($command);
operations_test_assert(is_string($missingJson) && $missingJson !== '', 'Missing-contract child check did not return a projection.');
$missing = json_decode($missingJson, true, 512, JSON_THROW_ON_ERROR);
operations_test_assert(($missing['contract_status'] ?? '') === 'UNAVAILABLE', 'Absent HR contract was rendered as empty success.');
operations_test_assert(str_contains((string) ($missing['message'] ?? ''), 'HR workforce'), 'Absent HR contract did not expose an actionable owner message.');

$sections = yovel_admin_operations_sections();
operations_test_assert(isset($sections['workforce-directory'], $sections['workforce-calendars']), 'OP-03 workspace sections are not registered.');
operations_test_assert(count(yovel_admin_operations_builder_targets()) === 16, 'Universal Operations Form Builder does not include the accepted OP-03 sections and subsequent registered projections.');
$workspaceSource = (string) file_get_contents(dirname(__DIR__) . '/company/admin/modules/operations/views/workspace.php');
$workforceSource = (string) file_get_contents(dirname(__DIR__) . '/company/admin/modules/operations/views/sections/workforce.php');
$calendarSource = (string) file_get_contents(dirname(__DIR__) . '/company/admin/modules/operations/views/sections/calendars.php');
operations_test_assert(str_contains($workspaceSource, "'workforce-directory' => 'workforce.php'") && str_contains($workspaceSource, "'workforce-calendars' => 'calendars.php'"), 'Workspace does not dispatch both OP-03 views.');
operations_test_assert(str_contains($workspaceSource, 'minmax(0,12fr)') && str_contains($workspaceSource, 'minmax(16rem,8fr)'), 'OP-03 did not retain the 12/8 workspace shell.');
foreach ([$workforceSource, $calendarSource] as $source) {
    operations_test_assert(!preg_match('/<(?:form|button)\b/i', $source), 'Read-only OP-03 view exposes a local or foreign mutation command.');
    operations_test_assert(str_contains($source, '?view=hr&amp;section=') || str_contains($source, "['owner_actions']"), 'OP-03 view is missing owner-routed actions.');
    operations_test_assert(!str_contains($source, 'project_company_hr_'), 'OP-03 view queries an HR owner table.');
    operations_test_assert(!str_contains($source, 'rounded-md border'), 'OP-03 view nests bordered cards inside the workspace panel.');
}
operations_test_assert(str_contains($workforceSource, 'workforce_status'), 'Workforce view is missing a server-backed status filter.');
operations_test_assert(str_contains($calendarSource, "['holiday_list']['records']") && str_contains($calendarSource, "['holiday']['records']"), 'Calendar view does not render verified Holiday List and Holiday records.');

$operationsFiles = glob(dirname(__DIR__) . '/company/admin/modules/operations/*.php') ?: [];
foreach ($operationsFiles as $operationsFile) {
    $source = (string) file_get_contents($operationsFile);
    operations_test_assert(!str_contains($source, 'project_company_hr_'), basename($operationsFile) . ' directly queries an HR owner table.');
}

echo "operations workforce/calendar tests passed\n";
