<?php
declare(strict_types=1);

$GLOBALS['support_sla_sales_provider'] = null;
$GLOBALS['support_sla_holiday_provider'] = null;

if (!function_exists('yovel_admin_sales_crm_customer_segmentation')) {
    function yovel_admin_sales_crm_customer_segmentation(array $company, array $request): array
    {
        $provider = $GLOBALS['support_sla_sales_provider'] ?? null;
        if (!is_callable($provider)) {
            throw new RuntimeException('Sales customer segmentation is unavailable.');
        }

        return $provider($company, $request);
    }
}

if (!function_exists('yovel_admin_operations_calendar_holiday_dates')) {
    function yovel_admin_operations_calendar_holiday_dates(array $company, array $request): array
    {
        $provider = $GLOBALS['support_sla_holiday_provider'] ?? null;
        if (!is_callable($provider)) {
            throw new RuntimeException('Operations holiday dates are unavailable.');
        }

        return $provider($company, $request);
    }
}

require_once __DIR__ . '/support-service-test-helper.php';

support_service_assert(function_exists('yovel_admin_support_sla_expected_at'), 'Support SLA expected-at engine is missing.');
support_service_assert(function_exists('yovel_admin_save_support_sla'), 'Support SLA aggregate save service is missing.');
support_service_assert(function_exists('yovel_admin_support_sla_resolve'), 'Support SLA resolver is missing.');
support_service_assert(function_exists('yovel_admin_support_sla_on_issue_created'), 'Support SLA Issue-created callback is missing.');
support_service_assert(function_exists('yovel_admin_support_sla_on_communication'), 'Support SLA communication callback is missing.');
support_service_assert(function_exists('yovel_admin_support_sla_on_status_change'), 'Support SLA status callback is missing.');
support_service_assert(function_exists('yovel_admin_reset_support_sla'), 'Support SLA reset service is missing.');
$slaFormAdapter = yovel_admin_support_service_form_adapter();
$slaProtectedFields = $slaFormAdapter['protected_fields']['service-level-agreement'] ?? [];
foreach (['sla_code', 'service_level_name', 'document_type', 'timezone_name', 'enabled', 'apply_for_resolution', 'sla_status', 'company_key_hash'] as $protectedField) {
    support_service_assert(in_array($protectedField, $slaProtectedFields, true), 'Support SLA Form Builder did not protect ' . $protectedField . '.');
}

$db = bx_db();
$primary = support_service_create_company($db, 'SLA Primary');
$foreign = support_service_create_company($db, 'SLA Foreign');
register_shutdown_function(static function () use ($db, $primary, $foreign): void {
    support_service_cleanup_company($db, $primary);
    support_service_cleanup_company($db, $foreign);
});

$company = $primary['company'];
$admin = $primary['admin'];
$foreignCompany = $foreign['company'];
$foreignAdmin = $foreign['admin'];
$companyHash = (string) $company['company_key_hash'];
yovel_admin_support_service_schema();

foreach ([
    ['project_company_support_sla', 'timezone_name'],
    ['project_company_support_sla', 'form_schema_checksum'],
    ['project_company_support_issue', 'sla_resolution_remaining_seconds'],
] as [$table, $column]) {
    support_service_assert(
        (int) $db->GetOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [BUILDERX_DB_NAME, $table, $column]
        ) === 1,
        $table . ' is missing ' . $column . '.'
    );
}

$high = yovel_admin_save_support_issue_priority($company, $admin, [
    'expected_version' => '0',
    'priority_name' => 'SLA High',
    'priority_status' => 'ACTIVE',
]);
$normal = yovel_admin_save_support_issue_priority($company, $admin, [
    'expected_version' => '0',
    'priority_name' => 'SLA Normal',
    'priority_status' => 'ACTIVE',
]);
$foreignPriority = yovel_admin_save_support_issue_priority($foreignCompany, $foreignAdmin, [
    'expected_version' => '0',
    'priority_name' => 'Foreign SLA Priority',
    'priority_status' => 'ACTIVE',
]);

