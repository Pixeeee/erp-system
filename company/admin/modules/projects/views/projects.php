<?php
declare(strict_types=1);

$projectRecords = is_array($projectsData['projects'] ?? null) ? $projectsData['projects'] : [];
$projectTypes = is_array($projectsData['project_types'] ?? null) ? $projectsData['project_types'] : [];
$projectTemplates = is_array($projectsData['project_templates'] ?? null) ? $projectsData['project_templates'] : [];
$projectWorkforce = is_array($projectsData['workforce'] ?? null) ? $projectsData['workforce'] : [];
$projectEmployees = is_array($projectWorkforce['employees'] ?? null) ? $projectWorkforce['employees'] : [];
$projectStateOpen = $projectsError !== '' && in_array($projectsAction, [
    'save_project', 'save_project_type', 'save_project_template', 'create_project_from_template',
], true);

$projectModalRender = static function (array $modal, bool $open = false, bool $showOpener = true): void {
    $projectsRecordModal = $modal;
    $projectsModalOpen = $open;
    ob_start();
    require __DIR__ . '/record-modal.php';
    $markup = (string) ob_get_clean();
    if (!$showOpener) {
        $markup = preg_replace('/^<button\b.*?<\/button>\s*/s', '', $markup, 1) ?? $markup;
    }
    echo $markup;
};
$projectValue = static function (array $record, array $prior, string $key, string $fallback = '') use ($projectsEscape): string {
    return $projectsEscape($prior[$key] ?? $record[$key] ?? $fallback);
};
$projectErrorHtml = static function (bool $open) use ($projectsError, $projectsEscape): string {
    return $open && $projectsError !== ''
        ? '<div role="alert" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">' . $projectsEscape($projectsError) . '</div>'
        : '';
};
$projectCommonHidden = static function (string $action) use ($projectsEscape): string {
    return '<input type="hidden" name="csrf" value="' . $projectsEscape(bx_csrf_token()) . '">'
        . '<input type="hidden" name="module_view" value="projects">'
        . '<input type="hidden" name="action" value="' . $projectsEscape($action) . '">'
        . '<input type="hidden" name="section" value="projects">';
};

