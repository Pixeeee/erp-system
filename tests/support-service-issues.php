<?php
declare(strict_types=1);

require_once __DIR__ . '/support-service-test-helper.php';

support_service_assert(function_exists('yovel_admin_save_support_issue'), 'Support Issue save service is missing.');
support_service_assert(function_exists('yovel_admin_save_support_issue_priority'), 'Support Issue Priority save service is missing.');
support_service_assert(function_exists('yovel_admin_save_support_issue_type'), 'Support Issue Type save service is missing.');
support_service_assert(function_exists('yovel_admin_transition_support_issue'), 'Support Issue lifecycle service is missing.');

$db = bx_db();
$primary = support_service_create_company($db, 'Issues Primary');
$foreign = support_service_create_company($db, 'Issues Foreign');
register_shutdown_function(static function () use ($db, $primary, $foreign): void {
    support_service_cleanup_company($db, $primary);
    support_service_cleanup_company($db, $foreign);
});

$company = $primary['company'];
$admin = $primary['admin'];
$foreignCompany = $foreign['company'];
$foreignAdmin = $foreign['admin'];
yovel_admin_support_service_schema();

foreach ([
    ['project_company_support_issue_priority', 'priority_version'],
    ['project_company_support_issue_type', 'issue_type_version'],
    ['project_company_support_issue', 'form_schema_checksum'],
] as [$table, $column]) {
    support_service_assert(
        (int) $db->GetOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [BUILDERX_DB_NAME, $table, $column]
        ) === 1,
        $table . ' is missing ' . $column . '.'
    );
}

$priority = yovel_admin_save_support_issue_priority($company, $admin, [
    'expected_version' => '0',
    'priority_name' => 'High',
    'priority_description' => 'Urgent customer-impacting work.',
    'priority_status' => 'ACTIVE',
]);
support_service_assert(yovel_admin_is_uuid((string) $priority['issue_priority_key']), 'Issue Priority did not receive a stable key.');
support_service_assert((int) $priority['priority_version'] === 1 && (string) $priority['priority_name'] === 'High', 'Issue Priority create read-back failed.');

$priority = yovel_admin_save_support_issue_priority($company, $admin, [
    'issue_priority_key' => (string) $priority['issue_priority_key'],
    'expected_version' => '1',
    'priority_name' => 'Urgent',
    'priority_description' => 'Immediate attention.',
    'priority_status' => 'ACTIVE',
]);
support_service_assert((int) $priority['priority_version'] === 2 && (string) $priority['priority_name'] === 'Urgent', 'Issue Priority update changed the stable identity or missed committed values.');
support_service_expect_exception(
    static fn () => yovel_admin_save_support_issue_priority($company, $admin, [
        'expected_version' => '0',
        'priority_name' => 'urgent',
        'priority_description' => 'Duplicate by case.',
        'priority_status' => 'ACTIVE',
    ]),
    RuntimeException::class,
    'changed since'
);

$priorityRollback = false;
try {
    yovel_admin_save_support_issue_priority($company, $admin, [
        'issue_priority_key' => (string) $priority['issue_priority_key'],
        'expected_version' => '2',
        'priority_name' => 'Rolled back priority',
        'priority_description' => 'Must not commit.',
        'priority_status' => 'INACTIVE',
    ], static function (): void {
        throw new RuntimeException('Priority rollback injection.');
    });
} catch (RuntimeException $error) {
    $priorityRollback = $error->getMessage() === 'Priority rollback injection.';
}
support_service_assert($priorityRollback, 'Issue Priority rollback injection did not run.');
support_service_assert((string) yovel_admin_support_issue_priority($company, $admin, (string) $priority['issue_priority_key'])['priority_name'] === 'Urgent', 'Issue Priority survived rollback.');

