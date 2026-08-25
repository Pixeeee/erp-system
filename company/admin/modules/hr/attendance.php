<?php
declare(strict_types=1);

function yovel_admin_hr_attendance_form_targets(): array
{
    return [
        'attendance' => ['label' => 'Attendance', 'protected_fields' => ['attendance_key', 'employee_key', 'attendance_date', 'attendance_status', 'source_mode', 'source_reference'], 'versioned' => true],
        'attendance-request' => ['label' => 'Attendance Request', 'protected_fields' => ['attendance_request_key', 'employee_key', 'from_date', 'to_date', 'request_status'], 'versioned' => true],
        'employee-checkin' => ['label' => 'Employee Check-in', 'protected_fields' => ['checkin_key', 'employee_key', 'checkin_at', 'log_type', 'source_reference'], 'versioned' => true],
        'shift-type' => ['label' => 'Shift Type', 'protected_fields' => ['shift_type_key', 'shift_code', 'timezone_name', 'start_time', 'end_time', 'shift_status'], 'versioned' => true],
        'shift-location' => ['label' => 'Shift Location', 'protected_fields' => ['shift_location_key', 'location_name', 'location_status'], 'versioned' => true],
        'shift-assignment' => ['label' => 'Shift Assignment', 'protected_fields' => ['shift_assignment_key', 'employee_key', 'shift_type_key', 'start_date', 'assignment_status', 'source_reference'], 'versioned' => true],
        'shift-schedule' => ['label' => 'Shift Schedule', 'protected_fields' => ['shift_schedule_key', 'schedule_code', 'shift_type_key', 'frequency_weeks', 'schedule_status'], 'versioned' => true],
        'shift-request' => ['label' => 'Shift Request', 'protected_fields' => ['shift_request_key', 'employee_key', 'shift_type_key', 'from_date', 'request_status'], 'versioned' => true],
        'overtime-type' => ['label' => 'Overtime Type', 'protected_fields' => ['overtime_type_key', 'overtime_code', 'calculation_method', 'overtime_status'], 'versioned' => true],
        'overtime-slip' => ['label' => 'Overtime Slip', 'protected_fields' => ['overtime_slip_key', 'employee_key', 'posting_date', 'slip_status', 'payroll_handoff_status'], 'versioned' => true],
    ];
}

function yovel_admin_hr_attendance_key(
    ADOConnection $db,
    string $companyKeyHash,
    string $table,
    string $column,
    mixed $value,
    string $label,
    bool $required = true
): string {
    return yovel_admin_hr_setup_key($db, $companyKeyHash, $table, $column, $value, $label, $required);
}

function yovel_admin_hr_decimal(mixed $value, string $label, float $minimum = 0.0, ?float $maximum = null, int $scale = 2, bool $required = false): ?string
{
    $text = trim((string) $value);
    if ($text === '') {
        if ($required) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        return null;
    }
    if (!is_numeric($text)) {
        throw new InvalidArgumentException($label . ' must be a number.');
    }
    $number = (float) $text;
    if (!is_finite($number) || $number < $minimum || ($maximum !== null && $number > $maximum)) {
        throw new InvalidArgumentException($label . ' is outside the supported range.');
    }
    return number_format($number, $scale, '.', '');
}

function yovel_admin_hr_time(string $value, string $label): string
{
    $value = trim($value);
    foreach (['!H:i:s', '!H:i'] as $format) {
        $time = DateTimeImmutable::createFromFormat($format, $value);
        if ($time instanceof DateTimeImmutable && $time->format($format === '!H:i' ? 'H:i' : 'H:i:s') === $value) {
            return $time->format('H:i:s');
        }
    }
    throw new InvalidArgumentException($label . ' must be a valid time.');
}

function yovel_admin_hr_timezone(string $value): DateTimeZone
{
    $value = trim($value);
    try {
        return new DateTimeZone($value !== '' ? $value : 'UTC');
    } catch (Throwable) {
        throw new InvalidArgumentException('Time zone is invalid.');
    }
}

function yovel_admin_hr_local_datetime(string $value, DateTimeZone $timezone, string $label): DateTimeImmutable
{
    $value = trim($value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
    if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
        throw new InvalidArgumentException($label . ' must use YYYY-MM-DD HH:MM:SS.');
    }
    return $date;
}

function yovel_admin_hr_iso_date(string $value, string $label): string
{
    $date = yovel_admin_optional_date($value, $label);
    if ($date === '') {
        throw new InvalidArgumentException($label . ' is required.');
    }
    return $date;
}

function yovel_admin_hr_attendance_checksum(array $payload): string
{
    return hash('sha256', yovel_admin_hr_json($payload));
}

function yovel_admin_hr_lock_employee(ADOConnection $db, string $companyKeyHash, string $employeeKey): array
{
    if (!yovel_admin_is_uuid($employeeKey)) {
        throw new InvalidArgumentException('Employee is invalid.');
    }
    $employee = $db->GetRow(
        "SELECT employee_key, employee_code, employee_name, department_key, job_position_key
         FROM project_company_hr_employee
         WHERE company_key_hash = ? AND employee_key = ? AND employee_status <> 'DELETED'
         FOR UPDATE",
        [$companyKeyHash, $employeeKey]
    );
    if (!is_array($employee) || $employee === []) {
        throw new InvalidArgumentException('The selected employee does not belong to this company.');
    }
    return $employee;
}