$settingsInput = [
    'expected_version' => '0',
    'close_issue_after_days' => '7',
    'portal_enabled' => '1',
    'track_service_level_agreement' => '1',
    'allow_resetting_service_level_agreement' => '1',
    'greeting_title' => 'SLA support',
    'greeting_subtitle' => 'Fixed-clock SLA verification.',
];
yovel_admin_save_support_settings($company, $admin, $settingsInput);

$serviceDays = [];
foreach (range(1, 5) as $weekday) {
    $serviceDays[] = [
        'weekday' => $weekday,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'sort_order' => $weekday * 10,
    ];
}
$priorityRules = [
    [
        'issue_priority_key' => (string) $high['issue_priority_key'],
        'response_seconds' => 1800,
        'resolution_seconds' => 7200,
        'is_default' => 0,
    ],
    [
        'issue_priority_key' => (string) $normal['issue_priority_key'],
        'response_seconds' => 3600,
        'resolution_seconds' => 14400,
        'is_default' => 1,
    ],
];
$baseInput = [
    'expected_version' => '0',
    'sla_code' => 'STANDARD',
    'service_level_name' => 'Standard Support',
    'document_type' => 'ISSUE',
    'entity_type' => 'NONE',
    'entity_key' => '',
    'entity_name_snapshot' => '',
    'start_date' => '2026-01-01',
    'end_date' => '2026-12-31',
    'timezone_name' => 'UTC',
    'calendar_key' => '',
    'is_default' => 1,
    'enabled' => 1,
    'apply_for_resolution' => 1,
    'condition_json' => '',
    'sla_status' => 'ACTIVE',
    'service_days' => $serviceDays,
    'priorities' => $priorityRules,
    'pause_statuses' => ['ON_HOLD'],
    'fulfilled_statuses' => ['RESOLVED', 'CLOSED'],
];

$invalidInputs = [
    'duplicate service day' => ['service_days' => array_merge($serviceDays, [$serviceDays[0]]), 'message' => 'weekday'],
    'invalid service window' => ['service_days' => array_merge(array_slice($serviceDays, 0, 4), [[
        'weekday' => 5, 'start_time' => '17:00:00', 'end_time' => '09:00:00', 'sort_order' => 50,
    ]]), 'message' => 'start must be before'],
    'duplicate priority' => ['priorities' => array_merge($priorityRules, [$priorityRules[0]]), 'message' => 'duplicate'],
    'missing default priority' => ['priorities' => array_map(static fn (array $row): array => array_replace($row, ['is_default' => 0]), $priorityRules), 'message' => 'exactly one default'],
    'response after resolution' => ['priorities' => [[
        'issue_priority_key' => (string) $normal['issue_priority_key'],
        'response_seconds' => 7200, 'resolution_seconds' => 3600, 'is_default' => 1,
    ]], 'message' => 'resolution'],
    'invalid dates' => ['start_date' => '2026-12-31', 'end_date' => '2026-01-01', 'message' => 'date'],
    'invalid condition field' => ['condition_json' => json_encode(['all' => [[
        'field' => 'customer_password', 'operator' => 'EQUALS', 'value' => 'unsafe',
    ]]], JSON_THROW_ON_ERROR), 'message' => 'condition'],
    'foreign priority' => ['priorities' => [[
        'issue_priority_key' => (string) $foreignPriority['issue_priority_key'],
        'response_seconds' => 3600, 'resolution_seconds' => 7200, 'is_default' => 1,
    ]], 'message' => 'another company'],
];
foreach ($invalidInputs as $label => $change) {
    $message = (string) $change['message'];
    unset($change['message']);
    support_service_expect_exception(
        static fn () => yovel_admin_save_support_sla($company, $admin, array_replace($baseInput, $change)),
        Throwable::class,
        $message
    );
}
support_service_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_support_sla WHERE company_key_hash = ?', [$companyHash]) === 0, 'Invalid SLA input reached persistence.');

