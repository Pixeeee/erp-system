<?php
declare(strict_types=1);

function yovel_admin_hr_submission_typed_answer(array $question, mixed $rawValue): ?array
{
    $type = strtoupper((string) ($question['type'] ?? 'SHORT_TEXT'));
    $label = (string) ($question['label'] ?? 'Question');
    $required = !empty($question['required']);
    if ($type === 'SECTION') {
        return null;
    }

    $typed = [
        'field_type' => $type,
        'value_text' => null,
        'value_number' => null,
        'value_date' => null,
        'value_json' => null,
    ];
    if ($type === 'CHECKBOXES') {
        $values = is_array($rawValue) ? $rawValue : [];
        $values = array_values(array_unique(array_filter(array_map(static fn ($value): string => trim((string) $value), $values), static fn (string $value): bool => $value !== '')));
        $options = array_values(array_map('strval', is_array($question['options'] ?? null) ? $question['options'] : []));
        foreach ($values as $value) {
            if (!in_array($value, $options, true)) {
                throw new InvalidArgumentException($label . ' contains an invalid selected option.');
            }
        }
        if ($values === []) {
            if ($required) {
                throw new InvalidArgumentException($label . ' is required.');
            }
            return null;
        }
        $typed['value_json'] = json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $typed;
    }

    if (is_array($rawValue)) {
        throw new InvalidArgumentException($label . ' has an invalid answer type.');
    }
    $value = trim((string) $rawValue);
    if ($value === '') {
        if ($required) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        return null;
    }
    if (strlen($value) > 5000) {
        throw new InvalidArgumentException($label . ' exceeds the allowed length.');
    }

    if ($type === 'NUMBER') {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException($label . ' must be a number.');
        }
        $typed['value_number'] = $value;
    } elseif ($type === 'DATE') {
        yovel_admin_optional_date($value, $label);
        $typed['value_date'] = $value;
    } elseif ($type === 'EMAIL') {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException($label . ' must be a valid email address.');
        }
        $typed['value_text'] = $value;
    } elseif ($type === 'DROPDOWN') {
        $options = array_values(array_map('strval', is_array($question['options'] ?? null) ? $question['options'] : []));
        if (!in_array($value, $options, true)) {
            throw new InvalidArgumentException($label . ' has an invalid selected option.');
        }
        $typed['value_text'] = $value;
    } else {
        $typed['value_text'] = $value;
    }

    return $typed;
}