function yovel_admin_persist_hr_attendance_master(
    ADOConnection $db,
    array $company,
    array $admin,
    string $recordType,
    array $input,
    ?callable $failureInjector = null
): array {
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $recordType = strtoupper(trim($recordType));

    if ($recordType === 'SHIFT_LOCATION') {
        $key = trim((string) ($input['shift_location_key'] ?? ''));
        $name = trim((string) ($input['location_name'] ?? ''));
        $radius = filter_var($input['checkin_radius'] ?? null, FILTER_VALIDATE_INT);
        $latitude = yovel_admin_hr_decimal($input['latitude'] ?? '', 'Latitude', -90, 90, 7);
        $longitude = yovel_admin_hr_decimal($input['longitude'] ?? '', 'Longitude', -180, 180, 7);
        $status = yovel_admin_status((string) ($input['location_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'DELETED'], 'ACTIVE');
        if (($key !== '' && !yovel_admin_is_uuid($key)) || $name === '' || strlen($name) > 160 || ($radius !== false && ($radius < 0 || $radius > 100000))) {
            throw new InvalidArgumentException('Shift location name and check-in radius are invalid.');
        }
        $radius = $radius === false ? null : $radius;
        return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $key, $name, $radius, $latitude, $longitude, $status, $failureInjector): array {
            $existing = $key !== '' ? $db->GetRow('SELECT * FROM project_company_hr_shift_location WHERE company_key_hash = ? AND shift_location_key = ? FOR UPDATE', [$companyKeyHash, $key]) : null;
            if ($key !== '' && (!$existing || !is_array($existing))) {
                throw new InvalidArgumentException('Shift location was not found in this company.');
            }
            $savedKey = $existing ? $key : bx_uuid();
            yovel_admin_db_execute($db, "INSERT INTO project_company_hr_shift_location (
                    shift_location_key, company_key, company_key_hash, location_name, checkin_radius, latitude, longitude,
                    location_status, created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE location_name = VALUES(location_name), checkin_radius = VALUES(checkin_radius),
                    latitude = VALUES(latitude), longitude = VALUES(longitude), location_status = VALUES(location_status),
                    updated_by_admin_key = VALUES(updated_by_admin_key)",
                [$savedKey, $companyKey, $companyKeyHash, $name, $radius, $latitude, $longitude, $status, $adminKey, $adminKey], 'Shift Location save');
            $saved = $db->GetRow('SELECT * FROM project_company_hr_shift_location WHERE company_key_hash = ? AND shift_location_key = ?', [$companyKeyHash, $savedKey]);
            if (!is_array($saved) || (string) $saved['location_name'] !== $name || (string) $saved['location_status'] !== $status) {
                throw new RuntimeException('Shift Location exact read-back verification failed.');
            }
            bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_shift_location', $savedKey, ['company_key' => $companyKey, 'location_name' => $name, 'location_status' => $status], 'Company administrator saved a Shift Location.');
            if ($failureInjector) {
                $failureInjector($saved);
            }
            return $saved;
        });
    }

    if ($recordType === 'OVERTIME_TYPE') {
        $key = trim((string) ($input['overtime_type_key'] ?? ''));
        $code = yovel_admin_code((string) ($input['overtime_code'] ?? ''));
        $name = trim((string) ($input['overtime_name'] ?? ''));
        $method = yovel_admin_status((string) ($input['calculation_method'] ?? 'SALARY_COMPONENT_BASED'), ['SALARY_COMPONENT_BASED', 'FIXED_HOURLY_RATE'], 'SALARY_COMPONENT_BASED');
        $hourlyRate = yovel_admin_hr_decimal($input['hourly_rate'] ?? '', 'Hourly rate', 0, null, 6, $method === 'FIXED_HOURLY_RATE');
        $standardMultiplier = yovel_admin_hr_decimal($input['standard_multiplier'] ?? '1', 'Standard multiplier', 0.01, 100, 4, true);
        $weekendMultiplier = yovel_admin_hr_decimal($input['weekend_multiplier'] ?? '', 'Weekend multiplier', 0.01, 100, 4);
        $holidayMultiplier = yovel_admin_hr_decimal($input['public_holiday_multiplier'] ?? '', 'Public holiday multiplier', 0.01, 100, 4);
        $maximumHours = yovel_admin_hr_decimal($input['maximum_hours'] ?? '', 'Maximum overtime hours', 0.01, 24, 2);
        $salaryComponent = trim((string) ($input['salary_component_reference'] ?? ''));
        $components = is_array($input['applicable_salary_components'] ?? null) ? $input['applicable_salary_components'] : [];
        $components = array_values(array_unique(array_filter(array_map(static fn (mixed $value): string => trim((string) $value), $components))));
        sort($components);
        $componentsJson = json_encode($components, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $status = yovel_admin_status((string) ($input['overtime_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'DELETED'], 'ACTIVE');
        if (($key !== '' && !yovel_admin_is_uuid($key)) || $code === '' || strlen($code) > 80 || $name === '' || strlen($name) > 160 || strlen($salaryComponent) > 120) {
            throw new InvalidArgumentException('Overtime Type code, name, or salary component reference is invalid.');
        }
        return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $key, $code, $name, $method, $salaryComponent, $componentsJson, $hourlyRate, $standardMultiplier, $weekendMultiplier, $holidayMultiplier, $maximumHours, $status, $failureInjector): array {
            $existing = $key !== '' ? $db->GetRow('SELECT * FROM project_company_hr_overtime_type WHERE company_key_hash = ? AND overtime_type_key = ? FOR UPDATE', [$companyKeyHash, $key]) : null;
            if ($key !== '' && (!$existing || !is_array($existing))) {
                throw new InvalidArgumentException('Overtime Type was not found in this company.');
            }
            $savedKey = $existing ? $key : bx_uuid();
            yovel_admin_db_execute($db, "INSERT INTO project_company_hr_overtime_type (
                    overtime_type_key, company_key, company_key_hash, overtime_code, overtime_name, calculation_method,
                    salary_component_reference, applicable_salary_components_json, hourly_rate, standard_multiplier,
                    weekend_multiplier, public_holiday_multiplier, maximum_hours, overtime_status,
                    created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE overtime_name = VALUES(overtime_name), calculation_method = VALUES(calculation_method),
                    salary_component_reference = VALUES(salary_component_reference), applicable_salary_components_json = VALUES(applicable_salary_components_json),
                    hourly_rate = VALUES(hourly_rate), standard_multiplier = VALUES(standard_multiplier), weekend_multiplier = VALUES(weekend_multiplier),
                    public_holiday_multiplier = VALUES(public_holiday_multiplier), maximum_hours = VALUES(maximum_hours),
                    overtime_status = VALUES(overtime_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
                [$savedKey, $companyKey, $companyKeyHash, $code, $name, $method, $salaryComponent !== '' ? $salaryComponent : null, $componentsJson, $hourlyRate, $standardMultiplier, $weekendMultiplier, $holidayMultiplier, $maximumHours, $status, $adminKey, $adminKey], 'Overtime Type save');
            yovel_admin_db_execute($db, 'DELETE FROM project_company_hr_overtime_salary_component WHERE company_key_hash = ? AND overtime_type_key = ?', [$companyKeyHash, $savedKey], 'Overtime Salary Component reset');
            $components = json_decode($componentsJson, true, 512, JSON_THROW_ON_ERROR);
            foreach (is_array($components) ? $components : [] as $componentReference) {
                yovel_admin_db_execute($db, "INSERT INTO project_company_hr_overtime_salary_component (
                        overtime_salary_component_key, overtime_type_key, company_key, company_key_hash,
                        salary_component_reference, created_by_admin_key
                    ) VALUES (?, ?, ?, ?, ?, ?)",
                    [bx_uuid(), $savedKey, $companyKey, $companyKeyHash, (string) $componentReference, $adminKey], 'Overtime Salary Component save');
            }
            $saved = $db->GetRow('SELECT * FROM project_company_hr_overtime_type WHERE company_key_hash = ? AND overtime_type_key = ?', [$companyKeyHash, $savedKey]);
            $savedComponents = $db->GetCol('SELECT salary_component_reference FROM project_company_hr_overtime_salary_component WHERE company_key_hash = ? AND overtime_type_key = ? ORDER BY salary_component_reference', [$companyKeyHash, $savedKey]);
            if (!is_array($saved) || (string) $saved['overtime_code'] !== $code || (string) $saved['calculation_method'] !== $method || (string) $saved['applicable_salary_components_json'] !== $componentsJson || $savedComponents !== $components) {
                throw new RuntimeException('Overtime Type exact read-back verification failed.');
            }
            bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_overtime_type', $savedKey, ['company_key' => $companyKey, 'overtime_code' => $code, 'calculation_method' => $method, 'overtime_status' => $status], 'Company administrator saved an Overtime Type.');
            if ($failureInjector) {
                $failureInjector($saved);
            }
            return $saved;
        });
    }

    if ($recordType === 'SHIFT_TYPE') {
        $key = trim((string) ($input['shift_type_key'] ?? ''));
        $code = yovel_admin_code((string) ($input['shift_code'] ?? ''));
        $name = trim((string) ($input['shift_name'] ?? ''));
        $timezone = yovel_admin_hr_timezone((string) ($input['timezone_name'] ?? 'UTC'));
        $startTime = yovel_admin_hr_time((string) ($input['start_time'] ?? ''), 'Shift start time');
        $endTime = yovel_admin_hr_time((string) ($input['end_time'] ?? ''), 'Shift end time');
        $crossesMidnight = strcmp($endTime, $startTime) <= 0 ? 1 : 0;
        $pairing = yovel_admin_status((string) ($input['checkin_pairing_mode'] ?? 'LOG_TYPE'), ['ALTERNATING', 'LOG_TYPE'], 'LOG_TYPE');
        $hoursMode = yovel_admin_status((string) ($input['working_hours_mode'] ?? 'FIRST_LAST'), ['FIRST_LAST', 'EVERY_PAIR'], 'FIRST_LAST');
        $before = filter_var($input['begin_checkin_before_minutes'] ?? 60, FILTER_VALIDATE_INT);
        $after = filter_var($input['allow_checkout_after_minutes'] ?? 60, FILTER_VALIDATE_INT);
        $late = filter_var($input['late_entry_grace_minutes'] ?? 0, FILTER_VALIDATE_INT);
        $early = filter_var($input['early_exit_grace_minutes'] ?? 0, FILTER_VALIDATE_INT);
        foreach (['before' => $before, 'after' => $after, 'late' => $late, 'early' => $early] as $label => $minutes) {
            if ($minutes === false || $minutes < 0 || $minutes > 1440) {
                throw new InvalidArgumentException('Shift ' . $label . ' minutes are invalid.');
            }
        }
        $halfDay = yovel_admin_hr_decimal($input['half_day_threshold_hours'] ?? '', 'Half-day threshold', 0, 24, 2);
        $absent = yovel_admin_hr_decimal($input['absent_threshold_hours'] ?? '', 'Absent threshold', 0, 24, 2);
        if ($halfDay !== null && $absent !== null && (float) $absent > (float) $halfDay) {
            throw new InvalidArgumentException('Absent threshold cannot exceed the half-day threshold.');
        }
        $auto = in_array($input['enable_auto_attendance'] ?? null, [1, '1', true, 'on', 'yes'], true) ? 1 : 0;
        $overtime = in_array($input['allow_overtime'] ?? null, [1, '1', true, 'on', 'yes'], true) ? 1 : 0;
        $overtimeTypeKey = yovel_admin_hr_attendance_key($db, $companyKeyHash, 'project_company_hr_overtime_type', 'overtime_type_key', $input['overtime_type_key'] ?? '', 'Overtime Type', $overtime === 1);
        $processAfter = yovel_admin_optional_date((string) ($input['process_attendance_after'] ?? ''), 'Process attendance after');
        $status = yovel_admin_status((string) ($input['shift_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'DELETED'], 'ACTIVE');
        if (($key !== '' && !yovel_admin_is_uuid($key)) || $code === '' || strlen($code) > 80 || $name === '' || strlen($name) > 160) {
            throw new InvalidArgumentException('Shift Type code and name are required.');
        }
        return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $key, $code, $name, $timezone, $startTime, $endTime, $crossesMidnight, $pairing, $hoursMode, $before, $after, $late, $early, $halfDay, $absent, $auto, $processAfter, $overtime, $overtimeTypeKey, $status, $failureInjector): array {
            $existing = $key !== '' ? $db->GetRow('SELECT * FROM project_company_hr_shift_type WHERE company_key_hash = ? AND shift_type_key = ? FOR UPDATE', [$companyKeyHash, $key]) : null;
            if ($key !== '' && (!$existing || !is_array($existing))) {
                throw new InvalidArgumentException('Shift Type was not found in this company.');
            }
            $savedKey = $existing ? $key : bx_uuid();
            yovel_admin_db_execute($db, "INSERT INTO project_company_hr_shift_type (
                    shift_type_key, company_key, company_key_hash, shift_code, shift_name, timezone_name, start_time, end_time,
                    crosses_midnight, checkin_pairing_mode, working_hours_mode, begin_checkin_before_minutes,
                    allow_checkout_after_minutes, late_entry_grace_minutes, early_exit_grace_minutes,
                    half_day_threshold_hours, absent_threshold_hours, enable_auto_attendance, process_attendance_after,
                    allow_overtime, overtime_type_key, shift_status, created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE shift_name = VALUES(shift_name), timezone_name = VALUES(timezone_name),
                    start_time = VALUES(start_time), end_time = VALUES(end_time), crosses_midnight = VALUES(crosses_midnight),
                    checkin_pairing_mode = VALUES(checkin_pairing_mode), working_hours_mode = VALUES(working_hours_mode),
                    begin_checkin_before_minutes = VALUES(begin_checkin_before_minutes), allow_checkout_after_minutes = VALUES(allow_checkout_after_minutes),
                    late_entry_grace_minutes = VALUES(late_entry_grace_minutes), early_exit_grace_minutes = VALUES(early_exit_grace_minutes),
                    half_day_threshold_hours = VALUES(half_day_threshold_hours), absent_threshold_hours = VALUES(absent_threshold_hours),
                    enable_auto_attendance = VALUES(enable_auto_attendance), process_attendance_after = VALUES(process_attendance_after),
                    allow_overtime = VALUES(allow_overtime), overtime_type_key = VALUES(overtime_type_key),
                    shift_status = VALUES(shift_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
                [$savedKey, $companyKey, $companyKeyHash, $code, $name, $timezone->getName(), $startTime, $endTime, $crossesMidnight, $pairing, $hoursMode, $before, $after, $late, $early, $halfDay, $absent, $auto, $processAfter !== '' ? $processAfter : null, $overtime, $overtimeTypeKey !== '' ? $overtimeTypeKey : null, $status, $adminKey, $adminKey], 'Shift Type save');
            $saved = $db->GetRow('SELECT * FROM project_company_hr_shift_type WHERE company_key_hash = ? AND shift_type_key = ?', [$companyKeyHash, $savedKey]);
            if (!is_array($saved) || (string) $saved['shift_code'] !== $code || (string) $saved['timezone_name'] !== $timezone->getName() || (int) $saved['crosses_midnight'] !== $crossesMidnight) {
                throw new RuntimeException('Shift Type exact read-back verification failed.');
            }
            bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_shift_type', $savedKey, ['company_key' => $companyKey, 'shift_code' => $code, 'timezone_name' => $timezone->getName(), 'shift_status' => $status], 'Company administrator saved a Shift Type.');
            if ($failureInjector) {
                $failureInjector($saved);
            }
            return $saved;
        });
    }

    if ($recordType === 'SHIFT_SCHEDULE') {
        $key = trim((string) ($input['shift_schedule_key'] ?? ''));
        $code = yovel_admin_code((string) ($input['schedule_code'] ?? ''));
        $name = trim((string) ($input['schedule_name'] ?? ''));
        $shiftTypeKey = yovel_admin_hr_attendance_key($db, $companyKeyHash, 'project_company_hr_shift_type', 'shift_type_key', $input['shift_type_key'] ?? '', 'Shift Type');
        $frequency = filter_var($input['frequency_weeks'] ?? 1, FILTER_VALIDATE_INT);
        $days = is_array($input['repeat_days'] ?? null) ? $input['repeat_days'] : [];
        $allowedDays = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
        $days = array_values(array_unique(array_map(static fn (mixed $value): string => strtoupper(trim((string) $value)), $days)));
        sort($days);
        if (($key !== '' && !yovel_admin_is_uuid($key)) || $code === '' || strlen($code) > 80 || $name === '' || strlen($name) > 160 || $frequency === false || $frequency < 1 || $frequency > 4 || $days === [] || array_diff($days, $allowedDays) !== []) {
            throw new InvalidArgumentException('Shift Schedule details are invalid.');
        }
        $daysJson = json_encode($days, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $status = yovel_admin_status((string) ($input['schedule_status'] ?? 'ACTIVE'), ['DRAFT', 'ACTIVE', 'INACTIVE', 'DELETED'], 'ACTIVE');
        return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $key, $code, $name, $shiftTypeKey, $frequency, $daysJson, $status, $failureInjector): array {
            $existing = $key !== '' ? $db->GetRow('SELECT * FROM project_company_hr_shift_schedule WHERE company_key_hash = ? AND shift_schedule_key = ? FOR UPDATE', [$companyKeyHash, $key]) : null;
            if ($key !== '' && (!$existing || !is_array($existing))) {
                throw new InvalidArgumentException('Shift Schedule was not found in this company.');
            }
            $savedKey = $existing ? $key : bx_uuid();
            yovel_admin_db_execute($db, "INSERT INTO project_company_hr_shift_schedule (
                    shift_schedule_key, company_key, company_key_hash, schedule_code, schedule_name,
                    shift_type_key, frequency_weeks, repeat_days_json, schedule_status,
                    created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE schedule_name = VALUES(schedule_name), shift_type_key = VALUES(shift_type_key),
                    frequency_weeks = VALUES(frequency_weeks), repeat_days_json = VALUES(repeat_days_json),
                    schedule_status = VALUES(schedule_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
                [$savedKey, $companyKey, $companyKeyHash, $code, $name, $shiftTypeKey, $frequency, $daysJson, $status, $adminKey, $adminKey], 'Shift Schedule save');
            $saved = $db->GetRow('SELECT * FROM project_company_hr_shift_schedule WHERE company_key_hash = ? AND shift_schedule_key = ?', [$companyKeyHash, $savedKey]);
            if (!is_array($saved) || (string) $saved['schedule_code'] !== $code || (string) $saved['repeat_days_json'] !== $daysJson || (int) $saved['frequency_weeks'] !== $frequency) {
                throw new RuntimeException('Shift Schedule exact read-back verification failed.');
            }
            bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_shift_schedule', $savedKey, ['company_key' => $companyKey, 'schedule_code' => $code, 'schedule_status' => $status], 'Company administrator saved a Shift Schedule.');
            if ($failureInjector) {
                $failureInjector($saved);
            }
            return $saved;
        });
    }

    throw new InvalidArgumentException('Unknown Shift & Attendance master type.');
}

function yovel_admin_persist_shift_assignment(
    ADOConnection $db,
    array $company,
    array $admin,
    array $input,
    ?callable $failureInjector = null
): array {
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $key = trim((string) ($input['shift_assignment_key'] ?? ''));
    $employeeKey = trim((string) ($input['employee_key'] ?? ''));
    $shiftTypeKey = yovel_admin_hr_attendance_key($db, $companyKeyHash, 'project_company_hr_shift_type', 'shift_type_key', $input['shift_type_key'] ?? '', 'Shift Type');
    $scheduleKey = yovel_admin_hr_attendance_key($db, $companyKeyHash, 'project_company_hr_shift_schedule', 'shift_schedule_key', $input['shift_schedule_key'] ?? '', 'Shift Schedule', false);
    $locationKey = yovel_admin_hr_attendance_key($db, $companyKeyHash, 'project_company_hr_shift_location', 'shift_location_key', $input['shift_location_key'] ?? '', 'Shift Location', false);
    $requestKey = yovel_admin_hr_attendance_key($db, $companyKeyHash, 'project_company_hr_shift_request', 'shift_request_key', $input['shift_request_key'] ?? '', 'Shift Request', false);
    $startDate = yovel_admin_hr_iso_date((string) ($input['start_date'] ?? ''), 'Shift start date');
    $endDate = yovel_admin_optional_date((string) ($input['end_date'] ?? ''), 'Shift end date');
    $status = yovel_admin_status((string) ($input['assignment_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'CANCELLED', 'DELETED'], 'ACTIVE');
    $sourceSystem = strtoupper(trim((string) ($input['source_system'] ?? 'MANUAL')));
    $sourceReference = trim((string) ($input['source_reference'] ?? ''));
    if (($key !== '' && !yovel_admin_is_uuid($key)) || ($endDate !== '' && $endDate < $startDate) || preg_match('/^[A-Z0-9_.-]{2,40}$/', $sourceSystem) !== 1 || $sourceReference === '' || strlen($sourceReference) > 160) {
        throw new InvalidArgumentException('Shift assignment dates or source reference are invalid.');
    }

    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $key, $employeeKey, $shiftTypeKey, $scheduleKey, $locationKey, $requestKey, $startDate, $endDate, $status, $sourceSystem, $sourceReference, $failureInjector): array {
        yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
        $existing = $key !== ''
            ? $db->GetRow('SELECT * FROM project_company_hr_shift_assignment WHERE company_key_hash = ? AND shift_assignment_key = ? FOR UPDATE', [$companyKeyHash, $key])
            : $db->GetRow('SELECT * FROM project_company_hr_shift_assignment WHERE company_key_hash = ? AND source_system = ? AND source_reference = ? FOR UPDATE', [$companyKeyHash, $sourceSystem, $sourceReference]);
        if ($key !== '' && (!$existing || !is_array($existing))) {
            throw new InvalidArgumentException('Shift Assignment was not found in this company.');
        }
        $overlap = $db->GetRow(
            "SELECT shift_assignment_key FROM project_company_hr_shift_assignment
             WHERE company_key_hash = ? AND employee_key = ? AND assignment_status = 'ACTIVE'
               AND shift_assignment_key <> ?
               AND start_date <= COALESCE(?, '9999-12-31')
               AND COALESCE(end_date, '9999-12-31') >= ?
             LIMIT 1 FOR UPDATE",
            [$companyKeyHash, $employeeKey, (string) ($existing['shift_assignment_key'] ?? ''), $endDate !== '' ? $endDate : null, $startDate]
        );
        if ($status === 'ACTIVE' && is_array($overlap) && $overlap !== []) {
            throw new InvalidArgumentException('The employee already has an active shift assignment in this date range.');
        }
        $savedKey = $existing ? (string) $existing['shift_assignment_key'] : bx_uuid();
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_shift_assignment (
                shift_assignment_key, company_key, company_key_hash, employee_key, shift_type_key,
                shift_schedule_key, shift_location_key, shift_request_key, start_date, end_date,
                assignment_status, source_system, source_reference, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE shift_type_key = VALUES(shift_type_key), shift_schedule_key = VALUES(shift_schedule_key),
                shift_location_key = VALUES(shift_location_key), shift_request_key = VALUES(shift_request_key),
                start_date = VALUES(start_date), end_date = VALUES(end_date), assignment_status = VALUES(assignment_status),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$savedKey, $companyKey, $companyKeyHash, $employeeKey, $shiftTypeKey, $scheduleKey !== '' ? $scheduleKey : null, $locationKey !== '' ? $locationKey : null, $requestKey !== '' ? $requestKey : null, $startDate, $endDate !== '' ? $endDate : null, $status, $sourceSystem, $sourceReference, $adminKey, $adminKey], 'Shift Assignment save');
        $saved = $db->GetRow('SELECT * FROM project_company_hr_shift_assignment WHERE company_key_hash = ? AND shift_assignment_key = ?', [$companyKeyHash, $savedKey]);
        if (!is_array($saved) || (string) $saved['employee_key'] !== $employeeKey || (string) $saved['shift_type_key'] !== $shiftTypeKey || (string) $saved['source_reference'] !== $sourceReference) {
            throw new RuntimeException('Shift Assignment exact read-back verification failed.');
        }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_shift_assignment', $savedKey, ['company_key' => $companyKey, 'employee_key' => $employeeKey, 'shift_type_key' => $shiftTypeKey, 'assignment_status' => $status, 'source_reference' => $sourceReference], 'Company administrator saved a Shift Assignment.');
        if ($failureInjector) {
            $failureInjector($saved);
        }
        return $saved;
    });
}

function yovel_admin_bulk_shift_assignment(ADOConnection $db, array $company, array $admin, array $employeeKeys, array $input): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $employeeKeys = array_values(array_unique(array_filter(array_map('strval', $employeeKeys), 'yovel_admin_is_uuid')));
    if ($employeeKeys === []) {
        throw new InvalidArgumentException('Select at least one employee for the Shift Assignment Tool.');
    }
    $shiftTypeKey = yovel_admin_hr_attendance_key($db, $companyKeyHash, 'project_company_hr_shift_type', 'shift_type_key', $input['shift_type_key'] ?? '', 'Shift Type');
    $scheduleKey = yovel_admin_hr_attendance_key($db, $companyKeyHash, 'project_company_hr_shift_schedule', 'shift_schedule_key', $input['shift_schedule_key'] ?? '', 'Shift Schedule', false);
    $locationKey = yovel_admin_hr_attendance_key($db, $companyKeyHash, 'project_company_hr_shift_location', 'shift_location_key', $input['shift_location_key'] ?? '', 'Shift Location', false);
    $startDate = yovel_admin_hr_iso_date((string) ($input['start_date'] ?? ''), 'Shift start date');
    $endDate = yovel_admin_optional_date((string) ($input['end_date'] ?? ''), 'Shift end date');
    $status = yovel_admin_status((string) ($input['assignment_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE'], 'ACTIVE');
    $sourceBase = trim((string) ($input['source_reference'] ?? 'SHIFT-TOOL'));
    if (($endDate !== '' && $endDate < $startDate) || $sourceBase === '' || strlen($sourceBase) > 100) {
        throw new InvalidArgumentException('Shift Assignment Tool dates or source reference are invalid.');
    }

    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $employeeKeys, $shiftTypeKey, $scheduleKey, $locationKey, $startDate, $endDate, $status, $sourceBase): array {
        $results = [];
        foreach ($employeeKeys as $employeeKey) {
            yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
            $sourceReference = $sourceBase . ':' . $employeeKey;
            $existing = $db->GetRow('SELECT * FROM project_company_hr_shift_assignment WHERE company_key_hash = ? AND source_system = ? AND source_reference = ? FOR UPDATE', [$companyKeyHash, 'ASSIGNMENT_TOOL', $sourceReference]);
            $overlap = $db->GetRow(
                "SELECT shift_assignment_key FROM project_company_hr_shift_assignment
                 WHERE company_key_hash = ? AND employee_key = ? AND assignment_status = 'ACTIVE'
                   AND shift_assignment_key <> ?
                   AND start_date <= COALESCE(?, '9999-12-31')
                   AND COALESCE(end_date, '9999-12-31') >= ?
                 LIMIT 1 FOR UPDATE",
                [$companyKeyHash, $employeeKey, (string) ($existing['shift_assignment_key'] ?? ''), $endDate !== '' ? $endDate : null, $startDate]
            );
            if ($status === 'ACTIVE' && is_array($overlap) && $overlap !== []) {
                throw new InvalidArgumentException('One or more selected employees already have an active shift assignment in this range.');
            }
            $key = is_array($existing) && $existing !== [] ? (string) $existing['shift_assignment_key'] : bx_uuid();
            yovel_admin_db_execute($db, "INSERT INTO project_company_hr_shift_assignment (
                    shift_assignment_key, company_key, company_key_hash, employee_key, shift_type_key,
                    shift_schedule_key, shift_location_key, start_date, end_date, assignment_status,
                    source_system, source_reference, created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ASSIGNMENT_TOOL', ?, ?, ?)
                ON DUPLICATE KEY UPDATE shift_type_key = VALUES(shift_type_key), shift_schedule_key = VALUES(shift_schedule_key),
                    shift_location_key = VALUES(shift_location_key), start_date = VALUES(start_date), end_date = VALUES(end_date),
                    assignment_status = VALUES(assignment_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
                [$key, $companyKey, $companyKeyHash, $employeeKey, $shiftTypeKey, $scheduleKey !== '' ? $scheduleKey : null, $locationKey !== '' ? $locationKey : null, $startDate, $endDate !== '' ? $endDate : null, $status, $sourceReference, $adminKey, $adminKey], 'Shift Assignment Tool save');
            $saved = $db->GetRow('SELECT * FROM project_company_hr_shift_assignment WHERE company_key_hash = ? AND shift_assignment_key = ?', [$companyKeyHash, $key]);
            if (!is_array($saved) || (string) $saved['employee_key'] !== $employeeKey || (string) $saved['source_system'] !== 'ASSIGNMENT_TOOL' || (string) $saved['source_reference'] !== $sourceReference) {
                throw new RuntimeException('Shift Assignment Tool exact read-back verification failed.');
            }
            bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_shift_assignment', $key, ['company_key' => $companyKey, 'employee_key' => $employeeKey, 'shift_type_key' => $shiftTypeKey, 'assignment_status' => $status, 'source_system' => 'ASSIGNMENT_TOOL'], 'Company administrator used the Shift Assignment Tool.');
            $results[] = $saved;
        }
        return $results;
    });
}

function yovel_admin_persist_shift_request(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $key = trim((string) ($input['shift_request_key'] ?? ''));
    $employeeKey = trim((string) ($input['employee_key'] ?? ''));
    $shiftTypeKey = yovel_admin_hr_attendance_key($db, $companyKeyHash, 'project_company_hr_shift_type', 'shift_type_key', $input['shift_type_key'] ?? '', 'Shift Type');
    $fromDate = yovel_admin_hr_iso_date((string) ($input['from_date'] ?? ''), 'From date');
    $toDate = yovel_admin_optional_date((string) ($input['to_date'] ?? ''), 'To date');
    $status = yovel_admin_status((string) ($input['request_status'] ?? 'DRAFT'), ['DRAFT', 'APPROVED', 'REJECTED', 'CANCELLED'], 'DRAFT');
    $approverKey = trim((string) ($input['approver_admin_key'] ?? ''));
    $reason = trim((string) ($input['reason'] ?? ''));
    if (($key !== '' && !yovel_admin_is_uuid($key)) || !yovel_admin_is_uuid($approverKey) || ($toDate !== '' && $toDate < $fromDate) || strlen($reason) > 4000) {
        throw new InvalidArgumentException('Shift Request details are invalid.');
    }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $key, $employeeKey, $shiftTypeKey, $fromDate, $toDate, $status, $approverKey, $reason, $failureInjector): array {
        yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
        $approver = $db->GetRow('SELECT admin_key FROM project_company_admin WHERE company_key_hash = ? AND admin_key = ? AND admin_status = ? FOR UPDATE', [$companyKeyHash, $approverKey, 'ACTIVE']);
        if (!is_array($approver) || $approver === []) {
            throw new InvalidArgumentException('Shift Request approver does not belong to this company.');
        }
        $existing = $key !== '' ? $db->GetRow('SELECT * FROM project_company_hr_shift_request WHERE company_key_hash = ? AND shift_request_key = ? FOR UPDATE', [$companyKeyHash, $key]) : null;
        if ($existing && in_array((string) $existing['request_status'], ['APPROVED', 'REJECTED', 'CANCELLED'], true)) {
            throw new InvalidArgumentException('A final Shift Request cannot be changed.');
        }
        if ($key !== '' && (!$existing || !is_array($existing))) {
            throw new InvalidArgumentException('Shift Request was not found in this company.');
        }
        $savedKey = $existing ? $key : bx_uuid();
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_shift_request (
                shift_request_key, company_key, company_key_hash, employee_key, shift_type_key,
                from_date, to_date, request_status, approver_admin_key, reason,
                created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE shift_type_key = VALUES(shift_type_key), from_date = VALUES(from_date),
                to_date = VALUES(to_date), request_status = VALUES(request_status), approver_admin_key = VALUES(approver_admin_key),
                reason = VALUES(reason), updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$savedKey, $companyKey, $companyKeyHash, $employeeKey, $shiftTypeKey, $fromDate, $toDate !== '' ? $toDate : null, $status, $approverKey, $reason, $adminKey, $adminKey], 'Shift Request save');
        $saved = $db->GetRow('SELECT * FROM project_company_hr_shift_request WHERE company_key_hash = ? AND shift_request_key = ?', [$companyKeyHash, $savedKey]);
        if (!is_array($saved) || (string) $saved['employee_key'] !== $employeeKey || (string) $saved['request_status'] !== $status) {
            throw new RuntimeException('Shift Request exact read-back verification failed.');
        }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_shift_request', $savedKey, ['company_key' => $companyKey, 'employee_key' => $employeeKey, 'request_status' => $status], 'Company administrator saved a Shift Request.');
        if ($failureInjector) {
            $failureInjector($saved);
        }
        return $saved;
    });
}

function yovel_admin_transition_shift_request(ADOConnection $db, array $company, array $admin, string $requestKey, string $status, ?callable $failureInjector = null): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $status = yovel_admin_status($status, ['APPROVED', 'REJECTED'], 'REJECTED');
    if (!yovel_admin_is_uuid($requestKey)) {
        throw new InvalidArgumentException('Shift Request key is invalid.');
    }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $requestKey, $status, $failureInjector): array {
        $request = $db->GetRow('SELECT * FROM project_company_hr_shift_request WHERE company_key_hash = ? AND shift_request_key = ? FOR UPDATE', [$companyKeyHash, $requestKey]);
        if (!is_array($request) || $request === []) {
            throw new InvalidArgumentException('Shift Request was not found in this company.');
        }
        if ((string) $request['request_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Only a draft Shift Request can be approved or rejected.');
        }
        yovel_admin_db_execute($db, 'UPDATE project_company_hr_shift_request SET request_status = ?, approved_by_admin_key = ?, approved_at = NOW(), updated_by_admin_key = ? WHERE company_key_hash = ? AND shift_request_key = ?', [$status, $adminKey, $adminKey, $companyKeyHash, $requestKey], 'Shift Request transition');
        $saved = $db->GetRow('SELECT * FROM project_company_hr_shift_request WHERE company_key_hash = ? AND shift_request_key = ?', [$companyKeyHash, $requestKey]);
        if (!is_array($saved) || (string) $saved['request_status'] !== $status || (string) $saved['approved_by_admin_key'] !== $adminKey) {
            throw new RuntimeException('Shift Request transition read-back verification failed.');
        }
        bx_audit($status, 'project_company_hr_shift_request', $requestKey, ['company_key' => $companyKey, 'request_status' => $status, 'approved_by_admin_key' => $adminKey], 'Company administrator reviewed a Shift Request.');
        if ($failureInjector) {
            $failureInjector($saved);
        }
        return $saved;
    });
}

