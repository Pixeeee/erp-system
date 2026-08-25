<?php
declare(strict_types=1);

function yovel_admin_manufacturing_handle_post(
    array $company,
    array $admin,
    string $action,
    array $input
): array {
    yovel_admin_manufacturing_scope($company, $admin);
    if (strtolower(trim((string) ($input['module_view'] ?? ''))) !== 'manufacturing') {
        throw new InvalidArgumentException('Manufacturing module scope is invalid.');
    }
    yovel_admin_manufacturing_assert_csrf($input);
    $action = strtolower(trim($action));
    $dependencyOverrides = is_array($GLOBALS['yovel_admin_manufacturing_dependency_overrides'] ?? null)
        ? $GLOBALS['yovel_admin_manufacturing_dependency_overrides']
        : [];
    $bomContext = ['company' => $company, 'admin' => $admin, 'gateway' => yovel_admin_manufacturing_dependency_gateway($dependencyOverrides)];
    return match ($action) {
        'save_manufacturing_bom' => (static function () use ($bomContext, $input): array {
            $saved = yovel_admin_manufacturing_save_bom($bomContext, $input);
            return ['message' => 'Manufacturing BOM saved.', 'section' => 'boms', 'query' => ['bom' => (string) $saved['bom_key']]];
        })(),
        'submit_manufacturing_bom' => (static function () use ($bomContext, $input): array {
            $saved = yovel_admin_manufacturing_submit_bom($bomContext, (string) ($input['bom_key'] ?? ''), (string) ($input['valuation_date'] ?? ''));
            return ['message' => 'Manufacturing BOM submitted.', 'section' => 'boms', 'query' => ['bom' => (string) $saved['bom_key']]];
        })(),
        'cancel_manufacturing_bom' => (static function () use ($bomContext, $input): array {
            $saved = yovel_admin_manufacturing_cancel_bom($bomContext, (string) ($input['bom_key'] ?? ''), (string) ($input['cancellation_reason'] ?? ''));
            return ['message' => 'Manufacturing BOM cancelled.', 'section' => 'boms', 'query' => ['bom' => (string) $saved['bom_key']]];
        })(),
        'amend_manufacturing_bom' => (static function () use ($bomContext, $input): array {
            $saved = yovel_admin_manufacturing_amend_bom($bomContext, (string) ($input['bom_key'] ?? ''), [
                'bom_code' => (string) ($input['bom_code'] ?? ''),
                'effective_from' => (string) ($input['effective_from'] ?? ''),
                'effective_to' => (string) ($input['effective_to'] ?? ''),
            ]);
            return ['message' => 'Manufacturing BOM amendment created.', 'section' => 'boms', 'query' => ['bom' => (string) $saved['bom_key']]];
        })(),
        'update_manufacturing_bom_cost' => (static function () use ($bomContext, $input): array {
            $saved = yovel_admin_manufacturing_update_bom_cost($bomContext, (string) ($input['bom_key'] ?? ''), (string) ($input['valuation_date'] ?? ''));
            return ['message' => 'Manufacturing BOM cost updated.', 'section' => 'boms', 'query' => ['bom' => (string) $saved['bom_key']]];
        })(),
        'save_manufacturing_settings' => (static function () use ($company, $admin, $input): array {
            $saved = yovel_admin_save_manufacturing_settings($company, $admin, $input);
            return ['message' => 'Manufacturing settings saved.', 'section' => 'settings', 'query' => ['setting' => (string) $saved['setting_key']]];
        })(),
        'save_manufacturing_form' => (static function () use ($company, $admin, $input): array {
            $saved = yovel_admin_save_manufacturing_form($company, $admin, $input);
            return ['message' => (string) $saved['form_status'] === 'PUBLISHED' ? 'Manufacturing form published.' : 'Manufacturing form draft saved.', 'section' => 'form-builder', 'query' => ['form' => (string) $saved['form_key'], 'target' => (string) $saved['target_section']]];
        })(),
        'archive_manufacturing_form' => (static function () use ($company, $admin, $input): array {
            yovel_admin_archive_manufacturing_form($company, $admin, (string) ($input['form_key'] ?? ''));
            return ['message' => 'Manufacturing form archived.', 'section' => 'form-builder', 'query' => ['target' => yovel_admin_manufacturing_section((string) ($input['target_section'] ?? 'boms'))]];
        })(),
        'save_manufacturing_operation' => (static function () use ($company, $admin, $input): array {
            $saved = yovel_admin_save_manufacturing_operation($company, $admin, $input);
            return ['message' => 'Manufacturing operation saved.', 'section' => 'operations', 'query' => ['operation' => (string) $saved['operation_key']]];
        })(),
        'save_manufacturing_routing' => (static function () use ($company, $admin, $input): array {
            $saved = yovel_admin_save_manufacturing_routing($company, $admin, $input);
            return ['message' => 'Manufacturing routing saved.', 'section' => 'operations', 'query' => ['routing' => (string) $saved['routing_key']]];
        })(),
        'save_manufacturing_workstation_type' => (static function () use ($company, $admin, $input): array {
            $saved = yovel_admin_save_manufacturing_workstation_type($company, $admin, $input);
            return ['message' => 'Workstation Type saved.', 'section' => 'workstations', 'query' => ['workstation_type' => (string) $saved['workstation_type_key']]];
        })(),
        'save_manufacturing_plant_floor' => (static function () use ($company, $admin, $input): array {
            $saved = yovel_admin_save_manufacturing_plant_floor($company, $admin, $input);
            return ['message' => 'Plant Floor saved.', 'section' => 'workstations', 'query' => ['plant_floor' => (string) $saved['plant_floor_key']]];
        })(),
        'save_manufacturing_workstation' => (static function () use ($company, $admin, $input): array {
            $saved = yovel_admin_save_manufacturing_workstation($company, $admin, $input);
            return ['message' => 'Manufacturing workstation saved.', 'section' => 'workstations', 'query' => ['workstation' => (string) $saved['workstation_key']]];
        })(),
        'save_manufacturing_downtime' => (static function () use ($company, $admin, $input): array {
            $saved = yovel_admin_save_manufacturing_downtime($company, $admin, $input);
            return ['message' => 'Downtime Entry saved.', 'section' => 'workstations', 'query' => ['downtime' => (string) $saved['downtime_key']]];
        })(),
        'set_manufacturing_status' => (static function () use ($company, $admin, $input): array {
            $type = strtoupper(trim((string) ($input['record_type'] ?? '')));
            $key = (string) ($input['record_key'] ?? '');
            $status = (string) ($input['record_status'] ?? '');
            yovel_admin_manufacturing_set_status($company, $admin, $type, $key, $status);
            $section = in_array($type, ['OPERATION', 'ROUTING'], true) ? 'operations' : 'workstations';
            return ['message' => 'Manufacturing record status updated.', 'section' => $section, 'query' => []];
        })(),
        default => throw new InvalidArgumentException('Unknown Manufacturing action.'),
    };
}

function yovel_admin_manufacturing_handle_submission(string $action): array
{
    $company = $GLOBALS['company'] ?? null;
    $admin = $GLOBALS['postAdmin'] ?? ($GLOBALS['admin'] ?? null);
    if (!is_array($company) || !is_array($admin)) {
        throw new RuntimeException('Manufacturing submission context is unavailable.');
    }
    return yovel_admin_manufacturing_handle_post($company, $admin, $action, $_POST);
}
