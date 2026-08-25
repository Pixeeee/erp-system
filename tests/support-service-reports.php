<?php
declare(strict_types=1);

require_once __DIR__ . '/support-service-test-helper.php';

foreach ([
    'yovel_admin_support_first_response_report',
    'yovel_admin_support_issue_analytics',
    'yovel_admin_support_issue_summary',
    'yovel_admin_support_hour_distribution',
] as $reportFunction) {
    support_service_assert(function_exists($reportFunction), 'Support report service is missing: ' . $reportFunction . '.');
}

$db = bx_db();
$primary = support_service_create_company($db, 'Reports Primary');
$foreign = support_service_create_company($db, 'Reports Foreign');
register_shutdown_function(static function () use ($db, $primary, $foreign): void {
    support_service_cleanup_company($db, $primary);
    support_service_cleanup_company($db, $foreign);
});

$company = $primary['company'];
$admin = $primary['admin'];
$foreignCompany = $foreign['company'];
$foreignAdmin = $foreign['admin'];
yovel_admin_support_service_schema();

$high = yovel_admin_save_support_issue_priority($company, $admin, [
    'expected_version' => '0', 'priority_name' => 'Report High', 'priority_status' => 'ACTIVE',
]);
$normal = yovel_admin_save_support_issue_priority($company, $admin, [
    'expected_version' => '0', 'priority_name' => 'Report Normal', 'priority_status' => 'ACTIVE',
]);
$bug = yovel_admin_save_support_issue_type($company, $admin, [
    'expected_version' => '0', 'issue_type_name' => 'Report Bug', 'issue_type_status' => 'ACTIVE',
]);
$question = yovel_admin_save_support_issue_type($company, $admin, [
    'expected_version' => '0', 'issue_type_name' => 'Report Question', 'issue_type_status' => 'ACTIVE',
]);

$insertIssue = static function (array $fixture, array $values) use ($db): string {
    $companyRow = $fixture['company'];
    $adminRow = $fixture['admin'];
    $issueKey = bx_uuid();
    $customerKey = (string) ($values['customer_key'] ?? '');
    $projectKey = (string) ($values['project_key'] ?? '');
    $saved = $db->Execute(
        'INSERT INTO project_company_support_issue (
            issue_key, company_key, company_key_hash, issue_code, issue_version,
            form_schema_checksum, subject, description, issue_status,
            issue_priority_key, issue_type_key,
            customer_key, customer_key_hash, customer_code_snapshot, customer_name_snapshot,
            project_key, project_key_hash, project_code_snapshot, project_name_snapshot,
            assigned_admin_key, response_by, first_responded_on, first_response_seconds,
            resolution_by, resolution_date, total_hold_seconds, agreement_status,
            via_customer_portal, opened_at, created_by_admin_key, updated_by_admin_key
         ) VALUES (?, ?, ?, ?, 1, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
        [
            $issueKey,
            (string) $companyRow['company_key'],
            (string) $companyRow['company_key_hash'],
            (string) $values['issue_code'],
            yovel_admin_support_service_issue_checksum(),
            (string) $values['subject'],
            (string) $values['issue_status'],
            ($values['issue_priority_key'] ?? '') !== '' ? (string) $values['issue_priority_key'] : null,
            ($values['issue_type_key'] ?? '') !== '' ? (string) $values['issue_type_key'] : null,
            $customerKey !== '' ? $customerKey : null,
            $customerKey !== '' ? hash('sha256', $customerKey) : null,
            $customerKey !== '' ? (string) ($values['customer_code_snapshot'] ?? '') : null,
            $customerKey !== '' ? (string) ($values['customer_name_snapshot'] ?? '') : null,
            $projectKey !== '' ? $projectKey : null,
            $projectKey !== '' ? hash('sha256', $projectKey) : null,
            $projectKey !== '' ? (string) ($values['project_code_snapshot'] ?? '') : null,
            $projectKey !== '' ? (string) ($values['project_name_snapshot'] ?? '') : null,
            ($values['assigned_admin_key'] ?? '') !== '' ? (string) $values['assigned_admin_key'] : null,
            $values['response_by'] ?? null,
            $values['first_responded_on'] ?? null,
            $values['first_response_seconds'] ?? null,
            $values['resolution_by'] ?? null,
            $values['resolution_date'] ?? null,
            (int) ($values['total_hold_seconds'] ?? 0),
            $values['agreement_status'] ?? null,
            (string) $values['opened_at'],
            (string) $adminRow['admin_key'],
            (string) $adminRow['admin_key'],
        ]
    );
    support_service_assert($saved !== false, 'Support report fixture Issue could not be inserted.');

    return $issueKey;
};

