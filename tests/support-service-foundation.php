<?php
declare(strict_types=1);

require_once __DIR__ . '/support-service-test-helper.php';

$db = bx_db();
$primary = support_service_create_company($db, 'Primary');
$foreign = support_service_create_company($db, 'Foreign');
register_shutdown_function(static function () use ($db, $primary, $foreign): void {
    support_service_cleanup_company($db, $primary);
    support_service_cleanup_company($db, $foreign);
});

$company = $primary['company'];
$admin = $primary['admin'];
$foreignCompany = $foreign['company'];
$foreignAdmin = $foreign['admin'];

support_service_assert(function_exists('yovel_admin_support_service_schema'), 'Support schema service is missing.');
support_service_assert(function_exists('yovel_admin_support_service_form_adapter'), 'Support Form Builder adapter is missing.');
support_service_assert(function_exists('yovel_admin_support_service_handle_post'), 'Support registry action provider is missing.');
$handlerReflection = new ReflectionFunction('yovel_admin_support_service_handle_post');
support_service_assert($handlerReflection->getNumberOfParameters() === 4, 'Support POST handler must expose exactly four arguments.');

$expectedSections = [
    'issues-tickets',
    'sla-rules',
    'warranty-claims',
    'first-response-tracking',
    'issue-summaries',
    'customer-support-portal',
];
support_service_assert(array_keys(yovel_admin_support_service_sections()) === $expectedSections, 'Support section registry is incomplete or unstable.');
$supportRoute = yovel_admin_module_route('support-service');
support_service_assert(($supportRoute['action_provider'] ?? '') === 'yovel_admin_support_service_handle_post', 'Shared registry does not reference the exact Support handler.');
support_service_assert(($supportRoute['data_provider'] ?? '') === 'yovel_admin_support_service_data', 'Shared registry does not reference Support data.');

yovel_admin_support_service_schema();
$requiredTables = [
    'project_company_support_setting',
    'project_company_support_search_source',
    'project_company_support_issue_priority',
    'project_company_support_issue_type',
    'project_company_support_issue',
    'project_company_support_issue_event',
    'project_company_support_sla',
    'project_company_support_sla_service_day',
    'project_company_support_sla_priority',
    'project_company_support_sla_pause_status',
    'project_company_support_sla_fulfilled_status',
    'project_company_support_warranty_claim',
    'project_company_support_telephony_call_type',
    'project_company_support_incoming_call_setting',
    'project_company_support_incoming_call_schedule',
    'project_company_support_voice_call_setting',
    'project_company_support_call_log',
    'project_company_support_call_log_link',
];
foreach ($requiredTables as $table) {
    support_service_assert(support_service_table_exists($db, $table), 'Missing Support foundation table: ' . $table);
}
$companyKeyColumn = $db->GetRow(
    "SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'project_company_support_setting' AND COLUMN_NAME = 'company_key'",
    [BUILDERX_DB_NAME]
);
support_service_assert(
    is_array($companyKeyColumn)
    && (string) ($companyKeyColumn['DATA_TYPE'] ?? '') === 'varchar'
    && (int) ($companyKeyColumn['CHARACTER_MAXIMUM_LENGTH'] ?? 0) === 1500,
    'Support company keys must remain bounded opaque VARCHAR(1500) values.'
);
$rowCountsBeforeSecondSchema = [];
foreach ($requiredTables as $table) {
    $rowCountsBeforeSecondSchema[$table] = (int) $db->GetOne("SELECT COUNT(*) FROM {$table}");
}
yovel_admin_support_service_schema();
foreach ($requiredTables as $table) {
    support_service_assert((int) $db->GetOne("SELECT COUNT(*) FROM {$table}") === $rowCountsBeforeSecondSchema[$table], 'Support schema is not idempotent for ' . $table . '.');
}

