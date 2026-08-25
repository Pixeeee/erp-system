<?php
declare(strict_types=1);

$complianceRules = is_array($complianceData['rules'] ?? null) ? $complianceData['rules'] : [];
$complianceSelectedRule = is_array($complianceData['selected_rule'] ?? null) ? $complianceData['selected_rule'] : null;
$complianceFormState = is_array($activeModuleFormState ?? null) ? $activeModuleFormState : [];
$complianceFailedAction = (string) ($complianceFormState['action'] ?? '');
$complianceFailedInput = is_array($complianceFormState['input'] ?? null) ? $complianceFormState['input'] : [];
$complianceRuleValue = static function (string $key, string $fallback = '') use ($complianceFailedInput): string {
    return (string) ($complianceFailedInput[$key] ?? $fallback);
};
$complianceRuleModalState = static function (string $action) use ($complianceFailedAction): string {
    return $complianceFailedAction === $action ? ' data-record-modal-open-on-load' : '';
};
$complianceRuleSection = in_array($complianceSection, ['tax-rules', 'vat-settings'], true) ? $complianceSection : 'tax-rules';
$complianceSelectedRuleKey = (string) ($complianceSelectedRule['rule_set_key'] ?? '');
?>
<div data-compliance-rules-view>
    <header class="flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4">
        <div>
            <h2 class="text-base font-semibold"><?= $complianceSection === 'vat-settings' ? 'Philippine VAT settings' : 'Effective compliance rules' ?></h2>
            <p class="mt-1 text-sm leading-6 text-muted-foreground">Versioned legal sources, policy controls, and immutable approved snapshots.</p>
        </div>
        <button type="button" data-record-modal-open="compliance-rule-modal" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">
            <span class="material-symbols-rounded text-base" aria-hidden="true">add</span>New rule
        </button>
    </header>
    <div class="min-h-0 overflow-auto">
        <table class="w-full min-w-[44rem] text-sm">
            <thead class="border-b bg-muted/30 text-left text-xs text-muted-foreground"><tr><th class="px-5 py-3">Rule</th><th class="px-5 py-3">Effective period</th><th class="px-5 py-3">Version</th><th class="px-5 py-3">State</th></tr></thead>
            <tbody class="divide-y">
            <?php foreach ($complianceRules as $rule): ?>
                <tr>
                    <td class="px-5 py-3"><a class="font-medium hover:underline" href="./?view=compliance-localization&amp;section=<?= bx_h($complianceRuleSection) ?>&amp;rule=<?= bx_h((string) $rule['rule_set_key']) ?>"><?= bx_h((string) $rule['rule_name']) ?></a><span class="block font-mono text-xs text-muted-foreground"><?= bx_h((string) $rule['rule_code']) ?></span></td>
                    <td class="px-5 py-3 font-mono text-xs"><?= bx_h((string) $rule['effective_from']) ?> to <?= bx_h((string) (($rule['effective_to'] ?? null) ?: 'Open')) ?></td>
                    <td class="px-5 py-3"><?= (int) $rule['version_number'] ?></td>
                    <td class="px-5 py-3"><?= bx_h((string) $rule['version_status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($complianceRules === []): ?><tr><td colspan="4" class="px-5 py-10 text-center text-muted-foreground">No Compliance rule versions yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($complianceSelectedRule): ?>
        <section class="border-t px-5 py-4" aria-labelledby="compliance-rule-timeline-title">
            <h3 id="compliance-rule-timeline-title" class="text-sm font-semibold">Selected version timeline</h3>
            <div class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                <div><span class="block text-xs text-muted-foreground">Created by</span><span class="font-mono text-xs"><?= bx_h((string) $complianceSelectedRule['created_by_admin_key']) ?></span></div>
                <div><span class="block text-xs text-muted-foreground">Approved by</span><span class="font-mono text-xs"><?= bx_h((string) (($complianceSelectedRule['approved_by_admin_key'] ?? null) ?: 'Pending')) ?></span></div>
                <div><span class="block text-xs text-muted-foreground">Snapshot</span><span class="font-mono text-xs"><?= bx_h(substr((string) $complianceSelectedRule['rule_sha256'], 0, 16)) ?>...</span></div>
            </div>
        </section>
    <?php endif; ?>
</div>

<div id="compliance-rule-modal" data-record-modal<?= $complianceRuleModalState('save_compliance_rule') ?> hidden class="fixed inset-0 z-[70] grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="compliance-rule-modal-title" aria-describedby="compliance-rule-modal-description">
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-4xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b bg-popover px-6 py-4"><div><h2 id="compliance-rule-modal-title" class="text-base font-semibold">New Compliance rule</h2><p id="compliance-rule-modal-description" class="mt-1 text-sm text-muted-foreground">Create an effective-dated Philippine rule Draft for separate approval.</p></div><button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close rule dialog"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button></header>
        <form method="post" data-confirm-submit data-confirm-message="Save this Philippine Compliance rule as a Draft?" class="contents">
            <div class="min-h-0 flex-1 overflow-y-auto p-6"><div class="grid gap-4 sm:grid-cols-2">
                <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="compliance-localization"><input type="hidden" name="action" value="save_compliance_rule"><input type="hidden" name="section" value="<?= bx_h($complianceRuleSection) ?>">
                <label class="grid gap-1.5 text-sm">Rule type<select name="rule_type" class="h-9 rounded-md border bg-background px-3" required><option value="VAT_SETTINGS">VAT Settings</option><option value="TAX_POLICY">Tax Policy</option><option value="RETENTION_POLICY">Retention Policy</option></select></label>
                <label class="grid gap-1.5 text-sm">Rule code<input name="rule_code" maxlength="80" required class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceRuleValue('rule_code', 'PH_VAT_CONFIGURATION')) ?>"></label>
                <label class="grid gap-1.5 text-sm sm:col-span-2">Rule name<input name="rule_name" maxlength="180" required class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceRuleValue('rule_name')) ?>"></label>
                <label class="grid gap-1.5 text-sm">Version<input type="number" name="version_number" min="1" required class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceRuleValue('version_number', '1')) ?>"></label>
                <label class="grid gap-1.5 text-sm">VAT registration class<input name="vat_registration_class" maxlength="80" required class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceRuleValue('vat_registration_class', 'VAT_REGISTERED')) ?>"></label>
                <label class="grid gap-1.5 text-sm">Effective from<input type="date" name="effective_from" required class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceRuleValue('effective_from', date('Y-m-d'))) ?>"></label>
                <label class="grid gap-1.5 text-sm">Effective to<input type="date" name="effective_to" class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceRuleValue('effective_to')) ?>"></label>
                <label class="grid gap-1.5 text-sm sm:col-span-2">Legal authority<input name="authority_reference" maxlength="160" required class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceRuleValue('authority_reference')) ?>"></label>
                <label class="grid gap-1.5 text-sm sm:col-span-2">Authority URL<input type="url" name="authority_url" maxlength="500" class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceRuleValue('authority_url')) ?>"></label>
                <label class="grid gap-1.5 text-sm">Retention years<input type="number" name="retention_years" min="1" max="25" required class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceRuleValue('retention_years', '10')) ?>"></label>
                <label class="grid gap-1.5 text-sm">Tax policy references<input name="tax_code_policy_refs" class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceRuleValue('tax_code_policy_refs', 'VAT12,ZERO_RATED,EXEMPT')) ?>"></label>
                <label class="grid gap-1.5 text-sm sm:col-span-2">Invoice profile keys<input name="invoice_profile_keys" class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceRuleValue('invoice_profile_keys', 'PH_STANDARD_TAX_INVOICE')) ?>"></label>
                <button type="submit" class="inline-flex h-9 w-fit items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Save Draft</button>
            </div></div>
            <footer class="m-0 flex w-full shrink-0 items-center justify-between border-t bg-popover px-6 py-4"><span class="text-xs text-muted-foreground">Approval is a separate action.</span><button type="button" data-record-modal-close class="h-9 rounded-md border px-3 text-sm">Cancel</button></footer>
        </form>
    </section>
