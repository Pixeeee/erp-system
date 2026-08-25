<?php
declare(strict_types=1);

require __DIR__ . '/manufacturing-test-helper.php';

$post = new ReflectionFunction('yovel_admin_manufacturing_handle_post');
manufacturing_test_assert($post->getNumberOfRequiredParameters() === 4 && $post->getNumberOfParameters() === 4, 'Manufacturing must expose the exact shared four-argument POST handler.');

$scopeA = manufacturing_test_create_scope('persistence-a');
$scopeB = manufacturing_test_create_scope('persistence-b');
try {
    $_SESSION['builderx_csrf'] = $scopeA['csrf'];
    yovel_admin_manufacturing_schema();
    $input = [
        'csrf' => $scopeA['csrf'],
        'module_view' => 'manufacturing',
        'section' => 'settings',
        'allow_overproduction_percent' => '10.5000',
        'capacity_planning_enabled' => '1',
        'default_wip_warehouse_key' => '',
        'default_finished_goods_warehouse_key' => '',
        'notes' => 'Initial setting',
    ];
    $created = yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'save_manufacturing_settings', $input);
    manufacturing_test_assert(($created['section'] ?? '') === 'settings', 'Settings handler returned the wrong section.');
    $saved = yovel_admin_manufacturing_settings($scopeA['company']);
    $stableKey = (string) $saved['setting_key'];
    manufacturing_test_assert($stableKey !== '' && ($saved['allow_overproduction_percent'] ?? '') === '10.5000', 'Manufacturing settings create/read-back failed.');
    manufacturing_test_assert((string) ($saved['company_key'] ?? '') === $scopeA['company']['company_key'], 'Manufacturing settings lost the opaque company key.');

    $input['allow_overproduction_percent'] = '12.2500';
    $input['notes'] = 'Updated setting';
    yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'save_manufacturing_settings', $input);
    $updated = yovel_admin_manufacturing_settings($scopeA['company']);
    manufacturing_test_assert(($updated['setting_key'] ?? '') === $stableKey, 'Manufacturing settings update did not preserve the stable key.');
    manufacturing_test_assert(($updated['allow_overproduction_percent'] ?? '') === '12.2500' && ($updated['notes'] ?? '') === 'Updated setting', 'Manufacturing settings update/read-back failed.');
    manufacturing_test_assert((int) bx_db()->GetOne('SELECT COUNT(*) FROM project_company_manufacturing_audit WHERE company_key_hash = ?', [$scopeA['company']['company_key_hash']]) === 2, 'Manufacturing create/update audit rows are incomplete.');

    manufacturing_test_assert(yovel_admin_manufacturing_settings($scopeB['company'])['setting_key'] === '', 'Manufacturing settings leaked across companies.');
    manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'save_manufacturing_settings', array_replace($input, ['module_view' => ''])), 'module scope');
    manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'save_manufacturing_settings', array_replace($input, ['allow_overproduction_percent' => ''])), 'percentage');
    manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'save_manufacturing_settings', array_replace($input, ['notes' => str_repeat('x', 2001)])), 'overlong');
    manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_handle_post($scopeB['company'], $scopeA['admin'], 'save_manufacturing_settings', array_replace($input, ['csrf' => $scopeB['csrf']])), 'authorized active company administrator');
    manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'save_manufacturing_settings', array_replace($input, ['csrf' => 'wrong'])), 'request token');
    manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'unknown_action', $input), 'unknown manufacturing action');

    $beforeFault = yovel_admin_manufacturing_settings($scopeA['company']);
    $beforeAudit = (int) bx_db()->GetOne('SELECT COUNT(*) FROM project_company_manufacturing_audit WHERE company_key_hash = ?', [$scopeA['company']['company_key_hash']]);
    $GLOBALS['yovel_admin_manufacturing_fault'] = static function (string $point): void {
        if ($point === 'before_readback') {
            throw new RuntimeException('Injected read-back failure.');
        }
    };
    $faultInput = $input;
    $faultInput['allow_overproduction_percent'] = '55.0000';
    manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'save_manufacturing_settings', $faultInput), 'read-back');
    unset($GLOBALS['yovel_admin_manufacturing_fault']);
    $afterFault = yovel_admin_manufacturing_settings($scopeA['company']);
    manufacturing_test_assert(($afterFault['allow_overproduction_percent'] ?? '') === ($beforeFault['allow_overproduction_percent'] ?? ''), 'Manufacturing transaction failure did not roll back the record.');
    manufacturing_test_assert((int) bx_db()->GetOne('SELECT COUNT(*) FROM project_company_manufacturing_audit WHERE company_key_hash = ?', [$scopeA['company']['company_key_hash']]) === $beforeAudit, 'Manufacturing transaction failure did not roll back audit.');

    manufacturing_test_expect_error(function () use ($scopeA, $stableKey): void {
        yovel_admin_manufacturing_with_transaction(
            (string) $scopeA['company']['company_key'],
            'MANUFACTURING_SETTINGS',
            'UPDATE',
            static function (ADOConnection $db) use ($scopeA, $stableKey): array {
                yovel_admin_manufacturing_execute($db, 'UPDATE project_company_manufacturing_setting SET notes=? WHERE company_key_hash=? AND setting_key=?', ['Read-back mismatch', $scopeA['company']['company_key_hash'], $stableKey], 'Manufacturing mismatch fixture update');
                return [
                    'record_key' => $stableKey,
                    'business_key' => 'manufacturing-settings',
                    'company_key' => $scopeA['company']['company_key'],
                    'company_key_hash' => $scopeA['company']['company_key_hash'],
                    'admin_key' => $scopeA['admin']['admin_key'],
                    'persisted_fields' => ['setting_key' => $stableKey, 'notes' => 'Read-back mismatch'],
                ];
            },
            static function (ADOConnection $db, array $expected) use ($scopeA): array {
                $row = (array) $db->GetRow('SELECT setting_key,notes FROM project_company_manufacturing_setting WHERE company_key_hash=? AND setting_key=?', [$scopeA['company']['company_key_hash'], $expected['record_key']]);
                $row['notes'] = 'Mismatched server value';
                return $row;
            }
        );
    }, 'exact read-back');
    manufacturing_test_assert((string) bx_db()->GetOne('SELECT notes FROM project_company_manufacturing_setting WHERE company_key_hash=?', [$scopeA['company']['company_key_hash']]) === 'Updated setting', 'Exact read-back mismatch did not roll back the domain mutation.');
    manufacturing_test_assert((int) bx_db()->GetOne('SELECT COUNT(*) FROM project_company_manufacturing_audit WHERE company_key_hash = ?', [$scopeA['company']['company_key_hash']]) === $beforeAudit, 'Exact read-back mismatch did not roll back its audit row.');

    manufacturing_test_expect_error(function () use ($scopeA): void {
        yovel_admin_manufacturing_with_transaction(
            (string) $scopeA['company']['company_key'],
            'MANUFACTURING_SETTINGS',
            'UPDATE',
            static function (ADOConnection $db) use ($scopeA): array {
                yovel_admin_manufacturing_execute($db, 'UPDATE project_company_manufacturing_setting SET notes=? WHERE company_key_hash=?', ['Must roll back', $scopeA['company']['company_key_hash']], 'Manufacturing rollback fixture update');
                yovel_admin_manufacturing_execute($db, 'INSERT INTO project_company_manufacturing_missing_table (missing_key) VALUES (?)', ['failure'], 'Manufacturing injected failed write');
                return [];
            },
            static fn (): array => []
        );
    }, 'could not be completed');
    manufacturing_test_assert((string) bx_db()->GetOne('SELECT notes FROM project_company_manufacturing_setting WHERE company_key_hash=?', [$scopeA['company']['company_key_hash']]) === 'Updated setting', 'A failed ADODB write did not roll back the domain mutation.');
    manufacturing_test_assert((int) bx_db()->GetOne('SELECT COUNT(*) FROM project_company_manufacturing_audit WHERE company_key_hash = ?', [$scopeA['company']['company_key_hash']]) === $beforeAudit, 'A failed ADODB write changed the audit history.');

    $safe = yovel_admin_manufacturing_rehydration_input(['csrf' => 'secret', 'notes' => 'keep', 'nested' => ['token' => 'secret', 'value' => 'keep nested']]);
    manufacturing_test_assert(!isset($safe['csrf']) && ($safe['notes'] ?? '') === 'keep' && ($safe['nested']['value'] ?? '') === 'keep nested', 'Manufacturing server rehydration filtering is invalid.');
} finally {
    manufacturing_test_cleanup_scope($scopeA);
    manufacturing_test_cleanup_scope($scopeB);
}

echo "Manufacturing persistence contract tests passed.\n";