function yovel_admin_hr_shift_window(array $shift, string $attendanceDate): array
{
    $timezone = yovel_admin_hr_timezone((string) $shift['timezone_name']);
    $start = new DateTimeImmutable($attendanceDate . ' ' . (string) $shift['start_time'], $timezone);
    $end = new DateTimeImmutable($attendanceDate . ' ' . (string) $shift['end_time'], $timezone);
    if ((int) $shift['crosses_midnight'] === 1 || $end <= $start) {
        $end = $end->modify('+1 day');
    }
    $actualStart = $start->modify('-' . (int) $shift['begin_checkin_before_minutes'] . ' minutes');
    $actualEnd = $end->modify('+' . (int) $shift['allow_checkout_after_minutes'] . ' minutes');
    return [
        'timezone' => $timezone,
        'start_local' => $start,
        'end_local' => $end,
        'actual_start_utc' => $actualStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        'actual_end_utc' => $actualEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
    ];
}

function yovel_admin_hr_resolve_shift(ADOConnection $db, string $companyKeyHash, string $employeeKey, DateTimeImmutable $localDateTime): array
{
    $dates = [$localDateTime->format('Y-m-d'), $localDateTime->modify('-1 day')->format('Y-m-d')];
    $assignments = $db->GetAll(
        "SELECT assignment.*, shift_record.*
         FROM project_company_hr_shift_assignment assignment
         INNER JOIN project_company_hr_shift_type shift_record
            ON shift_record.company_key_hash = assignment.company_key_hash
           AND shift_record.shift_type_key = assignment.shift_type_key
           AND shift_record.shift_status = 'ACTIVE'
         WHERE assignment.company_key_hash = ? AND assignment.employee_key = ?
           AND assignment.assignment_status = 'ACTIVE'
           AND assignment.start_date <= ?
           AND COALESCE(assignment.end_date, '9999-12-31') >= ?
         ORDER BY assignment.start_date DESC, assignment.x_id DESC
         FOR UPDATE",
        [$companyKeyHash, $employeeKey, max($dates), min($dates)]
    );
    $utc = $localDateTime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    foreach (is_array($assignments) ? $assignments : [] as $assignment) {
        foreach ($dates as $date) {
            if ($date < (string) $assignment['start_date'] || ((string) ($assignment['end_date'] ?? '') !== '' && $date > (string) $assignment['end_date'])) {
                continue;
            }
            $window = yovel_admin_hr_shift_window($assignment, $date);
            if ($utc >= $window['actual_start_utc'] && $utc <= $window['actual_end_utc']) {
                return ['assignment' => $assignment, 'attendance_date' => $date, 'window' => $window];
            }
        }
    }
    return [];
}

