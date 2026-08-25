<?php
declare(strict_types=1);

$assetsModalData = is_array($assetsData ?? null) ? $assetsData : [];
$assetsModalState = is_array($assetsModalData['form_state'] ?? null) ? $assetsModalData['form_state'] : [];
$assetsModalPrior = is_array($assetsModalState['input'] ?? null) ? $assetsModalState['input'] : [];
$assetsSelectedForm = is_array($assetsModalData['selected_form'] ?? null) ? $assetsModalData['selected_form'] : [];
$assetsFormAdapter = is_array($assetsModalData['form_adapter'] ?? null) ? $assetsModalData['form_adapter'] : yovel_admin_assets_form_adapter();
$assetsRecordTypes = is_array($assetsFormAdapter['target_record_types'] ?? null) ? $assetsFormAdapter['target_record_types'] : yovel_admin_assets_form_record_types();
$assetsFormSchema = is_array($assetsModalData['active_form_schema'] ?? null) ? $assetsModalData['active_form_schema'] : yovel_admin_assets_default_form_schemas()['ASSET'];
$assetsModalEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$assetsModalValue = static fn (string $key, string $fallback = ''): string => (string) ($assetsModalPrior[$key] ?? $assetsSelectedForm[$key] ?? $fallback);
$assetsEditing = (string) ($assetsSelectedForm['form_key'] ?? '') !== '';
$assetsTarget = $assetsModalValue('target_section', (string) ($assetsSelectedForm['target_section'] ?? 'asset-records'));
$assetsRecordType = $assetsModalValue('record_type', (string) ($assetsSelectedForm['record_type'] ?? ($assetsRecordTypes[$assetsTarget] ?? 'ASSET')));
$assetsSchemaJson = $assetsModalValue('schema_json', yovel_admin_assets_form_json($assetsFormSchema));

ob_start();
?>
<?php if (trim((string) ($assetsModalState['error'] ?? '')) !== ''): ?><div role="alert" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= $assetsModalEscape((string) $assetsModalState['error']) ?></div><?php endif; ?>
<div class="grid gap-4 sm:grid-cols-2">
    <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Form title<input class="h-9 rounded-md border bg-background px-3" name="form_title" required maxlength="180" value="<?= $assetsModalEscape($assetsModalValue('form_title', 'Asset form')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Description<textarea class="min-h-20 rounded-md border bg-background px-3 py-2" name="form_description" maxlength="2000"><?= $assetsModalEscape($assetsModalValue('form_description')) ?></textarea></label>
    <label class="grid gap-1.5 text-sm font-medium">Target<select data-assets-target class="h-9 rounded-md border bg-background px-3" name="target_section"<?= $assetsEditing ? ' disabled' : '' ?>><?php foreach ($assetsRecordTypes as $target => $type): ?><option value="<?= $assetsModalEscape((string) $target) ?>" data-record-type="<?= $assetsModalEscape((string) $type) ?>"<?= $assetsTarget === $target ? ' selected' : '' ?>><?= $assetsModalEscape(ucwords(str_replace('-', ' ', (string) $target))) ?></option><?php endforeach; ?></select><?php if ($assetsEditing): ?><input type="hidden" name="target_section" value="<?= $assetsModalEscape($assetsTarget) ?>"><?php endif; ?></label>
    <label class="grid gap-1.5 text-sm font-medium">Stable field key<input class="h-9 rounded-md border bg-background px-3 font-mono text-sm" name="new_field_key" maxlength="80" value="<?= $assetsModalEscape($assetsModalValue('new_field_key')) ?>" placeholder="service_interval_days"></label>
    <label class="grid gap-1.5 text-sm font-medium">New field label<input class="h-9 rounded-md border bg-background px-3" name="new_field_label" maxlength="180" value="<?= $assetsModalEscape($assetsModalValue('new_field_label')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium">Field type<select class="h-9 rounded-md border bg-background px-3" name="new_field_type"><?php foreach ($assetsFormAdapter['field_types'] ?? [] as $type): ?><option value="<?= $assetsModalEscape((string) $type) ?>"<?= $assetsModalValue('new_field_type', 'SHORT_TEXT') === $type ? ' selected' : '' ?>><?= $assetsModalEscape(ucwords(strtolower(str_replace('_', ' ', (string) $type)))) ?></option><?php endforeach; ?></select></label>
    <label class="grid gap-1.5 text-sm font-medium">Section<input class="h-9 rounded-md border bg-background px-3" name="new_field_section" maxlength="80" value="<?= $assetsModalEscape($assetsModalValue('new_field_section', 'custom')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium">Column<select class="h-9 rounded-md border bg-background px-3" name="new_field_column"><?php foreach ([1, 2, 3] as $column): ?><option value="<?= $column ?>"<?= (int) $assetsModalValue('new_field_column', '1') === $column ? ' selected' : '' ?>><?= $column ?></option><?php endforeach; ?></select></label>
    <label class="grid gap-1.5 text-sm font-medium">Width<select class="h-9 rounded-md border bg-background px-3" name="new_field_width"><?php foreach (['THIRD', 'HALF', 'FULL'] as $width): ?><option value="<?= $width ?>"<?= $assetsModalValue('new_field_width', 'FULL') === $width ? ' selected' : '' ?>><?= ucfirst(strtolower($width)) ?></option><?php endforeach; ?></select></label>
    <label class="flex items-center gap-2 self-end pb-2 text-sm font-medium"><input type="checkbox" name="new_field_required" value="1"<?= !empty($assetsModalPrior['new_field_required']) ? ' checked' : '' ?>>Required field</label>
