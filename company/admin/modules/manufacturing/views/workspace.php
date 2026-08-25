<?php
declare(strict_types=1);

$manufacturingData = is_array($activeModuleData ?? null) ? $activeModuleData : [];
$manufacturingSections = is_array($activeModuleSections ?? null) && $activeModuleSections !== [] ? $activeModuleSections : yovel_admin_manufacturing_sections();
$manufacturingSection = yovel_admin_manufacturing_section((string) ($manufacturingData['section'] ?? $activeModuleSection ?? 'dashboard'));
$manufacturingMeta = $manufacturingSections[$manufacturingSection] ?? $manufacturingSections['dashboard'];
$manufacturingState = is_array($activeModuleFormState ?? null) && (string) ($activeModuleFormState['section'] ?? '') === $manufacturingSection ? $activeModuleFormState : [];
$manufacturingInput = is_array($manufacturingState['input'] ?? null) ? $manufacturingState['input'] : [];
$manufacturingEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$manufacturingSharedModal = dirname(__DIR__, 3) . '/views/partials/record-modal.php';
$manufacturingRenderModal = static function (array $definition, bool $open) use ($manufacturingSharedModal): void {
    $recordModal = $definition;
    ob_start();
    require $manufacturingSharedModal;
    $markup = (string) ob_get_clean();
    if ($open) {
        $markup = preg_replace('/data-record-modal hidden/', 'data-record-modal data-record-modal-open-on-load hidden', $markup, 1) ?? $markup;
    }
    echo $markup;
};
$manufacturingSettings = is_array($manufacturingData['settings'] ?? null) ? $manufacturingData['settings'] : [];
$manufacturingFormSchema = is_array($manufacturingData['form_schema'] ?? null) ? $manufacturingData['form_schema'] : [];
$manufacturingTarget = (string) ($manufacturingData['selected_target'] ?? 'boms');
$manufacturingForms = is_array($manufacturingData['forms'] ?? null) ? $manufacturingData['forms'] : [];
$manufacturingSelectedForm = is_array($manufacturingData['selected_form'] ?? null) ? $manufacturingData['selected_form'] : null;
$manufacturingSelectedFormKey = trim((string) ($_GET['form'] ?? ''));
foreach ($manufacturingSelectedForm === null ? $manufacturingForms : [] as $candidate) {
    if ((string) ($candidate['form_key'] ?? '') === $manufacturingSelectedFormKey) {
        $manufacturingSelectedForm = $candidate;
        $manufacturingTarget = (string) $candidate['target_section'];
        break;
    }
}
?>
<div data-manufacturing-workspace class="grid min-h-0 gap-4">
    <style>[data-manufacturing-workspace] .yovel-record-modal{z-index:90}@media (min-width:1024px){[data-manufacturing-panels]{grid-template-columns:minmax(0, 12fr) minmax(16rem, 8fr)}[data-manufacturing-panels]>[data-manufacturing-main],[data-manufacturing-panels]>[data-manufacturing-tools]{height:max(32rem,calc(100dvh - 16rem))}}</style>
    <header class="flex flex-wrap items-start justify-between gap-4 border-b pb-4">
        <div><p class="text-xs font-medium uppercase text-muted-foreground">Manufacturing</p><h1 class="mt-1 text-xl font-semibold"><?= $manufacturingEscape((string) $manufacturingMeta['label']) ?></h1></div>
        <a href="?view=manufacturing&section=form-builder&target=<?= $manufacturingEscape($manufacturingSection === 'form-builder' ? $manufacturingTarget : (array_key_exists($manufacturingSection, yovel_admin_manufacturing_form_targets()) ? $manufacturingSection : 'boms')) ?>" class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">dynamic_form</span>Form Builder</a>
    </header>
    <nav class="flex gap-1 overflow-x-auto border-b pb-2" aria-label="Manufacturing sections">
        <?php foreach ($manufacturingSections as $sectionKey => $sectionMeta): ?><a href="?view=manufacturing&section=<?= $manufacturingEscape((string) $sectionKey) ?>" class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md px-3 text-sm <?= $sectionKey === $manufacturingSection ? 'bg-secondary font-medium text-secondary-foreground' : 'text-muted-foreground hover:bg-muted' ?>"<?= $sectionKey === $manufacturingSection ? ' aria-current="page"' : '' ?>><span class="material-symbols-rounded text-base" aria-hidden="true"><?= $manufacturingEscape((string) $sectionMeta['icon']) ?></span><?= $manufacturingEscape((string) $sectionMeta['label']) ?></a><?php endforeach; ?>
    </nav>

    <div data-manufacturing-panels class="grid min-h-0 gap-4">
        <main data-manufacturing-main data-grid-span="12" class="flex min-h-[32rem] min-w-0 flex-col overflow-hidden rounded-lg border bg-card">
            <header class="border-b px-5 py-4"><h2 class="text-base font-semibold"><?= $manufacturingEscape((string) $manufacturingMeta['label']) ?></h2><p class="mt-1 text-sm text-muted-foreground"><?= $manufacturingEscape((string) ($manufacturingData['state']['message'] ?? 'Company-scoped production workspace.')) ?></p></header>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                <?php if ($manufacturingSection === 'dashboard'): require __DIR__ . '/dashboard.php'; ?>
                <?php elseif ($manufacturingSection === 'form-builder'): require __DIR__ . '/form-builder.php'; ?>
                <?php elseif ($manufacturingSection === 'reports'): require __DIR__ . '/reports.php'; ?>
                <?php else: require __DIR__ . '/records.php'; endif; ?>
            </div>
        </main>

        <aside data-manufacturing-tools data-grid-span="8" class="flex min-h-[32rem] min-w-0 flex-col overflow-hidden rounded-lg border bg-card">
            <header class="border-b px-5 py-4"><h2 class="text-base font-semibold">Actions and tools</h2><p class="mt-1 text-sm text-muted-foreground"><?= $manufacturingEscape((string) ($manufacturingData['company_name'] ?? $companyName ?? 'Company')) ?></p></header>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                <?php if ($manufacturingSection === 'dashboard'): ?>
                    <?php $manufacturingDashboardRegion = 'tools'; require __DIR__ . '/dashboard.php'; unset($manufacturingDashboardRegion); ?>
                <?php elseif ($manufacturingSection === 'boms'): ?>
                    <?php require __DIR__ . '/bom-tools.php'; ?>
                <?php elseif (in_array($manufacturingSection, ['operations', 'workstations'], true)): ?>
                    <div class="flex flex-wrap gap-2"><?php require __DIR__ . '/capacity-tools.php'; ?></div>
                <?php elseif ($manufacturingSection === 'settings'): ?>
                    <?php
                    $settingsValues = array_merge($manufacturingSettings, $manufacturingInput);
                    $settingsError = (string) ($manufacturingState['error'] ?? '');
                    $settingsBody = ($settingsError !== '' ? '<div role="alert" class="rounded-md bg-destructive/10 p-3 text-sm text-destructive">' . $manufacturingEscape($settingsError) . '</div>' : '')
                        . '<div class="grid gap-4 sm:grid-cols-2">'
                        . '<label class="grid gap-1.5 text-sm"><span>Overproduction limit (%)</span><input class="h-9 rounded-md border bg-background px-3" type="number" min="0" max="100" step="0.0001" name="allow_overproduction_percent" required value="' . $manufacturingEscape((string) ($settingsValues['allow_overproduction_percent'] ?? '0.0000')) . '"></label>'
                        . '<label class="flex items-center gap-2 self-end pb-2 text-sm"><input type="checkbox" name="capacity_planning_enabled" value="1" ' . (!empty($settingsValues['capacity_planning_enabled']) ? 'checked' : '') . '>Capacity planning</label>'
                        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>WIP warehouse key</span><input class="h-9 rounded-md border bg-background px-3" name="default_wip_warehouse_key" maxlength="1500" value="' . $manufacturingEscape((string) ($settingsValues['default_wip_warehouse_key'] ?? '')) . '"></label>'
                        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Finished goods warehouse key</span><input class="h-9 rounded-md border bg-background px-3" name="default_finished_goods_warehouse_key" maxlength="1500" value="' . $manufacturingEscape((string) ($settingsValues['default_finished_goods_warehouse_key'] ?? '')) . '"></label>'
                        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Notes</span><textarea class="min-h-28 rounded-md border bg-background p-3" name="notes" maxlength="2000">' . $manufacturingEscape((string) ($settingsValues['notes'] ?? '')) . '</textarea></label></div>';
                    $manufacturingRenderModal([
                        'id' => 'manufacturing-settings-modal', 'title' => 'Manufacturing Settings', 'description' => 'Company production defaults and planning controls.',
                        'open_label' => (string) ($manufacturingSettings['setting_key'] ?? '') !== '' ? 'Edit Settings' : 'Add Settings', 'submit_label' => 'Submit',
                        'confirm_message' => 'Confirm these Manufacturing settings before saving.',
                        'hidden_html' => '<input type="hidden" name="csrf" value="' . $manufacturingEscape(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="manufacturing"><input type="hidden" name="action" value="save_manufacturing_settings"><input type="hidden" name="section" value="settings">',
                        'body_html' => $settingsBody,
                    ], $manufacturingState !== []);
                    ?>
                    <dl class="mt-5 grid grid-cols-2 gap-3 bg-muted/30 p-3 text-sm"><div><dt class="text-xs text-muted-foreground">Overproduction</dt><dd class="mt-1 font-medium"><?= $manufacturingEscape((string) ($manufacturingSettings['allow_overproduction_percent'] ?? '0.0000')) ?>%</dd></div><div><dt class="text-xs text-muted-foreground">Capacity planning</dt><dd class="mt-1 font-medium"><?= !empty($manufacturingSettings['capacity_planning_enabled']) ? 'Enabled' : 'Disabled' ?></dd></div></dl>
                <?php elseif ($manufacturingSection === 'form-builder'): ?>
                    <div class="grid gap-5">
                        <section aria-labelledby="manufacturing-field-toolbox"><h3 id="manufacturing-field-toolbox" class="text-sm font-semibold">Field Toolbox</h3><div class="mt-3 grid grid-cols-2 gap-2"><?php foreach (['SHORT_TEXT' => 'Short text', 'PARAGRAPH' => 'Paragraph', 'NUMBER' => 'Number', 'DATE' => 'Date', 'DROPDOWN' => 'Dropdown', 'CHECKBOXES' => 'Checkboxes', 'LINK' => 'Link', 'SECTION' => 'Section'] as $type => $label): ?><span class="rounded-md bg-muted px-3 py-2 text-xs font-medium" data-field-type="<?= $manufacturingEscape($type) ?>"><?= $manufacturingEscape($label) ?></span><?php endforeach; ?></div></section>
                        <section aria-labelledby="manufacturing-field-properties"><h3 id="manufacturing-field-properties" class="text-sm font-semibold">Field Properties</h3><dl class="mt-3 grid grid-cols-2 gap-3 bg-muted/30 p-3 text-sm"><div><dt class="text-xs text-muted-foreground">Target</dt><dd class="mt-1 font-medium"><?= $manufacturingEscape((string) ($manufacturingFormSchema['recordType'] ?? 'BOM')) ?></dd></div><div><dt class="text-xs text-muted-foreground">Fields</dt><dd class="mt-1 font-medium"><?= count($manufacturingFormSchema['fields'] ?? []) ?></dd></div><div><dt class="text-xs text-muted-foreground">Columns</dt><dd class="mt-1 font-medium">Up to 3</dd></div><div><dt class="text-xs text-muted-foreground">Stable keys</dt><dd class="mt-1 font-medium">Protected</dd></div></dl></section>
                    </div>
                    <?php
                    $formValues = array_merge([
                        'form_key' => (string) ($manufacturingSelectedForm['form_key'] ?? ''),
                        'target_section' => $manufacturingTarget,
                        'form_title' => (string) ($manufacturingSelectedForm['form_title'] ?? ''),
                        'form_description' => (string) ($manufacturingSelectedForm['form_description'] ?? ''),
                        'form_status' => (string) ($manufacturingSelectedForm['form_status'] ?? 'DRAFT'),
                        'schema_json' => yovel_admin_manufacturing_json($manufacturingFormSchema),
                    ], $manufacturingInput);
                    $formError = (string) ($manufacturingState['error'] ?? '');
                    $targetOptions = '';
                    foreach (yovel_admin_manufacturing_form_targets() as $target => $recordType) {
                        $targetOptions .= '<option value="' . $manufacturingEscape($target) . '"' . ((string) $formValues['target_section'] === $target ? ' selected' : '') . '>' . $manufacturingEscape((string) ($manufacturingSections[$target]['label'] ?? $target)) . '</option>';
                    }
                    $formBody = ($formError !== '' ? '<div role="alert" class="rounded-md bg-destructive/10 p-3 text-sm text-destructive">' . $manufacturingEscape($formError) . '</div>' : '')
                        . '<div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm"><span>Target</span><select class="h-9 rounded-md border bg-background px-3" name="target_section" required>' . $targetOptions . '</select></label>'
                        . '<label class="grid gap-1.5 text-sm"><span>Status</span><select class="h-9 rounded-md border bg-background px-3" name="form_status" required><option value="DRAFT"' . ((string) $formValues['form_status'] === 'DRAFT' ? ' selected' : '') . '>Draft</option><option value="PUBLISHED"' . ((string) $formValues['form_status'] === 'PUBLISHED' ? ' selected' : '') . '>Published</option></select></label>'
                        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Form title</span><input class="h-9 rounded-md border bg-background px-3" name="form_title" maxlength="180" required value="' . $manufacturingEscape((string) $formValues['form_title']) . '"></label>'
                        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Description</span><textarea class="min-h-20 rounded-md border bg-background p-3" name="form_description" maxlength="1000">' . $manufacturingEscape((string) $formValues['form_description']) . '</textarea></label>'
                        . '<label class="grid gap-1.5 text-sm"><span>Field label</span><input class="h-9 rounded-md border bg-background px-3" name="new_field_label" maxlength="180"></label>'
                        . '<label class="grid gap-1.5 text-sm"><span>Field type</span><select class="h-9 rounded-md border bg-background px-3" name="new_field_type"><option>SHORT_TEXT</option><option>PARAGRAPH</option><option>NUMBER</option><option>DATE</option><option>DROPDOWN</option><option>CHECKBOXES</option><option>LINK</option><option>SECTION</option></select></label>'
                        . '<label class="grid gap-1.5 text-sm"><span>Field key</span><input class="h-9 rounded-md border bg-background px-3" name="new_field_key" maxlength="80"></label>'
                        . '<label class="grid gap-1.5 text-sm"><span>Section</span><input class="h-9 rounded-md border bg-background px-3" name="new_field_section" maxlength="80" value="details"></label>'
                        . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Layout definition</span><textarea class="min-h-48 rounded-md border bg-background p-3 font-mono text-xs" name="schema_json" required>' . $manufacturingEscape((string) $formValues['schema_json']) . '</textarea></label></div>';
                    $manufacturingRenderModal([
                        'id' => 'manufacturing-form-modal', 'title' => $manufacturingSelectedForm ? 'Edit Manufacturing Form' : 'New Manufacturing Form', 'description' => 'Versioned fields and layout for one production record type.',
                        'open_label' => $manufacturingSelectedForm ? 'Edit Form' : 'New Form', 'submit_label' => 'Submit', 'confirm_message' => 'Confirm this Manufacturing form version before saving.',
                        'hidden_html' => '<input type="hidden" name="csrf" value="' . $manufacturingEscape(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="manufacturing"><input type="hidden" name="action" value="save_manufacturing_form"><input type="hidden" name="section" value="form-builder"><input type="hidden" name="form_key" value="' . $manufacturingEscape((string) $formValues['form_key']) . '">',
                        'body_html' => $formBody,
                    ], $manufacturingState !== []);
                    if ($manufacturingSelectedForm): ?>
                        <form method="post" data-confirm-submit data-confirm-message="Confirm archive for this Manufacturing form." class="mt-3"><input type="hidden" name="csrf" value="<?= $manufacturingEscape(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="manufacturing"><input type="hidden" name="action" value="archive_manufacturing_form"><input type="hidden" name="section" value="form-builder"><input type="hidden" name="target_section" value="<?= $manufacturingEscape($manufacturingTarget) ?>"><input type="hidden" name="form_key" value="<?= $manufacturingEscape((string) $manufacturingSelectedForm['form_key']) ?>"><button type="submit" class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">archive</span>Archive</button></form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="grid gap-4"><div class="bg-muted/30 p-3"><p class="text-xs font-medium text-muted-foreground">Package</p><p class="mt-1 text-sm font-semibold"><?= $manufacturingEscape((string) ($manufacturingMeta['package'] ?? 'WP-01')) ?></p></div><div><p class="text-xs font-medium text-muted-foreground">Form target</p><p class="mt-1 text-sm"><?= $manufacturingEscape((string) ($manufacturingMeta['record_type'] ?? 'Not configurable')) ?></p></div></div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
    <span data-manufacturing-panels-end hidden></span>
    <?php if ($manufacturingSection === 'dashboard'): $manufacturingDashboardRegion = 'tour'; require __DIR__ . '/dashboard.php'; unset($manufacturingDashboardRegion); endif; ?>
</div>
