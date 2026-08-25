<?php
declare(strict_types=1);

$taskProjects = is_array($projectsData['projects'] ?? null) ? $projectsData['projects'] : [];
$taskTypes = is_array($projectsData['task_types'] ?? null) ? $projectsData['task_types'] : [];
$taskRecords = is_array($projectsData['tasks'] ?? null) ? $projectsData['tasks'] : [];
$assignmentDependency = is_array($projectsData['assignment_dependency'] ?? null) ? $projectsData['assignment_dependency'] : [];
$taskStateOpen = $projectsError !== '' && in_array($projectsAction, [
    'save_project_task', 'save_task_type', 'transition_project_task',
    'reschedule_project_task', 'retry_project_task_assignment',
], true);

$taskModalRender = static function (array $modal, bool $open = false, bool $showOpener = true): void {
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
$taskValue = static function (array $record, array $prior, string $key, string $fallback = '') use ($projectsEscape): string {
    return $projectsEscape($prior[$key] ?? $record[$key] ?? $fallback);
};
$taskErrorHtml = static function (bool $open) use ($projectsError, $projectsEscape): string {
    return $open && $projectsError !== ''
        ? '<div role="alert" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">' . $projectsEscape($projectsError) . '</div>'
        : '';
};
$taskCommonHidden = static function (string $action) use ($projectsEscape): string {
    return '<input type="hidden" name="csrf" value="' . $projectsEscape(bx_csrf_token()) . '">'
        . '<input type="hidden" name="module_view" value="projects">'
        . '<input type="hidden" name="action" value="' . $projectsEscape($action) . '">'
        . '<input type="hidden" name="section" value="project-tasks">';
};
$taskDependenciesJson = static function (array $record, array $prior): string {
    if (array_key_exists('dependency_keys_json', $prior)) {
        return (string) $prior['dependency_keys_json'];
    }
    if (array_key_exists('dependency_keys', $prior)) {
        return json_encode((array) $prior['dependency_keys'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
    return json_encode((array) ($record['dependency_keys'] ?? []), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
};
$taskBody = static function (array $record, array $prior, bool $open) use (
    $taskProjects, $taskTypes, $taskRecords, $taskValue, $taskErrorHtml, $taskDependenciesJson, $projectsEscape
): string {
    $projectKey = (string) ($prior['project_key'] ?? $record['project_key'] ?? '');
    $parentKey = (string) ($prior['parent_task_key'] ?? $record['parent_task_key'] ?? '');
    $typeKey = (string) ($prior['task_type_key'] ?? $record['task_type_key'] ?? '');
    $status = (string) ($prior['task_status'] ?? $record['task_status'] ?? 'OPEN');
    $priority = (string) ($prior['priority'] ?? $record['priority'] ?? 'MEDIUM');
    $isGroup = yovel_admin_projects_flag($prior['is_group'] ?? $record['is_group'] ?? 0);
    ob_start();
    echo $taskErrorHtml($open);
    ?>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Project<select name="project_key" required class="h-9 rounded-md border bg-background px-3"><option value="">Select Project</option><?php foreach ($taskProjects as $project): ?><option value="<?= $projectsEscape($project['project_key'] ?? '') ?>"<?= $projectKey === (string) ($project['project_key'] ?? '') ? ' selected' : '' ?>><?= $projectsEscape(($project['project_code'] ?? '') . ' · ' . ($project['project_name'] ?? '')) ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Task code<input name="task_code" required maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9_-]*" class="h-9 rounded-md border bg-background px-3" value="<?= $taskValue($record, $prior, 'task_code') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Subject<input name="task_subject" required maxlength="190" class="h-9 rounded-md border bg-background px-3" value="<?= $taskValue($record, $prior, 'task_subject') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Task Type<select name="task_type_key" class="h-9 rounded-md border bg-background px-3"><option value="">No Task Type</option><?php foreach ($taskTypes as $type): ?><option value="<?= $projectsEscape($type['task_type_key'] ?? '') ?>"<?= $typeKey === (string) ($type['task_type_key'] ?? '') ? ' selected' : '' ?>><?= $projectsEscape($type['task_type_name'] ?? '') ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Parent group<select name="parent_task_key" class="h-9 rounded-md border bg-background px-3"><option value="">Root Task</option><?php foreach ($taskRecords as $candidate): if (empty($candidate['is_group']) || (string) ($candidate['task_key'] ?? '') === (string) ($record['task_key'] ?? '')) continue; ?><option value="<?= $projectsEscape($candidate['task_key'] ?? '') ?>"<?= $parentKey === (string) ($candidate['task_key'] ?? '') ? ' selected' : '' ?>><?= $projectsEscape($candidate['task_subject'] ?? '') ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Status<select name="task_status" class="h-9 rounded-md border bg-background px-3"><?php foreach (['OPEN', 'WORKING', 'PENDING_REVIEW', 'OVERDUE', 'COMPLETED', 'CANCELLED'] as $option): ?><option value="<?= $option ?>"<?= $status === $option ? ' selected' : '' ?>><?= $projectsEscape(str_replace('_', ' ', $option)) ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Priority<select name="priority" class="h-9 rounded-md border bg-background px-3"><?php foreach (['LOW', 'MEDIUM', 'HIGH', 'URGENT'] as $option): ?><option value="<?= $option ?>"<?= $priority === $option ? ' selected' : '' ?>><?= $option ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Progress<input type="number" name="progress" min="0" max="100" step="0.0001" required class="h-9 rounded-md border bg-background px-3" value="<?= $taskValue($record, $prior, 'progress', '0') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Weight<input type="number" name="task_weight" min="0" max="100" step="0.0001" required class="h-9 rounded-md border bg-background px-3" value="<?= $taskValue($record, $prior, 'task_weight', '0') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Expected start<input type="date" name="expected_start_date" class="h-9 rounded-md border bg-background px-3" value="<?= $taskValue($record, $prior, 'expected_start_date') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Expected end<input type="date" name="expected_end_date" class="h-9 rounded-md border bg-background px-3" value="<?= $taskValue($record, $prior, 'expected_end_date') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Sort order<input type="number" name="sort_order" min="0" max="1000000" step="1" required class="h-9 rounded-md border bg-background px-3" value="<?= $taskValue($record, $prior, 'sort_order', '100') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Assigned employee key<input name="assigned_employee_key" maxlength="36" class="h-9 rounded-md border bg-background px-3" value="<?= $taskValue($record, $prior, 'assigned_employee_key') ?>"><span class="text-xs font-normal text-muted-foreground">Blank saves unassigned. HR handoff occurs only after the Task commits.</span></label>
        <label class="flex items-center gap-2 text-sm font-medium sm:col-span-2"><input type="checkbox" name="is_group" value="1"<?= $isGroup === 1 ? ' checked' : '' ?> class="size-4 rounded border">Group Task</label>
        <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Dependency task keys JSON<textarea name="dependency_keys_json" rows="4" class="rounded-md border bg-background p-3 font-mono text-xs"><?= $projectsEscape($taskDependenciesJson($record, $prior)) ?></textarea><span class="text-xs font-normal text-muted-foreground">Use Task UUIDs from this Project. Cycles and cross-Project references are rejected.</span></label>
    </div>
    <?php
    return (string) ob_get_clean();
};
$taskTypeBody = static function (array $record, array $prior, bool $open) use ($taskValue, $taskErrorHtml): string {
    $status = (string) ($prior['task_type_status'] ?? $record['task_type_status'] ?? 'ACTIVE');
    ob_start();
    echo $taskErrorHtml($open);
    ?>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="grid gap-1.5 text-sm font-medium">Type code<input name="task_type_code" required maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9_-]*" class="h-9 rounded-md border bg-background px-3" value="<?= $taskValue($record, $prior, 'task_type_code') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Type name<input name="task_type_name" required maxlength="160" class="h-9 rounded-md border bg-background px-3" value="<?= $taskValue($record, $prior, 'task_type_name') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Status<select name="task_type_status" class="h-9 rounded-md border bg-background px-3"><option value="ACTIVE"<?= $status === 'ACTIVE' ? ' selected' : '' ?>>Active</option><option value="INACTIVE"<?= $status === 'INACTIVE' ? ' selected' : '' ?>>Inactive</option></select></label>
    </div>
    <?php
    return (string) ob_get_clean();
};
?>
<div data-projects-task-tree class="space-y-6">
    <section class="space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b pb-3">
            <div><h2 class="text-lg font-semibold">Task tree</h2><p class="mt-1 text-sm text-muted-foreground">Company-owned Tasks, group hierarchy, dependencies, schedules, and assignment state.</p></div>
            <div class="flex flex-wrap gap-2">
                <?php
                $newTaskOpen = $taskStateOpen && $projectsAction === 'save_project_task' && trim((string) ($projectsPrior['task_key'] ?? '')) === '';
                $taskModalRender([
                    'id' => 'projects-task-modal-new', 'title' => 'Add Task',
                    'description' => 'Create a Task in a Project tree.', 'open_label' => 'Add Task',
                    'submit_label' => 'Submit', 'confirm_message' => 'Confirm this Task before saving it.',
                    'body_html' => $taskBody([], $newTaskOpen ? $projectsPrior : [], $newTaskOpen),
                    'hidden_html' => $taskCommonHidden('save_project_task') . '<input type="hidden" name="task_key" value="">',
                ], $newTaskOpen);
                $newTypeOpen = $taskStateOpen && $projectsAction === 'save_task_type' && trim((string) ($projectsPrior['task_type_key'] ?? '')) === '';
                $taskModalRender([
                    'id' => 'projects-task-type-modal-new', 'title' => 'Add Task Type',
                    'description' => 'Create a reusable Task classification.', 'open_label' => 'Add Task Type',
                    'submit_label' => 'Submit', 'confirm_message' => 'Confirm this Task Type before saving it.',
                    'body_html' => $taskTypeBody([], $newTypeOpen ? $projectsPrior : [], $newTypeOpen),
                    'hidden_html' => $taskCommonHidden('save_task_type') . '<input type="hidden" name="task_type_key" value="">',
                ], $newTypeOpen);
                ?>
            </div>
        </div>
        <?php if (empty($assignmentDependency['available'])): ?>
            <div role="status" class="border-l-2 border-amber-500 px-3 py-2 text-sm"><strong>UNAVAILABLE_DEPENDENCY</strong><span class="ml-2 text-muted-foreground">HR assignment handoff is unavailable. Tasks still save unassigned with an audited retry state.</span></div>
        <?php endif; ?>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[58rem] text-left text-sm">
                <thead class="border-b text-xs text-muted-foreground"><tr><th class="py-2 pr-3">Task</th><th class="py-2 pr-3">Project</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3">Progress</th><th class="py-2 pr-3">Assignment</th><th class="py-2 text-right">Actions</th></tr></thead>
                <tbody class="divide-y">
                <?php if ($taskRecords === []): ?><tr><td colspan="6" class="py-6 text-center text-muted-foreground">No Tasks yet.</td></tr><?php endif; ?>
                <?php foreach ($taskRecords as $task):
                    $depth = min(8, max(0, (int) ($task['tree_depth'] ?? 0)));
                    $projectLabel = '';
                    foreach ($taskProjects as $project) if ((string) ($project['project_key'] ?? '') === (string) ($task['project_key'] ?? '')) $projectLabel = (string) ($project['project_code'] ?? '');
                    $assignment = is_array($task['assignment'] ?? null) ? $task['assignment'] : [];
                    ?>
                    <tr>
                        <td class="py-3 pr-3"><div style="padding-left:<?= $depth * 18 ?>px"><span class="font-medium"><?= $projectsEscape($task['task_subject'] ?? '') ?></span><code class="ml-2 text-xs text-muted-foreground"><?= $projectsEscape($task['task_code'] ?? '') ?></code></div></td>
                        <td class="py-3 pr-3 font-mono text-xs"><?= $projectsEscape($projectLabel) ?></td>
                        <td class="py-3 pr-3"><?= $projectsEscape($task['task_status'] ?? '') ?></td>
                        <td class="py-3 pr-3"><?= $projectsEscape($task['progress'] ?? '0') ?>%</td>
                        <td class="py-3 pr-3"><?= $projectsEscape($assignment['status'] ?? 'UNASSIGNED') ?></td>
                        <td class="py-3 text-right"><div class="flex justify-end gap-1">
                            <button type="button" data-record-modal-open="projects-task-modal-<?= $projectsEscape($task['task_key'] ?? '') ?>" class="h-8 rounded-md border px-2 text-xs">Edit</button>
                            <button type="button" data-record-modal-open="projects-task-status-modal-<?= $projectsEscape($task['task_key'] ?? '') ?>" class="h-8 rounded-md border px-2 text-xs">Status</button>
                            <button type="button" data-record-modal-open="projects-task-schedule-modal-<?= $projectsEscape($task['task_key'] ?? '') ?>" class="h-8 rounded-md border px-2 text-xs">Schedule</button>
                            <?php if (!empty($assignment['retryable'])): ?><button type="button" data-record-modal-open="projects-task-retry-modal-<?= $projectsEscape($task['task_key'] ?? '') ?>" class="h-8 rounded-md border px-2 text-xs">Retry</button><?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-3 border-t pt-5"><h2 class="text-sm font-semibold">Task Types</h2><?php if ($taskTypes === []): ?><p class="text-sm text-muted-foreground">No Task Types yet.</p><?php endif; ?><?php foreach ($taskTypes as $type): ?><div class="flex items-center justify-between gap-3 border-b py-2 text-sm"><span><strong class="block"><?= $projectsEscape($type['task_type_name'] ?? '') ?></strong><code class="text-xs text-muted-foreground"><?= $projectsEscape($type['task_type_code'] ?? '') ?></code></span><button type="button" data-record-modal-open="projects-task-type-modal-<?= $projectsEscape($type['task_type_key'] ?? '') ?>" class="h-8 rounded-md border px-3 text-xs">Edit</button></div><?php endforeach; ?></section>

    <?php foreach ($taskTypes as $type): $open = $taskStateOpen && $projectsAction === 'save_task_type' && (string) ($projectsPrior['task_type_key'] ?? '') === (string) $type['task_type_key']; $taskModalRender(['id' => 'projects-task-type-modal-' . $type['task_type_key'], 'title' => 'Edit Task Type', 'description' => 'Update this Task classification.', 'open_label' => 'Edit Task Type', 'submit_label' => 'Submit', 'confirm_message' => 'Confirm these Task Type changes.', 'body_html' => $taskTypeBody($type, $open ? $projectsPrior : [], $open), 'hidden_html' => $taskCommonHidden('save_task_type') . '<input type="hidden" name="task_type_key" value="' . $projectsEscape($type['task_type_key']) . '">'], $open, false); endforeach; ?>
    <?php foreach ($taskRecords as $task):
        $taskKey = (string) $task['task_key'];
        $editOpen = $taskStateOpen && $projectsAction === 'save_project_task' && (string) ($projectsPrior['task_key'] ?? '') === $taskKey;
        $taskModalRender(['id' => 'projects-task-modal-' . $taskKey, 'title' => 'Edit Task', 'description' => 'Update this Task and its graph edges.', 'open_label' => 'Edit Task', 'submit_label' => 'Submit', 'confirm_message' => 'Confirm these Task changes.', 'body_html' => $taskBody($task, $editOpen ? $projectsPrior : [], $editOpen), 'hidden_html' => $taskCommonHidden('save_project_task') . '<input type="hidden" name="task_key" value="' . $projectsEscape($taskKey) . '">'], $editOpen, false);
        $statusOpen = $taskStateOpen && $projectsAction === 'transition_project_task' && (string) ($projectsPrior['task_key'] ?? '') === $taskKey;
        $statusBody = $taskErrorHtml($statusOpen) . '<label class="grid gap-1.5 text-sm font-medium">Transition<select name="transition" class="h-9 rounded-md border bg-background px-3"><option value="START">Start</option><option value="REVIEW">Pending review</option><option value="COMPLETE">Complete</option><option value="CANCEL">Cancel</option><option value="REOPEN">Reopen</option><option value="ARCHIVE">Archive</option></select></label>';
        $taskModalRender(['id' => 'projects-task-status-modal-' . $taskKey, 'title' => 'Change Task Status', 'description' => 'Apply a validated Task lifecycle transition.', 'open_label' => 'Change status', 'submit_label' => 'Submit', 'confirm_message' => 'Confirm this Task status transition.', 'body_html' => $statusBody, 'hidden_html' => $taskCommonHidden('transition_project_task') . '<input type="hidden" name="task_key" value="' . $projectsEscape($taskKey) . '">'], $statusOpen, false);
        $scheduleOpen = $taskStateOpen && $projectsAction === 'reschedule_project_task' && (string) ($projectsPrior['task_key'] ?? '') === $taskKey;
        $scheduleBody = $taskErrorHtml($scheduleOpen) . '<div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm font-medium">Expected start<input type="date" name="expected_start_date" required class="h-9 rounded-md border bg-background px-3" value="' . $taskValue($task, $scheduleOpen ? $projectsPrior : [], 'expected_start_date') . '"></label><label class="grid gap-1.5 text-sm font-medium">Expected end<input type="date" name="expected_end_date" required class="h-9 rounded-md border bg-background px-3" value="' . $taskValue($task, $scheduleOpen ? $projectsPrior : [], 'expected_end_date') . '"></label></div>';
        $taskModalRender(['id' => 'projects-task-schedule-modal-' . $taskKey, 'title' => 'Reschedule Task', 'description' => 'Shift this Task and its dependent schedule within Project dates.', 'open_label' => 'Reschedule Task', 'submit_label' => 'Submit', 'confirm_message' => 'Confirm this Task and dependent schedule change.', 'body_html' => $scheduleBody, 'hidden_html' => $taskCommonHidden('reschedule_project_task') . '<input type="hidden" name="task_key" value="' . $projectsEscape($taskKey) . '">'], $scheduleOpen, false);
        if (!empty($task['assignment']['retryable'])) {
            $retryOpen = $taskStateOpen && $projectsAction === 'retry_project_task_assignment' && (string) ($projectsPrior['task_key'] ?? '') === $taskKey;
            $retryBody = $taskErrorHtml($retryOpen) . '<p class="text-sm">Retry the HR assignment using the same company, Task, employee, and idempotency identity.</p>';
            $taskModalRender(['id' => 'projects-task-retry-modal-' . $taskKey, 'title' => 'Retry HR Assignment', 'description' => 'The Task remains saved while this owner handoff is retried.', 'open_label' => 'Retry assignment', 'submit_label' => 'Submit', 'confirm_message' => 'Confirm this HR assignment retry.', 'body_html' => $retryBody, 'hidden_html' => $taskCommonHidden('retry_project_task_assignment') . '<input type="hidden" name="task_key" value="' . $projectsEscape($taskKey) . '">'], $retryOpen, false);
        }
    endforeach; ?>
</div>
