<?php
declare(strict_types=1);

require __DIR__ . '/operations-test-helper.php';

$db = bx_db();
$suffix = substr(str_replace('-', '', bx_uuid()), 0, 12);
$makeFixture = static function (string $label) use ($db, $suffix): array {
    $companyKey = bx_uuid();
    $companyHash = hash('sha256', $companyKey);
    $adminKey = bx_uuid();
    yovel_admin_operations_db_execute($db,
        "INSERT INTO project_company (company_key,company_key_hash,company_code,company_slug,company_name,company_status) VALUES (?,?,?,?,?,'ACTIVE')",
        [$companyKey, $companyHash, strtoupper(substr(hash('sha256', $label), 0, 6)) . substr($suffix, 0, 6), strtolower(str_replace(' ', '-', $label) . '-' . $suffix), $label],
        'Operations notification test company');
    yovel_admin_operations_db_execute($db,
        "INSERT INTO project_company_admin (admin_key,company_key,company_key_hash,admin_login,admin_password_hash,admin_name,admin_email,admin_status) VALUES (?,?,?,?,?,?,?,'ACTIVE')",
        [$adminKey, $companyKey, $companyHash, strtolower($label . '_' . $suffix), password_hash('Operations-Test-12345', PASSWORD_DEFAULT), $label . ' Admin', strtolower($label . '-' . $suffix) . '@example.test'],
        'Operations notification test admin');
    return [
        'company' => ['company_key' => $companyKey, 'company_key_hash' => $companyHash, 'company_name' => $label],
        'admin' => ['admin_key' => $adminKey, 'admin_status' => 'ACTIVE'],
    ];
};
$owner = $makeFixture('Notification Owner');
$other = $makeFixture('Notification Other');
register_shutdown_function(static function () use ($db, $owner, $other): void {
    foreach ([$owner, $other] as $fixture) {
        operations_test_cleanup($fixture['company']);
        $db->Execute('DELETE FROM project_company_admin WHERE company_key_hash=?', [$fixture['company']['company_key_hash']]);
        $db->Execute('DELETE FROM project_company WHERE company_key_hash=?', [$fixture['company']['company_key_hash']]);
    }
});
yovel_admin_operations_schema();
$contract = new ReflectionFunction('yovel_admin_operations_create_notification_handoff');
operations_test_assert($contract->getNumberOfRequiredParameters() === 2 && $contract->getNumberOfParameters() === 2, 'Notification handoff does not expose the exact two-argument owner contract.');

$input = [
    'actor_admin_key' => $owner['admin']['admin_key'],
    'source_module' => 'buying-procurement',
    'source_record_type' => 'supplier-scorecard-period',
    'source_record_key' => bx_uuid(),
    'supplier_key' => bx_uuid(),
    'scorecard_key' => bx_uuid(),
    'period_start' => '2026-08-01',
    'period_end' => '2026-08-31',
    'total_score' => '32.0000',
    'standing_code' => 'BLOCKED',
    'notify_supplier' => true,
    'notify_employee' => true,
    'employee_key' => bx_uuid(),
];

$created = yovel_admin_operations_create_notification_handoff($owner['company'], $input);
operations_test_assert(array_keys($created) === ['job_key', 'idempotency_key', 'status', 'checksum'], 'Notification handoff returned fields outside the sanitized envelope.');
operations_test_assert(yovel_admin_is_uuid((string) $created['job_key']), 'Notification handoff did not return a stable job key.');
operations_test_assert((string) $created['status'] === 'QUEUED', 'Notification handoff was not queued.');
operations_test_assert(preg_match('/^notification-handoff:[a-f0-9]{64}$/', (string) $created['idempotency_key']) === 1, 'Notification handoff idempotency key is not deterministic and bounded.');
operations_test_assert(preg_match('/^[a-f0-9]{64}$/', (string) $created['checksum']) === 1, 'Notification handoff checksum is invalid.');

$job = yovel_admin_operations_job($owner['company'], (string) $created['job_key']);
operations_test_assert(is_array($job), 'Notification handoff was not exactly read back.');
operations_test_assert((string) $job['job_type'] === 'NOTIFICATION' && (string) $job['source_section'] === 'notifications', 'Notification handoff used the wrong job route.');
operations_test_assert((string) $job['idempotency_key'] === (string) $created['idempotency_key'], 'Notification handoff changed its idempotency key on read-back.');
operations_test_assert(hash('sha256', yovel_admin_operations_json($job['payload'])) === (string) $created['checksum'], 'Notification handoff checksum does not match persisted payload.');
operations_test_assert(($job['payload']['event'] ?? '') === 'supplier-scorecard-period-calculated', 'Notification handoff did not derive the allow-listed event.');
operations_test_assert(count($job['payload']['recipients'] ?? []) === 2, 'Notification handoff did not canonicalize intended recipients.');
operations_test_assert(!str_contains(yovel_admin_operations_json($job['payload']), (string) $owner['admin']['admin_key']), 'Notification payload exposed its actor identity instead of using job metadata.');
operations_test_assert(yovel_admin_operations_job($other['company'], (string) $created['job_key']) === null, 'Notification handoff leaked across company scope.');
$otherCreated = yovel_admin_operations_create_notification_handoff($other['company'], [...$input, 'actor_admin_key' => $other['admin']['admin_key']]);
operations_test_assert((string) $otherCreated['job_key'] !== (string) $created['job_key'] && (string) $otherCreated['idempotency_key'] !== (string) $created['idempotency_key'], 'Notification handoff identity did not include company scope.');

