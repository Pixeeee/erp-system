<?php
declare(strict_types=1);

$workforceProjection = is_array($activeModuleData['workforce_calendar_projection'] ?? null)
    ? $activeModuleData['workforce_calendar_projection']
    : yovel_admin_operations_workforce_projection_unavailable($company ?? [], 'HR workforce projection is unavailable.');
$workforceDirectories = is_array($workforceProjection['directories'] ?? null) ? $workforceProjection['directories'] : [];
$workforceEmployees = is_array($workforceDirectories['employee']['records'] ?? null) ? $workforceDirectories['employee']['records'] : [];
$workforceStatus = (string) ($workforceProjection['status_filter'] ?? 'ALL');
ob_start();
?>
<div class="grid gap-4">
    <div class="grid gap-2"><h3 class="text-sm font-semibold">HR owner actions</h3><?php foreach (array_slice($workforceProjection['owner_actions'] ?? [], 0, 3) as $action): ?><a class="inline-flex min-h-9 items-center gap-2 rounded-md bg-muted px-3 py-2 text-sm font-medium hover:bg-muted/70" href="<?= bx_h((string) $action['href']) ?>"><span class="material-symbols-rounded text-base" aria-hidden="true">open_in_new</span><?= bx_h((string) $action['label']) ?></a><?php endforeach; ?></div>
    <div class="grid gap-2"><h3 class="text-sm font-semibold">Employee status</h3><div class="flex flex-wrap gap-2"><?php foreach (['ALL', 'ACTIVE', 'INACTIVE', 'ON_LEAVE', 'SEPARATED', 'DRAFT'] as $status): ?><a class="inline-flex min-h-8 items-center rounded-md px-2.5 py-1 text-xs font-medium <?= $workforceStatus === $status ? 'bg-primary text-primary-foreground' : 'bg-muted hover:bg-muted/70' ?>" href="?view=operations&amp;section=workforce-directory&amp;workforce_status=<?= bx_h($status) ?>"><?= bx_h(ucwords(strtolower(str_replace('_', ' ', $status)))) ?></a><?php endforeach; ?></div></div>
</div>
<?php
$operationsSectionTools = (string) ob_get_clean();
?>
<div class="grid gap-5" data-operations-workforce>
    <div><h2 class="text-base font-semibold">Workforce directory</h2><p class="mt-1 text-sm leading-6 text-muted-foreground">Read-only employee assignments from the verified HR workforce contract. Authoritative changes remain in HR.</p></div>
    <?php if (($workforceProjection['contract_status'] ?? '') !== 'AVAILABLE'): ?><div role="status" class="rounded-md bg-muted/60 p-4 text-sm"><p class="font-semibold">HR workforce unavailable</p><p class="mt-1 leading-6 text-muted-foreground"><?= bx_h((string) ($workforceProjection['message'] ?? 'The HR workforce owner contract is unavailable.')) ?> Other Operations work remains available.</p></div><?php endif; ?>
    <div class="grid gap-3 sm:grid-cols-3">
        <?php foreach (['employee', 'department', 'designation'] as $directoryKey): $directory = $workforceDirectories[$directoryKey] ?? []; ?>
            <div class="rounded-md bg-muted/40 p-3"><div class="flex items-start justify-between gap-3"><h3 class="text-sm font-semibold"><?= bx_h((string) ($directory['label'] ?? ucwords(str_replace('_', ' ', $directoryKey)))) ?></h3><span class="text-xs font-medium"><?= bx_h((string) ($directory['status'] ?? 'UNAVAILABLE_DEPENDENCY')) ?></span></div><p class="mt-1 text-xs text-muted-foreground"><?= count($directory['records'] ?? []) ?> stable references</p><?php if (($directory['reason'] ?? '') !== ''): ?><p class="mt-2 text-xs leading-5 text-muted-foreground"><?= bx_h((string) $directory['reason']) ?></p><?php endif; ?></div>
        <?php endforeach; ?>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[48rem] text-left text-sm"><thead class="bg-muted/50 text-xs text-muted-foreground"><tr><th class="px-3 py-2.5">Employee</th><th class="px-3 py-2.5">Status</th><th class="px-3 py-2.5">Department reference</th><th class="px-3 py-2.5">Job position reference</th></tr></thead><tbody class="divide-y"><?php if ($workforceEmployees === []): ?><tr><td colspan="4" class="px-3 py-10 text-center text-muted-foreground"><?= ($workforceProjection['contract_status'] ?? '') === 'AVAILABLE' ? 'No employees match this status filter.' : 'Employee records are unavailable from HR.' ?></td></tr><?php else: foreach ($workforceEmployees as $employee): ?><tr><td class="px-3 py-2.5"><div class="font-medium"><?= bx_h((string) $employee['employee_name']) ?></div><div class="text-xs text-muted-foreground"><?= bx_h((string) $employee['employee_code']) ?></div></td><td class="px-3 py-2.5"><?= bx_h((string) $employee['employee_status']) ?></td><td class="px-3 py-2.5 font-mono text-xs break-all"><?= bx_h((string) ($employee['department_key'] !== '' ? $employee['department_key'] : 'Not assigned')) ?></td><td class="px-3 py-2.5 font-mono text-xs break-all"><?= bx_h((string) ($employee['job_position_key'] !== '' ? $employee['job_position_key'] : 'Not assigned')) ?></td></tr><?php endforeach; endif; ?></tbody></table>
    </div>
    <div class="grid gap-3 sm:grid-cols-2"><?php foreach (['education', 'external_work_history', 'internal_work_history', 'employee_group', 'employee_group_table'] as $directoryKey): $directory = $workforceDirectories[$directoryKey] ?? []; ?><div class="rounded-md bg-muted/40 p-3"><div class="flex items-start justify-between gap-3"><h3 class="text-sm font-semibold"><?= bx_h((string) ($directory['label'] ?? ucwords(str_replace('_', ' ', $directoryKey)))) ?></h3><span class="text-xs font-medium"><?= bx_h((string) ($directory['status'] ?? 'UNAVAILABLE_DEPENDENCY')) ?></span></div><p role="status" class="mt-2 text-sm leading-6 text-muted-foreground"><?= bx_h((string) ($directory['reason'] ?? 'This HR owner field is unavailable.')) ?> This source is non-blocking.</p></div><?php endforeach; ?></div>
</div>