$projectBody = static function (array $record, array $prior, bool $open) use (
    $projectValue, $projectErrorHtml, $projectTypes, $projectEmployees, $projectWorkforce, $projectsEscape
): string {
    $status = (string) ($prior['project_status'] ?? $record['project_status'] ?? 'OPEN');
    $method = (string) ($prior['completion_method'] ?? $record['completion_method'] ?? 'MANUAL');
    $selectedType = (string) ($prior['project_type_key'] ?? $record['project_type_key'] ?? '');
    $selectedEmployee = (string) ($prior['project_lead_employee_key'] ?? $record['project_lead_employee_key'] ?? '');
    $descriptionSuffix = preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) ($record['project_key'] ?? 'new'))) ?: 'new';
    $customerDescriptionId = 'projects-customer-dependency-' . $descriptionSuffix;
    $workforceDescriptionId = 'projects-workforce-dependency-' . $descriptionSuffix;
    ob_start();
    echo $projectErrorHtml($open);
    ?>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="grid gap-1.5 text-sm font-medium">Project code<input name="project_code" required maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9_-]*" class="h-9 rounded-md border bg-background px-3" value="<?= $projectValue($record, $prior, 'project_code') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Project name<input name="project_name" required maxlength="190" class="h-9 rounded-md border bg-background px-3" value="<?= $projectValue($record, $prior, 'project_name') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Project Type<select name="project_type_key" class="h-9 rounded-md border bg-background px-3"><option value="">No Project Type</option><?php foreach ($projectTypes as $type): ?><option value="<?= $projectsEscape($type['project_type_key'] ?? '') ?>"<?= $selectedType === (string) ($type['project_type_key'] ?? '') ? ' selected' : '' ?>><?= $projectsEscape($type['project_type_name'] ?? '') ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Customer key<input name="customer_key" maxlength="36" class="h-9 rounded-md border bg-background px-3" value="<?= $projectValue($record, $prior, 'customer_key') ?>" aria-describedby="<?= $projectsEscape($customerDescriptionId) ?>"><span id="<?= $projectsEscape($customerDescriptionId) ?>" class="text-xs font-normal text-muted-foreground">Blank is allowed. Nonblank validation is UNAVAILABLE_DEPENDENCY until Sales exposes its contract.</span></label>
        <label class="grid gap-1.5 text-sm font-medium">Project lead<?php if (!empty($projectWorkforce['available'])): ?><select name="project_lead_employee_key" class="h-9 rounded-md border bg-background px-3"><option value="">No project lead</option><?php foreach ($projectEmployees as $employee): ?><option value="<?= $projectsEscape($employee['employee_key'] ?? '') ?>"<?= $selectedEmployee === (string) ($employee['employee_key'] ?? '') ? ' selected' : '' ?>><?= $projectsEscape(($employee['employee_code'] ?? '') . ' · ' . ($employee['employee_name'] ?? '')) ?></option><?php endforeach; ?></select><?php else: ?><input name="project_lead_employee_key" maxlength="36" class="h-9 rounded-md border bg-background px-3" value="<?= $projectValue($record, $prior, 'project_lead_employee_key') ?>" aria-describedby="<?= $projectsEscape($workforceDescriptionId) ?>"><span id="<?= $projectsEscape($workforceDescriptionId) ?>" class="text-xs font-normal text-muted-foreground">UNAVAILABLE_DEPENDENCY: hr.workforce.v1</span><?php endif; ?></label>
        <label class="grid gap-1.5 text-sm font-medium">Status<select name="project_status" class="h-9 rounded-md border bg-background px-3"><?php foreach (['OPEN' => 'Open', 'ON_HOLD' => 'On hold', 'COMPLETED' => 'Completed', 'CANCELLED' => 'Cancelled'] as $value => $label): ?><option value="<?= $value ?>"<?= $status === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Completion method<select name="completion_method" class="h-9 rounded-md border bg-background px-3"><?php foreach (['MANUAL' => 'Manual', 'TASK_COMPLETION' => 'Task completion', 'TASK_PROGRESS' => 'Task progress', 'TASK_WEIGHT' => 'Task weight'] as $value => $label): ?><option value="<?= $value ?>"<?= $method === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Percent complete<input type="number" name="percent_complete" min="0" max="100" step="0.0001" required class="h-9 rounded-md border bg-background px-3" value="<?= $projectValue($record, $prior, 'percent_complete', '0') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Expected start<input type="date" name="expected_start_date" class="h-9 rounded-md border bg-background px-3" value="<?= $projectValue($record, $prior, 'expected_start_date') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Expected end<input type="date" name="expected_end_date" class="h-9 rounded-md border bg-background px-3" value="<?= $projectValue($record, $prior, 'expected_end_date') ?>"></label>
    </div>
    <?php
    return (string) ob_get_clean();
};

$typeBody = static function (array $record, array $prior, bool $open) use ($projectValue, $projectErrorHtml): string {
    $status = (string) ($prior['project_type_status'] ?? $record['project_type_status'] ?? 'ACTIVE');
    ob_start();
    echo $projectErrorHtml($open);
    ?>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="grid gap-1.5 text-sm font-medium">Type code<input name="project_type_code" required maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9_-]*" class="h-9 rounded-md border bg-background px-3" value="<?= $projectValue($record, $prior, 'project_type_code') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Type name<input name="project_type_name" required maxlength="160" class="h-9 rounded-md border bg-background px-3" value="<?= $projectValue($record, $prior, 'project_type_name') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Status<select name="project_type_status" class="h-9 rounded-md border bg-background px-3"><option value="ACTIVE"<?= $status === 'ACTIVE' ? ' selected' : '' ?>>Active</option><option value="INACTIVE"<?= $status === 'INACTIVE' ? ' selected' : '' ?>>Inactive</option></select></label>
    </div>
    <?php
    return (string) ob_get_clean();
};