function yovel_admin_persist_employee_checkin(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $employeeKey = trim((string) ($input['employee_key'] ?? ''));
    $logType = yovel_admin_status((string) ($input['log_type'] ?? ''), ['IN', 'OUT'], 'IN');
    $timezone = yovel_admin_hr_timezone((string) ($input['timezone_name'] ?? 'UTC'));
    $local = yovel_admin_hr_local_datetime((string) ($input['checkin_at'] ?? ''), $timezone, 'Check-in time');
    $utc = $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $deviceId = trim((string) ($input['device_id'] ?? ''));
    $latitude = yovel_admin_hr_decimal($input['latitude'] ?? '', 'Latitude', -90, 90, 7);
    $longitude = yovel_admin_hr_decimal($input['longitude'] ?? '', 'Longitude', -180, 180, 7);
    $sourceSystem = strtoupper(trim((string) ($input['source_system'] ?? 'MANUAL')));
    $sourceReference = trim((string) ($input['source_reference'] ?? ''));
    $skip = in_array($input['skip_auto_attendance'] ?? null, [1, '1', true, 'on', 'yes'], true) ? 1 : 0;
    if (preg_match('/^[A-Z0-9_.-]{2,40}$/', $sourceSystem) !== 1 || $sourceReference === '' || strlen($sourceReference) > 160 || strlen($deviceId) > 120) {
        throw new InvalidArgumentException('Check-in source or device is invalid.');
    }
    $checksum = yovel_admin_hr_attendance_checksum([
        'employee_key' => $employeeKey, 'log_type' => $logType, 'checkin_at_utc' => $utc,
        'timezone_name' => $timezone->getName(), 'device_id' => $deviceId,
        'latitude' => $latitude, 'longitude' => $longitude, 'source_system' => $sourceSystem,
        'source_reference' => $sourceReference, 'skip_auto_attendance' => $skip,
    ]);

    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $employeeKey, $logType, $timezone, $local, $utc, $deviceId, $latitude, $longitude, $sourceSystem, $sourceReference, $skip, $checksum, $failureInjector): array {
        yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
        $existing = $db->GetRow('SELECT * FROM project_company_hr_employee_checkin WHERE company_key_hash = ? AND source_system = ? AND source_reference = ? FOR UPDATE', [$companyKeyHash, $sourceSystem, $sourceReference]);
        if (is_array($existing) && $existing !== []) {
            if (!hash_equals((string) $existing['immutable_checksum'], $checksum)) {
                throw new InvalidArgumentException('The check-in source reference already belongs to different event data.');
            }
            return $existing;
        }
        $resolved = yovel_admin_hr_resolve_shift($db, $companyKeyHash, $employeeKey, $local);
        $assignment = is_array($resolved['assignment'] ?? null) ? $resolved['assignment'] : [];
        $key = bx_uuid();
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_employee_checkin (
                checkin_key, company_key, company_key_hash, employee_key, log_type, checkin_at_utc,
                checkin_at_local, timezone_name, device_id, latitude, longitude, shift_type_key,
                shift_assignment_key, skip_auto_attendance, offshift, source_system, source_reference,
                immutable_checksum, created_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$key, $companyKey, $companyKeyHash, $employeeKey, $logType, $utc, $local->format('Y-m-d H:i:s'), $timezone->getName(), $deviceId !== '' ? $deviceId : null, $latitude, $longitude, ($assignment['shift_type_key'] ?? null) ?: null, ($assignment['shift_assignment_key'] ?? null) ?: null, $skip, $assignment === [] ? 1 : 0, $sourceSystem, $sourceReference, $checksum, $adminKey], 'Employee Check-in save');
        $saved = $db->GetRow('SELECT * FROM project_company_hr_employee_checkin WHERE company_key_hash = ? AND checkin_key = ?', [$companyKeyHash, $key]);
        if (!is_array($saved) || (string) $saved['employee_key'] !== $employeeKey || (string) $saved['checkin_at_utc'] !== $utc || (string) $saved['immutable_checksum'] !== $checksum) {
            throw new RuntimeException('Employee Check-in exact read-back verification failed.');
        }
        bx_audit('CREATE', 'project_company_hr_employee_checkin', $key, ['company_key' => $companyKey, 'employee_key' => $employeeKey, 'log_type' => $logType, 'checkin_at_utc' => $utc, 'source_reference' => $sourceReference], 'Company administrator saved a raw Employee Check-in.');
        if ($failureInjector) {
            $failureInjector($saved);
        }
        return $saved;
    });
}

