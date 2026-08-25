<?php
declare(strict_types=1);

$capacityAction = (string) ($manufacturingState['action'] ?? '');
$capacityError = static fn (string $action): string => $capacityAction === $action ? (string) ($manufacturingState['error'] ?? '') : '';
$capacityInput = static fn (string $action): array => $capacityAction === $action && is_array($manufacturingInput) ? $manufacturingInput : [];
$capacityFind = static function (array $rows, string $key, string $value): ?array {
    foreach ($rows as $row) {
        if (is_array($row) && (string) ($row[$key] ?? '') === $value) {
            return $row;
        }
    }
    return null;
};
$capacityJson = static function (mixed $value) use ($manufacturingEscape): string {
    if (is_string($value)) {
        return $manufacturingEscape($value);
    }
    return $manufacturingEscape(yovel_admin_manufacturing_json(is_array($value) ? $value : []));
};
$capacityStatusOptions = static function (string $selected) use ($manufacturingEscape): string {
    $markup = '';
    foreach (['DRAFT' => 'Draft', 'ACTIVE' => 'Active', 'INACTIVE' => 'Inactive'] as $value => $label) {
        $markup .= '<option value="' . $value . '"' . ($selected === $value ? ' selected' : '') . '>' . $manufacturingEscape($label) . '</option>';
    }
    return $markup;
};
$capacityHidden = static fn (string $action, string $section): string => '<input type="hidden" name="csrf" value="' . $manufacturingEscape(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="manufacturing"><input type="hidden" name="action" value="' . $manufacturingEscape($action) . '"><input type="hidden" name="section" value="' . $manufacturingEscape($section) . '">';

if ($manufacturingSection === 'operations') {
    $operations = is_array($manufacturingData['operations'] ?? null) ? $manufacturingData['operations'] : [];
    $routings = is_array($manufacturingData['routings'] ?? null) ? $manufacturingData['routings'] : [];
    $selectedOperation = $capacityFind($operations, 'operation_key', trim((string) ($_GET['operation'] ?? '')));
    $operationValues = array_replace($selectedOperation ?? [
        'operation_key' => '', 'operation_code' => '', 'operation_name' => '', 'description' => '',
        'hourly_rate' => '0.000000', 'cost_account_key' => '', 'record_status' => 'ACTIVE', 'sub_operations' => [],
    ], $capacityInput('save_manufacturing_operation'));
    $operationError = $capacityError('save_manufacturing_operation');
    $operationChildren = array_map(static fn (array $row): array => ['operation_key' => (string) ($row['operation_key'] ?? ''), 'sequence' => (int) ($row['sequence'] ?? 0), 'duration_minutes' => (string) ($row['duration_minutes'] ?? '')], is_array($operationValues['sub_operations'] ?? null) ? $operationValues['sub_operations'] : []);
    $operationBody = ($operationError !== '' ? '<div role="alert" class="rounded-md bg-destructive/10 p-3 text-sm text-destructive">' . $manufacturingEscape($operationError) . '</div>' : '')
        . '<div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm"><span>Operation code</span><input class="h-9 rounded-md border bg-background px-3" name="operation_code" maxlength="80" required value="' . $manufacturingEscape((string) $operationValues['operation_code']) . '"></label>'
        . '<label class="grid gap-1.5 text-sm"><span>Status</span><select class="h-9 rounded-md border bg-background px-3" name="record_status" required>' . $capacityStatusOptions((string) $operationValues['record_status']) . '</select></label>'
        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Operation name</span><input class="h-9 rounded-md border bg-background px-3" name="operation_name" maxlength="180" required value="' . $manufacturingEscape((string) $operationValues['operation_name']) . '"></label>'
        . '<label class="grid gap-1.5 text-sm"><span>Hourly rate</span><input class="h-9 rounded-md border bg-background px-3" type="number" min="0" step="0.000001" name="hourly_rate" required value="' . $manufacturingEscape((string) $operationValues['hourly_rate']) . '"></label>'
        . '<label class="grid gap-1.5 text-sm"><span>Finance cost account key</span><input class="h-9 rounded-md border bg-background px-3" name="cost_account_key" maxlength="1500" value="' . $manufacturingEscape((string) ($operationValues['cost_account_key'] ?? '')) . '"></label>'
        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Description</span><textarea class="min-h-20 rounded-md border bg-background p-3" name="description" maxlength="1000">' . $manufacturingEscape((string) ($operationValues['description'] ?? '')) . '</textarea></label>'
        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Ordered sub-operations</span><textarea class="min-h-32 rounded-md border bg-background p-3 font-mono text-xs" name="sub_operations">' . $capacityJson($operationChildren) . '</textarea></label></div>';
    $manufacturingRenderModal([
        'id' => 'manufacturing-operation-modal', 'title' => $selectedOperation ? 'Edit Operation' : 'Add Operation',
        'description' => 'Company operation identity, cost, and ordered sub-operations.', 'open_label' => $selectedOperation ? 'Edit Operation' : 'Add Operation',
        'submit_label' => 'Submit', 'confirm_message' => 'Confirm this Manufacturing operation before saving.',
        'hidden_html' => $capacityHidden('save_manufacturing_operation', 'operations') . '<input type="hidden" name="operation_key" value="' . $manufacturingEscape((string) $operationValues['operation_key']) . '">',
        'body_html' => $operationBody,
    ], $capacityAction === 'save_manufacturing_operation' || $selectedOperation !== null);

    $selectedRouting = $capacityFind($routings, 'routing_key', trim((string) ($_GET['routing'] ?? '')));
    $routingValues = array_replace($selectedRouting ?? ['routing_key' => '', 'routing_code' => '', 'routing_name' => '', 'record_status' => 'ACTIVE', 'operations' => []], $capacityInput('save_manufacturing_routing'));
    $routingRows = array_map(static fn (array $row): array => ['operation_key' => (string) ($row['operation_key'] ?? ''), 'sequence' => (int) ($row['sequence'] ?? 0), 'duration_minutes' => (string) ($row['duration_minutes'] ?? '')], is_array($routingValues['operations'] ?? null) ? $routingValues['operations'] : []);
    $routingError = $capacityError('save_manufacturing_routing');
    $routingBody = ($routingError !== '' ? '<div role="alert" class="rounded-md bg-destructive/10 p-3 text-sm text-destructive">' . $manufacturingEscape($routingError) . '</div>' : '')
        . '<div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm"><span>Routing code</span><input class="h-9 rounded-md border bg-background px-3" name="routing_code" maxlength="80" required value="' . $manufacturingEscape((string) $routingValues['routing_code']) . '"></label>'
        . '<label class="grid gap-1.5 text-sm"><span>Status</span><select class="h-9 rounded-md border bg-background px-3" name="record_status" required>' . $capacityStatusOptions((string) $routingValues['record_status']) . '</select></label>'
        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Routing name</span><input class="h-9 rounded-md border bg-background px-3" name="routing_name" maxlength="180" required value="' . $manufacturingEscape((string) $routingValues['routing_name']) . '"></label>'
        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Ordered operations</span><textarea class="min-h-40 rounded-md border bg-background p-3 font-mono text-xs" name="operations" required>' . $capacityJson($routingRows) . '</textarea></label></div>';
    $manufacturingRenderModal([
        'id' => 'manufacturing-routing-modal', 'title' => $selectedRouting ? 'Edit Routing' : 'Add Routing',
        'description' => 'Reusable ordered operation path with standard durations.', 'open_label' => $selectedRouting ? 'Edit Routing' : 'Add Routing',
        'submit_label' => 'Submit', 'confirm_message' => 'Confirm this Manufacturing routing before saving.',
        'hidden_html' => $capacityHidden('save_manufacturing_routing', 'operations') . '<input type="hidden" name="routing_key" value="' . $manufacturingEscape((string) $routingValues['routing_key']) . '">',
        'body_html' => $routingBody,
    ], $capacityAction === 'save_manufacturing_routing' || $selectedRouting !== null);
} elseif ($manufacturingSection === 'workstations') {
    $types = is_array($manufacturingData['workstation_types'] ?? null) ? $manufacturingData['workstation_types'] : [];
    $floors = is_array($manufacturingData['plant_floors'] ?? null) ? $manufacturingData['plant_floors'] : [];
    $stations = is_array($manufacturingData['workstations'] ?? null) ? $manufacturingData['workstations'] : [];

    $selectedType = $capacityFind($types, 'workstation_type_key', trim((string) ($_GET['workstation_type'] ?? '')));
    $typeValues = array_replace($selectedType ?? ['workstation_type_key' => '', 'workstation_type_code' => '', 'workstation_type_name' => '', 'record_status' => 'ACTIVE', 'operation_keys' => [], 'working_hours' => [], 'operating_components' => []], $capacityInput('save_manufacturing_workstation_type'));
    $typeBody = ($capacityError('save_manufacturing_workstation_type') !== '' ? '<div role="alert" class="rounded-md bg-destructive/10 p-3 text-sm text-destructive">' . $manufacturingEscape($capacityError('save_manufacturing_workstation_type')) . '</div>' : '')
        . '<div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm"><span>Type code</span><input class="h-9 rounded-md border bg-background px-3" name="workstation_type_code" maxlength="80" required value="' . $manufacturingEscape((string) $typeValues['workstation_type_code']) . '"></label>'
        . '<label class="grid gap-1.5 text-sm"><span>Status</span><select class="h-9 rounded-md border bg-background px-3" name="record_status" required>' . $capacityStatusOptions((string) $typeValues['record_status']) . '</select></label>'
        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Type name</span><input class="h-9 rounded-md border bg-background px-3" name="workstation_type_name" maxlength="180" required value="' . $manufacturingEscape((string) $typeValues['workstation_type_name']) . '"></label>'
        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Operation keys</span><textarea class="min-h-20 rounded-md border bg-background p-3 font-mono text-xs" name="operation_keys">' . $capacityJson($typeValues['operation_keys'] ?? []) . '</textarea></label>'
        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Working hours</span><textarea class="min-h-32 rounded-md border bg-background p-3 font-mono text-xs" name="working_hours" required>' . $capacityJson($typeValues['working_hours'] ?? []) . '</textarea></label>'
        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Operating components</span><textarea class="min-h-28 rounded-md border bg-background p-3 font-mono text-xs" name="operating_components">' . $capacityJson($typeValues['operating_components'] ?? []) . '</textarea></label></div>';
    $manufacturingRenderModal(['id' => 'manufacturing-workstation-type-modal', 'title' => $selectedType ? 'Edit Workstation Type' : 'Add Workstation Type', 'description' => 'Eligible operations, weekly working hours, and type-level operating components.', 'open_label' => $selectedType ? 'Edit Type' : 'Add Type', 'submit_label' => 'Submit', 'confirm_message' => 'Confirm this Workstation Type before saving.', 'hidden_html' => $capacityHidden('save_manufacturing_workstation_type', 'workstations') . '<input type="hidden" name="workstation_type_key" value="' . $manufacturingEscape((string) $typeValues['workstation_type_key']) . '">', 'body_html' => $typeBody], $capacityAction === 'save_manufacturing_workstation_type' || $selectedType !== null);

    $selectedFloor = $capacityFind($floors, 'plant_floor_key', trim((string) ($_GET['plant_floor'] ?? '')));
    $floorValues = array_replace($selectedFloor ?? ['plant_floor_key' => '', 'plant_floor_code' => '', 'plant_floor_name' => '', 'record_status' => 'ACTIVE'], $capacityInput('save_manufacturing_plant_floor'));
    $floorBody = ($capacityError('save_manufacturing_plant_floor') !== '' ? '<div role="alert" class="rounded-md bg-destructive/10 p-3 text-sm text-destructive">' . $manufacturingEscape($capacityError('save_manufacturing_plant_floor')) . '</div>' : '')
        . '<div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm"><span>Floor code</span><input class="h-9 rounded-md border bg-background px-3" name="plant_floor_code" maxlength="80" required value="' . $manufacturingEscape((string) $floorValues['plant_floor_code']) . '"></label><label class="grid gap-1.5 text-sm"><span>Status</span><select class="h-9 rounded-md border bg-background px-3" name="record_status" required>' . $capacityStatusOptions((string) $floorValues['record_status']) . '</select></label><label class="grid gap-1.5 text-sm sm:col-span-2"><span>Floor name</span><input class="h-9 rounded-md border bg-background px-3" name="plant_floor_name" maxlength="180" required value="' . $manufacturingEscape((string) $floorValues['plant_floor_name']) . '"></label></div>';
    $manufacturingRenderModal(['id' => 'manufacturing-plant-floor-modal', 'title' => $selectedFloor ? 'Edit Plant Floor' : 'Add Plant Floor', 'description' => 'Company production-floor identity for workstation placement.', 'open_label' => $selectedFloor ? 'Edit Floor' : 'Add Floor', 'submit_label' => 'Submit', 'confirm_message' => 'Confirm this Plant Floor before saving.', 'hidden_html' => $capacityHidden('save_manufacturing_plant_floor', 'workstations') . '<input type="hidden" name="plant_floor_key" value="' . $manufacturingEscape((string) $floorValues['plant_floor_key']) . '">', 'body_html' => $floorBody], $capacityAction === 'save_manufacturing_plant_floor' || $selectedFloor !== null);

    $selectedStation = $capacityFind($stations, 'workstation_key', trim((string) ($_GET['workstation'] ?? '')));
    $stationValues = array_replace($selectedStation ?? ['workstation_key' => '', 'workstation_code' => '', 'workstation_name' => '', 'workstation_type_key' => '', 'plant_floor_key' => '', 'capacity_units' => 1, 'hourly_rate' => '0.000000', 'cost_account_key' => '', 'holiday_dates' => [], 'costs' => [], 'operating_components' => [], 'record_status' => 'ACTIVE'], $capacityInput('save_manufacturing_workstation'));
    $typeOptions = '<option value="">Select type</option>';
    foreach ($types as $type) {
        $typeOptions .= '<option value="' . $manufacturingEscape((string) $type['workstation_type_key']) . '"' . ((string) $stationValues['workstation_type_key'] === (string) $type['workstation_type_key'] ? ' selected' : '') . '>' . $manufacturingEscape((string) $type['workstation_type_code'] . ' - ' . (string) $type['workstation_type_name']) . '</option>';
    }
    $floorOptions = '<option value="">Select floor</option>';
    foreach ($floors as $floor) {
        $floorOptions .= '<option value="' . $manufacturingEscape((string) $floor['plant_floor_key']) . '"' . ((string) $stationValues['plant_floor_key'] === (string) $floor['plant_floor_key'] ? ' selected' : '') . '>' . $manufacturingEscape((string) $floor['plant_floor_code'] . ' - ' . (string) $floor['plant_floor_name']) . '</option>';
    }
    $stationBody = ($capacityError('save_manufacturing_workstation') !== '' ? '<div role="alert" class="rounded-md bg-destructive/10 p-3 text-sm text-destructive">' . $manufacturingEscape($capacityError('save_manufacturing_workstation')) . '</div>' : '')
        . '<div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm"><span>Workstation code</span><input class="h-9 rounded-md border bg-background px-3" name="workstation_code" maxlength="80" required value="' . $manufacturingEscape((string) $stationValues['workstation_code']) . '"></label><label class="grid gap-1.5 text-sm"><span>Status</span><select class="h-9 rounded-md border bg-background px-3" name="record_status" required>' . $capacityStatusOptions((string) $stationValues['record_status']) . '</select></label>'
        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Workstation name</span><input class="h-9 rounded-md border bg-background px-3" name="workstation_name" maxlength="180" required value="' . $manufacturingEscape((string) $stationValues['workstation_name']) . '"></label><label class="grid gap-1.5 text-sm"><span>Workstation Type</span><select class="h-9 rounded-md border bg-background px-3" name="workstation_type_key" required>' . $typeOptions . '</select></label><label class="grid gap-1.5 text-sm"><span>Plant Floor</span><select class="h-9 rounded-md border bg-background px-3" name="plant_floor_key" required>' . $floorOptions . '</select></label>'
        . '<label class="grid gap-1.5 text-sm"><span>Capacity units</span><input class="h-9 rounded-md border bg-background px-3" type="number" min="1" max="100000" name="capacity_units" required value="' . $manufacturingEscape((string) $stationValues['capacity_units']) . '"></label><label class="grid gap-1.5 text-sm"><span>Hourly rate</span><input class="h-9 rounded-md border bg-background px-3" type="number" min="0" step="0.000001" name="hourly_rate" required value="' . $manufacturingEscape((string) $stationValues['hourly_rate']) . '"></label>'
        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Finance cost account key</span><input class="h-9 rounded-md border bg-background px-3" name="cost_account_key" maxlength="1500" value="' . $manufacturingEscape((string) $stationValues['cost_account_key']) . '"></label><label class="grid gap-1.5 text-sm sm:col-span-2"><span>Holiday dates</span><textarea class="min-h-20 rounded-md border bg-background p-3 font-mono text-xs" name="holiday_dates">' . $capacityJson($stationValues['holiday_dates'] ?? []) . '</textarea></label><label class="grid gap-1.5 text-sm sm:col-span-2"><span>Workstation costs</span><textarea class="min-h-28 rounded-md border bg-background p-3 font-mono text-xs" name="costs">' . $capacityJson($stationValues['costs'] ?? []) . '</textarea></label><label class="grid gap-1.5 text-sm sm:col-span-2"><span>Operating components</span><textarea class="min-h-28 rounded-md border bg-background p-3 font-mono text-xs" name="operating_components">' . $capacityJson($stationValues['operating_components'] ?? []) . '</textarea></label></div>';
    $manufacturingRenderModal(['id' => 'manufacturing-workstation-modal', 'title' => $selectedStation ? 'Edit Workstation' : 'Add Workstation', 'description' => 'Capacity, cost, calendar exceptions, and Plant Floor placement.', 'open_label' => $selectedStation ? 'Edit Workstation' : 'Add Workstation', 'submit_label' => 'Submit', 'confirm_message' => 'Confirm this Manufacturing workstation before saving.', 'hidden_html' => $capacityHidden('save_manufacturing_workstation', 'workstations') . '<input type="hidden" name="workstation_key" value="' . $manufacturingEscape((string) $stationValues['workstation_key']) . '">', 'body_html' => $stationBody], $capacityAction === 'save_manufacturing_workstation' || $selectedStation !== null);

    $downtimeValues = array_replace(['downtime_key' => '', 'workstation_key' => '', 'starts_at' => '', 'ends_at' => '', 'reason' => '', 'record_status' => 'ACTIVE'], $capacityInput('save_manufacturing_downtime'));
    $stationOptions = '<option value="">Select workstation</option>';
    foreach ($stations as $station) {
        $stationOptions .= '<option value="' . $manufacturingEscape((string) $station['workstation_key']) . '"' . ((string) $downtimeValues['workstation_key'] === (string) $station['workstation_key'] ? ' selected' : '') . '>' . $manufacturingEscape((string) $station['workstation_code'] . ' - ' . (string) $station['workstation_name']) . '</option>';
    }
    $downtimeBody = ($capacityError('save_manufacturing_downtime') !== '' ? '<div role="alert" class="rounded-md bg-destructive/10 p-3 text-sm text-destructive">' . $manufacturingEscape($capacityError('save_manufacturing_downtime')) . '</div>' : '')
        . '<div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm sm:col-span-2"><span>Workstation</span><select class="h-9 rounded-md border bg-background px-3" name="workstation_key" required>' . $stationOptions . '</select></label><label class="grid gap-1.5 text-sm"><span>Starts at</span><input class="h-9 rounded-md border bg-background px-3" type="datetime-local" name="starts_at" required value="' . $manufacturingEscape(str_replace(' ', 'T', substr((string) $downtimeValues['starts_at'], 0, 16))) . '"></label><label class="grid gap-1.5 text-sm"><span>Ends at</span><input class="h-9 rounded-md border bg-background px-3" type="datetime-local" name="ends_at" required value="' . $manufacturingEscape(str_replace(' ', 'T', substr((string) $downtimeValues['ends_at'], 0, 16))) . '"></label><label class="grid gap-1.5 text-sm sm:col-span-2"><span>Reason</span><textarea class="min-h-24 rounded-md border bg-background p-3" name="reason" maxlength="500" required>' . $manufacturingEscape((string) $downtimeValues['reason']) . '</textarea></label><label class="grid gap-1.5 text-sm"><span>Status</span><select class="h-9 rounded-md border bg-background px-3" name="record_status" required>' . $capacityStatusOptions((string) $downtimeValues['record_status']) . '</select></label></div>';
    $manufacturingRenderModal(['id' => 'manufacturing-downtime-modal', 'title' => 'Add Downtime Entry', 'description' => 'Persisted workstation outage used by capacity and utilization.', 'open_label' => 'Add Downtime', 'submit_label' => 'Submit', 'confirm_message' => 'Confirm this Downtime Entry before saving.', 'hidden_html' => $capacityHidden('save_manufacturing_downtime', 'workstations') . '<input type="hidden" name="downtime_key" value="' . $manufacturingEscape((string) $downtimeValues['downtime_key']) . '">', 'body_html' => $downtimeBody], $capacityAction === 'save_manufacturing_downtime');
}