function yovel_admin_persist_hr_form_submission(
    ADOConnection $db,
    array $company,
    array $admin,
    array $payload
): array {
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    $adminKey = (string) ($admin['admin_key'] ?? '');
    $submissionKey = trim((string) ($payload['submission_key'] ?? ''));
    $builderFormKey = trim((string) ($payload['builder_form_key'] ?? ''));
    $requestedVersionKey = trim((string) ($payload['form_version_key'] ?? ''));
    $moduleFormKey = trim((string) ($payload['module_form_key'] ?? ''));
    $subjectType = strtoupper(trim((string) ($payload['subject_type'] ?? 'EMPLOYEE')));
    $subjectKey = trim((string) ($payload['subject_key'] ?? ''));
    $submissionStatus = yovel_admin_status((string) ($payload['submission_status'] ?? 'DRAFT'), ['DRAFT', 'SUBMITTED', 'VOID'], 'DRAFT');
    $answers = $payload['answers'] ?? [];
    if (!is_array($answers)) {
        throw new InvalidArgumentException('Form answers must be a keyed list.');
    }
    if ($submissionKey !== '' && !yovel_admin_is_uuid($submissionKey)) {
        throw new InvalidArgumentException('Invalid form submission key.');
    }
    if (!yovel_admin_is_uuid($builderFormKey) || !yovel_admin_is_uuid($subjectKey) || $subjectType !== 'EMPLOYEE') {
        throw new InvalidArgumentException('Invalid form or employee submission subject.');
    }

    $existing = null;
    if ($submissionKey !== '') {
        $existing = $db->GetRow(
            'SELECT * FROM project_form_submission WHERE company_key_hash = ? AND submission_key = ? FOR UPDATE',
            [$companyKeyHash, $submissionKey]
        );
        if (!is_array($existing) || $existing === []) {
            throw new InvalidArgumentException('The form submission was not found for this company.');
        }
        if ((string) $existing['builder_form_key'] !== $builderFormKey
            || (string) $existing['subject_type'] !== $subjectType
            || (string) $existing['subject_key'] !== $subjectKey) {
            throw new InvalidArgumentException('The form submission identity cannot be changed.');
        }
        $requestedVersionKey = (string) $existing['form_version_key'];
        $moduleFormKey = (string) ($existing['module_form_key'] ?? '');
    }

    $builderForm = $db->GetRow(
        "SELECT * FROM project_company_hr_builder_form
         WHERE company_key_hash = ? AND builder_form_key = ? AND form_status <> 'DELETED' FOR UPDATE",
        [$companyKeyHash, $builderFormKey]
    );
    if (!is_array($builderForm) || $builderForm === []) {
        throw new InvalidArgumentException('The selected custom form was not found for this company.');
    }
    if ($requestedVersionKey === '') {
        $requestedVersionKey = (string) $db->GetOne(
            'SELECT form_version_key FROM project_company_hr_builder_form_version WHERE company_key_hash = ? AND builder_form_key = ? ORDER BY version_number DESC LIMIT 1',
            [$companyKeyHash, $builderFormKey]
        );
    }
    $version = $db->GetRow(
        'SELECT * FROM project_company_hr_builder_form_version WHERE company_key_hash = ? AND builder_form_key = ? AND form_version_key = ? LIMIT 1',
        [$companyKeyHash, $builderFormKey, $requestedVersionKey]
    );
    if (!is_array($version) || $version === []) {
        throw new InvalidArgumentException('The selected form version was not found for this company.');
    }
    if ($submissionStatus === 'SUBMITTED' && (string) $version['form_status'] !== 'ACTIVE') {
        throw new InvalidArgumentException('Only an active form version can be submitted.');
    }

    $employee = $db->GetRow(
        "SELECT
            employee_record.employee_key,
            COALESCE(assignment_record.branch_key, employee_record.branch_key, '') AS branch_key,
            COALESCE(assignment_record.project_key, '') AS project_key
         FROM project_company_hr_employee employee_record
         LEFT JOIN project_company_hr_employee_assignment assignment_record
            ON assignment_record.company_key_hash = employee_record.company_key_hash
           AND assignment_record.employee_key = employee_record.employee_key
           AND assignment_record.assignment_status = 'ACTIVE'
           AND assignment_record.is_primary = 1
         WHERE employee_record.company_key_hash = ? AND employee_record.employee_key = ?
           AND employee_record.employee_status <> 'DELETED'
         LIMIT 1",
        [$companyKeyHash, $subjectKey]
    );
    if (!is_array($employee) || $employee === []) {
        throw new InvalidArgumentException('The selected employee was not found for this company.');
    }

    $moduleGroupKey = null;
    $moduleKey = null;
    $moduleProjectKey = null;
    if ($moduleFormKey !== '') {
        if (!yovel_admin_is_uuid($moduleFormKey)) {
            throw new InvalidArgumentException('Invalid project module form key.');
        }
        $moduleForm = $db->GetRow(
            "SELECT module_group_key, module_key, project_key, source_builder_form_key
             FROM project_module_form
             WHERE company_key_hash = ? AND form_key = ? AND form_status <> 'DELETED' LIMIT 1",
            [$companyKeyHash, $moduleFormKey]
        );
        if (!is_array($moduleForm)
            || (string) ($moduleForm['source_builder_form_key'] ?? '') !== $builderFormKey) {
            throw new InvalidArgumentException('The project module form is not bound to the selected custom form.');
        }
        $moduleGroupKey = (string) $moduleForm['module_group_key'];
        $moduleKey = (string) $moduleForm['module_key'];
        $moduleProjectKey = (string) $moduleForm['project_key'];
        if ((string) ($employee['project_key'] ?? '') !== '' && (string) $employee['project_key'] !== $moduleProjectKey) {
            throw new InvalidArgumentException('The project module form does not belong to the employee assignment project.');
        }
    }

    $schema = json_decode((string) $version['schema_json'], true);
    if (!is_array($schema) || !is_array($schema['questions'] ?? null)) {
        throw new RuntimeException('The saved form version schema is invalid.');
    }
    $questions = [];
    foreach ($schema['questions'] as $question) {
        if (!is_array($question)) {
            continue;
        }
        $questionKey = (string) ($question['key'] ?? '');
        if ($questionKey === '' || isset($questions[$questionKey])) {
            throw new RuntimeException('The saved form version contains invalid question identities.');
        }
        $questions[$questionKey] = $question;
    }
    foreach (array_keys($answers) as $answerKey) {
        if (!isset($questions[(string) $answerKey]) || (string) ($questions[(string) $answerKey]['type'] ?? '') === 'SECTION') {
            throw new InvalidArgumentException('The submitted answers contain an unknown question key.');
        }
    }

    $typedAnswers = [];
    foreach ($questions as $questionKey => $question) {
        if ((string) ($question['type'] ?? '') === 'SECTION') {
            continue;
        }
        $typedAnswers[$questionKey] = yovel_admin_hr_submission_typed_answer($question, $answers[$questionKey] ?? null);
    }

    $submissionKey = $submissionKey !== '' ? $submissionKey : bx_uuid();
    $branchKey = (string) ($employee['branch_key'] ?? '');
    $projectKey = $moduleProjectKey ?? ((string) ($employee['project_key'] ?? '') ?: null);
    $submittedAtSql = $submissionStatus === 'SUBMITTED' ? 'CURRENT_TIMESTAMP' : 'NULL';
    yovel_admin_db_execute(
        $db,
        "INSERT INTO project_form_submission (
            submission_key, company_key_hash, branch_key, project_key, module_group_key, module_key,
            module_form_key, builder_form_key, form_version_key, subject_type, subject_key,
            submission_status, submitted_by_admin_key, submitted_at, created_by_admin_key, updated_by_admin_key
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, {$submittedAtSql}, ?, ?)
        ON DUPLICATE KEY UPDATE
            branch_key = VALUES(branch_key),
            project_key = VALUES(project_key),
            module_group_key = VALUES(module_group_key),
            module_key = VALUES(module_key),
            module_form_key = VALUES(module_form_key),
            submission_status = VALUES(submission_status),
            submitted_by_admin_key = VALUES(submitted_by_admin_key),
            submitted_at = {$submittedAtSql},
            updated_by_admin_key = VALUES(updated_by_admin_key)",
        [
            $submissionKey, $companyKeyHash, $branchKey !== '' ? $branchKey : null, $projectKey,
            $moduleGroupKey, $moduleKey, $moduleFormKey !== '' ? $moduleFormKey : null,
            $builderFormKey, (string) $version['form_version_key'], $subjectType, $subjectKey,
            $submissionStatus, $adminKey, $adminKey, $adminKey,
        ],
        'HR form submission save'
    );

    $changedQuestionKeys = [];
    foreach ($typedAnswers as $questionKey => $typed) {
        if ($typed === null) {
            yovel_admin_db_execute(
                $db,
                'DELETE FROM project_form_submission_value WHERE submission_key = ? AND question_key = ?',
                [$submissionKey, $questionKey],
                'HR cleared form answer delete'
            );
            $changedQuestionKeys[] = $questionKey;
            continue;
        }
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_form_submission_value (
                submission_value_key, submission_key, question_key, field_type,
                value_text, value_number, value_date, value_json,
                created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                field_type = VALUES(field_type),
                value_text = VALUES(value_text),
                value_number = VALUES(value_number),
                value_date = VALUES(value_date),
                value_json = VALUES(value_json),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                bx_uuid(), $submissionKey, $questionKey, $typed['field_type'],
                $typed['value_text'], $typed['value_number'], $typed['value_date'], $typed['value_json'],
                $adminKey, $adminKey,
            ],
            'HR typed form answer save'
        );
        $changedQuestionKeys[] = $questionKey;
    }

    $readBack = $db->GetRow(
        'SELECT * FROM project_form_submission WHERE company_key_hash = ? AND submission_key = ? LIMIT 1',
        [$companyKeyHash, $submissionKey]
    );
    $expectedSubmission = [
        'submission_key' => $submissionKey,
        'company_key_hash' => $companyKeyHash,
        'branch_key' => $branchKey,
        'project_key' => (string) ($projectKey ?? ''),
        'module_group_key' => (string) ($moduleGroupKey ?? ''),
        'module_key' => (string) ($moduleKey ?? ''),
        'module_form_key' => $moduleFormKey,
        'builder_form_key' => $builderFormKey,
        'form_version_key' => (string) $version['form_version_key'],
        'subject_type' => $subjectType,
        'subject_key' => $subjectKey,
        'submission_status' => $submissionStatus,
    ];
    foreach ($expectedSubmission as $column => $expected) {
        if (!is_array($readBack) || (string) ($readBack[$column] ?? '') !== $expected) {
            throw new RuntimeException('HR form submission read-back mismatch for ' . $column . '.');
        }
    }

    $savedValues = $db->GetAll(
        'SELECT question_key, field_type, value_text, value_number, value_date, value_json FROM project_form_submission_value WHERE submission_key = ? ORDER BY question_key',
        [$submissionKey]
    );
    $savedByQuestion = [];
    foreach (is_array($savedValues) ? $savedValues : [] as $savedValue) {
        $savedByQuestion[(string) $savedValue['question_key']] = $savedValue;
    }
    foreach ($typedAnswers as $questionKey => $typed) {
        if ($typed === null) {
            if (isset($savedByQuestion[$questionKey])) {
                throw new RuntimeException('HR cleared form answer read-back failed for ' . $questionKey . '.');
            }
            continue;
        }
        $savedValue = $savedByQuestion[$questionKey] ?? null;
        if (!is_array($savedValue)
            || (string) ($savedValue['field_type'] ?? '') !== (string) $typed['field_type']
            || (string) ($savedValue['value_text'] ?? '') !== (string) ($typed['value_text'] ?? '')
            || (string) ($savedValue['value_date'] ?? '') !== (string) ($typed['value_date'] ?? '')
            || (string) ($savedValue['value_json'] ?? '') !== (string) ($typed['value_json'] ?? '')
            || ($typed['value_number'] === null
                ? (string) ($savedValue['value_number'] ?? '') !== ''
                : (float) ($savedValue['value_number'] ?? 0) !== (float) $typed['value_number'])) {
            throw new RuntimeException('HR typed form answer read-back failed for ' . $questionKey . '.');
        }
    }

    bx_audit(is_array($existing) ? 'UPDATE' : 'CREATE', 'project_form_submission', $submissionKey, [
        'builder_form_key' => $builderFormKey,
        'form_version_key' => (string) $version['form_version_key'],
        'subject_type' => $subjectType,
        'subject_key' => $subjectKey,
        'submission_status' => $submissionStatus,
        'changed_question_keys' => $changedQuestionKeys,
    ], is_array($existing) ? 'Company admin updated an HR form submission.' : 'Company admin created an HR form submission.');

    return $readBack;
}