$issueType = yovel_admin_save_support_issue_type($company, $admin, [
    'expected_version' => '0',
    'issue_type_name' => 'Service request',
    'issue_type_description' => 'Dependency-safe local request.',
    'issue_type_status' => 'ACTIVE',
]);
support_service_assert(yovel_admin_is_uuid((string) $issueType['issue_type_key']) && (int) $issueType['issue_type_version'] === 1, 'Issue Type create read-back failed.');
$issueType = yovel_admin_save_support_issue_type($company, $admin, [
    'issue_type_key' => (string) $issueType['issue_type_key'],
    'expected_version' => '1',
    'issue_type_name' => 'Technical request',
    'issue_type_description' => 'Local technical issue.',
    'issue_type_status' => 'ACTIVE',
]);
support_service_assert((int) $issueType['issue_type_version'] === 2 && (string) $issueType['issue_type_name'] === 'Technical request', 'Issue Type update failed.');

$foreignPriority = yovel_admin_save_support_issue_priority($foreignCompany, $foreignAdmin, [
    'expected_version' => '0',
    'priority_name' => 'Foreign Priority',
    'priority_description' => 'Foreign company.',
    'priority_status' => 'ACTIVE',
]);
$foreignType = yovel_admin_save_support_issue_type($foreignCompany, $foreignAdmin, [
    'expected_version' => '0',
    'issue_type_name' => 'Foreign Type',
    'issue_type_description' => 'Foreign company.',
    'issue_type_status' => 'ACTIVE',
]);
support_service_expect_exception(
    static fn () => yovel_admin_save_support_issue_priority($company, $admin, [
        'issue_priority_key' => (string) $foreignPriority['issue_priority_key'],
        'expected_version' => '1',
        'priority_name' => 'Cross-company edit',
        'priority_status' => 'ACTIVE',
    ]),
    InvalidArgumentException::class,
    'another company'
);

$issue = yovel_admin_save_support_issue($company, $admin, [
    'expected_version' => '0',
    'subject' => 'Local dependency-safe issue',
    'description' => 'Created without customer, contact, project, assignment, communication, or portal ownership.',
    'issue_priority_key' => (string) $priority['issue_priority_key'],
    'issue_type_key' => (string) $issueType['issue_type_key'],
    'customer_key' => '',
    'contact_key' => '',
    'project_key' => '',
    'assigned_admin_key' => '',
    'communication_key' => '',
    'portal_owner_key' => '',
]);
$issueKey = (string) $issue['issue_key'];
support_service_assert(yovel_admin_is_uuid($issueKey), 'Issue did not receive a stable key.');
support_service_assert(preg_match('/^ISS-[0-9]{8}-[A-F0-9]{8}$/D', (string) $issue['issue_code']) === 1, 'Issue code was not generated server-side.');
support_service_assert((int) $issue['issue_version'] === 1 && (string) $issue['issue_status'] === 'OPEN', 'Issue create read-back failed.');
support_service_assert((string) $issue['customer_key'] === '' && (string) $issue['project_key'] === '', 'Blank external references were not preserved as blank.');
support_service_assert(preg_match('/^[0-9a-f]{64}$/D', (string) $issue['form_schema_checksum']) === 1, 'Issue submission is not bound to a Form Builder identity.');
support_service_assert((string) $issue['opened_at'] !== '', 'Issue opening timestamp was not generated server-side.');

$createdCode = (string) $issue['issue_code'];
$openedAt = (string) $issue['opened_at'];
$issue = yovel_admin_save_support_issue($company, $admin, [
    'issue_key' => $issueKey,
    'expected_version' => '1',
    'subject' => 'Updated local issue',
    'description' => 'Updated without external references.',
    'issue_priority_key' => (string) $priority['issue_priority_key'],
    'issue_type_key' => (string) $issueType['issue_type_key'],
    'issue_status' => 'OPEN',
    'form_schema_checksum' => (string) $issue['form_schema_checksum'],
]);
support_service_assert((int) $issue['issue_version'] === 2 && (string) $issue['subject'] === 'Updated local issue', 'Issue update read-back failed.');
support_service_assert((string) $issue['issue_code'] === $createdCode && (string) $issue['opened_at'] === $openedAt, 'Issue update changed immutable identity fields.');

support_service_expect_exception(
    static fn () => yovel_admin_save_support_issue($company, $admin, [
        'expected_version' => '0',
        'subject' => 'Foreign master reference',
        'issue_priority_key' => (string) $foreignPriority['issue_priority_key'],
        'issue_type_key' => (string) $issueType['issue_type_key'],
    ]),
    InvalidArgumentException::class,
    'another company'
);
support_service_expect_exception(
    static fn () => yovel_admin_save_support_issue($company, $admin, [
        'expected_version' => '0',
        'subject' => 'Foreign type reference',
        'issue_priority_key' => (string) $priority['issue_priority_key'],
        'issue_type_key' => (string) $foreignType['issue_type_key'],
    ]),
    InvalidArgumentException::class,
    'another company'
);