$defaultSla = yovel_admin_save_support_sla($company, $admin, $baseInput);
$slaKey = (string) $defaultSla['service_level_agreement_key'];
support_service_assert(yovel_admin_is_uuid($slaKey), 'SLA did not receive a stable key.');
support_service_assert((int) $defaultSla['sla_version'] === 1, 'SLA create version is invalid.');
support_service_assert(count($defaultSla['service_days']) === 5 && count($defaultSla['priorities']) === 2, 'SLA aggregate read-back lost child rows.');
support_service_assert(array_column($defaultSla['service_days'], 'weekday') === [1, 2, 3, 4, 5], 'SLA service days are not deterministically ordered.');
support_service_assert(array_column($defaultSla['pause_statuses'], 'issue_status') === ['ON_HOLD'], 'SLA pause statuses were not read back.');
support_service_assert(array_column($defaultSla['fulfilled_statuses'], 'issue_status') === ['CLOSED', 'RESOLVED'], 'SLA fulfilled statuses were not read back deterministically.');
support_service_assert(preg_match('/^[0-9a-f]{64}$/D', (string) $defaultSla['form_schema_checksum']) === 1, 'SLA is not bound to the Form Builder schema.');

support_service_expect_exception(
    static fn () => yovel_admin_save_support_sla($company, $admin, array_replace($baseInput, [
        'sla_code' => 'SECOND-DEFAULT',
        'service_level_name' => 'Second Default',
    ])),
    RuntimeException::class,
    'default'
);

$updated = yovel_admin_save_support_sla($company, $admin, array_replace($baseInput, [
    'service_level_agreement_key' => $slaKey,
    'expected_version' => '1',
    'service_level_name' => 'Standard Support Updated',
]));
support_service_assert((string) $updated['service_level_agreement_key'] === $slaKey && (int) $updated['sla_version'] === 2, 'SLA update changed stable identity or version incorrectly.');

$rollbackThrown = false;
try {
    yovel_admin_save_support_sla($company, $admin, array_replace($baseInput, [
        'service_level_agreement_key' => $slaKey,
        'expected_version' => '2',
        'service_level_name' => 'Rolled back SLA',
    ]), static function (): void {
        throw new RuntimeException('SLA rollback injection.');
    });
} catch (RuntimeException $error) {
    $rollbackThrown = $error->getMessage() === 'SLA rollback injection.';
}
support_service_assert($rollbackThrown, 'SLA rollback injection did not execute.');
support_service_assert((string) yovel_admin_support_sla($company, $admin, $slaKey)['service_level_name'] === 'Standard Support Updated', 'SLA aggregate survived rollback.');

$mondayBefore = new DateTimeImmutable('2026-08-24 08:00:00', new DateTimeZone('UTC'));
$mondayDuring = new DateTimeImmutable('2026-08-24 10:00:00', new DateTimeZone('UTC'));
$mondayAfter = new DateTimeImmutable('2026-08-24 18:00:00', new DateTimeZone('UTC'));
$fridayLate = new DateTimeImmutable('2026-08-28 16:00:00', new DateTimeZone('UTC'));
support_service_assert(yovel_admin_support_sla_expected_at($updated, $mondayBefore, 3600)->format('Y-m-d H:i:s') === '2026-08-24 10:00:00', 'SLA before-hours calculation is incorrect.');
support_service_assert(yovel_admin_support_sla_expected_at($updated, $mondayDuring, 7200)->format('Y-m-d H:i:s') === '2026-08-24 12:00:00', 'SLA during-hours calculation is incorrect.');
support_service_assert(yovel_admin_support_sla_expected_at($updated, $mondayAfter, 3600)->format('Y-m-d H:i:s') === '2026-08-25 10:00:00', 'SLA after-hours calculation is incorrect.');
support_service_assert(yovel_admin_support_sla_expected_at($updated, $mondayAfter, 36000)->format('Y-m-d H:i:s') === '2026-08-26 11:00:00', 'SLA multi-day calculation is incorrect.');
support_service_assert(yovel_admin_support_sla_expected_at($updated, $fridayLate, 7200, ['2026-08-31'])->format('Y-m-d H:i:s') === '2026-09-01 10:00:00', 'SLA holiday/weekend calculation is incorrect.');

