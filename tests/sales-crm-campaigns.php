<?php
declare(strict_types=1);

require_once __DIR__ . '/sales-crm-test-helper.php';

$root = dirname(__DIR__);
sales_crm_assert(is_file($root . '/company/admin/modules/sales-crm/campaigns.php'), 'SC-05 Campaign service is missing.');
sales_crm_assert(is_file($root . '/company/admin/modules/sales-crm/views/campaigns.php'), 'SC-05 Campaign workspace is missing.');
foreach ([
    'yovel_admin_sales_campaign_save',
    'yovel_admin_sales_campaign_schedule_save',
    'yovel_admin_sales_campaign_transition',
    'yovel_admin_sales_campaign_efficiency',
    'yovel_admin_sales_campaign_attribution_sources',
] as $function) {
    sales_crm_assert(function_exists($function), 'SC-05 public contract is missing: ' . $function);
}

yovel_admin_sales_crm_schema();
$db = bx_db();
foreach (['campaign_key', 'company_key', 'company_key_hash', 'campaign_code', 'campaign_name', 'campaign_status'] as $preservedColumn) {
    sales_crm_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [BUILDERX_DB_NAME, 'project_company_sales_campaign', $preservedColumn]
    ) === 1, 'The preserved Campaign column is missing: ' . $preservedColumn);
}
foreach (['campaign_version', 'campaign_type', 'start_date', 'end_date', 'budget', 'expected_revenue', 'notes', 'idempotency_key'] as $expandedColumn) {
    sales_crm_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [BUILDERX_DB_NAME, 'project_company_sales_campaign', $expandedColumn]
    ) === 1, 'The expanded Campaign column is missing: ' . $expandedColumn);
}
sales_crm_assert((int) $db->GetOne(
    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
    [BUILDERX_DB_NAME, 'project_company_sales_campaign_email_schedule']
) === 1, 'Campaign Email Schedule table is missing.');

$fixture = sales_crm_create_isolated_company($db);
$company = $fixture['company'];
$admin = $fixture['admin'];
$companyHash = (string) $company['company_key_hash'];
$campaignKey = '';
$leadKey = '';
$rollbackCode = sales_crm_test_code('SC05_ROLLBACK');
$attributionSourceStates = [];