function yovel_admin_hr_save_attendance_row(ADOConnection $db, array $context): array
{
    $existing = $db->GetRow('SELECT * FROM project_company_hr_attendance WHERE company_key_hash = ? AND employee_key = ? AND attendance_date = ? FOR UPDATE', [$context['company_key_hash'], $context['employee_key'], $context['attendance_date']]);
    if (($context['attendance_key'] ?? '') !== '' && (!$existing || (string) $existing['attendance_key'] !== (string) $context['attendance_key'])) {
        throw new InvalidArgumentException('Attendance record was not found for this employee and date.');
    }
    $key = $existing ? (string) $existing['attendance_key'] : bx_uuid();
    yovel_admin_db_execute($db, "INSERT INTO project_company_hr_attendance (
            attendance_key, company_key, company_key_hash, employee_key, attendance_date, attendance_status,
            working_hours, in_time_utc, out_time_utc, shift_type_key, shift_assignment_key,
            attendance_request_key, late_entry, early_exit, source_mode, source_reference,
            source_references_json, source_checksum, remarks, created_by_admin_key, updated_by_admin_key
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE attendance_status = VALUES(attendance_status), working_hours = VALUES(working_hours),
            in_time_utc = VALUES(in_time_utc), out_time_utc = VALUES(out_time_utc), shift_type_key = VALUES(shift_type_key),
            shift_assignment_key = VALUES(shift_assignment_key), attendance_request_key = VALUES(attendance_request_key),
            late_entry = VALUES(late_entry), early_exit = VALUES(early_exit), source_mode = VALUES(source_mode),
            source_reference = VALUES(source_reference), source_references_json = VALUES(source_references_json),
            source_checksum = VALUES(source_checksum), remarks = VALUES(remarks), updated_by_admin_key = VALUES(updated_by_admin_key)",
        [$key, $context['company_key'], $context['company_key_hash'], $context['employee_key'], $context['attendance_date'], $context['attendance_status'], $context['working_hours'], $context['in_time_utc'], $context['out_time_utc'], $context['shift_type_key'], $context['shift_assignment_key'], $context['attendance_request_key'], $context['late_entry'], $context['early_exit'], $context['source_mode'], $context['source_reference'], $context['source_references_json'], $context['source_checksum'], $context['remarks'], $context['admin_key'], $context['admin_key']], 'Attendance save');
    $saved = $db->GetRow('SELECT * FROM project_company_hr_attendance WHERE company_key_hash = ? AND attendance_key = ?', [$context['company_key_hash'], $key]);
    if (!is_array($saved) || (string) $saved['employee_key'] !== (string) $context['employee_key'] || (string) $saved['attendance_date'] !== (string) $context['attendance_date'] || (string) $saved['attendance_status'] !== (string) $context['attendance_status'] || (string) $saved['source_checksum'] !== (string) $context['source_checksum']) {
        throw new RuntimeException('Attendance exact read-back verification failed.');
    }
    bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_attendance', $key, ['company_key' => $context['company_key'], 'employee_key' => $context['employee_key'], 'attendance_date' => $context['attendance_date'], 'attendance_status' => $context['attendance_status'], 'source_mode' => $context['source_mode'], 'source_reference' => $context['source_reference']], 'Company administrator saved Attendance.');
    return $saved;
}

function yovel_admin_mark_attendance(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $employeeKey = trim((string) ($input['employee_key'] ?? ''));
    $date = yovel_admin_hr_iso_date((string) ($input['attendance_date'] ?? ''), 'Attendance date');
    $status = yovel_admin_status((string) ($input['attendance_status'] ?? 'PRESENT'), ['PRESENT', 'ABSENT', 'ON_LEAVE', 'HALF_DAY', 'WORK_FROM_HOME'], 'PRESENT');
    $sourceReference = trim((string) ($input['source_reference'] ?? ''));
    $remarks = trim((string) ($input['remarks'] ?? ''));
    if ($sourceReference === '' || strlen($sourceReference) > 160 || strlen($remarks) > 4000) {
        throw new InvalidArgumentException('Attendance source reference or remarks are invalid.');
    }
    $sourceJson = json_encode([], JSON_THROW_ON_ERROR);
    $checksum = yovel_admin_hr_attendance_checksum(['employee_key' => $employeeKey, 'attendance_date' => $date, 'attendance_status' => $status, 'source_mode' => 'MANUAL', 'source_reference' => $sourceReference, 'remarks' => $remarks]);
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $employeeKey, $date, $status, $sourceReference, $remarks, $sourceJson, $checksum, $input, $failureInjector): array {
        yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
        $saved = yovel_admin_hr_save_attendance_row($db, [
            'attendance_key' => trim((string) ($input['attendance_key'] ?? '')),
            'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'employee_key' => $employeeKey,
            'attendance_date' => $date, 'attendance_status' => $status, 'working_hours' => '0.00',
            'in_time_utc' => null, 'out_time_utc' => null, 'shift_type_key' => null, 'shift_assignment_key' => null,
            'attendance_request_key' => null, 'late_entry' => 0, 'early_exit' => 0, 'source_mode' => 'MANUAL',
            'source_reference' => $sourceReference, 'source_references_json' => $sourceJson,
            'source_checksum' => $checksum, 'remarks' => $remarks, 'admin_key' => $adminKey,
        ]);
        if ($failureInjector) {
            $failureInjector($saved);
        }
        return $saved;
    });
}

function yovel_admin_bulk_mark_attendance(ADOConnection $db, array $company, array $admin, array $employeeKeys, array $input): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $employeeKeys = array_values(array_unique(array_filter(array_map('strval', $employeeKeys), 'yovel_admin_is_uuid')));
    if ($employeeKeys === []) {
        throw new InvalidArgumentException('Select at least one employee for the Attendance Tool.');
    }
    $date = yovel_admin_hr_iso_date((string) ($input['attendance_date'] ?? ''), 'Attendance date');
    $status = yovel_admin_status((string) ($input['attendance_status'] ?? 'PRESENT'), ['PRESENT', 'ABSENT', 'HALF_DAY', 'WORK_FROM_HOME'], 'PRESENT');
    $sourceBase = trim((string) ($input['source_reference'] ?? 'ATTENDANCE-TOOL'));
    $remarks = trim((string) ($input['remarks'] ?? ''));
    if ($sourceBase === '' || strlen($sourceBase) > 100 || strlen($remarks) > 4000) {
        throw new InvalidArgumentException('Attendance Tool source reference or remarks are invalid.');
    }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $employeeKeys, $date, $status, $sourceBase, $remarks): array {
        $results = [];
        foreach ($employeeKeys as $employeeKey) {
            yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
            $sourceReference = $sourceBase . ':' . $employeeKey . ':' . $date;
            $checksum = yovel_admin_hr_attendance_checksum(['employee_key' => $employeeKey, 'attendance_date' => $date, 'attendance_status' => $status, 'source_mode' => 'MANUAL', 'source_reference' => $sourceReference, 'remarks' => $remarks]);
            $results[] = yovel_admin_hr_save_attendance_row($db, [
                'attendance_key' => '', 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash,
                'employee_key' => $employeeKey, 'attendance_date' => $date, 'attendance_status' => $status,
                'working_hours' => '0.00', 'in_time_utc' => null, 'out_time_utc' => null,
                'shift_type_key' => null, 'shift_assignment_key' => null, 'attendance_request_key' => null,
                'late_entry' => 0, 'early_exit' => 0, 'source_mode' => 'MANUAL',
                'source_reference' => $sourceReference, 'source_references_json' => '[]',
                'source_checksum' => $checksum, 'remarks' => $remarks, 'admin_key' => $adminKey,
            ]);
        }
        return $results;
    });
}