$dependencyInputs = [
    'customer_key' => ['customer_key', 'sales-crm.customer-reference.v1'],
    'contact_key' => ['contact_key', 'sales-crm.contact-reference.v1'],
    'project_key' => ['project_key', 'projects.service-work-reference.v1'],
    'assigned_admin_key' => ['assigned_admin_key', 'operations.support-assignment.v1'],
    'communication_key' => ['communication_key', 'operations.append-communication.v1'],
    'portal_owner_key' => ['portal_owner_key', 'sales-crm.support-portal-owner.v1'],
];
$issueCountBeforeDependencies = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_support_issue WHERE company_key_hash = ?', [$company['company_key_hash']]);
foreach ($dependencyInputs as $field => [$inputField, $contract]) {
    $caught = null;
    $input = [
        'expected_version' => '0',
        'subject' => 'Dependency blocked ' . $field,
        'issue_priority_key' => (string) $priority['issue_priority_key'],
        'issue_type_key' => (string) $issueType['issue_type_key'],
        $inputField => $field === 'assigned_admin_key' ? bx_uuid() : 'owner-reference',
    ];
    try {
        yovel_admin_save_support_issue($company, $admin, $input);
    } catch (Throwable $error) {
        $caught = $error;
    }
    support_service_assert($caught instanceof YovelAdminSupportUnavailableDependency, $field . ' did not fail with the dependency exception.');
    support_service_assert(str_contains($caught->getMessage(), 'UNAVAILABLE_DEPENDENCY') && str_contains($caught->getMessage(), $contract), $field . ' did not name its unavailable contract.');
    support_service_assert((string) ($caught->rehydration()['values']['subject'] ?? '') === $input['subject'], $field . ' did not preserve rehydration values.');
    support_service_assert((string) ($caught->rehydration()['values'][$inputField] ?? '') === (string) $input[$inputField], $field . ' lost the blocked reference during rehydration.');
}
support_service_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_support_issue WHERE company_key_hash = ?', [$company['company_key_hash']]) === $issueCountBeforeDependencies, 'A dependency-blocked Issue reached persistence.');

$dependencyContracts = array_column(yovel_admin_support_service_dependencies(), 'contract');
foreach (['operations.support-assignment.v1', 'operations.support-communication-split.v1', 'sales-crm.support-portal-owner.v1'] as $contract) {
    support_service_assert(in_array($contract, $dependencyContracts, true), 'Support dependency health omitted ' . $contract . '.');
}

$issue = yovel_admin_transition_support_issue($company, $admin, $issueKey, 'REPLIED', [
    'expected_version' => '2',
    'reason' => 'Agent acknowledged the local issue.',
]);
support_service_assert((string) $issue['issue_status'] === 'REPLIED' && (int) $issue['issue_version'] === 3, 'Issue REPLIED transition failed.');
$issue = yovel_admin_transition_support_issue($company, $admin, $issueKey, 'ON_HOLD', ['expected_version' => '3', 'reason' => 'Waiting for internal review.']);
support_service_assert((string) $issue['on_hold_since'] !== '', 'Issue ON_HOLD transition did not capture its timestamp.');
$issue = yovel_admin_transition_support_issue($company, $admin, $issueKey, 'OPEN', ['expected_version' => '4', 'reason' => 'Internal review completed.']);
support_service_assert((string) $issue['on_hold_since'] === '', 'Issue reopening did not clear the hold marker.');
$issue = yovel_admin_transition_support_issue($company, $admin, $issueKey, 'RESOLVED', ['expected_version' => '5', 'reason' => 'Local resolution completed.']);
support_service_assert((string) $issue['resolution_date'] !== '', 'Issue resolution timestamp is missing.');
$issue = yovel_admin_transition_support_issue($company, $admin, $issueKey, 'CLOSED', ['expected_version' => '6', 'reason' => 'Resolution accepted.']);
support_service_assert((string) $issue['issue_status'] === 'CLOSED', 'Issue close transition failed.');
$issue = yovel_admin_transition_support_issue($company, $admin, $issueKey, 'OPEN', ['expected_version' => '7', 'reason' => 'Issue reopened.']);
support_service_assert((string) $issue['resolution_date'] === '', 'Reopened Issue retained its resolution timestamp.');
$issue = yovel_admin_transition_support_issue($company, $admin, $issueKey, 'ARCHIVED', ['expected_version' => '8', 'reason' => 'Archived after review.']);
support_service_assert((string) $issue['archived_at'] !== '' && (int) $issue['issue_version'] === 9, 'Issue archive transition failed.');
support_service_expect_exception(
    static fn () => yovel_admin_save_support_issue($company, $admin, [
        'issue_key' => $issueKey,
        'expected_version' => '9',
        'subject' => 'Archived update',
    ]),
    RuntimeException::class,
    'archived'
);
$issue = yovel_admin_transition_support_issue($company, $admin, $issueKey, 'RESTORED', ['expected_version' => '9', 'reason' => 'Restored for follow-up.']);
support_service_assert((string) $issue['archived_at'] === '' && (int) $issue['issue_version'] === 10, 'Issue restore transition failed.');