</div>

<?php
$complianceRuleActions = [
    ['id' => 'compliance-mapping-modal', 'action' => 'add_compliance_mapping', 'title' => 'Add mapping', 'description' => 'Validate an active posting account through the injected Finance reference service.', 'confirm' => 'Add this validated Finance account mapping to the selected Draft?', 'submit' => 'Add Mapping'],
    ['id' => 'compliance-approve-modal', 'action' => 'approve_compliance_rule', 'title' => 'Approve', 'description' => 'Freeze the rule, authority, policy, and account mappings as one immutable snapshot.', 'confirm' => 'Approve and freeze this Compliance rule snapshot?', 'submit' => 'Approve Rule'],
    ['id' => 'compliance-supersede-modal', 'action' => 'supersede_compliance_rule', 'title' => 'Supersede', 'description' => 'Retain the approved snapshot for historical dates while a successor takes effect.', 'confirm' => 'Supersede this approved Compliance rule?', 'submit' => 'Supersede Rule'],
    ['id' => 'compliance-rule-archive-modal', 'action' => 'archive_compliance_rule', 'title' => 'Archive', 'description' => 'Archive the selected Draft or historical rule without changing its snapshot.', 'confirm' => 'Archive this Compliance rule version?', 'submit' => 'Archive Rule'],
];
foreach ($complianceRuleActions as $ruleAction):
?>
<div id="<?= bx_h($ruleAction['id']) ?>" data-record-modal<?= $complianceRuleModalState($ruleAction['action']) ?> hidden class="fixed inset-0 z-[70] grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="<?= bx_h($ruleAction['id']) ?>-title" aria-describedby="<?= bx_h($ruleAction['id']) ?>-description">
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b bg-popover px-6 py-4"><div><h2 id="<?= bx_h($ruleAction['id']) ?>-title" class="text-base font-semibold"><?= bx_h($ruleAction['title']) ?></h2><p id="<?= bx_h($ruleAction['id']) ?>-description" class="mt-1 text-sm leading-6 text-muted-foreground"><?= bx_h($ruleAction['description']) ?></p></div><button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close <?= bx_h($ruleAction['title']) ?> dialog"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button></header>
        <form method="post" data-confirm-submit data-confirm-message="<?= bx_h($ruleAction['confirm']) ?>" class="contents">
            <div class="min-h-0 flex-1 overflow-y-auto p-6"><div class="grid gap-4">
                <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="compliance-localization"><input type="hidden" name="action" value="<?= bx_h($ruleAction['action']) ?>"><input type="hidden" name="section" value="<?= bx_h($complianceRuleSection) ?>"><input type="hidden" name="rule_set_key" value="<?= bx_h($complianceRuleValue('rule_set_key', $complianceSelectedRuleKey)) ?>">
                <?php if ($ruleAction['action'] === 'add_compliance_mapping'): ?>
                    <label class="grid gap-1.5 text-sm">Mapping code<input name="mapping_code" maxlength="80" required class="h-9 rounded-md border bg-background px-3" value="<?= bx_h($complianceRuleValue('mapping_code')) ?>"></label>
                    <label class="grid gap-1.5 text-sm">Tax role<select name="tax_role" class="h-9 rounded-md border bg-background px-3" required><?php foreach (['OUTPUT_VAT','INPUT_VAT','WITHHOLDING','ADJUSTMENT','SETTLEMENT'] as $role): ?><option><?= bx_h($role) ?></option><?php endforeach; ?></select></label>
                    <label class="grid gap-1.5 text-sm">Finance account key<input name="finance_account_key" maxlength="36" required class="h-9 rounded-md border bg-background px-3 font-mono" value="<?= bx_h($complianceRuleValue('finance_account_key')) ?>"></label>
                <?php else: ?>
                    <label class="grid gap-1.5 text-sm">Decision comments<textarea name="comments" required class="min-h-24 rounded-md border bg-background p-3"><?= bx_h($complianceRuleValue('comments')) ?></textarea></label>
                <?php endif; ?>
                <button type="submit" class="inline-flex h-9 w-fit items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"><?= bx_h($ruleAction['submit']) ?></button>
            </div></div>
            <footer class="m-0 flex w-full shrink-0 items-center justify-between border-t bg-popover px-6 py-4"><span class="text-xs text-muted-foreground">Separate confirmation required.</span><button type="button" data-record-modal-close class="h-9 rounded-md border px-3 text-sm">Cancel</button></footer>
        </form>
    </section>
</div>
<?php endforeach; ?>