$scope = yovel_admin_support_service_scope($company, $admin);
support_service_assert($scope === [$company['company_key'], $company['company_key_hash'], $admin['admin_key']], 'Support scope did not preserve the opaque company key and authorized admin.');
support_service_expect_exception(
    static fn () => yovel_admin_support_service_scope(array_merge($company, ['company_key_hash' => 'not-a-hash']), $admin),
    InvalidArgumentException::class,
    '64-character'
);
support_service_expect_exception(
    static fn () => yovel_admin_support_service_scope(array_merge($company, ['company_key' => $company['company_key'] . '-wrong']), $admin),
    InvalidArgumentException::class,
    'active company'
);
support_service_expect_exception(
    static fn () => yovel_admin_support_service_scope($company, $foreignAdmin),
    InvalidArgumentException::class,
    'active company administrator'
);

$defaults = yovel_admin_support_settings($company, $admin);
support_service_assert((int) $defaults['setting_version'] === 0 && (string) $defaults['support_setting_key'] === '', 'Support settings defaults are not version zero.');

$savedSettings = yovel_admin_save_support_settings($company, $admin, [
    'expected_version' => '0',
    'close_issue_after_days' => '14',
    'portal_enabled' => '1',
    'track_service_level_agreement' => '1',
    'allow_resetting_service_level_agreement' => '0',
    'greeting_title' => 'How can we help?',
    'greeting_subtitle' => 'Search help or open a ticket.',
]);
$settingsKey = (string) $savedSettings['support_setting_key'];
support_service_assert(yovel_admin_is_uuid($settingsKey), 'Support settings create did not assign a stable record key.');
support_service_assert((int) $savedSettings['setting_version'] === 1 && (int) $savedSettings['close_issue_after_days'] === 14, 'Support settings create did not return exact persisted values.');
support_service_assert((string) $savedSettings['company_key'] === $company['company_key'], 'Support settings lost the opaque company key.');
$settingsAuditCount = (int) $db->GetOne(
    "SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_support_setting' AND record_key = ?",
    [$settingsKey]
);
support_service_assert($settingsAuditCount === 1, 'Support settings create was not audited inside its transaction.');

$updatedSettings = yovel_admin_save_support_settings($company, $admin, [
    'expected_version' => '1',
    'close_issue_after_days' => '30',
    'portal_enabled' => '0',
    'track_service_level_agreement' => '1',
    'allow_resetting_service_level_agreement' => '1',
    'greeting_title' => 'Support center',
    'greeting_subtitle' => 'Track every request.',
]);
support_service_assert((string) $updatedSettings['support_setting_key'] === $settingsKey, 'Support settings update changed the stable key.');
support_service_assert((int) $updatedSettings['setting_version'] === 2 && (int) $updatedSettings['portal_enabled'] === 0, 'Support settings update did not return exact persisted values.');

support_service_expect_exception(
    static fn () => yovel_admin_save_support_settings($company, $admin, [
        'expected_version' => '1',
        'close_issue_after_days' => '30',
        'portal_enabled' => '0',
        'track_service_level_agreement' => '1',
        'allow_resetting_service_level_agreement' => '1',
        'greeting_title' => 'Stale Support center',
        'greeting_subtitle' => 'This stale form must not save.',
    ]),
    RuntimeException::class,
    'changed since'
);
$rollbackSettingsThrown = false;
try {
    yovel_admin_save_support_settings($company, $admin, [
        'expected_version' => '2',
        'close_issue_after_days' => '99',
        'portal_enabled' => '1',
        'track_service_level_agreement' => '0',
        'allow_resetting_service_level_agreement' => '0',
        'greeting_title' => 'Rolled back',
        'greeting_subtitle' => 'This value must not commit.',
    ], static function (): void {
        throw new RuntimeException('Support settings rollback injection.');
    });
} catch (RuntimeException $error) {
    $rollbackSettingsThrown = $error->getMessage() === 'Support settings rollback injection.';
}
support_service_assert($rollbackSettingsThrown, 'Support settings rollback injection did not execute.');
$afterSettingsRollback = yovel_admin_support_settings($company, $admin);
support_service_assert((int) $afterSettingsRollback['setting_version'] === 2 && (string) $afterSettingsRollback['greeting_title'] === 'Support center', 'Support settings survived rollback.');

