<?php
declare(strict_types=1);

function yovel_admin_operations_workforce_status(string $status): string
{
    $status = strtoupper(trim($status));
    return in_array($status, ['ACTIVE', 'INACTIVE', 'ON_LEAVE', 'SEPARATED', 'DRAFT', 'ALL'], true)
        ? $status
        : 'ALL';
}

function yovel_admin_operations_workforce_unavailable_directories(string $reason): array
{
    $reason = trim($reason) !== '' ? substr(trim($reason), 0, 240) : 'HR workforce data is unavailable.';
    $definitions = [
        'department' => ['Department', ['department_code', 'department_name', 'department_status', 'parent_department_key']],
        'designation' => ['Designation', ['designation_code', 'designation_name', 'designation_status', 'parent_designation_key']],
        'employee' => ['Employee', ['employee_key', 'employee_code', 'employee_name', 'employee_status', 'department_key', 'job_position_key']],
        'education' => ['Employee Education', ['education_key', 'employee_key', 'school', 'qualification', 'level', 'year']],
        'external_work_history' => ['Employee External Work History', ['history_key', 'employee_key', 'company_name', 'designation', 'from_date', 'to_date']],
        'employee_group' => ['Employee Group', ['employee_group_key', 'group_name', 'group_status']],
        'employee_group_table' => ['Employee Group Table', ['employee_group_member_key', 'employee_group_key', 'employee_key']],
        'internal_work_history' => ['Employee Internal Work History', ['history_key', 'employee_key', 'department_key', 'job_position_key', 'from_date', 'to_date']],
        'holiday' => ['Holiday', ['holiday_key', 'holiday_list_key', 'holiday_date', 'description']],
        'holiday_list' => ['Holiday List', ['holiday_list_key', 'holiday_list_name', 'from_date', 'to_date', 'holiday_list_status']],
    ];
    $directories = [];
    foreach ($definitions as $key => [$label, $fields]) {
        $directories[$key] = [
            'label' => $label,
            'status' => 'UNAVAILABLE_DEPENDENCY',
            'blocking' => false,
            'records' => [],
            'unavailable_fields' => $fields,
            'reason' => $reason,
        ];
    }
    return $directories;
}

function yovel_admin_operations_workforce_owner_actions(): array
{
    return [
        ['label' => 'Manage employees in HR', 'href' => '?view=hr&section=employee-profiles'],
        ['label' => 'Manage departments in HR', 'href' => '?view=hr&section=departments'],
        ['label' => 'Manage job positions in HR', 'href' => '?view=hr&section=job-positions'],
        ['label' => 'Open HR attendance', 'href' => '?view=hr&section=attendance'],
    ];
}

function yovel_admin_operations_workforce_projection_unavailable(array $company, string $message): array
{
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    return [
        'contract' => 'hr.workforce.v1',
        'contract_status' => 'UNAVAILABLE',
        'company_key_hash' => $companyKeyHash,
        'status_filter' => 'ALL',
        'blocking' => false,
        'message' => $message,
        'directories' => yovel_admin_operations_workforce_unavailable_directories($message),
        'owner_actions' => yovel_admin_operations_workforce_owner_actions(),
    ];
}

function yovel_admin_operations_workforce_bounded_value(mixed $value, string $field, int $maximum): string
{
    $value = trim((string) $value);
    if (strlen($value) > $maximum) {
        throw new RuntimeException('HR workforce response contains an oversized ' . $field . '.');
    }
    return $value;
}

function yovel_admin_operations_workforce_reference(mixed $value, string $field): string
{
    $value = yovel_admin_operations_workforce_bounded_value($value, $field, 64);
    if ($value !== '' && (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($value))) {
        throw new RuntimeException('HR workforce response contains an invalid ' . $field . '.');
    }
    return $value;
}