function yovel_admin_save_hr_form_submission(array $company, array $admin): string
{
    yovel_admin_hr_schema();
    $answers = $_POST['answers'] ?? [];
    if (!is_array($answers)) {
        $answers = [];
    }
    $db = bx_db();
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('HR form submission transaction could not start.');
    }
    try {
        $saved = yovel_admin_persist_hr_form_submission($db, $company, $admin, [
            'submission_key' => (string) ($_POST['submission_key'] ?? ''),
            'builder_form_key' => (string) ($_POST['builder_form_key'] ?? ''),
            'form_version_key' => (string) ($_POST['form_version_key'] ?? ''),
            'module_form_key' => (string) ($_POST['module_form_key'] ?? ''),
            'subject_type' => 'EMPLOYEE',
            'subject_key' => (string) ($_POST['subject_key'] ?? ''),
            'submission_status' => (string) ($_POST['submission_status'] ?? 'DRAFT'),
            'answers' => $answers,
        ]);
        $_POST['submission_key'] = (string) $saved['submission_key'];
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('HR form submission transaction commit failed.');
        }
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return (string) $saved['submission_status'] === 'SUBMITTED'
        ? 'HR form submitted.'
        : 'HR form draft saved.';
}

function yovel_admin_hr_form_submissions(array $company): array
{
    $rows = bx_db()->GetAll(
        "SELECT
            submission_record.*,
            form_record.form_title,
            form_record.target_section,
            version_record.version_number,
            employee_record.employee_code,
            employee_record.employee_name
         FROM project_form_submission submission_record
         INNER JOIN project_company_hr_builder_form form_record
            ON form_record.builder_form_key = submission_record.builder_form_key
           AND form_record.company_key_hash = submission_record.company_key_hash
         INNER JOIN project_company_hr_builder_form_version version_record
            ON version_record.form_version_key = submission_record.form_version_key
           AND version_record.builder_form_key = submission_record.builder_form_key
         LEFT JOIN project_company_hr_employee employee_record
            ON submission_record.subject_type = 'EMPLOYEE'
           AND employee_record.employee_key = submission_record.subject_key
           AND employee_record.company_key_hash = submission_record.company_key_hash
         WHERE submission_record.company_key_hash = ? AND submission_record.submission_status <> 'VOID'
         ORDER BY submission_record.updated_at DESC, submission_record.x_id DESC",
        [(string) $company['company_key_hash']]
    );

    return is_array($rows) ? $rows : [];
}