$apiSearch = yovel_admin_save_support_search_source($company, $admin, [
    'search_source_key' => '',
    'expected_version' => '0',
    'source_name' => 'Public Knowledge Base',
    'source_type' => 'API',
    'base_url' => 'https://help.example.test',
    'query_route' => 'api/search',
    'search_term_param_name' => 'query',
    'response_result_key_path' => 'data.results',
    'post_route' => 'articles/{slug}',
    'post_route_key_list' => 'slug,locale',
    'post_title_key' => 'title',
    'post_description_key' => 'summary',
    'source_status' => 'ACTIVE',
]);
$apiSearchKey = (string) $apiSearch['search_source_key'];
support_service_assert(yovel_admin_is_uuid($apiSearchKey), 'Support API search source did not receive a stable key.');
support_service_assert((int) $apiSearch['source_version'] === 1 && (string) $apiSearch['query_route'] === 'api/search', 'Support API search source read-back is incomplete.');
support_service_assert((string) $apiSearch['post_route_key_list'] === 'locale,slug', 'Support API route keys were not normalized deterministically.');

$linkSearch = yovel_admin_save_support_search_source($company, $admin, [
    'expected_version' => '0',
    'source_name' => 'Support Articles',
    'source_type' => 'LINK',
    'source_doctype' => 'Support Article',
    'result_title_field' => 'article_title',
    'result_preview_field' => 'summary',
    'result_route_field' => 'route',
    'source_status' => 'ACTIVE',
]);
support_service_assert((string) $linkSearch['source_type'] === 'LINK' && (string) $linkSearch['base_url'] === '', 'Support Link search source retained API-only values.');

$unsafeInputs = [
    ['base_url' => 'http://help.example.test', 'query_route' => 'api/search'],
    ['base_url' => 'https://127.0.0.1', 'query_route' => 'api/search'],
    ['base_url' => 'https://user:pass@help.example.test', 'query_route' => 'api/search'],
    ['base_url' => 'https://help.example.test?token=secret', 'query_route' => 'api/search'],
    ['base_url' => 'https://help.example.test', 'query_route' => '../admin'],
    ['base_url' => 'https://help.example.test', 'query_route' => '%2e%2e/admin'],
    ['base_url' => 'https://help.example.test', 'query_route' => '%252e%252e/admin'],
];
foreach ($unsafeInputs as $index => $unsafe) {
    support_service_expect_exception(
        static fn () => yovel_admin_save_support_search_source($company, $admin, array_merge([
            'expected_version' => '0',
            'source_name' => 'Unsafe ' . $index,
            'source_type' => 'API',
            'source_status' => 'ACTIVE',
        ], $unsafe)),
        InvalidArgumentException::class,
        'safe'
    );
}
support_service_expect_exception(
    static fn () => yovel_admin_support_service_handle_post($company, $admin, 'support_save_search_source', [
        'expected_version' => '0',
        'source_name' => 'Secret Source',
        'source_type' => 'API',
        'base_url' => 'https://help.example.test',
        'query_route' => 'api/search',
        'api_token' => 'must-not-enter-persistence',
    ]),
    InvalidArgumentException::class,
    'secret'
);
support_service_expect_exception(
    static fn () => yovel_admin_support_service_handle_post($company, $admin, 'support_unknown_action', []),
    InvalidArgumentException::class,
    'Unknown Support / Service module action'
);
support_service_expect_exception(
    static fn () => yovel_admin_support_service_handle_post(
        array_merge($company, ['company_key_hash' => 'invalid']),
        $foreignAdmin,
        'support_unknown_action',
        ['api_token' => 'must-not-be-inspected-after-dispatch']
    ),
    InvalidArgumentException::class,
    'Unknown Support / Service module action'
);