$rollbackThrown = false;
try {
    yovel_admin_transition_support_issue($company, $admin, $issueKey, 'ON_HOLD', [
        'expected_version' => '10',
        'reason' => 'Rollback this transition.',
    ], static function (): void {
        throw new RuntimeException('Issue transition rollback injection.');
    });
} catch (RuntimeException $error) {
    $rollbackThrown = $error->getMessage() === 'Issue transition rollback injection.';
}
support_service_assert($rollbackThrown, 'Issue transition rollback injection did not run.');
$afterRollback = yovel_admin_support_issue($company, $admin, $issueKey);
support_service_assert((string) $afterRollback['issue_status'] === 'OPEN' && (int) $afterRollback['issue_version'] === 10, 'Issue transition survived rollback.');

support_service_expect_exception(
    static fn () => yovel_admin_transition_support_issue($foreignCompany, $foreignAdmin, $issueKey, 'CLOSED', ['expected_version' => '10', 'reason' => 'Cross-company transition.']),
    InvalidArgumentException::class,
    'another company'
);
support_service_expect_exception(
    static fn () => yovel_admin_transition_support_issue($company, $admin, $issueKey, 'INVALID', ['expected_version' => '10', 'reason' => 'Invalid.']),
    InvalidArgumentException::class,
    'status'
);

$events = yovel_admin_support_issue_events($company, $admin, $issueKey);
support_service_assert(count($events) === 9, 'Issue lifecycle event count is incomplete.');
support_service_assert((string) $events[0]['event_type'] === 'CREATED', 'Issue creation event is missing.');
support_service_assert(in_array('ARCHIVED', array_column($events, 'event_type'), true), 'Issue archive event is missing.');
support_service_assert(in_array('RESTORED', array_column($events, 'event_type'), true), 'Issue restore event is missing.');

$issueAuditCount = (int) $db->GetOne(
    "SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_support_issue' AND record_key = ?",
    [$issueKey]
);
support_service_assert($issueAuditCount === 10, 'Issue create/update/transitions were not each audited inside their transaction.');
support_service_assert(count(yovel_admin_support_issues($company, $admin)) === 1, 'Support Issue list is not company scoped.');
support_service_assert(count(yovel_admin_support_issues($foreignCompany, $foreignAdmin)) === 0, 'Support Issue list leaked into another company.');

foreach ([
    'yovel_admin_support_issue_record_communication' => static fn () => yovel_admin_support_issue_record_communication($company, $admin, $issueKey, ['body' => 'Blocked communication']),
    'yovel_admin_support_issue_split' => static fn () => yovel_admin_support_issue_split($company, $admin, $issueKey, ['communication_keys' => ['external']]),
    'yovel_admin_support_portal_issues' => static fn () => yovel_admin_support_portal_issues($company, ['portal_owner_key' => 'external'], []),
    'yovel_admin_support_portal_issue' => static fn () => yovel_admin_support_portal_issue($company, ['portal_owner_key' => 'external'], $issueKey),
] as $interface => $call) {
    support_service_assert(function_exists($interface), $interface . ' public handoff interface is missing.');
    support_service_expect_exception($call, YovelAdminSupportUnavailableDependency::class, 'UNAVAILABLE_DEPENDENCY');
}