$templateTasksJson = static function (array $record, array $prior): string {
    if (array_key_exists('tasks_json', $prior)) {
        return (string) $prior['tasks_json'];
    }
    $rows = [];
    foreach (($record['tasks'] ?? []) as $task) {
        $rows[] = [
            'project_template_task_key' => (string) ($task['project_template_task_key'] ?? ''),
            'parent_template_task_key' => (string) ($task['parent_template_task_key'] ?? ''),
            'depends_on_template_task_key' => (string) ($task['source_task_key'] ?? ''),
            'task_subject' => (string) ($task['task_subject'] ?? ''),
            'relative_start_days' => (int) ($task['relative_start_days'] ?? 0),
            'duration_days' => (int) ($task['duration_days'] ?? 1),
            'task_weight' => (string) ($task['task_weight'] ?? '0.0000'),
            'sort_order' => (int) ($task['sort_order'] ?? 100),
        ];
    }
    return json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
};
$templateBody = static function (array $record, array $prior, bool $open) use (
    $projectValue, $projectErrorHtml, $projectTypes, $templateTasksJson, $projectsEscape
): string {
    $status = (string) ($prior['template_status'] ?? $record['template_status'] ?? 'DRAFT');
    $selectedType = (string) ($prior['project_type_key'] ?? $record['project_type_key'] ?? '');
    ob_start();
    echo $projectErrorHtml($open);
    ?>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="grid gap-1.5 text-sm font-medium">Template code<input name="template_code" required maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9_-]*" class="h-9 rounded-md border bg-background px-3" value="<?= $projectValue($record, $prior, 'template_code') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Template name<input name="template_name" required maxlength="190" class="h-9 rounded-md border bg-background px-3" value="<?= $projectValue($record, $prior, 'template_name') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Project Type<select name="project_type_key" class="h-9 rounded-md border bg-background px-3"><option value="">No Project Type</option><?php foreach ($projectTypes as $type): ?><option value="<?= $projectsEscape($type['project_type_key'] ?? '') ?>"<?= $selectedType === (string) ($type['project_type_key'] ?? '') ? ' selected' : '' ?>><?= $projectsEscape($type['project_type_name'] ?? '') ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Status<select name="template_status" class="h-9 rounded-md border bg-background px-3"><?php foreach (['DRAFT' => 'Draft', 'ACTIVE' => 'Active', 'ARCHIVED' => 'Archived'] as $value => $label): ?><option value="<?= $value ?>"<?= $status === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Template tasks JSON<textarea name="tasks_json" rows="10" required class="min-h-48 rounded-md border bg-background p-3 font-mono text-xs"><?= $projectsEscape($templateTasksJson($record, $prior)) ?></textarea><span class="text-xs font-normal text-muted-foreground">Use stable task UUIDs with parent and dependency task keys from the same template.</span></label>
    </div>
    <?php
    return (string) ob_get_clean();
};
?>
<div data-projects-projects class="space-y-6">
    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b pb-3">
            <div><h2 class="text-lg font-semibold">Project masters</h2><p class="mt-1 text-sm text-muted-foreground">Projects, reusable types, and task templates.</p></div>
            <div class="flex flex-wrap gap-2">
                <?php
                $openNewProject = $projectStateOpen && $projectsAction === 'save_project' && trim((string) ($projectsPrior['project_key'] ?? '')) === '';
                $projectModalRender([
                    'id' => 'projects-project-modal-new', 'title' => 'Add Project',
                    'description' => 'Create a company-owned Project master.', 'open_label' => 'Add Project',
                    'submit_label' => 'Submit', 'confirm_message' => 'Confirm this Project before saving it.',
                    'body_html' => $projectBody([], $openNewProject ? $projectsPrior : [], $openNewProject),
                    'hidden_html' => $projectCommonHidden('save_project') . '<input type="hidden" name="project_key" value="">',
                ], $openNewProject);
                $openNewType = $projectStateOpen && $projectsAction === 'save_project_type' && trim((string) ($projectsPrior['project_type_key'] ?? '')) === '';
                $projectModalRender([
                    'id' => 'projects-type-modal-new', 'title' => 'Add Project Type',
                    'description' => 'Create a reusable Project classification.', 'open_label' => 'Add Project Type',
                    'submit_label' => 'Submit', 'confirm_message' => 'Confirm this Project Type before saving it.',
                    'body_html' => $typeBody([], $openNewType ? $projectsPrior : [], $openNewType),
                    'hidden_html' => $projectCommonHidden('save_project_type') . '<input type="hidden" name="project_type_key" value="">',
                ], $openNewType);
                $openNewTemplate = $projectStateOpen && $projectsAction === 'save_project_template' && trim((string) ($projectsPrior['project_template_key'] ?? '')) === '';
                $projectModalRender([
                    'id' => 'projects-template-modal-new', 'title' => 'Add Project Template',
                    'description' => 'Create a reusable task graph.', 'open_label' => 'Add Project Template',
                    'submit_label' => 'Submit', 'confirm_message' => 'Confirm this Project Template before saving it.',
                    'body_html' => $templateBody([], $openNewTemplate ? $projectsPrior : [], $openNewTemplate),
                    'hidden_html' => $projectCommonHidden('save_project_template') . '<input type="hidden" name="project_template_key" value="">',
                ], $openNewTemplate);
                ?>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[42rem] text-left text-sm">
                <thead class="border-b text-xs text-muted-foreground"><tr><th class="py-2 pr-3">Code</th><th class="py-2 pr-3">Project</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3">Progress</th><th class="py-2 text-right">Action</th></tr></thead>
                <tbody class="divide-y">
                    <?php if ($projectRecords === []): ?><tr><td colspan="5" class="py-6 text-center text-muted-foreground">No Projects yet.</td></tr><?php endif; ?>
                    <?php foreach ($projectRecords as $record): ?>
                        <tr><td class="py-3 pr-3 font-mono text-xs"><?= $projectsEscape($record['project_code'] ?? '') ?></td><td class="py-3 pr-3 font-medium"><?= $projectsEscape($record['project_name'] ?? '') ?></td><td class="py-3 pr-3"><?= $projectsEscape($record['project_status'] ?? '') ?></td><td class="py-3 pr-3"><?= $projectsEscape($record['percent_complete'] ?? '0') ?>%</td><td class="py-3 text-right"><button type="button" data-record-modal-open="projects-project-modal-<?= $projectsEscape($record['project_key'] ?? '') ?>" class="h-8 rounded-md border px-3 text-xs">Edit</button></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="space-y-3 border-t pt-5"><h2 class="text-sm font-semibold">Project Types</h2><?php if ($projectTypes === []): ?><p class="text-sm text-muted-foreground">No Project Types yet.</p><?php endif; ?><?php foreach ($projectTypes as $type): ?><div class="flex items-center justify-between gap-3 border-b py-2 text-sm"><span><strong class="block"><?= $projectsEscape($type['project_type_name'] ?? '') ?></strong><code class="text-xs text-muted-foreground"><?= $projectsEscape($type['project_type_code'] ?? '') ?></code></span><button type="button" data-record-modal-open="projects-type-modal-<?= $projectsEscape($type['project_type_key'] ?? '') ?>" class="h-8 rounded-md border px-3 text-xs">Edit</button></div><?php endforeach; ?></section>
        <section class="space-y-3 border-t pt-5"><h2 class="text-sm font-semibold">Project Templates</h2><?php if ($projectTemplates === []): ?><p class="text-sm text-muted-foreground">No Project Templates yet.</p><?php endif; ?><?php foreach ($projectTemplates as $template): ?><div class="flex items-center justify-between gap-3 border-b py-2 text-sm"><span><strong class="block"><?= $projectsEscape($template['template_name'] ?? '') ?></strong><span class="text-xs text-muted-foreground"><?= count($template['tasks'] ?? []) ?> tasks · <?= $projectsEscape($template['template_status'] ?? '') ?></span></span><button type="button" data-record-modal-open="projects-template-modal-<?= $projectsEscape($template['project_template_key'] ?? '') ?>" class="h-8 rounded-md border px-3 text-xs">Edit</button></div><?php endforeach; ?></section>
    </div>

    <?php foreach ($projectRecords as $record): ?><?php $open = $projectStateOpen && $projectsAction === 'save_project' && (string) ($projectsPrior['project_key'] ?? '') === (string) $record['project_key']; $projectModalRender(['id' => 'projects-project-modal-' . $record['project_key'], 'title' => 'Edit Project', 'description' => 'Update this company Project master.', 'open_label' => 'Edit Project', 'submit_label' => 'Submit', 'confirm_message' => 'Confirm these Project changes before saving them.', 'body_html' => $projectBody($record, $open ? $projectsPrior : [], $open), 'hidden_html' => $projectCommonHidden('save_project') . '<input type="hidden" name="project_key" value="' . $projectsEscape($record['project_key']) . '">'], $open, false); ?><?php endforeach; ?>
    <?php foreach ($projectTypes as $type): ?><?php $open = $projectStateOpen && $projectsAction === 'save_project_type' && (string) ($projectsPrior['project_type_key'] ?? '') === (string) $type['project_type_key']; $projectModalRender(['id' => 'projects-type-modal-' . $type['project_type_key'], 'title' => 'Edit Project Type', 'description' => 'Update this Project classification.', 'open_label' => 'Edit Project Type', 'submit_label' => 'Submit', 'confirm_message' => 'Confirm these Project Type changes.', 'body_html' => $typeBody($type, $open ? $projectsPrior : [], $open), 'hidden_html' => $projectCommonHidden('save_project_type') . '<input type="hidden" name="project_type_key" value="' . $projectsEscape($type['project_type_key']) . '">'], $open, false); ?><?php endforeach; ?>
    <?php foreach ($projectTemplates as $template): ?><?php $open = $projectStateOpen && $projectsAction === 'save_project_template' && (string) ($projectsPrior['project_template_key'] ?? '') === (string) $template['project_template_key']; $projectModalRender(['id' => 'projects-template-modal-' . $template['project_template_key'], 'title' => 'Edit Project Template', 'description' => 'Update this reusable task graph.', 'open_label' => 'Edit Project Template', 'submit_label' => 'Submit', 'confirm_message' => 'Confirm these Project Template changes.', 'body_html' => $templateBody($template, $open ? $projectsPrior : [], $open), 'hidden_html' => $projectCommonHidden('save_project_template') . '<input type="hidden" name="project_template_key" value="' . $projectsEscape($template['project_template_key']) . '">'], $open, false); ?><?php endforeach; ?>

    <?php if ($projectTemplates !== []): ?>
        <?php
        $instantiateOpen = $projectStateOpen && $projectsAction === 'create_project_from_template';
        ob_start();
        echo $projectErrorHtml($instantiateOpen);
        ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Project Template<select name="project_template_key" required class="h-9 rounded-md border bg-background px-3"><option value="">Select template</option><?php foreach ($projectTemplates as $template): if (($template['template_status'] ?? '') !== 'ACTIVE') continue; ?><option value="<?= $projectsEscape($template['project_template_key'] ?? '') ?>"<?= (string) ($projectsPrior['project_template_key'] ?? '') === (string) ($template['project_template_key'] ?? '') ? ' selected' : '' ?>><?= $projectsEscape($template['template_name'] ?? '') ?></option><?php endforeach; ?></select></label>
            <label class="grid gap-1.5 text-sm font-medium">Project code<input name="project_code" required maxlength="80" class="h-9 rounded-md border bg-background px-3" value="<?= $projectValue([], $instantiateOpen ? $projectsPrior : [], 'project_code') ?>"></label>
            <label class="grid gap-1.5 text-sm font-medium">Project name<input name="project_name" required maxlength="190" class="h-9 rounded-md border bg-background px-3" value="<?= $projectValue([], $instantiateOpen ? $projectsPrior : [], 'project_name') ?>"></label>
            <label class="grid gap-1.5 text-sm font-medium">Expected start<input type="date" name="expected_start_date" required class="h-9 rounded-md border bg-background px-3" value="<?= $projectValue([], $instantiateOpen ? $projectsPrior : [], 'expected_start_date') ?>"></label>
            <label class="grid gap-1.5 text-sm font-medium">Completion method<select name="completion_method" class="h-9 rounded-md border bg-background px-3"><option value="TASK_WEIGHT">Task weight</option><option value="TASK_PROGRESS">Task progress</option><option value="TASK_COMPLETION">Task completion</option></select></label>
            <label class="grid gap-1.5 text-sm font-medium">Customer key<input name="customer_key" maxlength="36" class="h-9 rounded-md border bg-background px-3" value="<?= $projectValue([], $instantiateOpen ? $projectsPrior : [], 'customer_key') ?>"></label>
            <input type="hidden" name="project_lead_employee_key" value="<?= $projectValue([], $instantiateOpen ? $projectsPrior : [], 'project_lead_employee_key') ?>"><input type="hidden" name="project_status" value="OPEN"><input type="hidden" name="percent_complete" value="0">
        </div>
        <?php $instantiateBody = (string) ob_get_clean();
        $projectModalRender(['id' => 'projects-instantiate-modal', 'title' => 'Create Project From Template', 'description' => 'Instantiate the complete task and dependency graph in one transaction.', 'open_label' => 'Create From Template', 'submit_label' => 'Submit', 'confirm_message' => 'Confirm this Project and template task graph before saving it.', 'body_html' => $instantiateBody, 'hidden_html' => $projectCommonHidden('create_project_from_template')], $instantiateOpen);
        ?>
    <?php endif; ?>
</div>