try {
    $invalidDatesRejected = false;
    try {
        yovel_admin_sales_campaign_save($company, $admin, [
            'campaign_code' => sales_crm_test_code('SC05_DATE'),
            'campaign_name' => 'Invalid date campaign',
            'campaign_status' => 'DRAFT',
            'start_date' => '2026-09-30',
            'end_date' => '2026-09-01',
            'budget' => '100.00',
        ]);
    } catch (InvalidArgumentException $error) {
        $invalidDatesRejected = str_contains($error->getMessage(), 'End date');
    }
    sales_crm_assert($invalidDatesRejected, 'Campaign accepted an end date before its start date.');

    foreach (['12.345', '-1.00', 'not-money'] as $invalidDecimal) {
        $decimalRejected = false;
        try {
            yovel_admin_sales_campaign_save($company, $admin, [
                'campaign_code' => sales_crm_test_code('SC05_DEC'),
                'campaign_name' => 'Invalid decimal campaign',
                'campaign_status' => 'DRAFT',
                'budget' => $invalidDecimal,
            ]);
        } catch (InvalidArgumentException) {
            $decimalRejected = true;
        }
        sales_crm_assert($decimalRejected, 'Campaign accepted a non-deterministic decimal: ' . $invalidDecimal);
    }

    $requestKey = bx_uuid();
    $created = yovel_admin_sales_campaign_save($company, $admin, [
        'idempotency_key' => $requestKey,
        'campaign_code' => sales_crm_test_code('SC05_CAMPAIGN'),
        'campaign_name' => 'Autumn account growth',
        'campaign_status' => 'DRAFT',
        'campaign_type' => 'Email and account outreach',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'budget' => '1200.5',
        'expected_revenue' => '5000',
        'notes' => 'SC-05 deterministic Campaign fixture.',
        'email_schedules' => [[
            'schedule_code' => 'WELCOME',
            'subject' => 'Welcome to the autumn account program',
            'recipient_segment' => 'Qualified leads',
            'scheduled_at' => '2026-09-03 09:30:00',
        ]],
    ]);
    $campaignKey = (string) ($created['campaign_key'] ?? '');
    sales_crm_assert(yovel_admin_is_uuid($campaignKey), 'Campaign create did not return a stable key.');
    sales_crm_assert((string) $created['campaign_status'] === 'DRAFT' && (int) $created['campaign_version'] === 1, 'Campaign create did not read back its draft lifecycle/version.');
    sales_crm_assert((string) $created['budget'] === '1200.50' && (string) $created['expected_revenue'] === '5000.00', 'Campaign decimals were not normalized deterministically.');
    sales_crm_assert(count($created['email_schedules'] ?? []) === 1, 'Campaign create did not read back its schedule child rows.');
    $scheduleKey = (string) $created['email_schedules'][0]['campaign_email_schedule_key'];
    sales_crm_assert(yovel_admin_is_uuid($scheduleKey), 'Campaign schedule did not receive a stable key.');
    sales_crm_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_sales_campaign' AND record_key = ? AND action = 'CREATE'", [$campaignKey]) === 1, 'Campaign create audit did not commit.');
    sales_crm_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_sales_campaign_email_schedule' AND record_key = ? AND action = 'CREATE'", [$scheduleKey]) === 1, 'Campaign schedule audit did not commit.');

    $replayed = yovel_admin_sales_campaign_save($company, $admin, [
        'idempotency_key' => $requestKey,
        'campaign_code' => sales_crm_test_code('IGNORED'),
        'campaign_name' => 'Ignored replay values',
    ]);
    sales_crm_assert((string) $replayed['campaign_key'] === $campaignKey, 'Campaign create idempotency did not return the original stable key.');
    sales_crm_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_sales_campaign WHERE company_key_hash = ? AND idempotency_key = ?', [$companyHash, $requestKey]) === 1, 'Campaign create idempotency duplicated a header.');

    $updated = yovel_admin_sales_campaign_save($company, $admin, [
        'campaign_key' => $campaignKey,
        'expected_version' => '1',
        'campaign_code' => (string) $created['campaign_code'],
        'campaign_name' => 'Autumn account growth updated',
        'campaign_status' => 'DRAFT',
        'campaign_type' => 'Account outreach',
        'start_date' => '2026-09-01',
        'end_date' => '2026-10-05',
        'budget' => '1250.00',
        'expected_revenue' => '5250.00',
        'notes' => 'Updated through the same stable Campaign key.',
    ]);
    sales_crm_assert((string) $updated['campaign_key'] === $campaignKey && (int) $updated['campaign_version'] === 2, 'Campaign update changed the key or failed to advance the version.');
    sales_crm_assert((string) $updated['campaign_name'] === 'Autumn account growth updated', 'Campaign update failed exact read-back.');

    $staleRejected = false;
    try {
        yovel_admin_sales_campaign_save($company, $admin, [
            'campaign_key' => $campaignKey,
            'expected_version' => '1',
            'campaign_code' => (string) $created['campaign_code'],
            'campaign_name' => 'Stale overwrite',
            'campaign_status' => 'DRAFT',
        ]);
    } catch (RuntimeException $error) {
        $staleRejected = str_contains($error->getMessage(), 'changed since');
    }
    sales_crm_assert($staleRejected, 'A stale Campaign version was not rejected.');

    $secondFixture = sales_crm_create_isolated_company($db);
    try {
        $foreignRejected = false;
        try {
            yovel_admin_sales_campaign_transition($secondFixture['company'], $secondFixture['admin'], [
                'campaign_key' => $campaignKey,
                'campaign_status' => 'ACTIVE',
            ]);
        } catch (InvalidArgumentException) {
            $foreignRejected = true;
        }
        sales_crm_assert($foreignRejected, 'A foreign company could transition a Campaign.');
    } finally {
        sales_crm_cleanup_isolated_company($db, (string) $secondFixture['company']['company_key_hash']);
    }

    $unauthorizedAdmin = $admin;
    $unauthorizedAdmin['admin_key'] = bx_uuid();
    $unauthorizedRejected = false;
    try {
        yovel_admin_sales_campaign_save($company, $unauthorizedAdmin, [
            'campaign_code' => sales_crm_test_code('SC05_UNAUTHORIZED'),
            'campaign_name' => 'Unauthorized Campaign',
            'campaign_status' => 'DRAFT',
        ]);
    } catch (InvalidArgumentException) {
        $unauthorizedRejected = true;
    }
    sales_crm_assert($unauthorizedRejected, 'An unauthorized administrator could write a Campaign.');

    $rollbackThrown = false;
    try {
        yovel_admin_sales_campaign_save($company, $admin, [
            'campaign_code' => $rollbackCode,
            'campaign_name' => 'Rollback Campaign',
            'campaign_status' => 'DRAFT',
            'budget' => '99.00',
        ], static function (): void {
            throw new RuntimeException('SC-05 rollback injection.');
        });
    } catch (RuntimeException $error) {
        $rollbackThrown = $error->getMessage() === 'SC-05 rollback injection.';
    }
    sales_crm_assert($rollbackThrown, 'Campaign rollback injection did not execute.');
    sales_crm_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_sales_campaign WHERE company_key_hash = ? AND campaign_code = ?', [$companyHash, $rollbackCode]) === 0, 'A failed Campaign transaction survived rollback.');

    $scheduleAuditBeforeRollback = (int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_sales_campaign_email_schedule'");
    $scheduleRollbackThrown = false;
    try {
        yovel_admin_sales_campaign_schedule_save($company, $admin, [
            'campaign_key' => $campaignKey,
            'schedule_code' => 'ROLLBACK_SCHEDULE',
            'subject' => 'Rollback schedule',
            'recipient_segment' => 'Test segment',
            'scheduled_at' => '2026-09-10 10:00:00',
        ], static function (): void {
            throw new RuntimeException('SC-05 schedule rollback injection.');
        });
    } catch (RuntimeException $error) {
        $scheduleRollbackThrown = $error->getMessage() === 'SC-05 schedule rollback injection.';
    }
    sales_crm_assert($scheduleRollbackThrown, 'Campaign Email Schedule rollback injection did not execute.');
    sales_crm_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_sales_campaign_email_schedule WHERE company_key_hash = ? AND campaign_key = ? AND schedule_code = ?', [$companyHash, $campaignKey, 'ROLLBACK_SCHEDULE']) === 0, 'A failed Campaign Email Schedule transaction survived rollback.');
    sales_crm_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_sales_campaign_email_schedule'") === $scheduleAuditBeforeRollback, 'A failed Campaign Email Schedule audit survived rollback.');

    $scheduleRequestKey = bx_uuid();
    $schedule = yovel_admin_sales_campaign_schedule_save($company, $admin, [
        'campaign_key' => $campaignKey,
        'idempotency_key' => $scheduleRequestKey,
        'schedule_code' => 'FOLLOW_UP',
        'subject' => 'Autumn follow-up',
        'recipient_segment' => 'Engaged leads',
        'scheduled_at' => '2026-09-15 11:00:00',
    ]);
    sales_crm_assert((string) $schedule['campaign_key'] === $campaignKey && (string) $schedule['send_status'] === 'SCHEDULED', 'Campaign Email Schedule save did not read back exact values.');
    $scheduleReplay = yovel_admin_sales_campaign_schedule_save($company, $admin, [
        'campaign_key' => $campaignKey,
        'idempotency_key' => $scheduleRequestKey,
        'schedule_code' => 'IGNORED_REPLAY',
        'subject' => 'Ignored replay subject',
        'scheduled_at' => '2026-09-16 11:00:00',
    ]);
    sales_crm_assert((string) $scheduleReplay['campaign_email_schedule_key'] === (string) $schedule['campaign_email_schedule_key'], 'Campaign Email Schedule idempotency did not return the original child key.');
    sales_crm_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_sales_campaign_email_schedule WHERE company_key_hash = ? AND idempotency_key = ?', [$companyHash, $scheduleRequestKey]) === 1, 'Campaign Email Schedule idempotency duplicated a child row.');

    $leadKey = bx_uuid();
    $db->Execute(
        "INSERT INTO project_company_sales_lead (
            lead_key, company_key, company_key_hash, lead_code, lead_name, lead_status, campaign_key,
            estimated_value, created_by_admin_key, updated_by_admin_key
        ) VALUES (?, ?, ?, ?, ?, 'QUALIFIED', ?, 2750.00, ?, ?)",
        [$leadKey, $company['company_key'], $companyHash, sales_crm_test_code('SC05_LEAD'), 'Attributed lead', $campaignKey, $admin['admin_key'], $admin['admin_key']]
    );
    $efficiency = yovel_admin_sales_campaign_efficiency($company, $admin, $campaignKey);
    $attributionSourceStates = $efficiency['source_states'];
    sales_crm_assert((int) $efficiency['lead_count'] === 1 && (int) $efficiency['qualified_lead_count'] === 1, 'Campaign efficiency did not read Lead attribution by stable key.');
    sales_crm_assert((string) $efficiency['lead_value'] === '2750.00', 'Campaign efficiency did not aggregate deterministic Lead value.');
    sales_crm_assert((string) $efficiency['budget'] === '1250.00', 'Campaign efficiency did not read the exact Campaign budget.');
    sales_crm_assert(($attributionSourceStates['leads'] ?? '') === 'AVAILABLE', 'Lead attribution dependency was not available.');
    $sources = yovel_admin_sales_campaign_attribution_sources();
    sales_crm_assert(array_keys($sources) === ['leads', 'opportunities', 'quotations', 'sales_orders'], 'Campaign attribution sources are not stable and ordered.');
    foreach ($sources as $source) {
        sales_crm_assert(($source['join_key'] ?? '') === 'campaign_key', 'An attribution source does not use campaign_key.');
        sales_crm_assert(str_ends_with((string) ($source['record_key'] ?? ''), '_key'), 'An attribution source does not expose a stable record key.');
    }

    $transitionAuditBeforeRollback = (int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_sales_campaign' AND record_key = ? AND action = 'STATUS'", [$campaignKey]);
    $transitionRollbackThrown = false;
    try {
        yovel_admin_sales_campaign_transition($company, $admin, ['campaign_key' => $campaignKey, 'campaign_status' => 'ACTIVE'], static function (): void {
            throw new RuntimeException('SC-05 transition rollback injection.');
        });
    } catch (RuntimeException $error) {
        $transitionRollbackThrown = $error->getMessage() === 'SC-05 transition rollback injection.';
    }
    sales_crm_assert($transitionRollbackThrown, 'Campaign lifecycle rollback injection did not execute.');
    $afterTransitionRollback = yovel_admin_sales_campaign_row($company, $campaignKey, false);
    sales_crm_assert((string) $afterTransitionRollback['campaign_status'] === 'DRAFT' && (int) $afterTransitionRollback['campaign_version'] === 2, 'A failed Campaign lifecycle transition survived rollback.');
    sales_crm_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_sales_campaign' AND record_key = ? AND action = 'STATUS'", [$campaignKey]) === $transitionAuditBeforeRollback, 'A failed Campaign lifecycle audit survived rollback.');

    $active = yovel_admin_sales_campaign_transition($company, $admin, ['campaign_key' => $campaignKey, 'campaign_status' => 'ACTIVE']);
    sales_crm_assert((string) $active['campaign_status'] === 'ACTIVE', 'Draft Campaign did not transition to ACTIVE.');
    $completed = yovel_admin_sales_campaign_transition($company, $admin, ['campaign_key' => $campaignKey, 'campaign_status' => 'COMPLETED']);
    sales_crm_assert((string) $completed['campaign_status'] === 'COMPLETED', 'Active Campaign did not transition to COMPLETED.');
    $completedReplay = yovel_admin_sales_campaign_transition($company, $admin, ['campaign_key' => $campaignKey, 'campaign_status' => 'COMPLETED']);
    sales_crm_assert((int) $completedReplay['campaign_version'] === (int) $completed['campaign_version'], 'Idempotent Campaign transition changed the completed record.');

    $completedEditRejected = false;
    try {
        yovel_admin_sales_campaign_save($company, $admin, [
            'campaign_key' => $campaignKey,
            'expected_version' => (string) $completed['campaign_version'],
            'campaign_code' => (string) $completed['campaign_code'],
            'campaign_name' => 'Completed mutation',
            'campaign_status' => 'COMPLETED',
        ]);
    } catch (RuntimeException $error) {
        $completedEditRejected = str_contains($error->getMessage(), 'completed');
    }
    sales_crm_assert($completedEditRejected, 'A completed Campaign header remained mutable.');
    $completedScheduleRejected = false;
    try {
        yovel_admin_sales_campaign_schedule_save($company, $admin, [
            'campaign_key' => $campaignKey,
            'schedule_code' => 'TOO_LATE',
            'subject' => 'Should not save',
            'scheduled_at' => '2026-09-20 10:00:00',
        ]);
    } catch (RuntimeException $error) {
        $completedScheduleRejected = str_contains($error->getMessage(), 'completed');
    }
    sales_crm_assert($completedScheduleRejected, 'A completed Campaign accepted a new Email Schedule.');
    $completedTransitionRejected = false;
    try {
        yovel_admin_sales_campaign_transition($company, $admin, ['campaign_key' => $campaignKey, 'campaign_status' => 'INACTIVE']);
    } catch (RuntimeException $error) {
        $completedTransitionRejected = str_contains($error->getMessage(), 'completed');
    }
    sales_crm_assert($completedTransitionRejected, 'A completed Campaign transitioned back to a mutable state.');

    $workspace = yovel_admin_sales_crm_workspace($company, $admin);
    $campaignDestination = array_values(array_filter($workspace['shortcuts'], static fn (array $item): bool => ($item['section'] ?? '') === 'campaigns'))[0] ?? [];
    sales_crm_assert(($campaignDestination['available'] ?? false) === true && str_contains((string) ($campaignDestination['href'] ?? ''), 'section=campaigns'), 'Campaign workspace destination is not operational.');
    $campaignView = (string) file_get_contents($root . '/company/admin/modules/sales-crm/views/campaigns.php');
    foreach (['data-record-modal', 'data-record-modal-form', 'data-confirm-submit', 'data-confirm-submit-action', 'data-campaign-transition-modal'] as $marker) {
        sales_crm_assert(str_contains($campaignView, $marker), 'Campaign workspace is missing interaction marker: ' . $marker);
    }
    sales_crm_assert(str_contains($campaignView, 'campaign_email_schedule'), 'Campaign form does not publish Email Schedule child controls.');
    $campaignSchema = yovel_admin_sales_crm_active_schema($company, 'campaign', $admin);
    $campaignSchemaKeys = array_column($campaignSchema['fields'], 'key');
    foreach (['campaign_email_schedule_code', 'campaign_email_subject', 'campaign_email_recipient_segment', 'campaign_email_scheduled_at', 'campaign_email_send_status'] as $scheduleFieldKey) {
        sales_crm_assert(in_array($scheduleFieldKey, $campaignSchemaKeys, true), 'Campaign Form Builder does not publish child field: ' . $scheduleFieldKey);
    }
} finally {
    if ($leadKey !== '') {
        $db->Execute('DELETE FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_key = ?', [$companyHash, $leadKey]);
    }
    sales_crm_cleanup_campaigns($db, $companyHash, $campaignKey, [$rollbackCode]);
    sales_crm_cleanup_sc02($db, $companyHash, [], '');
    sales_crm_cleanup_isolated_company($db, $companyHash);
}

echo 'Sales / CRM SC-05 Campaign database tests passed. Attribution dependencies: '
    . json_encode($attributionSourceStates, JSON_UNESCAPED_SLASHES) . "\n";