$base = [
    'customer_key' => 'CUSTOMER-A',
    'customer_code_snapshot' => 'CUST-A',
    'customer_name_snapshot' => 'Alpha Customer',
    'project_key' => 'PROJECT-1',
    'project_code_snapshot' => 'PRJ-1',
    'project_name_snapshot' => 'Project One',
    'assigned_admin_key' => (string) $admin['admin_key'],
    'issue_priority_key' => (string) $high['issue_priority_key'],
    'issue_type_key' => (string) $bug['issue_type_key'],
];
$insertIssue($primary, array_replace($base, [
    'issue_code' => 'RPT-001', 'subject' => 'January morning reply', 'issue_status' => 'REPLIED',
    'opened_at' => '2026-01-05 08:15:00', 'response_by' => '2026-01-05 09:00:00',
    'first_responded_on' => '2026-01-05 08:25:00', 'first_response_seconds' => 600,
    'resolution_by' => '2026-01-05 12:00:00', 'agreement_status' => 'RESOLUTION_DUE',
]));
$insertIssue($primary, array_replace($base, [
    'issue_code' => 'RPT-002', 'subject' => 'January resolved', 'issue_status' => 'RESOLVED',
    'issue_priority_key' => (string) $normal['issue_priority_key'],
    'issue_type_key' => (string) $question['issue_type_key'], 'assigned_admin_key' => '',
    'opened_at' => '2026-01-05 09:30:00', 'response_by' => '2026-01-05 10:30:00',
    'first_responded_on' => '2026-01-05 10:00:00', 'first_response_seconds' => 1800,
    'resolution_by' => '2026-01-05 13:30:00', 'resolution_date' => '2026-01-05 12:30:00',
    'agreement_status' => 'FULFILLED',
]));
$insertIssue($primary, array_replace($base, [
    'issue_code' => 'RPT-003', 'subject' => 'January hold', 'issue_status' => 'ON_HOLD',
    'customer_key' => 'CUSTOMER-B', 'customer_code_snapshot' => 'CUST-B', 'customer_name_snapshot' => 'Beta Customer',
    'project_key' => 'PROJECT-2', 'project_code_snapshot' => 'PRJ-2', 'project_name_snapshot' => 'Project Two',
    'opened_at' => '2026-01-06 08:45:00', 'response_by' => '2026-01-06 09:45:00',
    'resolution_by' => '2026-01-06 16:45:00', 'agreement_status' => 'FIRST_RESPONSE_DUE',
]));
$insertIssue($primary, array_replace($base, [
    'issue_code' => 'RPT-004', 'subject' => 'February closed', 'issue_status' => 'CLOSED',
    'customer_key' => 'CUSTOMER-B', 'customer_code_snapshot' => 'CUST-B', 'customer_name_snapshot' => 'Beta Customer',
    'project_key' => 'PROJECT-2', 'project_code_snapshot' => 'PRJ-2', 'project_name_snapshot' => 'Project Two',
    'issue_priority_key' => (string) $normal['issue_priority_key'],
    'opened_at' => '2026-02-02 15:10:00', 'response_by' => '2026-02-02 16:10:00',
    'first_responded_on' => '2026-02-02 16:10:00', 'first_response_seconds' => 3600,
    'resolution_by' => '2026-02-02 20:10:00', 'resolution_date' => '2026-02-02 19:10:00',
    'total_hold_seconds' => 3600, 'agreement_status' => 'FULFILLED',
]));
$insertIssue($primary, array_replace($base, [
    'issue_code' => 'RPT-005', 'subject' => 'April unassigned', 'issue_status' => 'OPEN',
    'customer_key' => '', 'customer_code_snapshot' => '', 'customer_name_snapshot' => '',
    'project_key' => '', 'project_code_snapshot' => '', 'project_name_snapshot' => '',
    'assigned_admin_key' => '', 'issue_priority_key' => '',
    'issue_type_key' => (string) $question['issue_type_key'],
    'opened_at' => '2026-04-01 23:55:00', 'response_by' => '2026-04-02 01:00:00',
    'first_responded_on' => '2026-04-01 23:57:00', 'first_response_seconds' => 120,
    'resolution_by' => '2026-04-02 05:00:00', 'agreement_status' => 'RESOLUTION_DUE',
]));
$insertIssue($primary, array_replace($base, [
    'issue_code' => 'RPT-006', 'subject' => 'Next year', 'issue_status' => 'OPEN',
    'opened_at' => '2027-01-01 10:00:00', 'first_response_seconds' => 300,
    'first_responded_on' => '2027-01-01 10:05:00', 'agreement_status' => 'RESOLUTION_DUE',
]));
$insertIssue($foreign, [
    'issue_code' => 'RPT-FOREIGN', 'subject' => 'Foreign leak probe', 'issue_status' => 'CLOSED',
    'issue_priority_key' => '', 'issue_type_key' => '',
    'customer_key' => 'CUSTOMER-A', 'customer_code_snapshot' => 'FOREIGN', 'customer_name_snapshot' => 'Foreign Customer',
    'project_key' => 'PROJECT-2', 'project_code_snapshot' => 'FOREIGN', 'project_name_snapshot' => 'Foreign Project',
    'assigned_admin_key' => (string) $foreignAdmin['admin_key'], 'opened_at' => '2026-01-05 08:00:00',
    'first_responded_on' => '2026-01-05 08:00:01', 'first_response_seconds' => 1,
    'resolution_date' => '2026-01-05 08:00:02', 'agreement_status' => 'FULFILLED',
]);

