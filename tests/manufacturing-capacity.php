<?php
declare(strict_types=1);

require __DIR__ . '/manufacturing-test-helper.php';

$requiredFunctions = [
    'yovel_admin_save_manufacturing_operation',
    'yovel_admin_save_manufacturing_routing',
    'yovel_admin_save_manufacturing_workstation_type',
    'yovel_admin_save_manufacturing_plant_floor',
    'yovel_admin_save_manufacturing_workstation',
    'yovel_admin_save_manufacturing_downtime',
    'yovel_admin_manufacturing_set_status',
    'yovel_admin_manufacturing_operations',
    'yovel_admin_manufacturing_routings',
    'yovel_admin_manufacturing_workstation_types',
    'yovel_admin_manufacturing_workstations',
    'yovel_admin_manufacturing_downtimes',
    'yovel_admin_manufacturing_schedule_capacity',
    'yovel_admin_manufacturing_plant_floor',
    'yovel_admin_manufacturing_report',
];
foreach ($requiredFunctions as $requiredFunction) {
    manufacturing_test_assert(function_exists($requiredFunction), 'Missing WP-03 interface: ' . $requiredFunction);
}

$scopeA = manufacturing_test_create_scope('capacity-a');
$scopeB = manufacturing_test_create_scope('capacity-b');
try {
    $_SESSION['builderx_csrf'] = $scopeA['csrf'];
    yovel_admin_manufacturing_schema();
    $base = ['csrf' => $scopeA['csrf'], 'module_view' => 'manufacturing'];

    $cut = yovel_admin_save_manufacturing_operation($scopeA['company'], $scopeA['admin'], $base + [
        'operation_code' => 'CUT', 'operation_name' => 'Cut material', 'description' => 'Primary cut',
        'hourly_rate' => '120.000000', 'cost_account_key' => '', 'record_status' => 'ACTIVE',
    ]);
    $inspect = yovel_admin_save_manufacturing_operation($scopeA['company'], $scopeA['admin'], $base + [
        'operation_code' => 'INSPECT', 'operation_name' => 'Inspect part', 'description' => '',
        'hourly_rate' => '90', 'cost_account_key' => '', 'record_status' => 'ACTIVE',
    ]);
    $assembly = yovel_admin_save_manufacturing_operation($scopeA['company'], $scopeA['admin'], $base + [
        'operation_code' => 'ASSEMBLE', 'operation_name' => 'Assembly', 'description' => 'Ordered sub-operations',
        'hourly_rate' => '180', 'cost_account_key' => '', 'record_status' => 'ACTIVE',
        'sub_operations' => [
            ['operation_key' => $inspect['operation_key'], 'sequence' => 20, 'duration_minutes' => '15'],
            ['operation_key' => $cut['operation_key'], 'sequence' => 10, 'duration_minutes' => '30'],
        ],
    ]);
    manufacturing_test_assert(($assembly['sub_operations'][0]['operation_key'] ?? '') === $cut['operation_key'], 'Sub-operations were not persisted and read back in deterministic sequence order.');
    manufacturing_test_assert(($assembly['hourly_rate'] ?? '') === '180.000000', 'Operation cost normalization/read-back failed.');

    $routing = yovel_admin_save_manufacturing_routing($scopeA['company'], $scopeA['admin'], $base + [
        'routing_code' => 'ROUTE-STD', 'routing_name' => 'Standard route', 'record_status' => 'ACTIVE',
        'operations' => [
            ['operation_key' => $inspect['operation_key'], 'sequence' => 20, 'duration_minutes' => '20'],
            ['operation_key' => $cut['operation_key'], 'sequence' => 10, 'duration_minutes' => '60'],
        ],
    ]);
    $routingUpdated = yovel_admin_save_manufacturing_routing($scopeA['company'], $scopeA['admin'], $base + [
        'routing_key' => $routing['routing_key'], 'routing_code' => 'ROUTE-STD', 'routing_name' => 'Reusable standard route',
        'record_status' => 'ACTIVE', 'operations' => [
            ['operation_key' => $cut['operation_key'], 'sequence' => 10, 'duration_minutes' => '45'],
            ['operation_key' => $inspect['operation_key'], 'sequence' => 20, 'duration_minutes' => '20'],
        ],
    ]);
    manufacturing_test_assert($routingUpdated['routing_key'] === $routing['routing_key'] && $routingUpdated['routing_name'] === 'Reusable standard route', 'Routing reuse did not preserve its stable key and updated read-back.');
    manufacturing_test_expect_error(
        fn () => yovel_admin_manufacturing_set_status($scopeA['company'], $scopeA['admin'], 'OPERATION', $cut['operation_key'], 'ARCHIVED'),
        'referenced by an active routing'
    );

    $type = yovel_admin_save_manufacturing_workstation_type($scopeA['company'], $scopeA['admin'], $base + [
        'workstation_type_code' => 'LASER', 'workstation_type_name' => 'Laser cutter', 'record_status' => 'ACTIVE',
        'operation_keys' => [$cut['operation_key']],
        'working_hours' => [
            ['weekday' => 1, 'start_time' => '08:00', 'end_time' => '12:00'],
            ['weekday' => 1, 'start_time' => '13:00', 'end_time' => '17:00'],
            ['weekday' => 2, 'start_time' => '08:00', 'end_time' => '17:00'],
        ],
        'operating_components' => [
            ['component_name' => 'Electricity', 'hourly_cost' => '30', 'cost_account_key' => ''],
        ],
    ]);
    manufacturing_test_assert(count($type['working_hours']) === 3 && ($type['working_hours'][1]['start_time'] ?? '') === '13:00:00', 'Workstation type working hours exact read-back failed.');
    manufacturing_test_assert(($type['operating_components'][0]['hourly_cost'] ?? '') === '30.000000', 'Workstation type operating component read-back failed.');

    $plantFloor = yovel_admin_save_manufacturing_plant_floor($scopeA['company'], $scopeA['admin'], $base + [
        'plant_floor_code' => 'NORTH', 'plant_floor_name' => 'North bay', 'record_status' => 'ACTIVE',
    ]);
    $gateway = yovel_admin_manufacturing_dependency_gateway([
        'account_validation' => static fn (): never => throw new RuntimeException('Finance account owner is offline.'),
    ]);
    manufacturing_test_expect_error(
        fn () => yovel_admin_save_manufacturing_workstation($scopeA['company'], $scopeA['admin'], $base + [
            'workstation_code' => 'WS-BLOCKED', 'workstation_name' => 'Blocked station',
            'workstation_type_key' => $type['workstation_type_key'], 'plant_floor_key' => $plantFloor['plant_floor_key'], 'capacity_units' => 1,
            'hourly_rate' => '100', 'cost_account_key' => 'finance-account', 'record_status' => 'ACTIVE',
        ], $gateway),
        'finance account owner is offline'
    );

    $station = yovel_admin_save_manufacturing_workstation($scopeA['company'], $scopeA['admin'], $base + [
        'workstation_code' => 'LASER-01', 'workstation_name' => 'Laser 01',
        'workstation_type_key' => $type['workstation_type_key'], 'plant_floor_key' => $plantFloor['plant_floor_key'],
        'capacity_units' => 1, 'hourly_rate' => '150', 'cost_account_key' => '',
        'holiday_dates' => ['2026-09-07'], 'record_status' => 'ACTIVE',
        'costs' => [['cost_type' => 'LABOUR', 'hourly_cost' => '40', 'cost_account_key' => '']],
        'operating_components' => [['component_name' => 'Assist gas', 'hourly_cost' => '12.5', 'cost_account_key' => '']],
    ]);
    manufacturing_test_assert(($station['plant_floor_name'] ?? '') === 'North bay' && count($station['costs']) === 1, 'Workstation, Plant Floor, or cost exact read-back failed.');

    $downtime = yovel_admin_save_manufacturing_downtime($scopeA['company'], $scopeA['admin'], $base + [
        'workstation_key' => $station['workstation_key'], 'starts_at' => '2026-08-31 09:00:00',
        'ends_at' => '2026-08-31 10:00:00', 'reason' => 'Planned lens service', 'record_status' => 'ACTIVE',
    ]);
    manufacturing_test_assert(($downtime['duration_minutes'] ?? 0) === 60, 'Downtime duration was not calculated from the persisted interval.');
    manufacturing_test_expect_error(
        fn () => yovel_admin_save_manufacturing_downtime($scopeA['company'], $scopeA['admin'], $base + [
            'workstation_key' => $station['workstation_key'], 'starts_at' => '2026-08-31 09:30:00',
            'ends_at' => '2026-08-31 10:30:00', 'reason' => 'Overlapping stop', 'record_status' => 'ACTIVE',
        ]),
        'overlaps'
    );

    $schedule = yovel_admin_manufacturing_schedule_capacity(
        $scopeA['company']['company_key'],
        [
            ['operation_ref' => 'LOT-1-CUT', 'operation_key' => $cut['operation_key'], 'workstation_type_key' => $type['workstation_type_key'], 'duration_minutes' => 120],
            ['operation_ref' => 'LOT-2-CUT', 'operation_key' => $cut['operation_key'], 'workstation_type_key' => $type['workstation_type_key'], 'duration_minutes' => 60],
        ],
        new DateTimeImmutable('2026-08-31 08:30:00')
    );
    manufacturing_test_assert(($schedule[0]['workstation_key'] ?? '') === $station['workstation_key'], 'Capacity scheduler did not select the eligible workstation deterministically.');
    manufacturing_test_assert(($schedule[0]['starts_at'] ?? '') === '2026-08-31 08:30:00' && ($schedule[0]['ends_at'] ?? '') === '2026-08-31 11:30:00', 'Capacity scheduler did not exclude downtime from elapsed allocation.');
    manufacturing_test_assert(($schedule[1]['starts_at'] ?? '') === '2026-08-31 11:30:00' && ($schedule[1]['ends_at'] ?? '') === '2026-08-31 13:30:00', 'Capacity scheduler did not reject overlap and skip the calendar break.');
    $holidaySchedule = yovel_admin_manufacturing_schedule_capacity(
        $scopeA['company']['company_key'],
        [['operation_ref' => 'HOLIDAY-CUT', 'operation_key' => $cut['operation_key'], 'workstation_key' => $station['workstation_key'], 'duration_minutes' => 30]],
        new DateTimeImmutable('2026-09-07 08:00:00')
    );
    manufacturing_test_assert(($holidaySchedule[0]['starts_at'] ?? '') === '2026-09-08 08:00:00', 'Capacity scheduler did not reject a workstation holiday.');

    $floorDuringStop = yovel_admin_manufacturing_plant_floor($scopeA['company'], new DateTimeImmutable('2026-08-31 09:30:00'));
    manufacturing_test_assert(($floorDuringStop[0]['state'] ?? '') === 'DOWN' && ($floorDuringStop[0]['reason'] ?? '') === 'Planned lens service', 'Plant Floor state is not derived from persisted downtime.');
    $floorAvailable = yovel_admin_manufacturing_plant_floor($scopeA['company'], new DateTimeImmutable('2026-08-31 10:30:00'));
    manufacturing_test_assert(($floorAvailable[0]['state'] ?? '') === 'AVAILABLE', 'Plant Floor did not recover after persisted downtime ended.');

    $analysis = yovel_admin_manufacturing_report($scopeA['company'], 'downtime-analysis', [
        'date_from' => '2026-08-31', 'date_to' => '2026-08-31',
    ]);
    manufacturing_test_assert(($analysis['total_downtime_minutes'] ?? 0) === 60, 'Downtime Analysis total is incorrect.');
    manufacturing_test_assert(($analysis['rows'][0]['utilization_percent'] ?? '') === '87.5000', 'Downtime Analysis utilization is not based on the working calendar.');
    $_GET['date_from'] = '2026-08-31';
    $_GET['date_to'] = '2026-08-31';
    $reportMarkup = manufacturing_test_render($scopeA, 'reports');
    unset($_GET['date_from'], $_GET['date_to']);
    manufacturing_test_assert(str_contains($reportMarkup, 'Downtime Analysis') && str_contains($reportMarkup, '87.5000%'), 'Downtime Analysis report UI is not server-backed.');

    manufacturing_test_assert(yovel_admin_manufacturing_operations($scopeB['company']) === [], 'Operations leaked across company scope.');
    manufacturing_test_assert(yovel_admin_manufacturing_workstations($scopeB['company']) === [], 'Workstations leaked across company scope.');

    $handler = yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'save_manufacturing_operation', $base + [
        'section' => 'operations', 'operation_code' => 'PACK', 'operation_name' => 'Pack',
        'hourly_rate' => '80', 'cost_account_key' => '', 'record_status' => 'ACTIVE',
    ]);
    manufacturing_test_assert(($handler['section'] ?? '') === 'operations' && yovel_admin_is_uuid((string) ($handler['query']['operation'] ?? '')), 'Exact POST handler did not route the WP-03 operation write.');
    manufacturing_test_expect_error(
        fn () => yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'save_manufacturing_operation', array_replace($base, ['csrf' => 'invalid', 'operation_code' => 'NO-CSRF', 'operation_name' => 'Rejected', 'hourly_rate' => '1'])),
        'request token'
    );
    $_SESSION['builderx_csrf'] = $scopeB['csrf'];
    manufacturing_test_expect_error(
        fn () => yovel_admin_manufacturing_handle_post($scopeB['company'], $scopeA['admin'], 'save_manufacturing_operation', ['csrf' => $scopeB['csrf'], 'module_view' => 'manufacturing', 'operation_code' => 'FOREIGN', 'operation_name' => 'Rejected', 'hourly_rate' => '1']),
        'authorized active company administrator'
    );
    $_SESSION['builderx_csrf'] = $scopeA['csrf'];

    $beforeFault = count(yovel_admin_manufacturing_operations($scopeA['company']));
    $GLOBALS['yovel_admin_manufacturing_fault'] = static function (string $point): void {
        if ($point === 'before_readback') {
            throw new RuntimeException('Injected capacity read-back failure.');
        }
    };
    manufacturing_test_expect_error(
        fn () => yovel_admin_save_manufacturing_operation($scopeA['company'], $scopeA['admin'], $base + [
            'operation_code' => 'ROLLBACK', 'operation_name' => 'Must roll back', 'hourly_rate' => '10',
            'cost_account_key' => '', 'record_status' => 'ACTIVE',
        ]),
        'read-back'
    );
    unset($GLOBALS['yovel_admin_manufacturing_fault']);
    manufacturing_test_assert(count(yovel_admin_manufacturing_operations($scopeA['company'])) === $beforeFault, 'WP-03 read-back failure did not roll back the owned record.');
} finally {
    manufacturing_test_cleanup_scope($scopeA);
    manufacturing_test_cleanup_scope($scopeB);
}

echo "Manufacturing capacity tests passed.\n";