$csrf = bx_csrf_token();
$handlerSaved = yovel_admin_support_service_handle_post($company, $admin, 'support_save_issue_priority', [
    'csrf' => $csrf,
    'section' => 'issues-tickets',
    'expected_version' => '0',
    'priority_name' => 'Handler Priority',
    'priority_description' => 'Saved through exact four-argument handler.',
    'priority_status' => 'ACTIVE',
]);
support_service_assert(($handlerSaved['section'] ?? '') === 'issues-tickets', 'Issue action handler returned the wrong section.');
support_service_assert(($handlerSaved['rehydration']['form_id'] ?? '') === 'support-issue-priority', 'Issue action handler omitted committed rehydration metadata.');
support_service_expect_exception(
    static fn () => yovel_admin_support_service_handle_post($company, $admin, 'support_save_issue', [
        'csrf' => $csrf,
        'section' => 'issues-tickets',
        'expected_version' => '0',
        'subject' => 'Rehydrated blocked issue',
        'customer_key' => 'external-customer',
    ]),
    YovelAdminSupportUnavailableDependency::class,
    'UNAVAILABLE_DEPENDENCY'
);
support_service_expect_exception(
    static fn () => yovel_admin_support_service_handle_post($company, $admin, 'support_save_issue', [
        'csrf' => 'invalid',
        'expected_version' => '0',
        'subject' => 'Invalid token',
    ]),
    InvalidArgumentException::class,
    'token'
);

$previousGet = $_GET;
$_GET = ['section' => 'issues-tickets', 'issue' => $issueKey];
$moduleData = yovel_admin_support_service_data($company, $admin);
$_GET = $previousGet;
$activeModuleData = $moduleData;
$activeModuleSection = 'issues-tickets';
$activeModuleSections = yovel_admin_support_service_sections();
$activeModuleFormState = [
    'section' => 'issues-tickets',
    'action' => 'support_save_issue',
    'error' => 'UNAVAILABLE_DEPENDENCY: sales-crm.customer-reference.v1 is unavailable.',
    'input' => [
        'expected_version' => '0',
        'subject' => 'Rehydrated blocked issue',
        'description' => 'Keep this text after server rejection.',
        'customer_key' => 'external-customer',
    ],
];
$companyName = (string) $company['company_name'];
ob_start();
require dirname(__DIR__) . '/company/admin/modules/support-service/views/workspace.php';
require dirname(__DIR__) . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();
foreach (['support-issue-modal', 'support-issue-priority-modal', 'support-issue-type-modal', 'support-issue-transition-modal'] as $modalId) {
    support_service_assert(str_contains($markup, 'id="' . $modalId . '"'), $modalId . ' is missing.');
}
support_service_assert(substr_count($markup, 'data-record-modal-form data-confirm-submit') >= 4, 'Issue forms do not use record modals with sibling confirmation.');
support_service_assert(str_contains($markup, 'data-confirm-dialog'), 'Shared sibling confirmation dialog is missing from the rendered page.');
support_service_assert(str_contains($markup, 'data-record-modal-open-on-load'), 'Dependency failure did not reopen the originating modal.');
support_service_assert(str_contains($markup, 'value="Rehydrated blocked issue"'), 'Dependency failure lost the Issue subject.');
support_service_assert(str_contains($markup, '>Keep this text after server rejection.</textarea>'), 'Dependency failure lost the Issue description.');
support_service_assert(str_contains($markup, 'value="external-customer"'), 'Dependency failure lost the blocked owner reference.');
support_service_assert(str_contains($markup, 'UNAVAILABLE_DEPENDENCY'), 'Dependency failure is not visible in the Issue modal.');

echo "Support Issue checks passed: masters, blank-reference create/update, status/archive events, fail-closed owner handoffs, transactions, rollback, audit, isolation, handler rehydration, and modal confirmation.\n";