$beforeReads = [
    'issues' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_support_issue'),
    'events' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_support_issue_event'),
    'audits' => (int) $db->GetOne('SELECT COUNT(*) FROM builder_audit_log'),
];

$january = ['date_from' => '2026-01-01', 'date_to' => '2026-01-31'];
$firstResponse = yovel_admin_support_first_response_report($company, $admin, $january);
support_service_assert($firstResponse['state'] === 'POPULATED', 'First-response report did not return populated state.');
support_service_assert(array_column($firstResponse['columns'], 'key') === ['date', 'issue_count', 'average_first_response_seconds'], 'First-response columns are unstable.');
support_service_assert($firstResponse['rows'] === [[
    'date' => '2026-01-05', 'issue_count' => 2, 'average_first_response_seconds' => '1200.00',
]], 'First-response daily average is incorrect.');
support_service_assert($firstResponse['totals'] === [
    'issue_count' => 2, 'average_first_response_seconds' => '1200.00',
], 'First-response totals are incorrect.');
support_service_assert($firstResponse['chart']['labels'] === ['2026-01-05'], 'First-response chart labels are unstable.');

$analytics = yovel_admin_support_issue_analytics($company, $admin, [
    'date_from' => '2026-01-01', 'date_to' => '2026-04-30',
    'period' => 'MONTH', 'group_by' => 'PRIORITY', 'sort' => 'PERIOD_ASC',
]);
support_service_assert($analytics['totals']['issue_count'] === 5, 'Issue analytics total is incorrect.');
support_service_assert(array_map(static fn (array $row): array => [$row['period'], $row['group_label'], $row['issue_count']], $analytics['rows']) === [
    ['2026-01', 'Report High', 2],
    ['2026-01', 'Report Normal', 1],
    ['2026-02', 'Report Normal', 1],
    ['2026-04', 'Unassigned priority', 1],
], 'Monthly priority analytics are incorrect.');

