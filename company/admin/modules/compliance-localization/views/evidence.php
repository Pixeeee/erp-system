<?php
declare(strict_types=1);

$complianceEvidenceRows = is_array($complianceData['evidence'] ?? null) ? $complianceData['evidence'] : [];
$complianceSelectedEvidence = is_array($complianceData['selected_evidence'] ?? null) ? $complianceData['selected_evidence'] : null;
$complianceRetentionRules = array_values(array_filter(
    is_array($complianceData['rules'] ?? null) ? $complianceData['rules'] : [],
    static fn (array $rule): bool => (string) $rule['rule_type'] === 'RETENTION_POLICY' && in_array((string) $rule['version_status'], ['APPROVED', 'SUPERSEDED'], true)
));
$complianceFormState = is_array($activeModuleFormState ?? null) ? $activeModuleFormState : [];
$complianceFailedAction = (string) ($complianceFormState['action'] ?? '');
$complianceFailedInput = is_array($complianceFormState['input'] ?? null) ? $complianceFormState['input'] : [];
$complianceEvidenceValue = static function (string $key, string $fallback = '') use ($complianceFailedInput): string {
    return (string) ($complianceFailedInput[$key] ?? $fallback);
};
$complianceEvidenceModalState = static function (string $action) use ($complianceFailedAction): string {
    return $complianceFailedAction === $action ? ' data-record-modal-open-on-load' : '';
};
$complianceSelectedEvidenceKey = (string) ($complianceSelectedEvidence['evidence_key'] ?? '');
$complianceActiveHolds = array_values(array_filter(
    is_array($complianceSelectedEvidence['holds'] ?? null) ? $complianceSelectedEvidence['holds'] : [],
    static fn (array $hold): bool => (string) $hold['hold_status'] === 'ACTIVE'
));
?>
<div data-compliance-evidence-view>
    <header class="flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4"><div><h2 class="text-base font-semibold">Audit evidence</h2><p class="mt-1 text-sm leading-6 text-muted-foreground">Checksum-verified files, generated storage paths, retention dates, and legal holds.</p></div><button type="button" data-record-modal-open="compliance-evidence-modal" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"><span class="material-symbols-rounded text-base" aria-hidden="true">add</span>Add evidence</button></header>
    <div class="min-h-0 overflow-auto"><table class="w-full min-w-[46rem] text-sm"><thead class="border-b bg-muted/30 text-left text-xs text-muted-foreground"><tr><th class="px-5 py-3">Evidence</th><th class="px-5 py-3">Integrity</th><th class="px-5 py-3">Retention</th><th class="px-5 py-3">State</th></tr></thead><tbody class="divide-y">
        <?php foreach ($complianceEvidenceRows as $row): ?><tr><td class="px-5 py-3"><a class="font-medium hover:underline" href="./?view=compliance-localization&amp;section=audit-evidence&amp;evidence=<?= bx_h((string) $row['evidence_key']) ?>"><?= bx_h((string) $row['original_file_name']) ?></a><span class="block text-xs text-muted-foreground"><?= bx_h((string) $row['evidence_type']) ?></span></td><td class="px-5 py-3 font-mono text-xs"><?= bx_h(substr((string) $row['sha256'], 0, 16)) ?>...</td><td class="px-5 py-3"><?= bx_h((string) (($row['retention_until'] ?? null) ?: 'Policy not assigned')) ?></td><td class="px-5 py-3"><?= bx_h((string) $row['evidence_status']) ?></td></tr><?php endforeach; ?>
        <?php if ($complianceEvidenceRows === []): ?><tr><td colspan="4" class="px-5 py-10 text-center text-muted-foreground">No retained Compliance evidence yet.</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php if ($complianceSelectedEvidence): ?><section class="border-t px-5 py-4"><h3 class="text-sm font-semibold">Selected retention state</h3><div class="mt-3 grid gap-2 text-sm sm:grid-cols-3"><div><span class="block text-xs text-muted-foreground">SHA-256</span><span class="font-mono text-xs"><?= bx_h(substr((string) $complianceSelectedEvidence['sha256'], 0, 20)) ?>...</span></div><div><span class="block text-xs text-muted-foreground">Retention</span><span><?= bx_h((string) $complianceSelectedEvidence['retention_state']) ?></span></div><div><span class="block text-xs text-muted-foreground">Active holds</span><span><?= (int) $complianceSelectedEvidence['active_hold_count'] ?></span></div></div></section><?php endif; ?>