function yovel_admin_process_auto_attendance(ADOConnection $db, array $company, array $admin, string $employeeKey, string $attendanceDate, ?callable $failureInjector = null): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $attendanceDate = yovel_admin_hr_iso_date($attendanceDate, 'Attendance date');
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $employeeKey, $attendanceDate, $failureInjector): array {
        yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
        $assignment = $db->GetRow(
            "SELECT assignment.*, shift_record.*
             FROM project_company_hr_shift_assignment assignment
             INNER JOIN project_company_hr_shift_type shift_record
               ON shift_record.company_key_hash = assignment.company_key_hash
              AND shift_record.shift_type_key = assignment.shift_type_key
             WHERE assignment.company_key_hash = ? AND assignment.employee_key = ?
               AND assignment.assignment_status = 'ACTIVE' AND shift_record.shift_status = 'ACTIVE'
               AND shift_record.enable_auto_attendance = 1
               AND assignment.start_date <= ? AND COALESCE(assignment.end_date, '9999-12-31') >= ?
             ORDER BY assignment.start_date DESC, assignment.x_id DESC LIMIT 1 FOR UPDATE",
            [$companyKeyHash, $employeeKey, $attendanceDate, $attendanceDate]
        );
        if (!is_array($assignment) || $assignment === []) {
            throw new InvalidArgumentException('No auto-attendance shift assignment covers this employee and date.');
        }
        if ((string) ($assignment['process_attendance_after'] ?? '') !== '' && $attendanceDate < (string) $assignment['process_attendance_after']) {
            throw new InvalidArgumentException('This shift is not ready for auto-attendance processing.');
        }
        $window = yovel_admin_hr_shift_window($assignment, $attendanceDate);
        $checkins = $db->GetAll(
            "SELECT * FROM project_company_hr_employee_checkin
             WHERE company_key_hash = ? AND employee_key = ? AND skip_auto_attendance = 0
               AND checkin_at_utc BETWEEN ? AND ?
             ORDER BY checkin_at_utc, x_id FOR UPDATE",
            [$companyKeyHash, $employeeKey, $window['actual_start_utc'], $window['actual_end_utc']]
        );
        $checkins = is_array($checkins) ? $checkins : [];
        $in = null;
        $out = null;
        foreach ($checkins as $checkin) {
            if ((string) $checkin['log_type'] === 'IN' && $in === null) {
                $in = $checkin;
            }
            if ((string) $checkin['log_type'] === 'OUT') {
                $out = $checkin;
            }
        }
        $hours = 0.0;
        if ($in && $out && (string) $out['checkin_at_utc'] > (string) $in['checkin_at_utc']) {
            $hours = (strtotime((string) $out['checkin_at_utc'] . ' UTC') - strtotime((string) $in['checkin_at_utc'] . ' UTC')) / 3600;
        }
        $status = $hours > 0 ? 'PRESENT' : 'ABSENT';
        if (($assignment['absent_threshold_hours'] ?? null) !== null && $hours < (float) $assignment['absent_threshold_hours']) {
            $status = 'ABSENT';
        } elseif (($assignment['half_day_threshold_hours'] ?? null) !== null && $hours < (float) $assignment['half_day_threshold_hours']) {
            $status = 'HALF_DAY';
        }
        $sourceRefs = array_map(static fn (array $row): string => (string) $row['checkin_key'], $checkins);
        $sourceJson = json_encode($sourceRefs, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $sourceReference = 'AUTO:' . $employeeKey . ':' . $attendanceDate;
        $lateEntry = $in && new DateTimeImmutable((string) $in['checkin_at_utc'], new DateTimeZone('UTC')) > $window['start_local']->modify('+' . (int) $assignment['late_entry_grace_minutes'] . ' minutes')->setTimezone(new DateTimeZone('UTC')) ? 1 : 0;
        $earlyExit = $out && new DateTimeImmutable((string) $out['checkin_at_utc'], new DateTimeZone('UTC')) < $window['end_local']->modify('-' . (int) $assignment['early_exit_grace_minutes'] . ' minutes')->setTimezone(new DateTimeZone('UTC')) ? 1 : 0;
        $checksum = yovel_admin_hr_attendance_checksum(['employee_key' => $employeeKey, 'attendance_date' => $attendanceDate, 'status' => $status, 'hours' => number_format($hours, 2, '.', ''), 'source_references' => $sourceRefs, 'shift_assignment_key' => (string) $assignment['shift_assignment_key']]);
        $saved = yovel_admin_hr_save_attendance_row($db, [
            'attendance_key' => '', 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash,
            'employee_key' => $employeeKey, 'attendance_date' => $attendanceDate, 'attendance_status' => $status,
            'working_hours' => number_format($hours, 2, '.', ''), 'in_time_utc' => $in['checkin_at_utc'] ?? null,
            'out_time_utc' => $out['checkin_at_utc'] ?? null, 'shift_type_key' => (string) $assignment['shift_type_key'],
            'shift_assignment_key' => (string) $assignment['shift_assignment_key'], 'attendance_request_key' => null,
            'late_entry' => $lateEntry, 'early_exit' => $earlyExit, 'source_mode' => 'AUTO',
            'source_reference' => $sourceReference, 'source_references_json' => $sourceJson,
            'source_checksum' => $checksum, 'remarks' => 'Derived from raw Employee Check-ins.', 'admin_key' => $adminKey,
        ]);
        if ($sourceRefs !== []) {
            $placeholders = implode(',', array_fill(0, count($sourceRefs), '?'));
            yovel_admin_db_execute($db, "UPDATE project_company_hr_employee_checkin SET attendance_key = ? WHERE company_key_hash = ? AND checkin_key IN ($placeholders)", array_merge([(string) $saved['attendance_key'], $companyKeyHash], $sourceRefs), 'Employee Check-in attendance link');
            $linked = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_hr_employee_checkin WHERE company_key_hash = ? AND attendance_key = ? AND checkin_key IN ($placeholders)", array_merge([$companyKeyHash, (string) $saved['attendance_key']], $sourceRefs));
            if ($linked !== count($sourceRefs)) {
                throw new RuntimeException('Auto Attendance source link verification failed.');
            }
        }
        if ($failureInjector) {
            $failureInjector($saved);
        }
        return $saved;
    });
}

function yovel_admin_persist_attendance_request(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $key = trim((string) ($input['attendance_request_key'] ?? ''));
    $employeeKey = trim((string) ($input['employee_key'] ?? ''));
    $fromDate = yovel_admin_hr_iso_date((string) ($input['from_date'] ?? ''), 'From date');
    $toDate = yovel_admin_hr_iso_date((string) ($input['to_date'] ?? ''), 'To date');
    $reason = yovel_admin_status((string) ($input['reason'] ?? 'WORK_FROM_HOME'), ['WORK_FROM_HOME', 'ON_DUTY'], 'WORK_FROM_HOME');
    $status = yovel_admin_status((string) ($input['request_status'] ?? 'DRAFT'), ['DRAFT', 'APPROVED', 'REJECTED', 'CANCELLED'], 'DRAFT');
    $halfDay = in_array($input['half_day'] ?? null, [1, '1', true, 'on', 'yes'], true) ? 1 : 0;
    $halfDayDate = yovel_admin_optional_date((string) ($input['half_day_date'] ?? ''), 'Half-day date');
    $includeHolidays = in_array($input['include_holidays'] ?? null, [1, '1', true, 'on', 'yes'], true) ? 1 : 0;
    $shiftTypeKey = yovel_admin_hr_attendance_key($db, $companyKeyHash, 'project_company_hr_shift_type', 'shift_type_key', $input['shift_type_key'] ?? '', 'Shift Type', false);
    $explanation = trim((string) ($input['explanation'] ?? ''));
    if (($key !== '' && !yovel_admin_is_uuid($key)) || $toDate < $fromDate || ($halfDay === 1 && ($halfDayDate === '' || $halfDayDate < $fromDate || $halfDayDate > $toDate)) || strlen($explanation) > 4000) {
        throw new InvalidArgumentException('Attendance Request dates or explanation are invalid.');
    }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $key, $employeeKey, $fromDate, $toDate, $reason, $status, $halfDay, $halfDayDate, $includeHolidays, $shiftTypeKey, $explanation, $failureInjector): array {
        yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
        $existing = $key !== '' ? $db->GetRow('SELECT * FROM project_company_hr_attendance_request WHERE company_key_hash = ? AND attendance_request_key = ? FOR UPDATE', [$companyKeyHash, $key]) : null;
        if ($existing && in_array((string) $existing['request_status'], ['APPROVED', 'REJECTED', 'CANCELLED'], true)) {
            throw new InvalidArgumentException('A final Attendance Request cannot be changed.');
        }
        if ($key !== '' && (!$existing || !is_array($existing))) {
            throw new InvalidArgumentException('Attendance Request was not found in this company.');
        }
        $savedKey = $existing ? $key : bx_uuid();
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_attendance_request (
                attendance_request_key, company_key, company_key_hash, employee_key, from_date, to_date,
                half_day, half_day_date, reason, explanation, include_holidays, shift_type_key,
                request_status, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE from_date = VALUES(from_date), to_date = VALUES(to_date), half_day = VALUES(half_day),
                half_day_date = VALUES(half_day_date), reason = VALUES(reason), explanation = VALUES(explanation),
                include_holidays = VALUES(include_holidays), shift_type_key = VALUES(shift_type_key),
                request_status = VALUES(request_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$savedKey, $companyKey, $companyKeyHash, $employeeKey, $fromDate, $toDate, $halfDay, $halfDayDate !== '' ? $halfDayDate : null, $reason, $explanation, $includeHolidays, $shiftTypeKey !== '' ? $shiftTypeKey : null, $status, $adminKey, $adminKey], 'Attendance Request save');
        $saved = $db->GetRow('SELECT * FROM project_company_hr_attendance_request WHERE company_key_hash = ? AND attendance_request_key = ?', [$companyKeyHash, $savedKey]);
        if (!is_array($saved) || (string) $saved['employee_key'] !== $employeeKey || (string) $saved['from_date'] !== $fromDate || (string) $saved['request_status'] !== $status) {
            throw new RuntimeException('Attendance Request exact read-back verification failed.');
        }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_attendance_request', $savedKey, ['company_key' => $companyKey, 'employee_key' => $employeeKey, 'from_date' => $fromDate, 'to_date' => $toDate, 'request_status' => $status], 'Company administrator saved an Attendance Request.');
        if ($failureInjector) {
            $failureInjector($saved);
        }
        return $saved;
    });
}