function yovel_admin_hr_builder_form_version(array $company, string $builderFormKey, string $formVersionKey = ''): ?array
{
    if (!yovel_admin_is_uuid($builderFormKey)) {
        return null;
    }
    if ($formVersionKey !== '' && !yovel_admin_is_uuid($formVersionKey)) {
        return null;
    }
    $params = [(string) $company['company_key_hash'], $builderFormKey];
    $versionFilter = '';
    if ($formVersionKey !== '') {
        $versionFilter = ' AND form_version_key = ?';
        $params[] = $formVersionKey;
    }
    $row = bx_db()->GetRow(
        "SELECT * FROM project_company_hr_builder_form_version
         WHERE company_key_hash = ? AND builder_form_key = ?{$versionFilter}
         ORDER BY version_number DESC LIMIT 1",
        $params
    );

    return is_array($row) && $row !== [] ? $row : null;
}

function yovel_admin_hr_submission_values(array $company, string $submissionKey): array
{
    if (!yovel_admin_is_uuid($submissionKey)) {
        return [];
    }
    $rows = bx_db()->GetAll(
        "SELECT value_record.*
         FROM project_form_submission_value value_record
         INNER JOIN project_form_submission submission_record
            ON submission_record.submission_key = value_record.submission_key
         WHERE submission_record.company_key_hash = ? AND value_record.submission_key = ?
         ORDER BY value_record.x_id",
        [(string) $company['company_key_hash'], $submissionKey]
    );
    $values = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $questionKey = (string) $row['question_key'];
        $type = (string) $row['field_type'];
        if ($type === 'CHECKBOXES') {
            $decoded = json_decode((string) ($row['value_json'] ?? '[]'), true);
            $values[$questionKey] = is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
        } elseif ($type === 'NUMBER') {
            $values[$questionKey] = (string) ($row['value_number'] ?? '');
        } elseif ($type === 'DATE') {
            $values[$questionKey] = (string) ($row['value_date'] ?? '');
        } else {
            $values[$questionKey] = (string) ($row['value_text'] ?? '');
        }
    }

    return $values;
}