</div>

<div id="compliance-evidence-modal" data-record-modal<?= $complianceEvidenceModalState('save_compliance_evidence') ?> hidden class="fixed inset-0 z-[70] grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="compliance-evidence-modal-title" aria-describedby="compliance-evidence-modal-description">
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-3xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg"><header class="flex shrink-0 items-start justify-between gap-4 border-b bg-popover px-6 py-4"><div><h2 id="compliance-evidence-modal-title" class="text-base font-semibold">Add evidence</h2><p id="compliance-evidence-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">The server validates MIME and size before opening the metadata transaction.</p></div><button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close evidence dialog"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button></header>
        <form method="post" enctype="multipart/form-data" data-confirm-submit data-confirm-message="Retain this file as checksum-verified Compliance evidence?" class="contents"><div class="min-h-0 flex-1 overflow-y-auto p-6"><div class="grid gap-4 sm:grid-cols-2">
            <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="compliance-localization"><input type="hidden" name="action" value="save_compliance_evidence"><input type="hidden" name="section" value="audit-evidence"><input type="hidden" name="source_module" value="COMPLIANCE_LOCALIZATION">
            <label class="grid gap-1.5 text-sm sm:col-span-2">File<input type="file" name="evidence_file" accept=".pdf,.png,.jpg,.jpeg,.txt,.csv" required class="rounded-md border bg-background px-3 py-2"></label>
            <label class="grid gap-1.5 text-sm">Evidence type<input name="evidence_type" maxlength="80" required class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceEvidenceValue('evidence_type', 'LEGAL_SOURCE')) ?>"></label>
            <label class="grid gap-1.5 text-sm">Evidence date<input type="date" name="evidence_date" required class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceEvidenceValue('evidence_date', date('Y-m-d'))) ?>"></label>
            <label class="grid gap-1.5 text-sm">Source record type<input name="source_record_type" maxlength="80" required class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceEvidenceValue('source_record_type', 'RULE_SET')) ?>"></label>
            <label class="grid gap-1.5 text-sm">Source record key<input name="source_record_key" maxlength="36" class="h-9 rounded-md border bg-background px-3 font-mono" value="<?= bx_h($complianceEvidenceValue('source_record_key')) ?>"></label>
            <label class="grid gap-1.5 text-sm sm:col-span-2">Retention rule<select name="retention_rule_set_key" class="h-9 rounded-md border bg-background px-3" required><option value="">Select approved retention rule</option><?php foreach ($complianceRetentionRules as $rule): ?><option value="<?= bx_h((string) $rule['rule_set_key']) ?>" <?= $complianceEvidenceValue('retention_rule_set_key') === (string) $rule['rule_set_key'] ? 'selected' : '' ?>><?= bx_h((string) $rule['rule_name'] . ' v' . (string) $rule['version_number']) ?></option><?php endforeach; ?></select></label>
            <label class="grid gap-1.5 text-sm sm:col-span-2">Description<textarea name="description" class="min-h-24 rounded-md border bg-background p-3"><?= bx_h($complianceEvidenceValue('description')) ?></textarea></label>
            <button type="submit" class="inline-flex h-9 w-fit items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Add Evidence</button>
        </div></div><footer class="m-0 flex w-full shrink-0 items-center justify-between border-t bg-popover px-6 py-4"><span class="text-xs text-muted-foreground">Maximum 10 MB, allow-listed types only.</span><button type="button" data-record-modal-close class="h-9 rounded-md border px-3 text-sm">Cancel</button></footer></form>
    </section>