function yovel_admin_transition_attendance_request(ADOConnection $db, array $company, array $admin, string $requestKey, string $status, ?callable $failureInjector = null): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $status = yovel_admin_status($status, ['APPROVED', 'REJECTED'], 'REJECTED');
    if (!yovel_admin_is_uuid($requestKey)) {
        throw new InvalidArgumentException('Attendance Request key is invalid.');
    }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $requestKey, $status, $failureInjector): array {
        $request = $db->GetRow('SELECT * FROM project_company_hr_attendance_request WHERE company_key_hash = ? AND attendance_request_key = ? FOR UPDATE', [$companyKeyHash, $requestKey]);
        if (!is_array($request) || $request === []) {
            throw new InvalidArgumentException('Attendance Request was not found in this company.');
        }
        if ((string) $request['request_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Only a draft Attendance Request can be approved or rejected.');
        }
        yovel_admin_hr_lock_employee($db, $companyKeyHash, (string) $request['employee_key']);
        if ($status === 'APPROVED') {
            $start = new DateTimeImmutable((string) $request['from_date']);
            $end = new DateTimeImmutable((string) $request['to_date']);
            for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
                $dateText = $date->format('Y-m-d');
                $attendanceStatus = (int) $request['half_day'] === 1 && $dateText === (string) $request['half_day_date'] ? 'HALF_DAY' : 'WORK_FROM_HOME';
                $sourceReference = 'REQUEST:' . $requestKey . ':' . $dateText;
                $sourceJson = json_encode([$requestKey], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $checksum = yovel_admin_hr_attendance_checksum(['attendance_request_key' => $requestKey, 'employee_key' => (string) $request['employee_key'], 'attendance_date' => $dateText, 'attendance_status' => $attendanceStatus]);
                yovel_admin_hr_save_attendance_row($db, [
                    'attendance_key' => '', 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash,
                    'employee_key' => (string) $request['employee_key'], 'attendance_date' => $dateText,
                    'attendance_status' => $attendanceStatus, 'working_hours' => '0.00', 'in_time_utc' => null,
                    'out_time_utc' => null, 'shift_type_key' => ($request['shift_type_key'] ?? null) ?: null,
                    'shift_assignment_key' => null, 'attendance_request_key' => $requestKey, 'late_entry' => 0,
                    'early_exit' => 0, 'source_mode' => 'REQUEST', 'source_reference' => $sourceReference,
                    'source_references_json' => $sourceJson, 'source_checksum' => $checksum,
                    'remarks' => (string) ($request['explanation'] ?? ''), 'admin_key' => $adminKey,
                ]);
            }
        }
        yovel_admin_db_execute($db, 'UPDATE project_company_hr_attendance_request SET request_status = ?, approved_by_admin_key = ?, approved_at = NOW(), updated_by_admin_key = ? WHERE company_key_hash = ? AND attendance_request_key = ?', [$status, $adminKey, $adminKey, $companyKeyHash, $requestKey], 'Attendance Request transition');
        $saved = $db->GetRow('SELECT * FROM project_company_hr_attendance_request WHERE company_key_hash = ? AND attendance_request_key = ?', [$companyKeyHash, $requestKey]);
        if (!is_array($saved) || (string) $saved['request_status'] !== $status || (string) $saved['approved_by_admin_key'] !== $adminKey) {
            throw new RuntimeException('Attendance Request transition read-back verification failed.');
        }
        bx_audit($status, 'project_company_hr_attendance_request', $requestKey, ['company_key' => $companyKey, 'request_status' => $status, 'approved_by_admin_key' => $adminKey], 'Company administrator reviewed an Attendance Request.');
        if ($failureInjector) {
            $failureInjector($saved);
        }
        return $saved;
    });
}

function yovel_admin_persist_overtime_slip(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $key = trim((string) ($input['overtime_slip_key'] ?? ''));
    $employeeKey = trim((string) ($input['employee_key'] ?? ''));
    $postingDate = yovel_admin_hr_iso_date((string) ($input['posting_date'] ?? ''), 'Posting date');
    $startDate = yovel_admin_hr_iso_date((string) ($input['start_date'] ?? ''), 'Start date');
    $endDate = yovel_admin_hr_iso_date((string) ($input['end_date'] ?? ''), 'End date');
    $status = yovel_admin_status((string) ($input['slip_status'] ?? 'DRAFT'), ['DRAFT', 'SUBMITTED', 'CANCELLED'], 'DRAFT');
    $details = is_array($input['details'] ?? null) ? $input['details'] : [];
    if (($key !== '' && !yovel_admin_is_uuid($key)) || $endDate < $startDate || $details === []) {
        throw new InvalidArgumentException('Overtime Slip dates and at least one detail row are required.');
    }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $key, $employeeKey, $postingDate, $startDate, $endDate, $status, $details, $failureInjector): array {
        yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
        $existing = $key !== '' ? $db->GetRow('SELECT * FROM project_company_hr_overtime_slip WHERE company_key_hash = ? AND overtime_slip_key = ? FOR UPDATE', [$companyKeyHash, $key]) : null;
        if ($existing && (string) $existing['slip_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('A submitted or cancelled Overtime Slip is immutable.');
        }
        if ($key !== '' && (!$existing || !is_array($existing))) {
            throw new InvalidArgumentException('Overtime Slip was not found in this company.');
        }
        $normalized = [];
        $total = 0.0;
        foreach ($details as $detail) {
            if (!is_array($detail)) {
                throw new InvalidArgumentException('Overtime detail row is invalid.');
            }
            $date = yovel_admin_hr_iso_date((string) ($detail['overtime_date'] ?? ''), 'Overtime date');
            if ($date < $startDate || $date > $endDate) {
                throw new InvalidArgumentException('Overtime detail date is outside the slip range.');
            }
            $typeKey = yovel_admin_hr_attendance_key($db, $companyKeyHash, 'project_company_hr_overtime_type', 'overtime_type_key', $detail['overtime_type_key'] ?? '', 'Overtime Type');
            $type = $db->GetRow('SELECT * FROM project_company_hr_overtime_type WHERE company_key_hash = ? AND overtime_type_key = ? AND overtime_status = ? FOR UPDATE', [$companyKeyHash, $typeKey, 'ACTIVE']);
            if (!is_array($type) || $type === []) {
                throw new InvalidArgumentException('Overtime Type is not active.');
            }
            $hours = yovel_admin_hr_decimal($detail['overtime_hours'] ?? '', 'Overtime hours', 0.01, 24, 2, true);
            $standardHours = yovel_admin_hr_decimal($detail['standard_working_hours'] ?? '', 'Standard working hours', 0.01, 24, 2, true);
            if (($type['maximum_hours'] ?? null) !== null && (float) $hours > (float) $type['maximum_hours']) {
                throw new InvalidArgumentException('Overtime hours exceed the maximum allowed for this type.');
            }
            $attendanceKey = yovel_admin_hr_attendance_key($db, $companyKeyHash, 'project_company_hr_attendance', 'attendance_key', $detail['reference_attendance_key'] ?? '', 'Attendance', false);
            $multiplier = number_format((float) $type['standard_multiplier'], 4, '.', '');
            $amount = null;
            if ((string) $type['calculation_method'] === 'FIXED_HOURLY_RATE') {
                $amount = number_format((float) $type['hourly_rate'] * (float) $hours * (float) $multiplier, 6, '.', '');
            }
            $normalized[] = [
                'reference_attendance_key' => $attendanceKey, 'overtime_date' => $date,
                'overtime_type_key' => $typeKey, 'overtime_hours' => $hours,
                'maximum_hours' => ($type['maximum_hours'] ?? null) !== null ? number_format((float) $type['maximum_hours'], 2, '.', '') : null,
                'standard_working_hours' => $standardHours, 'applied_multiplier' => $multiplier,
                'calculated_amount' => $amount,
            ];
            $total += (float) $hours;
        }
        usort($normalized, static fn (array $a, array $b): int => [$a['overtime_date'], $a['overtime_type_key']] <=> [$b['overtime_date'], $b['overtime_type_key']]);
        $totalText = number_format($total, 2, '.', '');
        $checksum = yovel_admin_hr_attendance_checksum(['employee_key' => $employeeKey, 'posting_date' => $postingDate, 'start_date' => $startDate, 'end_date' => $endDate, 'total_overtime_hours' => $totalText, 'details' => $normalized, 'slip_status' => $status]);
        $savedKey = $existing ? $key : bx_uuid();
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_overtime_slip (
                overtime_slip_key, company_key, company_key_hash, employee_key, posting_date,
                start_date, end_date, total_overtime_hours, slip_status, payroll_handoff_status,
                immutable_checksum, created_by_admin_key, updated_by_admin_key, submitted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE posting_date = VALUES(posting_date), start_date = VALUES(start_date),
                end_date = VALUES(end_date), total_overtime_hours = VALUES(total_overtime_hours),
                slip_status = VALUES(slip_status), immutable_checksum = VALUES(immutable_checksum),
                updated_by_admin_key = VALUES(updated_by_admin_key), submitted_at = VALUES(submitted_at)",
            [$savedKey, $companyKey, $companyKeyHash, $employeeKey, $postingDate, $startDate, $endDate, $totalText, $status, $checksum, $adminKey, $adminKey, $status === 'SUBMITTED' ? date('Y-m-d H:i:s') : null], 'Overtime Slip save');
        yovel_admin_db_execute($db, 'DELETE FROM project_company_hr_overtime_detail WHERE company_key_hash = ? AND overtime_slip_key = ?', [$companyKeyHash, $savedKey], 'Overtime Detail reset');
        foreach ($normalized as $detail) {
            $detailKey = bx_uuid();
            $detailChecksum = yovel_admin_hr_attendance_checksum(array_merge(['overtime_slip_key' => $savedKey], $detail));
            yovel_admin_db_execute($db, "INSERT INTO project_company_hr_overtime_detail (
                    overtime_detail_key, overtime_slip_key, company_key, company_key_hash, employee_key,
                    reference_attendance_key, overtime_date, overtime_type_key, overtime_hours, maximum_hours,
                    standard_working_hours, applied_multiplier, calculated_amount, immutable_checksum, created_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$detailKey, $savedKey, $companyKey, $companyKeyHash, $employeeKey, $detail['reference_attendance_key'] !== '' ? $detail['reference_attendance_key'] : null, $detail['overtime_date'], $detail['overtime_type_key'], $detail['overtime_hours'], $detail['maximum_hours'], $detail['standard_working_hours'], $detail['applied_multiplier'], $detail['calculated_amount'], $detailChecksum, $adminKey], 'Overtime Detail save');
        }
        $saved = $db->GetRow('SELECT * FROM project_company_hr_overtime_slip WHERE company_key_hash = ? AND overtime_slip_key = ?', [$companyKeyHash, $savedKey]);
        $detailCount = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_overtime_detail WHERE company_key_hash = ? AND overtime_slip_key = ?', [$companyKeyHash, $savedKey]);
        if (!is_array($saved) || (string) $saved['employee_key'] !== $employeeKey || number_format((float) $saved['total_overtime_hours'], 2, '.', '') !== $totalText || (string) $saved['immutable_checksum'] !== $checksum || $detailCount !== count($normalized) || (string) $saved['payroll_handoff_status'] !== 'PENDING' || !empty($saved['payroll_entry_key']) || !empty($saved['salary_slip_key'])) {
            throw new RuntimeException('Overtime Slip exact read-back or Payroll-boundary verification failed.');
        }
        $saved['total_overtime_hours'] = $totalText;
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_overtime_slip', $savedKey, ['company_key' => $companyKey, 'employee_key' => $employeeKey, 'posting_date' => $postingDate, 'total_overtime_hours' => $totalText, 'slip_status' => $status, 'payroll_handoff_status' => 'PENDING'], 'Company administrator saved an Overtime Slip for Payroll handoff.');
        if ($failureInjector) {
            $failureInjector($saved);
        }
        return $saved;
    });
}