foreach (['WEEK', 'MONTH', 'QUARTER', 'YEAR'] as $period) {
    $periodReport = yovel_admin_support_issue_analytics($company, $admin, [
        'date_from' => '2026-01-01', 'date_to' => '2026-12-31',
        'period' => $period, 'group_by' => 'CUSTOMER',
    ]);
    support_service_assert($periodReport['totals']['issue_count'] === 5, $period . ' analytics lost Issues.');
}
foreach (['CUSTOMER', 'ASSIGNEE', 'TYPE', 'PRIORITY'] as $groupBy) {
    $groupReport = yovel_admin_support_issue_analytics($company, $admin, [
        'date_from' => '2026-01-01', 'date_to' => '2026-12-31',
        'period' => 'YEAR', 'group_by' => $groupBy,
    ]);
    support_service_assert($groupReport['totals']['issue_count'] === 5, $groupBy . ' analytics lost Issues.');
}

$filterExpectations = [
    ['customer_key' => 'CUSTOMER-A', 'count' => 2],
    ['project_key' => 'PROJECT-2', 'count' => 2],
    ['issue_status' => 'OPEN', 'count' => 1],
    ['issue_priority_key' => (string) $high['issue_priority_key'], 'count' => 2],
    ['assigned_admin_key' => (string) $admin['admin_key'], 'count' => 3],
];
foreach ($filterExpectations as $filterExpectation) {
    $count = (int) $filterExpectation['count'];
    unset($filterExpectation['count']);
    $filtered = yovel_admin_support_issue_analytics($company, $admin, array_merge([
        'date_from' => '2026-01-01', 'date_to' => '2026-12-31', 'period' => 'YEAR', 'group_by' => 'CUSTOMER',
    ], $filterExpectation));
    support_service_assert($filtered['totals']['issue_count'] === $count, 'Analytics filter count is incorrect for ' . array_key_first($filterExpectation) . '.');
}

$summary = yovel_admin_support_issue_summary($company, $admin, [
    'date_from' => '2026-01-01', 'date_to' => '2026-02-28', 'group_by' => 'STATUS', 'sort' => 'LABEL_ASC',
]);
support_service_assert($summary['totals'] === [
    'issue_count' => 4,
    'responded_issue_count' => 3,
    'resolved_issue_count' => 2,
    'average_first_response_seconds' => '2000.00',
    'average_resolution_seconds' => '10800.00',
], 'Issue summary totals or deterministic decimals are incorrect.');
support_service_assert(array_column($summary['rows'], 'group_label') === ['CLOSED', 'ON_HOLD', 'REPLIED', 'RESOLVED'], 'Issue summary status order is incorrect.');
$slaSummary = yovel_admin_support_issue_summary($company, $admin, array_merge($january, ['group_by' => 'SLA_STATUS']));
support_service_assert(array_column($slaSummary['rows'], 'group_label') === ['FIRST_RESPONSE_DUE', 'FULFILLED', 'RESOLUTION_DUE'], 'Issue summary SLA-state counts are incorrect.');

$hours = yovel_admin_support_hour_distribution($company, $admin, [
    'date_from' => '2026-01-01', 'date_to' => '2026-02-28',
]);
support_service_assert(count($hours['rows']) === 3, 'Hour distribution did not return one row per active date.');
support_service_assert($hours['rows'][0]['date'] === '2026-01-05' && $hours['rows'][0]['hour_08'] === 1 && $hours['rows'][0]['hour_09'] === 1 && $hours['rows'][0]['total'] === 2, 'Hour distribution first date is incorrect.');
support_service_assert($hours['totals']['hour_08'] === 2 && $hours['totals']['hour_09'] === 1 && $hours['totals']['hour_15'] === 1 && $hours['totals']['total'] === 4, 'Hour distribution totals are incorrect.');
support_service_assert($hours['chart']['labels'] === array_map(static fn (int $hour): string => sprintf('%02d:00', $hour), range(0, 23)), 'Hour distribution chart labels are unstable.');