$GLOBALS['support_sla_sales_provider'] = static function (array $scope, array $request) use ($companyHash): array {
    $entityType = strtoupper((string) ($request['entity_type'] ?? ''));
    $entityKey = (string) ($request['entity_key'] ?? '');
    if ($entityType !== '' && $entityKey !== '') {
        return [
            'contract' => 'sales-crm.customer-segmentation.v1',
            'company_key_hash' => $companyHash,
            'entity_type' => $entityType,
            'entity_key' => $entityKey,
            'valid' => true,
        ];
    }

    $customerKey = (string) ($request['customer_key'] ?? '');
    $segments = $GLOBALS['support_sla_segments'][$customerKey] ?? [];
    return [
        'contract' => 'sales-crm.customer-segmentation.v1',
        'company_key_hash' => $companyHash,
        'customer_key' => $customerKey,
        'customer_group_key' => (string) ($segments['customer_group_key'] ?? ''),
        'territory_key' => (string) ($segments['territory_key'] ?? ''),
    ];
};
$GLOBALS['support_sla_segments'] = [
    'CUSTOMER-A' => ['customer_group_key' => 'GROUP-A', 'territory_key' => 'TERRITORY-A'],
    'CUSTOMER-B' => ['customer_group_key' => 'GROUP-A', 'territory_key' => 'TERRITORY-A'],
    'CUSTOMER-C' => ['customer_group_key' => 'GROUP-B', 'territory_key' => 'TERRITORY-A'],
];

$saveSpecialized = static function (string $code, string $entityType, string $entityKey, string $condition = '') use (
    $company,
    $admin,
    $baseInput
): array {
    return yovel_admin_save_support_sla($company, $admin, array_replace($baseInput, [
        'sla_code' => $code,
        'service_level_name' => $code,
        'is_default' => 0,
        'entity_type' => $entityType,
        'entity_key' => $entityKey,
        'entity_name_snapshot' => $entityKey,
        'condition_json' => $condition,
    ]));
};
$conditionSla = $saveSpecialized('VIP-CONDITION', 'NONE', '', json_encode(['all' => [[
    'field' => 'subject', 'operator' => 'CONTAINS', 'value' => 'VIP',
]]], JSON_THROW_ON_ERROR));
$customerSla = $saveSpecialized('CUSTOMER-SLA', 'CUSTOMER', 'CUSTOMER-A');
$groupSla = $saveSpecialized('GROUP-SLA', 'CUSTOMER_GROUP', 'GROUP-A');
$territorySla = $saveSpecialized('TERRITORY-SLA', 'TERRITORY', 'TERRITORY-A');

$opened = new DateTimeImmutable('2026-08-24 09:00:00', new DateTimeZone('UTC'));
$issueFor = static fn (string $subject, string $customerKey = ''): array => [
    'subject' => $subject,
    'description' => '',
    'issue_status' => 'OPEN',
    'issue_priority_key' => '',
    'issue_type_key' => '',
    'customer_key' => $customerKey,
    'opened_at' => '2026-08-24 09:00:00',
];
support_service_assert((string) yovel_admin_support_sla_resolve($company, $admin, $issueFor('VIP local request'), $opened)['service_level_agreement_key'] === (string) $conditionSla['service_level_agreement_key'], 'Condition SLA did not outrank the default.');
support_service_assert((string) yovel_admin_support_sla_resolve($company, $admin, $issueFor('Customer request', 'CUSTOMER-A'), $opened)['service_level_agreement_key'] === (string) $customerSla['service_level_agreement_key'], 'Customer SLA was not selected first.');
support_service_assert((string) yovel_admin_support_sla_resolve($company, $admin, $issueFor('Group request', 'CUSTOMER-B'), $opened)['service_level_agreement_key'] === (string) $groupSla['service_level_agreement_key'], 'Customer Group SLA was not selected before Territory.');
support_service_assert((string) yovel_admin_support_sla_resolve($company, $admin, $issueFor('Territory request', 'CUSTOMER-C'), $opened)['service_level_agreement_key'] === (string) $territorySla['service_level_agreement_key'], 'Territory SLA was not selected.');