yovel_admin_save_support_settings($foreignCompany, $foreignAdmin, [
    'expected_version' => '0',
    'close_issue_after_days' => '7',
    'portal_enabled' => '1',
    'track_service_level_agreement' => '0',
    'allow_resetting_service_level_agreement' => '0',
    'greeting_title' => 'Foreign support',
    'greeting_subtitle' => 'Foreign company support settings.',
]);
$foreignSearch = yovel_admin_save_support_search_source($foreignCompany, $foreignAdmin, [
    'expected_version' => '0',
    'source_name' => 'Foreign Knowledge',
    'source_type' => 'LINK',
    'source_doctype' => 'Foreign Article',
    'result_title_field' => 'title',
    'result_preview_field' => 'summary',
    'result_route_field' => 'route',
    'source_status' => 'ACTIVE',
]);
support_service_expect_exception(
    static fn () => yovel_admin_save_support_search_source($company, $admin, [
        'search_source_key' => (string) $foreignSearch['search_source_key'],
        'expected_version' => '1',
        'source_name' => 'Cross Company Update',
        'source_type' => 'LINK',
        'source_doctype' => 'Support Article',
        'result_title_field' => 'title',
        'result_preview_field' => 'summary',
        'result_route_field' => 'route',
        'source_status' => 'ACTIVE',
    ]),
    InvalidArgumentException::class,
    'another company'
);

$rollbackSearchThrown = false;
try {
    yovel_admin_save_support_search_source($company, $admin, [
        'search_source_key' => $apiSearchKey,
        'expected_version' => '1',
        'source_name' => 'Public Knowledge Base Updated',
        'source_type' => 'API',
        'base_url' => 'https://help.example.test',
        'query_route' => 'api/v2/search',
        'search_term_param_name' => 'query',
        'response_result_key_path' => 'data.results',
        'post_route' => 'articles/{slug}',
        'post_route_key_list' => 'slug',
        'post_title_key' => 'title',
        'post_description_key' => 'summary',
        'source_status' => 'ACTIVE',
    ], static function (): void {
        throw new RuntimeException('Support search rollback injection.');
    });
} catch (RuntimeException $error) {
    $rollbackSearchThrown = $error->getMessage() === 'Support search rollback injection.';
}
support_service_assert($rollbackSearchThrown, 'Support search rollback injection did not execute.');
$afterSearchRollback = yovel_admin_support_search_source($company, $admin, $apiSearchKey);
support_service_assert((int) $afterSearchRollback['source_version'] === 1 && (string) $afterSearchRollback['query_route'] === 'api/search', 'Support search source survived rollback.');

$handlerResult = yovel_admin_support_service_handle_post($company, $admin, 'support_save_settings', [
    'section' => 'customer-support-portal',
    'expected_version' => '2',
    'close_issue_after_days' => '21',
    'portal_enabled' => '1',
    'track_service_level_agreement' => '1',
    'allow_resetting_service_level_agreement' => '0',
    'greeting_title' => 'Customer support',
    'greeting_subtitle' => 'We are ready to help.',
]);
support_service_assert(($handlerResult['section'] ?? '') === 'customer-support-portal', 'Support handler returned the wrong section.');
support_service_assert(($handlerResult['query']['settings'] ?? '') === $settingsKey, 'Support handler did not return stable redirect metadata.');
support_service_assert(($handlerResult['rehydration']['form_id'] ?? '') === 'support-settings', 'Support handler omitted server rehydration metadata.');
support_service_assert((string) ($handlerResult['rehydration']['values']['greeting_title'] ?? '') === 'Customer support', 'Support handler rehydration does not use committed values.');

$adapter = yovel_admin_support_service_form_adapter();
$expectedTargets = [
    'issues-tickets' => 'issue',
    'issue-priorities' => 'issue-priority',
    'issue-types' => 'issue-type',
    'sla-rules' => 'service-level-agreement',
    'warranty-claims' => 'warranty-claim',
    'telephony-call-types' => 'telephony-call-type',
    'incoming-call-settings' => 'incoming-call-settings',
    'voice-call-settings' => 'voice-call-settings',
];
support_service_assert(($adapter['module'] ?? '') === 'support-service', 'Support Form Builder adapter has the wrong owner.');
support_service_assert(($adapter['target_record_types'] ?? []) === $expectedTargets, 'Support Form Builder targets are incomplete or unstable.');
support_service_assert(($adapter['protected_fields']['issue'] ?? []) === ['subject', 'status', 'company_key_hash'], 'Support Issue protected fields changed.');
support_service_assert(!empty($adapter['row_column_layout']['stable_keys']), 'Support Form Builder does not enforce stable keys.');
support_service_assert(!empty($adapter['workflow_constraints']['issue']['published_immutable']), 'Support published Form Builder versions are mutable.');

