<?php
declare(strict_types=1);

ob_start();
foreach (['import' => ['Import Records', 'import_operations_records', 'IMPORT'], 'export' => ['Export Records', 'export_operations_records', 'EXPORT']] as $direction => [$title, $action, $type]) {
    $recordModal = [
        'id' => 'operations-' . $direction . '-modal', 'title' => $title, 'description' => 'Queue a company-scoped ' . $direction . ' using references and validated metadata.', 'open_label' => $title, 'submit_label' => 'Submit',
        'confirm_message' => 'Confirm this ' . $direction . ' request. Nothing is queued before confirmation.',
        'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="section" value="import-export-jobs"><input type="hidden" name="job_type" value="' . $type . '">',
        'body_html' => '<div class="grid gap-1.5"><label class="text-sm font-medium">Idempotency key</label><input name="idempotency_key" required maxlength="160" class="h-9 rounded-md border bg-background px-3 text-sm"></div><div class="grid gap-1.5"><label class="text-sm font-medium">Request JSON</label><textarea name="payload_json" required rows="7" class="rounded-md border bg-background px-3 py-2 font-mono text-xs">{}</textarea></div>',
    ];
    require dirname(__DIR__) . '/record-modal.php';
}
$operationsSectionTools = (string) ob_get_clean();
$operationsBulkJobs = array_values(array_filter($activeModuleData['jobs'] ?? [], static fn (array $job): bool => in_array((string) ($job['job_type'] ?? ''), ['IMPORT', 'EXPORT'], true)));
?>
<div class="grid gap-3"><div><h2 class="text-base font-semibold">Import and export jobs</h2><p class="mt-1 text-sm text-muted-foreground">Uploads use opaque attachment references; exported results stay attached to job history.</p></div><div class="overflow-x-auto rounded-md border"><table class="w-full min-w-[42rem] text-left text-sm"><thead class="bg-muted/50 text-xs text-muted-foreground"><tr><th class="px-3 py-2.5">Job</th><th class="px-3 py-2.5">Direction</th><th class="px-3 py-2.5">Status</th><th class="px-3 py-2.5">Created</th></tr></thead><tbody class="divide-y"><?php if ($operationsBulkJobs === []): ?><tr><td colspan="4" class="px-3 py-10 text-center text-muted-foreground">No import or export jobs recorded.</td></tr><?php else: foreach ($operationsBulkJobs as $job): ?><tr><td class="px-3 py-2.5 font-mono text-xs"><?= bx_h((string) $job['job_key']) ?></td><td class="px-3 py-2.5"><?= bx_h((string) $job['job_type']) ?></td><td class="px-3 py-2.5"><?= bx_h((string) $job['status']) ?></td><td class="px-3 py-2.5 text-muted-foreground"><?= bx_h((string) $job['created_at']) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