function yovel_admin_hr_workforce_read_contract(array $company, array $options = []): array
{
    $companyKeyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if (preg_match('/^[0-9a-f]{64}$/', $companyKeyHash) !== 1) {
        throw new InvalidArgumentException('HR workforce company scope is invalid.');
    }
    $status = strtoupper(trim((string) ($options['status'] ?? 'ACTIVE')));
    $allowed = ['ACTIVE', 'INACTIVE', 'ON_LEAVE', 'SEPARATED', 'DRAFT', 'ALL'];
    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('HR workforce status filter is invalid.');
    }
    $sql = "SELECT employee.employee_key, employee.employee_code, employee.employee_name, employee.employee_status,
                   COALESCE(assignment.department_key, employee.department_key, '') AS department_key,
                   COALESCE(assignment.job_position_key, employee.job_position_key, '') AS job_position_key
            FROM project_company_hr_employee employee
            LEFT JOIN project_company_hr_employee_assignment assignment
              ON assignment.company_key_hash = employee.company_key_hash
             AND assignment.employee_key = employee.employee_key
             AND assignment.assignment_status = 'ACTIVE' AND assignment.is_primary = 1
            WHERE employee.company_key_hash = ? AND employee.employee_status <> 'DELETED'";
    $params = [$companyKeyHash];
    if ($status !== 'ALL') {
        $sql .= ' AND employee.employee_status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY employee.employee_code, employee.employee_key';
    $employees = bx_db()->GetAll($sql, $params);
    return [
        'contract' => 'hr.workforce.v1',
        'company_key_hash' => $companyKeyHash,
        'employees' => is_array($employees) ? $employees : [],
        'availability' => [
            'education' => ['available' => false, 'reason' => 'Deferred to HR-WP-10.'],
            'employment_history' => ['available' => false, 'reason' => 'Deferred to HR-WP-10.'],
            'employee_group' => ['available' => false, 'reason' => 'Deferred to HR-WP-10.'],
            'calendar' => ['available' => false, 'reason' => 'Deferred to HR-WP-10.'],
        ],
    ];
}

function yovel_admin_hr_attendance_data(array $company): array
{
    yovel_admin_hr_schema();
    $companyKeyHash = (string) $company['company_key_hash'];
    $db = bx_db();
    return [
        'attendance' => $db->GetAll("SELECT attendance.*, employee.employee_code, employee.employee_name FROM project_company_hr_attendance attendance INNER JOIN project_company_hr_employee employee ON employee.company_key_hash = attendance.company_key_hash AND employee.employee_key = attendance.employee_key WHERE attendance.company_key_hash = ? ORDER BY attendance.attendance_date DESC, employee.employee_name LIMIT 300", [$companyKeyHash]) ?: [],
        'checkins' => $db->GetAll("SELECT checkin.*, employee.employee_code, employee.employee_name FROM project_company_hr_employee_checkin checkin INNER JOIN project_company_hr_employee employee ON employee.company_key_hash = checkin.company_key_hash AND employee.employee_key = checkin.employee_key WHERE checkin.company_key_hash = ? ORDER BY checkin.checkin_at_utc DESC LIMIT 300", [$companyKeyHash]) ?: [],
        'attendanceRequests' => $db->GetAll("SELECT request.*, employee.employee_code, employee.employee_name FROM project_company_hr_attendance_request request INNER JOIN project_company_hr_employee employee ON employee.company_key_hash = request.company_key_hash AND employee.employee_key = request.employee_key WHERE request.company_key_hash = ? ORDER BY request.from_date DESC, request.x_id DESC LIMIT 300", [$companyKeyHash]) ?: [],
        'shiftTypes' => $db->GetAll("SELECT * FROM project_company_hr_shift_type WHERE company_key_hash = ? AND shift_status <> 'DELETED' ORDER BY shift_name", [$companyKeyHash]) ?: [],
        'shiftLocations' => $db->GetAll("SELECT * FROM project_company_hr_shift_location WHERE company_key_hash = ? AND location_status <> 'DELETED' ORDER BY location_name", [$companyKeyHash]) ?: [],
        'shiftSchedules' => $db->GetAll("SELECT schedule.*, shift_record.shift_name FROM project_company_hr_shift_schedule schedule INNER JOIN project_company_hr_shift_type shift_record ON shift_record.company_key_hash = schedule.company_key_hash AND shift_record.shift_type_key = schedule.shift_type_key WHERE schedule.company_key_hash = ? AND schedule.schedule_status <> 'DELETED' ORDER BY schedule.schedule_name", [$companyKeyHash]) ?: [],
        'shiftAssignments' => $db->GetAll("SELECT assignment.*, employee.employee_name, shift_record.shift_name FROM project_company_hr_shift_assignment assignment INNER JOIN project_company_hr_employee employee ON employee.company_key_hash = assignment.company_key_hash AND employee.employee_key = assignment.employee_key INNER JOIN project_company_hr_shift_type shift_record ON shift_record.company_key_hash = assignment.company_key_hash AND shift_record.shift_type_key = assignment.shift_type_key WHERE assignment.company_key_hash = ? AND assignment.assignment_status <> 'DELETED' ORDER BY assignment.start_date DESC, employee.employee_name LIMIT 300", [$companyKeyHash]) ?: [],
        'shiftRequests' => $db->GetAll("SELECT request.*, employee.employee_name, shift_record.shift_name FROM project_company_hr_shift_request request INNER JOIN project_company_hr_employee employee ON employee.company_key_hash = request.company_key_hash AND employee.employee_key = request.employee_key INNER JOIN project_company_hr_shift_type shift_record ON shift_record.company_key_hash = request.company_key_hash AND shift_record.shift_type_key = request.shift_type_key WHERE request.company_key_hash = ? ORDER BY request.from_date DESC, request.x_id DESC LIMIT 300", [$companyKeyHash]) ?: [],
        'overtimeTypes' => $db->GetAll("SELECT * FROM project_company_hr_overtime_type WHERE company_key_hash = ? AND overtime_status <> 'DELETED' ORDER BY overtime_name", [$companyKeyHash]) ?: [],
        'overtimeSlips' => $db->GetAll("SELECT slip.*, employee.employee_name FROM project_company_hr_overtime_slip slip INNER JOIN project_company_hr_employee employee ON employee.company_key_hash = slip.company_key_hash AND employee.employee_key = slip.employee_key WHERE slip.company_key_hash = ? ORDER BY slip.posting_date DESC, slip.x_id DESC LIMIT 300", [$companyKeyHash]) ?: [],
        'formTargets' => yovel_admin_hr_attendance_form_targets(),
    ];
}

function yovel_admin_hr_handle_attendance_post(array $company, array $admin, string $action, array $input): array
{
    $db = bx_db();
    $overtimeDetails = [];
    if ($action === 'hr_attendance_save_overtime') {
        $detailsJson = trim((string) ($input['details_json'] ?? ''));
        $overtimeDetails = $detailsJson !== ''
            ? json_decode($detailsJson, true, 512, JSON_THROW_ON_ERROR)
            : [[
                'reference_attendance_key' => (string) ($input['reference_attendance_key'] ?? ''),
                'overtime_date' => (string) ($input['overtime_date'] ?? ''),
                'overtime_type_key' => (string) ($input['overtime_type_key'] ?? ''),
                'overtime_hours' => (string) ($input['overtime_hours'] ?? ''),
                'standard_working_hours' => (string) ($input['standard_working_hours'] ?? ''),
            ]];
        if (!is_array($overtimeDetails)) {
            throw new InvalidArgumentException('Overtime details are invalid.');
        }
    }
    $saved = match ($action) {
        'hr_attendance_save_checkin' => yovel_admin_persist_employee_checkin($db, $company, $admin, $input),
        'hr_attendance_mark' => yovel_admin_mark_attendance($db, $company, $admin, $input),
        'hr_attendance_bulk_mark' => yovel_admin_bulk_mark_attendance($db, $company, $admin, is_array($input['employee_keys'] ?? null) ? $input['employee_keys'] : [], $input),
        'hr_attendance_save_request' => yovel_admin_persist_attendance_request($db, $company, $admin, $input),
        'hr_attendance_transition_request' => yovel_admin_transition_attendance_request($db, $company, $admin, (string) ($input['attendance_request_key'] ?? ''), (string) ($input['request_status'] ?? '')),
        'hr_attendance_save_master' => yovel_admin_persist_hr_attendance_master($db, $company, $admin, (string) ($input['record_type'] ?? ''), $input),
        'hr_attendance_save_assignment' => yovel_admin_persist_shift_assignment($db, $company, $admin, $input),
        'hr_attendance_bulk_assignment' => yovel_admin_bulk_shift_assignment($db, $company, $admin, is_array($input['employee_keys'] ?? null) ? $input['employee_keys'] : [], $input),
        'hr_attendance_save_shift_request' => yovel_admin_persist_shift_request($db, $company, $admin, $input),
        'hr_attendance_transition_shift_request' => yovel_admin_transition_shift_request($db, $company, $admin, (string) ($input['shift_request_key'] ?? ''), (string) ($input['request_status'] ?? '')),
        'hr_attendance_save_overtime' => yovel_admin_persist_overtime_slip($db, $company, $admin, array_merge($input, ['details' => $overtimeDetails])),
        'hr_attendance_auto_process' => yovel_admin_process_auto_attendance($db, $company, $admin, (string) ($input['employee_key'] ?? ''), (string) ($input['attendance_date'] ?? '')),
        default => throw new InvalidArgumentException('This Shift & Attendance action is not available.'),
    };
    return [
        'message' => 'Shift & Attendance record saved.',
        'section' => 'attendance',
        'query' => ['record' => (string) ($saved['attendance_key'] ?? $saved['checkin_key'] ?? $saved['attendance_request_key'] ?? $saved['shift_assignment_key'] ?? $saved['shift_request_key'] ?? $saved['overtime_slip_key'] ?? $saved['shift_type_key'] ?? $saved['shift_schedule_key'] ?? $saved['shift_location_key'] ?? $saved['overtime_type_key'] ?? '')],
    ];
}