$normalized = yovel_admin_support_service_normalize_form_schema('issue', [
    'fields' => [
        ['key' => 'renamed_subject', 'label' => 'Renamed subject', 'type' => 'NUMBER'],
        ['key' => 'custom_note', 'label' => 'Customer note', 'type' => 'PARAGRAPH', 'required' => false, 'visible' => true, 'width' => 'full'],
    ],
]);
support_service_assert(array_column($normalized['fields'], 'key') === ['subject', 'status', 'company_key_hash', 'custom_note'], 'Support normalization removed protected fields or accepted a renamed protected key.');
support_service_assert(($normalized['fields'][0]['type'] ?? '') === 'SHORT_TEXT' && !empty($normalized['fields'][0]['required']), 'Support normalization changed protected field semantics.');

$published = yovel_admin_support_service_normalize_form_schema('issue', [
    'fields' => [
        ['key' => 'custom_reference', 'label' => 'Reference', 'type' => 'SHORT_TEXT', 'required' => false, 'visible' => true, 'width' => 'half'],
    ],
]);
support_service_expect_exception(
    static fn () => yovel_admin_support_service_normalize_form_schema('issue', [
        'fields' => [
            ['key' => 'custom_reference', 'label' => 'Reference', 'type' => 'NUMBER', 'required' => false, 'visible' => true, 'width' => 'half'],
        ],
    ], $published),
    InvalidArgumentException::class,
    'immutable'
);
$checksumA = yovel_admin_support_service_form_checksum(['fields' => [['key' => 'a', 'label' => 'A']]]);
$checksumB = yovel_admin_support_service_form_checksum(['fields' => [['label' => 'A', 'key' => 'a']]]);
support_service_assert(hash_equals($checksumA, $checksumB), 'Support Form Builder version identity is not canonical.');

$dependencies = yovel_admin_support_service_dependencies();
support_service_assert(($dependencies['operations_search']['contract'] ?? '') === 'operations.support-search.v1', 'Operations search dependency is not explicit.');
support_service_assert(($dependencies['operations_jobs']['contract'] ?? '') === 'operations.enqueue-job.v1', 'Operations job dependency is not explicit.');
support_service_assert(($dependencies['operations_search']['available'] ?? true) === false, 'Unavailable Operations search dependency was reported as available.');
support_service_assert(
    ($dependencies['operations_search']['signature'] ?? '') === 'yovel_admin_operations_support_search(array $company, array $request): array',
    'Operations search dependency signature changed.'
);
support_service_assert(
    ($dependencies['operations_jobs']['signature'] ?? '') === 'yovel_admin_operations_enqueue_job(array $company, array $admin, array $job): array',
    'Operations job dependency signature changed.'
);
support_service_assert(
    ($dependencies['sales_customer']['signature'] ?? '') === 'yovel_admin_sales_crm_customer_reference(array $company, string $customerKey): ?array',
    'Sales customer dependency signature changed.'
);
support_service_assert(
    ($dependencies['sales_contact']['signature'] ?? '') === 'yovel_admin_sales_crm_contact_reference(array $company, string $contactKey): ?array',
    'Sales contact dependency signature changed.'
);
support_service_assert(
    ($dependencies['projects_service_work']['signature'] ?? '') === 'yovel_admin_projects_service_work_reference(array $company, string $serviceWorkKey): ?array',
    'Projects service-work dependency signature changed.'
);

$moduleData = yovel_admin_support_service_data($company, $admin);
support_service_assert((string) ($moduleData['settings']['support_setting_key'] ?? '') === $settingsKey, 'Support module data did not rehydrate settings from the server.');
support_service_assert(count($moduleData['search_sources'] ?? []) === 2, 'Support module data did not return company-scoped search sources.');
support_service_assert(($moduleData['form_adapter']['module'] ?? '') === 'support-service', 'Support module data omitted its Form Builder adapter.');

echo "Support / Service foundation checks passed: schema, opaque scope, transactions, rollback, audit, safe search, registry handler, rehydration, and Form Builder.\n";