$duplicate = yovel_admin_operations_create_notification_handoff($owner['company'], $input);
operations_test_assert($duplicate === $created, 'Duplicate notification handoff did not return the exact idempotent envelope.');
operations_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_operations_job WHERE company_key_hash=? AND idempotency_key=?', [$owner['company']['company_key_hash'], $created['idempotency_key']]) === 1, 'Duplicate notification handoff enqueued more than one job.');
operations_test_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module='project_company_operations_job' AND record_key=?", [$created['job_key']]) === 1, 'Notification handoff did not retain exactly one enqueue audit event.');

$jobCount = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_operations_job WHERE company_key_hash=?', [$owner['company']['company_key_hash']]);
$unauthorizedHookCalls = 0;
$GLOBALS['yovel_admin_operations_job_enqueue_hook'] = static function () use (&$unauthorizedHookCalls): void {
    $unauthorizedHookCalls++;
};
operations_test_expect_exception(static fn () => yovel_admin_operations_create_notification_handoff($owner['company'], [...$input, 'actor_admin_key' => $other['admin']['admin_key']]), 'authorized');
unset($GLOBALS['yovel_admin_operations_job_enqueue_hook']);
operations_test_assert($unauthorizedHookCalls === 0, 'Unauthorized notification actor reached the enqueue transaction.');
$db->Execute("UPDATE project_company_admin SET admin_status='INACTIVE' WHERE admin_key=?", [$owner['admin']['admin_key']]);
operations_test_expect_exception(static fn () => yovel_admin_operations_create_notification_handoff($owner['company'], $input), 'authorized');
$db->Execute("UPDATE project_company_admin SET admin_status='ACTIVE' WHERE admin_key=?", [$owner['admin']['admin_key']]);
operations_test_expect_exception(static fn () => yovel_admin_operations_create_notification_handoff([...$owner['company'], 'company_key' => bx_uuid()], $input), 'authorized');
operations_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_operations_job WHERE company_key_hash=?', [$owner['company']['company_key_hash']]) === $jobCount, 'Unauthorized notification attempt started a persisted enqueue.');

foreach ([
    [[...$input, 'source_module' => 'sales-crm'], 'source'],
    [[...$input, 'source_record_type' => 'supplier'], 'source'],
    [[...$input, 'source_record_key' => 'not-a-key'], 'source'],
    [[...$input, 'notify_supplier' => false, 'notify_employee' => true, 'employee_key' => 'not-a-key'], 'recipient'],
    [[...$input, 'notify_supplier' => false, 'notify_employee' => false, 'employee_key' => null], 'recipient'],
    [[...$input, 'unexpected_field' => 'rejected'], 'unknown'],
    [[...$input, 'api_token' => 'must-not-persist'], 'secret'],
    [[...$input, 'standing_code' => ['api_token' => 'nested-must-not-persist']], 'secret'],
    [[...$input, 'standing_code' => str_repeat('A', 9000)], 'large'],
] as [$invalid, $message]) {
    operations_test_expect_exception(static fn () => yovel_admin_operations_create_notification_handoff($owner['company'], $invalid), $message);
}

$rollbackInput = [...$input, 'source_record_key' => bx_uuid()];
$rollbackKey = yovel_admin_operations_notification_idempotency($owner['company'], yovel_admin_operations_normalize_notification_handoff($rollbackInput));
$GLOBALS['yovel_admin_operations_job_enqueue_hook'] = static function (): void {
    throw new RuntimeException('Injected notification enqueue failure.');
};
operations_test_expect_exception(static fn () => yovel_admin_operations_create_notification_handoff($owner['company'], $rollbackInput), 'injected');
unset($GLOBALS['yovel_admin_operations_job_enqueue_hook']);
operations_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_operations_job WHERE company_key_hash=? AND idempotency_key=?', [$owner['company']['company_key_hash'], $rollbackKey]) === 0, 'Notification enqueue failure did not roll back the job.');
operations_test_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module='project_company_operations_job' AND new_values LIKE ?", ['%' . $rollbackKey . '%']) === 0, 'Notification enqueue failure did not roll back its audit event.');

echo "Operations notification handoff checks passed.\n";