$GLOBALS['support_sla_sales_provider'] = null;
$fallback = yovel_admin_support_sla_resolve($company, $admin, $issueFor('Owner service unavailable', 'CUSTOMER-A'), $opened);
support_service_assert((string) $fallback['service_level_agreement_key'] === $slaKey, 'Default SLA stopped functioning when Sales segmentation was unavailable.');
support_service_assert(($fallback['selection_dependencies']['sales-crm.customer-segmentation.v1'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Specialized SLA unavailability was not explicit.');

$GLOBALS['support_sla_holiday_provider'] = static fn (array $scope, array $request): array => [
    'contract' => 'operations.calendar-holiday-dates.v1',
    'company_key_hash' => (string) $scope['company_key_hash'],
    'holiday_dates' => ['2026-08-31'],
];
$holidays = yovel_admin_support_sla_holiday_dates($company, $updated);
support_service_assert($holidays['dates'] === ['2026-08-31'] && $holidays['status'] === 'AVAILABLE', 'Operations holiday dates were not consumed through the owner service.');
$GLOBALS['support_sla_holiday_provider'] = null;
$holidaysUnavailable = yovel_admin_support_sla_holiday_dates($company, $updated);
support_service_assert($holidaysUnavailable['dates'] === [] && $holidaysUnavailable['status'] === 'UNAVAILABLE_DEPENDENCY', 'Missing Operations holidays did not preserve default SLA behavior.');

yovel_admin_save_support_settings($company, $admin, array_replace($settingsInput, [
    'expected_version' => '1',
    'track_service_level_agreement' => '0',
]));
$callbackIssue = yovel_admin_save_support_issue($company, $admin, [
    'expected_version' => '0',
    'subject' => 'Standalone created callback',
    'issue_priority_key' => (string) $high['issue_priority_key'],
], null, $opened);
support_service_assert((string) $callbackIssue['service_level_agreement_key'] === '', 'Disabled tracking unexpectedly applied an SLA.');
yovel_admin_save_support_settings($company, $admin, array_replace($settingsInput, [
    'expected_version' => '2',
]));
$callbackIssue = yovel_admin_support_sla_on_issue_created($company, $admin, (string) $callbackIssue['issue_key'], $opened);
support_service_assert((int) $callbackIssue['issue_version'] === 2 && (string) $callbackIssue['service_level_agreement_key'] === $slaKey, 'Standalone Issue-created callback did not version and apply the SLA.');
support_service_assert((int) $db->GetOne(
    "SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_support_issue' AND record_key = ? AND action = 'SLA_APPLY'",
    [(string) $callbackIssue['issue_key']]
) === 1, 'Standalone Issue-created callback did not audit exactly once.');

$issue = yovel_admin_save_support_issue($company, $admin, [
    'expected_version' => '0',
    'subject' => 'Fixed-clock lifecycle',
    'description' => 'Support-owned SLA lifecycle.',
    'issue_priority_key' => (string) $high['issue_priority_key'],
    'issue_type_key' => '',
], null, $opened);
support_service_assert((string) $issue['service_level_agreement_key'] === $slaKey, 'Issue-created callback did not select the default SLA.');
support_service_assert((string) $issue['response_by'] === '2026-08-24 09:30:00', 'Issue-created response deadline is incorrect.');
support_service_assert((string) $issue['resolution_by'] === '2026-08-24 11:00:00', 'Issue-created resolution deadline is incorrect.');
support_service_assert((string) $issue['agreement_status'] === 'FIRST_RESPONSE_DUE', 'Issue-created SLA state is incorrect.');
$createdReplay = yovel_admin_support_sla_on_issue_created($company, $admin, (string) $issue['issue_key'], $opened);
support_service_assert((int) $createdReplay['issue_version'] === (int) $issue['issue_version'], 'Repeated Issue-created callback was not idempotent.');

$issue = yovel_admin_support_sla_on_communication($company, $admin, (string) $issue['issue_key'], [
    'direction' => 'SENT',
    'actor_type' => 'ADMIN',
    'communication_key' => 'operations-communication-1',
], new DateTimeImmutable('2026-08-24 09:20:00', new DateTimeZone('UTC')));
support_service_assert((string) $issue['first_responded_on'] === '2026-08-24 09:20:00' && (int) $issue['first_response_seconds'] === 1200, 'First-response callback did not capture deterministic metrics.');
support_service_assert((string) $issue['agreement_status'] === 'RESOLUTION_DUE', 'First response did not advance the SLA state.');
$responseReplay = yovel_admin_support_sla_on_communication($company, $admin, (string) $issue['issue_key'], [
    'direction' => 'SENT',
    'actor_type' => 'ADMIN',
    'communication_key' => 'operations-communication-1',
], new DateTimeImmutable('2026-08-24 09:21:00', new DateTimeZone('UTC')));
support_service_assert((int) $responseReplay['issue_version'] === (int) $issue['issue_version'], 'Repeated first-response callback was not idempotent.');

$issue = yovel_admin_transition_support_issue($company, $admin, (string) $issue['issue_key'], 'ON_HOLD', [
    'expected_version' => (string) $issue['issue_version'],
    'reason' => 'Pause the resolution clock.',
], null, new DateTimeImmutable('2026-08-24 10:00:00', new DateTimeZone('UTC')));
support_service_assert((string) $issue['on_hold_since'] === '2026-08-24 10:00:00', 'SLA hold callback did not capture the fixed clock.');
$issue = yovel_admin_transition_support_issue($company, $admin, (string) $issue['issue_key'], 'OPEN', [
    'expected_version' => (string) $issue['issue_version'],
    'reason' => 'Resume the resolution clock.',
], null, new DateTimeImmutable('2026-08-25 10:00:00', new DateTimeZone('UTC')));
support_service_assert((int) $issue['total_hold_seconds'] === 86400, 'SLA resume did not accumulate hold duration.');
support_service_assert((string) $issue['resolution_by'] === '2026-08-25 11:00:00', 'SLA resume did not extend the resolution deadline.');

$issue = yovel_admin_transition_support_issue($company, $admin, (string) $issue['issue_key'], 'RESOLVED', [
    'expected_version' => (string) $issue['issue_version'],
    'reason' => 'Resolved with remaining SLA time.',
], null, new DateTimeImmutable('2026-08-25 10:30:00', new DateTimeZone('UTC')));
support_service_assert((string) $issue['agreement_status'] === 'FULFILLED', 'Fulfilled status did not complete the SLA.');
support_service_assert((int) $issue['sla_resolution_remaining_seconds'] === 1800, 'Resolution callback did not retain the remaining business time.');

$issue = yovel_admin_transition_support_issue($company, $admin, (string) $issue['issue_key'], 'OPEN', [
    'expected_version' => (string) $issue['issue_version'],
    'reason' => 'Reopen with the remaining clock.',
], null, new DateTimeImmutable('2026-08-26 09:00:00', new DateTimeZone('UTC')));
support_service_assert((string) $issue['resolution_date'] === '' && (string) $issue['agreement_status'] === 'RESOLUTION_DUE', 'Reopening did not clear resolution metrics and resume SLA state.');
support_service_assert((string) $issue['resolution_by'] === '2026-08-26 09:30:00', 'Reopening did not resume the retained business time.');

$resetRollback = false;
try {
    yovel_admin_reset_support_sla($company, $admin, (string) $issue['issue_key'], [
        'expected_version' => (string) $issue['issue_version'],
        'reason' => 'Rollback this reset.',
    ], new DateTimeImmutable('2026-08-26 09:00:00', new DateTimeZone('UTC')), static function (): void {
        throw new RuntimeException('SLA reset rollback injection.');
    });
} catch (RuntimeException $error) {
    $resetRollback = $error->getMessage() === 'SLA reset rollback injection.';
}
support_service_assert($resetRollback, 'SLA reset rollback injection did not execute.');
$afterResetRollback = yovel_admin_support_issue($company, $admin, (string) $issue['issue_key']);
support_service_assert((int) $afterResetRollback['issue_version'] === (int) $issue['issue_version'], 'SLA reset survived rollback.');

$issue = yovel_admin_reset_support_sla($company, $admin, (string) $issue['issue_key'], [
    'expected_version' => (string) $issue['issue_version'],
    'reason' => 'Authorized SLA reset.',
], new DateTimeImmutable('2026-08-26 09:00:00', new DateTimeZone('UTC')));
support_service_assert((string) $issue['agreement_status'] === 'FIRST_RESPONSE_DUE', 'SLA reset did not restart first response tracking.');
support_service_assert((string) $issue['first_responded_on'] === '' && (string) $issue['resolution_date'] === '', 'SLA reset retained completed metrics.');
support_service_assert((string) $issue['response_by'] === '2026-08-26 09:30:00' && (string) $issue['resolution_by'] === '2026-08-26 11:00:00', 'SLA reset deadlines are incorrect.');

$lateIssue = yovel_admin_save_support_issue($company, $admin, [
    'expected_version' => '0',
    'subject' => 'Late first response',
    'issue_priority_key' => (string) $high['issue_priority_key'],
], null, $opened);
$communicationRollback = false;
try {
    yovel_admin_support_sla_on_communication($company, $admin, (string) $lateIssue['issue_key'], [
        'direction' => 'SENT',
        'actor_type' => 'ADMIN',
        'communication_key' => 'operations-communication-rollback',
    ], new DateTimeImmutable('2026-08-24 09:20:00', new DateTimeZone('UTC')), static function (): void {
        throw new RuntimeException('SLA communication rollback injection.');
    });
} catch (RuntimeException $error) {
    $communicationRollback = $error->getMessage() === 'SLA communication rollback injection.';
}
support_service_assert($communicationRollback, 'SLA communication rollback injection did not execute.');
$afterCommunicationRollback = yovel_admin_support_issue($company, $admin, (string) $lateIssue['issue_key']);
support_service_assert((int) $afterCommunicationRollback['issue_version'] === 1 && (string) $afterCommunicationRollback['first_responded_on'] === '', 'First response survived rollback.');
$lateIssue = yovel_admin_support_sla_on_communication($company, $admin, (string) $lateIssue['issue_key'], [
    'direction' => 'SENT',
    'actor_type' => 'ADMIN',
    'communication_key' => 'operations-communication-late',
], new DateTimeImmutable('2026-08-24 10:00:00', new DateTimeZone('UTC')));
support_service_assert((string) $lateIssue['agreement_status'] === 'FAILED', 'Late first response did not fail the SLA.');

$statusRollback = false;
try {
    yovel_admin_transition_support_issue($company, $admin, (string) $lateIssue['issue_key'], 'ON_HOLD', [
        'expected_version' => (string) $lateIssue['issue_version'],
        'reason' => 'Rollback SLA status callback.',
    ], static function (): void {
        throw new RuntimeException('SLA status rollback injection.');
    }, new DateTimeImmutable('2026-08-24 10:10:00', new DateTimeZone('UTC')));
} catch (RuntimeException $error) {
    $statusRollback = $error->getMessage() === 'SLA status rollback injection.';
}
support_service_assert($statusRollback, 'SLA status rollback injection did not execute.');
$afterStatusRollback = yovel_admin_support_issue($company, $admin, (string) $lateIssue['issue_key']);
support_service_assert((string) $afterStatusRollback['issue_status'] === 'OPEN' && (string) $afterStatusRollback['on_hold_since'] === '', 'SLA status transition survived rollback.');

support_service_expect_exception(
    static fn () => yovel_admin_support_sla_on_communication($foreignCompany, $foreignAdmin, (string) $lateIssue['issue_key'], [
        'direction' => 'SENT',
        'actor_type' => 'ADMIN',
        'communication_key' => 'cross-company-communication',
    ], new DateTimeImmutable('2026-08-24 10:15:00', new DateTimeZone('UTC'))),
    InvalidArgumentException::class,
    'another company'
);

support_service_expect_exception(
    static fn () => yovel_admin_reset_support_sla($foreignCompany, $foreignAdmin, (string) $issue['issue_key'], [
        'expected_version' => (string) $issue['issue_version'],
        'reason' => 'Cross-company reset.',
    ], new DateTimeImmutable('2026-08-26 09:00:00', new DateTimeZone('UTC'))),
    InvalidArgumentException::class,
    'another company'
);

$slaAuditCount = (int) $db->GetOne(
    "SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_support_sla' AND record_key = ?",
    [$slaKey]
);
support_service_assert($slaAuditCount === 2, 'SLA create/update aggregates were not audited exactly once per committed transaction.');
support_service_assert(count(yovel_admin_support_slas($company, $admin)) === 5, 'SLA list did not return the company aggregate set before handler dispatch.');
support_service_assert(count(yovel_admin_support_slas($foreignCompany, $foreignAdmin)) === 0, 'SLA list leaked into another company.');

$csrf = bx_csrf_token();
$handlerInput = array_replace($baseInput, [
    'csrf' => $csrf,
    'section' => 'sla-rules',
    'sla_code' => 'HANDLER-SLA',
    'service_level_name' => 'Handler SLA',
    'is_default' => 0,
    'service_days_json' => json_encode($serviceDays, JSON_THROW_ON_ERROR),
    'priorities_json' => json_encode($priorityRules, JSON_THROW_ON_ERROR),
    'pause_statuses_csv' => 'ON_HOLD',
    'fulfilled_statuses_csv' => 'RESOLVED,CLOSED',
]);
unset($handlerInput['service_days'], $handlerInput['priorities'], $handlerInput['pause_statuses'], $handlerInput['fulfilled_statuses']);
$handlerResult = yovel_admin_support_service_handle_post($company, $admin, 'support_save_sla', $handlerInput);
support_service_assert(($handlerResult['section'] ?? '') === 'sla-rules', 'SLA action handler returned the wrong section.');
support_service_assert(($handlerResult['rehydration']['form_id'] ?? '') === 'support-sla', 'SLA action handler omitted committed rehydration metadata.');
support_service_assert(count(yovel_admin_support_slas($company, $admin)) === 6, 'SLA handler dispatch did not persist the aggregate.');

$previousGet = $_GET;
$_GET = ['section' => 'sla-rules', 'sla' => $slaKey];
$moduleData = yovel_admin_support_service_data($company, $admin);
$_GET = $previousGet;
$activeModuleData = $moduleData;
$activeModuleSection = 'sla-rules';
$activeModuleSections = yovel_admin_support_service_sections();
$activeModuleFormState = [
    'section' => 'sla-rules',
    'action' => 'support_save_sla',
    'error' => 'Service day start must be before end.',
    'input' => [
        'expected_version' => '0',
        'sla_code' => 'REHYDRATED-SLA',
        'service_level_name' => 'Rehydrated SLA',
        'service_days_json' => '[{"weekday":1,"start_time":"17:00:00","end_time":"09:00:00"}]',
        'priorities_json' => json_encode($priorityRules, JSON_THROW_ON_ERROR),
    ],
];
$companyName = (string) $company['company_name'];
ob_start();
require dirname(__DIR__) . '/company/admin/modules/support-service/views/workspace.php';
require dirname(__DIR__) . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();
foreach (['support-sla-modal', 'support-sla-reset-modal'] as $modalId) {
    support_service_assert(str_contains($markup, 'id="' . $modalId . '"'), $modalId . ' is missing.');
}
support_service_assert(substr_count($markup, 'data-record-modal-form data-confirm-submit') >= 2, 'SLA forms do not use record modals with sibling confirmation.');
support_service_assert(str_contains($markup, 'data-record-modal-open-on-load'), 'SLA failure did not reopen its modal.');
support_service_assert(str_contains($markup, 'value="REHYDRATED-SLA"'), 'SLA failure lost the submitted code.');
support_service_assert(str_contains($markup, 'Service day start must be before end.'), 'SLA failure is not visible in the modal.');

echo "Support SLA checks passed: aggregate validation, fixed-clock business time, default/specialized selection, owner fallbacks, Issue callbacks, hold/resume, first response, resolution, reset, rollback, audit, isolation, rehydration, and modal confirmation.\n";