function yovel_admin_operations_workforce_availability_reason(array $availability, string $key): string
{
    $state = $availability[$key] ?? null;
    if (!is_array($state) || ($state['available'] ?? null) !== false) {
        return 'The verified HR workforce contract does not provide this directory.';
    }
    $reason = yovel_admin_operations_workforce_bounded_value($state['reason'] ?? '', $key . ' availability reason', 240);
    return $reason !== '' ? $reason : 'The verified HR workforce contract does not provide this directory.';
}

function yovel_admin_operations_calendar_date(mixed $value, string $default): string
{
    $value = trim((string) $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
        return $default;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : $default;
}

function yovel_admin_operations_calendar_records(array $company, string $fromDate, string $toDate): array
{
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    if (!function_exists('yovel_admin_hr_calendar_read_contract')) {
        throw new RuntimeException('HR calendar owner contract is unavailable.');
    }
    $envelope = yovel_admin_hr_calendar_read_contract($company, [
        'from_date' => $fromDate,
        'to_date' => $toDate,
    ]);
    $range = $envelope['range'] ?? null;
    $availability = $envelope['availability'] ?? null;
    if (($envelope['contract'] ?? null) !== 'hr.calendar-directory.v1'
        || !hash_equals($companyKeyHash, strtolower(trim((string) ($envelope['company_key_hash'] ?? ''))))
        || !is_array($range)
        || ($range['from_date'] ?? null) !== $fromDate
        || ($range['to_date'] ?? null) !== $toDate
        || !is_array($envelope['holiday_lists'] ?? null)
        || !is_array($envelope['holidays'] ?? null)
        || !is_array($availability)
        || ($availability['status'] ?? null) !== 'AVAILABLE'
        || ($availability['authoritative'] ?? null) !== true) {
        throw new RuntimeException('HR calendar owner response failed contract or company validation.');
    }
    if (count($envelope['holiday_lists']) > 2000 || count($envelope['holidays']) > 10000) {
        throw new RuntimeException('HR calendar owner response exceeds Operations projection limits.');
    }

    $holidayLists = [];
    $knownLists = [];
    foreach (array_values($envelope['holiday_lists']) as $record) {
        if (!is_array($record)) {
            throw new RuntimeException('HR calendar owner response contains an invalid Holiday List record.');
        }
        $holidayListKey = yovel_admin_operations_workforce_reference($record['holiday_list_key'] ?? '', 'holiday list key');
        if ($holidayListKey === '' || isset($knownLists[$holidayListKey])) {
            throw new RuntimeException('HR calendar owner response contains an invalid or duplicate Holiday List key.');
        }
        $listFrom = yovel_admin_operations_calendar_date($record['from_date'] ?? '', '');
        $listTo = yovel_admin_operations_calendar_date($record['to_date'] ?? '', '');
        $status = strtoupper(yovel_admin_operations_workforce_bounded_value($record['holiday_list_status'] ?? '', 'holiday list status', 20));
        if ($listFrom === '' || $listTo === '' || $listTo < $listFrom || $status !== 'ACTIVE') {
            throw new RuntimeException('HR calendar owner response contains an invalid Holiday List range or status.');
        }
        $knownLists[$holidayListKey] = true;
        $holidayLists[] = [
            'holiday_list_key' => $holidayListKey,
            'holiday_list_code' => yovel_admin_operations_workforce_bounded_value($record['holiday_list_code'] ?? '', 'holiday list code', 80),
            'holiday_list_name' => yovel_admin_operations_workforce_bounded_value($record['holiday_list_name'] ?? '', 'holiday list name', 180),
            'from_date' => $listFrom,
            'to_date' => $listTo,
            'timezone_name' => yovel_admin_operations_workforce_bounded_value($record['timezone_name'] ?? '', 'holiday list timezone', 80),
            'holiday_list_status' => $status,
        ];
    }

    $holidays = [];
    $knownHolidays = [];
    foreach (array_values($envelope['holidays']) as $record) {
        if (!is_array($record)) {
            throw new RuntimeException('HR calendar owner response contains an invalid Holiday record.');
        }
        $holidayKey = yovel_admin_operations_workforce_reference($record['holiday_key'] ?? '', 'holiday key');
        $holidayListKey = yovel_admin_operations_workforce_reference($record['holiday_list_key'] ?? '', 'holiday list key');
        $holidayDate = yovel_admin_operations_calendar_date($record['holiday_date'] ?? '', '');
        $status = strtoupper(yovel_admin_operations_workforce_bounded_value($record['holiday_status'] ?? '', 'holiday status', 20));
        if ($holidayKey === '' || isset($knownHolidays[$holidayKey]) || !isset($knownLists[$holidayListKey])
            || $holidayDate === '' || $holidayDate < $fromDate || $holidayDate > $toDate || $status !== 'ACTIVE') {
            throw new RuntimeException('HR calendar owner response contains an invalid Holiday reference, date, or status.');
        }
        $knownHolidays[$holidayKey] = true;
        $holidays[] = [
            'holiday_key' => $holidayKey,
            'holiday_list_key' => $holidayListKey,
            'holiday_date' => $holidayDate,
            'description' => yovel_admin_operations_workforce_bounded_value($record['description'] ?? '', 'holiday description', 240),
            'holiday_status' => $status,
        ];
    }

    return [
        'range' => ['from_date' => $fromDate, 'to_date' => $toDate],
        'holiday_lists' => $holidayLists,
        'holidays' => $holidays,
    ];
}

function yovel_admin_operations_workforce_calendar_projection(array $company, array $options = []): array
{
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    $status = yovel_admin_operations_workforce_status((string) ($options['status'] ?? 'ALL'));
    $fromDate = yovel_admin_operations_calendar_date($options['from_date'] ?? '', date('Y-01-01'));
    $toDate = yovel_admin_operations_calendar_date($options['to_date'] ?? '', date('Y-12-31'));
    if ($toDate < $fromDate) {
        $fromDate = date('Y-01-01');
        $toDate = date('Y-12-31');
    }
    if (!function_exists('yovel_admin_hr_workforce_read_contract')) {
        return yovel_admin_operations_workforce_projection_unavailable(
            $company,
            'HR workforce owner contract is unavailable. Open HR to verify the module contract.'
        );
    }

    try {
        $envelope = yovel_admin_hr_workforce_read_contract($company, ['status' => $status]);
        if (($envelope['contract'] ?? null) !== 'hr.workforce.v1'
            || !hash_equals($companyKeyHash, strtolower(trim((string) ($envelope['company_key_hash'] ?? ''))))
            || !is_array($envelope['employees'] ?? null)
            || !is_array($envelope['availability'] ?? null)) {
            throw new RuntimeException('HR workforce owner response failed contract or company validation.');
        }

        $employees = [];
        $departmentReferences = [];
        $positionReferences = [];
        foreach (array_values($envelope['employees']) as $employee) {
            if (!is_array($employee)) {
                throw new RuntimeException('HR workforce owner response contains an invalid employee record.');
            }
            $employeeKey = yovel_admin_operations_workforce_reference($employee['employee_key'] ?? '', 'employee key');
            if ($employeeKey === '') {
                throw new RuntimeException('HR workforce owner response contains an empty employee key.');
            }
            $departmentKey = yovel_admin_operations_workforce_reference($employee['department_key'] ?? '', 'department key');
            $positionKey = yovel_admin_operations_workforce_reference($employee['job_position_key'] ?? '', 'job position key');
            $employeeStatus = strtoupper(yovel_admin_operations_workforce_bounded_value($employee['employee_status'] ?? '', 'employee status', 40));
            if (!in_array($employeeStatus, ['ACTIVE', 'INACTIVE', 'ON_LEAVE', 'SEPARATED', 'DRAFT'], true)) {
                throw new RuntimeException('HR workforce owner response contains an invalid employee status.');
            }
            $employees[] = [
                'employee_key' => $employeeKey,
                'employee_code' => yovel_admin_operations_workforce_bounded_value($employee['employee_code'] ?? '', 'employee code', 80),
                'employee_name' => yovel_admin_operations_workforce_bounded_value($employee['employee_name'] ?? '', 'employee name', 180),
                'employee_status' => $employeeStatus,
                'department_key' => $departmentKey,
                'job_position_key' => $positionKey,
            ];
            if ($departmentKey !== '') {
                $departmentReferences[$departmentKey] = ['department_key' => $departmentKey];
            }
            if ($positionKey !== '') {
                $positionReferences[$positionKey] = ['job_position_key' => $positionKey];
            }
        }
        ksort($departmentReferences);
        ksort($positionReferences);

        $availability = $envelope['availability'];
        $educationReason = yovel_admin_operations_workforce_availability_reason($availability, 'education');
        $historyReason = yovel_admin_operations_workforce_availability_reason($availability, 'employment_history');
        $groupReason = yovel_admin_operations_workforce_availability_reason($availability, 'employee_group');
        $directories = yovel_admin_operations_workforce_unavailable_directories('HR workforce data is unavailable.');
        $directories['department'] = [
            'label' => 'Department',
            'status' => 'PARTIAL',
            'blocking' => false,
            'records' => array_values($departmentReferences),
            'unavailable_fields' => ['department_code', 'department_name', 'department_status', 'parent_department_key'],
            'reason' => 'HR exposes department keys on employee assignments, but not department directory labels or hierarchy.',
        ];
        $directories['designation'] = [
            'label' => 'Designation',
            'status' => 'PARTIAL',
            'blocking' => false,
            'records' => array_values($positionReferences),
            'unavailable_fields' => ['designation_code', 'designation_name', 'designation_status', 'parent_designation_key'],
            'reason' => 'HR exposes job-position keys on employee assignments, but not designation directory labels or hierarchy.',
        ];
        $directories['employee'] = [
            'label' => 'Employee',
            'status' => 'AVAILABLE',
            'blocking' => false,
            'records' => $employees,
            'unavailable_fields' => [],
            'reason' => '',
        ];
        foreach (['education'] as $directory) {
            $directories[$directory]['reason'] = $educationReason;
        }
        foreach (['external_work_history', 'internal_work_history'] as $directory) {
            $directories[$directory]['reason'] = $historyReason;
        }
        foreach (['employee_group', 'employee_group_table'] as $directory) {
            $directories[$directory]['reason'] = $groupReason;
        }
        $calendarRange = ['from_date' => $fromDate, 'to_date' => $toDate];
        try {
            $calendar = yovel_admin_operations_calendar_records($company, $fromDate, $toDate);
            $calendarRange = $calendar['range'];
            $directories['holiday'] = [
                'label' => 'Holiday',
                'status' => 'AVAILABLE',
                'blocking' => false,
                'records' => $calendar['holidays'],
                'unavailable_fields' => [],
                'reason' => '',
            ];
            $directories['holiday_list'] = [
                'label' => 'Holiday List',
                'status' => 'AVAILABLE',
                'blocking' => false,
                'records' => $calendar['holiday_lists'],
                'unavailable_fields' => [],
                'reason' => '',
            ];
        } catch (Throwable) {
            foreach (['holiday', 'holiday_list'] as $directory) {
                $directories[$directory]['reason'] = 'HR calendar owner response is unavailable or invalid for this company and date range.';
            }
        }

        return [
            'contract' => 'hr.workforce.v1',
            'contract_status' => 'AVAILABLE',
            'company_key_hash' => $companyKeyHash,
            'status_filter' => $status,
            'calendar_range' => $calendarRange,
            'blocking' => false,
            'message' => '',
            'directories' => $directories,
            'owner_actions' => yovel_admin_operations_workforce_owner_actions(),
        ];
    } catch (Throwable) {
        return yovel_admin_operations_workforce_projection_unavailable(
            $company,
            'HR workforce owner response is unavailable or invalid for this company.'
        );
    }
}