</div>

<?php
$complianceEvidenceActions = [
    ['id' => 'compliance-hold-modal', 'action' => 'place_compliance_hold', 'title' => 'Place hold', 'confirm' => 'Place this evidence on an indefinite legal hold?', 'submit' => 'Place Hold'],
    ['id' => 'compliance-release-hold-modal', 'action' => 'release_compliance_hold', 'title' => 'Release hold', 'confirm' => 'Release this legal hold and restore dated retention?', 'submit' => 'Release Hold'],
    ['id' => 'compliance-evidence-archive-modal', 'action' => 'archive_compliance_evidence', 'title' => 'Archive', 'confirm' => 'Archive this evidence after retention and hold validation?', 'submit' => 'Archive Evidence'],
];
foreach ($complianceEvidenceActions as $evidenceAction):
?>
<div id="<?= bx_h($evidenceAction['id']) ?>" data-record-modal<?= $complianceEvidenceModalState($evidenceAction['action']) ?> hidden class="fixed inset-0 z-[70] grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="<?= bx_h($evidenceAction['id']) ?>-title">
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg"><header class="flex shrink-0 items-start justify-between gap-4 border-b bg-popover px-6 py-4"><h2 id="<?= bx_h($evidenceAction['id']) ?>-title" class="text-base font-semibold"><?= bx_h($evidenceAction['title']) ?></h2><button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close <?= bx_h($evidenceAction['title']) ?> dialog"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button></header>
        <form method="post" data-confirm-submit data-confirm-message="<?= bx_h($evidenceAction['confirm']) ?>" class="contents"><div class="min-h-0 flex-1 overflow-y-auto p-6"><div class="grid gap-4">
            <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="compliance-localization"><input type="hidden" name="action" value="<?= bx_h($evidenceAction['action']) ?>"><input type="hidden" name="section" value="audit-evidence"><input type="hidden" name="evidence_key" value="<?= bx_h($complianceEvidenceValue('evidence_key', $complianceSelectedEvidenceKey)) ?>">
            <?php if ($evidenceAction['action'] === 'place_compliance_hold'): ?><label class="grid gap-1.5 text-sm">Hold reference<input name="hold_reference" maxlength="160" required class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceEvidenceValue('hold_reference')) ?>"></label><label class="grid gap-1.5 text-sm">Hold reason<textarea name="hold_reason" required class="min-h-24 rounded-md border bg-background p-3"><?= bx_h($complianceEvidenceValue('hold_reason')) ?></textarea></label>
            <?php elseif ($evidenceAction['action'] === 'release_compliance_hold'): ?><label class="grid gap-1.5 text-sm">Active hold<select name="retention_hold_key" required class="h-9 rounded-md border bg-background px-3"><option value="">Select hold</option><?php foreach ($complianceActiveHolds as $hold): ?><option value="<?= bx_h((string) $hold['retention_hold_key']) ?>"><?= bx_h((string) $hold['hold_reference']) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm">Release reason<textarea name="release_reason" required class="min-h-24 rounded-md border bg-background p-3"><?= bx_h($complianceEvidenceValue('release_reason')) ?></textarea></label>
            <?php else: ?><label class="grid gap-1.5 text-sm">Archive reason<textarea name="archive_reason" required class="min-h-24 rounded-md border bg-background p-3"><?= bx_h($complianceEvidenceValue('archive_reason')) ?></textarea></label><?php endif; ?>
            <button type="submit" class="inline-flex h-9 w-fit items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"><?= bx_h($evidenceAction['submit']) ?></button>
        </div></div><footer class="m-0 flex w-full shrink-0 items-center justify-between border-t bg-popover px-6 py-4"><span class="text-xs text-muted-foreground">Separate confirmation required.</span><button type="button" data-record-modal-close class="h-9 rounded-md border px-3 text-sm">Cancel</button></footer></form>
    </section>
</div>
<?php endforeach; ?>