$empty = yovel_admin_support_first_response_report($company, $admin, ['date_from' => '2030-01-01', 'date_to' => '2030-01-31']);
support_service_assert($empty['state'] === 'EMPTY' && $empty['rows'] === [] && $empty['totals']['issue_count'] === 0, 'Empty report state is invalid.');

$invalidCases = [
    [['date_from' => 'not-a-date', 'date_to' => '2026-01-31'], 'date'],
    [['date_from' => '2026-02-01', 'date_to' => '2026-01-01'], 'range'],
    [['date_from' => '2020-01-01', 'date_to' => '2030-01-01'], 'range'],
    [array_merge($january, ['period' => 'DAY']), 'period'],
    [array_merge($january, ['group_by' => 'PASSWORD']), 'group'],
    [array_merge($january, ['sort' => 'RAW_SQL']), 'sort'],
    [array_merge($january, ['unknown_filter' => 'unsafe']), 'filter'],
    [array_merge($january, ['issue_status' => 'DELETED']), 'status'],
    [array_merge($january, ['issue_priority_key' => 'not-a-uuid']), 'Priority'],
];
foreach ($invalidCases as [$invalid, $message]) {
    support_service_expect_exception(
        static fn () => yovel_admin_support_issue_analytics($company, $admin, $invalid),
        InvalidArgumentException::class,
        $message
    );
}
support_service_expect_exception(
    static fn () => yovel_admin_support_issue_summary($company, $foreignAdmin, $january),
    InvalidArgumentException::class,
    'membership'
);

$foreignReport = yovel_admin_support_issue_analytics($foreignCompany, $foreignAdmin, array_merge($january, [
    'period' => 'MONTH', 'group_by' => 'CUSTOMER',
]));
support_service_assert($foreignReport['totals']['issue_count'] === 1 && $foreignReport['rows'][0]['group_label'] === 'Foreign Customer', 'Foreign report fixture is invalid.');
support_service_assert($firstResponse['totals']['issue_count'] === 2, 'Foreign company leaked into the primary report.');

$afterReads = [
    'issues' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_support_issue'),
    'events' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_support_issue_event'),
    'audits' => (int) $db->GetOne('SELECT COUNT(*) FROM builder_audit_log'),
];
support_service_assert($afterReads === $beforeReads, 'A Support report read mutated persisted data.');

$previousGet = $_GET;
$_GET = [
    'section' => 'issue-summaries', 'report' => 'analytics',
    'date_from' => '2026-01-01', 'date_to' => '2026-04-30', 'period' => 'MONTH', 'group_by' => 'PRIORITY',
];
$moduleData = yovel_admin_support_service_data($company, $admin);
$_GET = $previousGet;
support_service_assert(($moduleData['report']['report_key'] ?? '') === 'analytics', 'Support report dispatch returned the wrong report.');
$activeModuleData = $moduleData;
$activeModuleSection = 'issue-summaries';
$activeModuleSections = yovel_admin_support_service_sections();
$activeModuleFormState = [];
$companyName = (string) $company['company_name'];
ob_start();
require dirname(__DIR__) . '/company/admin/modules/support-service/views/workspace.php';
$markup = (string) ob_get_clean();
foreach (['data-support-report', 'data-support-report-table', 'data-support-report-filters', 'name="date_from"', 'name="date_to"', 'name="period"', 'name="group_by"', 'data-support-report-export', 'data-support-report-print'] as $needle) {
    support_service_assert(str_contains($markup, $needle), 'Support report workspace is missing ' . $needle . '.');
}
support_service_assert(str_contains($markup, 'data-grid-span="12"') && str_contains($markup, 'data-grid-span="8"'), 'Support reports do not preserve the 12/8 shell.');
support_service_assert(!str_contains($markup, 'UNAVAILABLE_DEPENDENCY: 0'), 'Unavailable dependency data was rendered as a fabricated zero.');

echo "Support report checks passed: read-only scope, daily response averages, period/group analytics, summary metrics, hour distribution, filters, decimals, totals, empty/errors, isolation, dispatch, and 12/8 rendering.\n";