</div>
<div class="border-t pt-4"><?php require __DIR__ . '/form-builder.php'; ?></div>
<?php
$assetsModalBody = (string) ob_get_clean();
$recordModal = [
    'id' => 'assets-form-modal',
    'title' => $assetsEditing ? 'Edit Assets Form' : 'New Assets Form',
    'description' => 'Configure a company-owned, immutable form version.',
    'open_label' => 'New Form',
    'submit_label' => 'Save form version',
    'confirm_message' => 'Confirm this Assets form version before saving it.',
    'body_html' => $assetsModalBody,
    'hidden_html' => '<input type="hidden" name="csrf" value="' . $assetsModalEscape(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="assets-maintenance"><input type="hidden" name="action" value="save_assets_form"><input type="hidden" name="section" value="form-builder"><input type="hidden" name="form_key" value="' . $assetsModalEscape((string) ($assetsSelectedForm['form_key'] ?? '')) . '"><input type="hidden" data-assets-record-type name="record_type" value="' . $assetsModalEscape($assetsRecordType) . '"><input type="hidden" name="schema_json" value="' . $assetsModalEscape($assetsSchemaJson) . '">',
];
ob_start();
require dirname(__DIR__, 3) . '/views/partials/record-modal.php';
$assetsModalMarkup = (string) ob_get_clean();
$assetsModalMarkup = preg_replace('/^<button\b.*?<\/button>\s*/s', '', $assetsModalMarkup, 1) ?? $assetsModalMarkup;
if (!empty($assetsModalState['open'])) {
    $assetsModalMarkup = preg_replace('/data-record-modal hidden/', 'data-record-modal data-record-modal-open-on-load hidden', $assetsModalMarkup, 1) ?? $assetsModalMarkup;
}
echo $assetsModalMarkup;
?>
<script>
(() => {
    const modal = document.getElementById('assets-form-modal');
    const target = modal?.querySelector('[data-assets-target]');
    const recordType = modal?.querySelector('[data-assets-record-type]');
    if (!target || !recordType) return;
    target.addEventListener('change', () => {
        recordType.value = target.selectedOptions[0]?.dataset.recordType || 'ASSET';
    });
})();
</script>
