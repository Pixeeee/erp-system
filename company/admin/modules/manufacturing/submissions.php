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
    return match ($action) {
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
