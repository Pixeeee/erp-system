<?php
declare(strict_types=1);

$supportSlaRegion = (string) ($supportSlaViewRegion ?? 'main');
$supportSlas = is_array($supportData['slas'] ?? null) ? $supportData['slas'] : [];
$supportSlaIssues = is_array($supportData['issues'] ?? null) ? $supportData['issues'] : [];
$supportSlaPriorities = is_array($supportData['issue_priorities'] ?? null) ? $supportData['issue_priorities'] : [];
$supportSelectedSla = is_array($supportData['selected_sla'] ?? null) ? $supportData['selected_sla'] : [];
$supportSlaState = is_array($activeModuleFormState ?? null) ? $activeModuleFormState : [];
$supportSlaAction = (string) ($supportSlaState['action'] ?? '');
$supportSlaPrior = is_array($supportSlaState['input'] ?? null) ? $supportSlaState['input'] : [];
$supportSlaError = trim((string) ($supportSlaState['error'] ?? ''));
$supportSlaEscape = static fn (mixed $value): string => bx_h((string) $value);

if ($supportSlaRegion === 'main'):
    if ($supportSection === 'first-response-tracking'):
?>
<section aria-labelledby="support-first-response-heading">
    <div class="flex flex-wrap items-end justify-between gap-3 border-b pb-3"><div><h3 id="support-first-response-heading" class="text-sm font-semibold">First-response tracking</h3><p class="mt-1 text-sm text-muted-foreground">Fixed-clock SLA state from company-owned Issues.</p></div><span class="text-xs text-muted-foreground"><?= count($supportSlaIssues) ?> records</span></div>
    <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[48rem] text-left text-sm"><thead class="border-b text-xs text-muted-foreground"><tr><th class="py-2 pr-3">Issue</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3">Agreement state</th><th class="py-2 pr-3">Response by</th><th class="py-2">Resolution by</th></tr></thead><tbody class="divide-y">
    <?php if ($supportSlaIssues === []): ?><tr><td colspan="5" class="py-8 text-center text-muted-foreground">No local Issues are available.</td></tr><?php endif; ?>
    <?php foreach ($supportSlaIssues as $issue): ?><tr><td class="py-3 pr-3"><p class="font-mono text-xs"><?= $supportSlaEscape($issue['issue_code'] ?? '') ?></p><p class="mt-1 max-w-64 truncate font-medium"><?= $supportSlaEscape($issue['subject'] ?? '') ?></p></td><td class="py-3 pr-3"><?= $supportSlaEscape($issue['issue_status'] ?? '') ?></td><td class="py-3 pr-3"><?= $supportSlaEscape($issue['agreement_status'] ?? 'Not tracked') ?></td><td class="py-3 pr-3"><?= $supportSlaEscape($issue['response_by'] ?? 'Unavailable') ?></td><td class="py-3"><?= $supportSlaEscape($issue['resolution_by'] ?? 'Unavailable') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php else: ?>
<section aria-labelledby="support-sla-directory-heading">
    <div class="flex flex-wrap items-end justify-between gap-3 border-b pb-3"><div><h3 id="support-sla-directory-heading" class="text-sm font-semibold">Service level agreements</h3><p class="mt-1 text-sm text-muted-foreground">Validated service windows, priority clocks, and applicability.</p></div><span class="text-xs text-muted-foreground"><?= count($supportSlas) ?> records</span></div>
    <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[44rem] text-left text-sm"><thead class="border-b text-xs text-muted-foreground"><tr><th class="py-2 pr-3">Code</th><th class="py-2 pr-3">Agreement</th><th class="py-2 pr-3">Applicability</th><th class="py-2 pr-3">Status</th><th class="py-2 text-right">Action</th></tr></thead><tbody class="divide-y">
    <?php if ($supportSlas === []): ?><tr><td colspan="5" class="py-8 text-center text-muted-foreground">No service level agreements yet.</td></tr><?php endif; ?>
    <?php foreach ($supportSlas as $sla): ?><tr><td class="py-3 pr-3 font-mono text-xs"><?= $supportSlaEscape($sla['sla_code'] ?? '') ?></td><td class="py-3 pr-3 font-medium"><?= $supportSlaEscape($sla['service_level_name'] ?? '') ?></td><td class="py-3 pr-3"><?= (int) ($sla['is_default'] ?? 0) === 1 ? 'Default' : $supportSlaEscape($sla['entity_type'] ?? 'Condition') ?></td><td class="py-3 pr-3"><?= $supportSlaEscape($sla['sla_status'] ?? '') ?></td><td class="py-3 text-right"><a class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium" href="./?view=support-service&amp;section=sla-rules&amp;sla=<?= $supportSlaEscape($sla['service_level_agreement_key'] ?? '') ?>">Edit</a></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php endif; ?>
<?php
else:
    $slaOpen = $supportSlaError !== '' && $supportSlaAction === 'support_save_sla';
    $resetOpen = $supportSlaError !== '' && $supportSlaAction === 'support_reset_sla';
    $value = static function (string $key, string $default = '') use ($slaOpen, $supportSlaPrior, $supportSelectedSla): string {
        if ($slaOpen && array_key_exists($key, $supportSlaPrior)) {
            return (string) $supportSlaPrior[$key];
        }
        return (string) ($supportSelectedSla[$key] ?? $default);
    };
    $renderModal = static function (array $definition, bool $open): void {
        $recordModal = $definition;
        ob_start();
        require dirname(__DIR__, 3) . '/views/partials/record-modal.php';
        $markup = (string) ob_get_clean();
        if ($open) {
            $markup = preg_replace('/data-record-modal hidden/', 'data-record-modal data-record-modal-open-on-load hidden', $markup, 1) ?? $markup;
        }
        echo $markup;
    };
    $hidden = static fn (string $action, string $section): string =>
        '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '">' .
        '<input type="hidden" name="module_view" value="support-service">' .
        '<input type="hidden" name="section" value="' . bx_h($section) . '">' .
        '<input type="hidden" name="action" value="' . bx_h($action) . '">';
    $error = static fn (string $action): string => $supportSlaError !== '' && $supportSlaAction === $action
        ? '<p role="alert" class="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">' . $supportSlaEscape($supportSlaError) . '</p>'
        : '';
    $defaultDays = json_encode(array_map(static fn (int $day): array => ['weekday' => $day, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'sort_order' => $day * 10], range(1, 5)), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $defaultPriorities = json_encode(array_map(static fn (array $priority, int $index): array => ['issue_priority_key' => (string) $priority['issue_priority_key'], 'response_seconds' => 3600, 'resolution_seconds' => 14400, 'is_default' => $index === 0 ? 1 : 0], array_values(array_filter($supportSlaPriorities, static fn (array $priority): bool => ($priority['priority_status'] ?? '') === 'ACTIVE')), array_keys(array_values(array_filter($supportSlaPriorities, static fn (array $priority): bool => ($priority['priority_status'] ?? '') === 'ACTIVE')))), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $daysJson = $value('service_days_json', $supportSelectedSla !== [] ? json_encode($supportSelectedSla['service_days'] ?? [], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) : $defaultDays);
    $prioritiesJson = $value('priorities_json', $supportSelectedSla !== [] ? json_encode($supportSelectedSla['priorities'] ?? [], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) : $defaultPriorities);
    $pauseCsv = $value('pause_statuses_csv', implode(',', array_column($supportSelectedSla['pause_statuses'] ?? [], 'issue_status')) ?: 'ON_HOLD');
    $fulfilledCsv = $value('fulfilled_statuses_csv', implode(',', array_column($supportSelectedSla['fulfilled_statuses'] ?? [], 'issue_status')) ?: 'RESOLVED,CLOSED');
    ob_start(); echo $error('support_save_sla');
?>
<div class="grid gap-4 sm:grid-cols-2">
    <label class="grid gap-1.5 text-sm font-medium">Code<input name="sla_code" required maxlength="120" class="h-9 rounded-md border bg-background px-3" value="<?= $supportSlaEscape($value('sla_code')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium">Agreement name<input name="service_level_name" required maxlength="160" class="h-9 rounded-md border bg-background px-3" value="<?= $supportSlaEscape($value('service_level_name')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium">Timezone<input name="timezone_name" required maxlength="64" class="h-9 rounded-md border bg-background px-3" value="<?= $supportSlaEscape($value('timezone_name', 'UTC')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium">Applicability<select name="entity_type" class="h-9 rounded-md border bg-background px-3"><?php foreach (['NONE' => 'Default or condition', 'CUSTOMER' => 'Customer', 'CUSTOMER_GROUP' => 'Customer Group', 'TERRITORY' => 'Territory'] as $key => $label): ?><option value="<?= $key ?>"<?= $value('entity_type', 'NONE') === $key ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
    <label class="grid gap-1.5 text-sm font-medium">Entity key<input name="entity_key" maxlength="1500" class="h-9 rounded-md border bg-background px-3" value="<?= $supportSlaEscape($value('entity_key')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium">Entity name<input name="entity_name_snapshot" maxlength="255" class="h-9 rounded-md border bg-background px-3" value="<?= $supportSlaEscape($value('entity_name_snapshot')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium">Start date<input type="date" name="start_date" class="h-9 rounded-md border bg-background px-3" value="<?= $supportSlaEscape($value('start_date')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium">End date<input type="date" name="end_date" class="h-9 rounded-md border bg-background px-3" value="<?= $supportSlaEscape($value('end_date')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Allowlisted condition JSON<textarea name="condition_json" rows="3" class="min-h-20 rounded-md border bg-background p-3 font-mono text-xs"><?= $supportSlaEscape($value('condition_json')) ?></textarea></label>
    <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Service days JSON<textarea name="service_days_json" required rows="8" class="min-h-36 rounded-md border bg-background p-3 font-mono text-xs"><?= $supportSlaEscape($daysJson) ?></textarea></label>
    <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Priority clocks JSON<textarea name="priorities_json" required rows="7" class="min-h-32 rounded-md border bg-background p-3 font-mono text-xs"><?= $supportSlaEscape($prioritiesJson) ?></textarea></label>
    <label class="grid gap-1.5 text-sm font-medium">Pause statuses<input name="pause_statuses_csv" class="h-9 rounded-md border bg-background px-3" value="<?= $supportSlaEscape($pauseCsv) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium">Fulfilled statuses<input name="fulfilled_statuses_csv" class="h-9 rounded-md border bg-background px-3" value="<?= $supportSlaEscape($fulfilledCsv) ?>"></label>
    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="is_default" value="1"<?= $value('is_default', '0') === '1' ? ' checked' : '' ?>>Default agreement</label>
    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="enabled" value="1"<?= $value('enabled', '1') === '1' ? ' checked' : '' ?>>Enabled</label>
    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="apply_for_resolution" value="1"<?= $value('apply_for_resolution', '1') === '1' ? ' checked' : '' ?>>Track resolution</label>
    <label class="grid gap-1.5 text-sm font-medium">Status<select name="sla_status" class="h-9 rounded-md border bg-background px-3"><?php foreach (['ACTIVE', 'INACTIVE', 'ARCHIVED'] as $status): ?><option value="<?= $status ?>"<?= $value('sla_status', 'ACTIVE') === $status ? ' selected' : '' ?>><?= ucfirst(strtolower($status)) ?></option><?php endforeach; ?></select></label>
</div>
<?php
    $slaBody = (string) ob_get_clean();
    $renderModal([
        'id' => 'support-sla-modal', 'title' => $supportSelectedSla === [] ? 'Add Service Level Agreement' : 'Edit Service Level Agreement',
        'description' => 'Save service windows and priority clocks as one company-scoped aggregate.',
        'open_label' => $supportSelectedSla === [] ? 'Add SLA' : 'Edit SLA', 'submit_label' => 'Submit',
        'confirm_message' => 'Confirm this Service Level Agreement before saving it.', 'body_html' => $slaBody,
        'hidden_html' => $hidden('support_save_sla', 'sla-rules') .
            '<input type="hidden" name="service_level_agreement_key" value="' . $supportSlaEscape($slaOpen ? ($supportSlaPrior['service_level_agreement_key'] ?? '') : ($supportSelectedSla['service_level_agreement_key'] ?? '')) . '">' .
            '<input type="hidden" name="expected_version" value="' . $supportSlaEscape($slaOpen ? ($supportSlaPrior['expected_version'] ?? '0') : ($supportSelectedSla['sla_version'] ?? '0')) . '">' .
            '<input type="hidden" name="document_type" value="ISSUE"><input type="hidden" name="calendar_key" value="' . $supportSlaEscape($value('calendar_key')) . '">' .
            '<input type="hidden" name="form_schema_checksum" value="' . $supportSlaEscape($supportSelectedSla['form_schema_checksum'] ?? yovel_admin_support_sla_form_checksum()) . '">',
    ], $slaOpen);

    ob_start(); echo $error('support_reset_sla');
    $resetIssueKey = $resetOpen ? (string) ($supportSlaPrior['issue_key'] ?? '') : '';
?>
<div class="grid gap-4"><label class="grid gap-1.5 text-sm font-medium">Issue<select name="issue_key" data-support-sla-reset-issue required class="h-9 rounded-md border bg-background px-3"><option value="" data-version="0">Select Issue</option><?php foreach ($supportSlaIssues as $issue): if ((string) ($issue['service_level_agreement_key'] ?? '') === '') continue; ?><option value="<?= $supportSlaEscape($issue['issue_key'] ?? '') ?>" data-version="<?= $supportSlaEscape($issue['issue_version'] ?? '0') ?>"<?= $resetIssueKey === (string) ($issue['issue_key'] ?? '') ? ' selected' : '' ?>><?= $supportSlaEscape(($issue['issue_code'] ?? '') . ' - ' . ($issue['subject'] ?? '')) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm font-medium">Reason<textarea name="reason" required rows="4" maxlength="1000" class="min-h-24 rounded-md border bg-background p-3"><?= $supportSlaEscape($resetOpen ? ($supportSlaPrior['reason'] ?? '') : '') ?></textarea></label><input type="hidden" name="expected_version" data-support-sla-reset-version value="<?= $supportSlaEscape($resetOpen ? ($supportSlaPrior['expected_version'] ?? '0') : '0') ?>"></div>
<?php
    $resetBody = (string) ob_get_clean();
    $renderModal([
        'id' => 'support-sla-reset-modal', 'title' => 'Reset Issue SLA',
        'description' => 'Restart the response and resolution clocks with an audited reason.',
        'open_label' => 'Reset SLA', 'submit_label' => 'Submit',
        'confirm_message' => 'Confirm this Issue SLA reset before saving it.', 'body_html' => $resetBody,
        'hidden_html' => $hidden('support_reset_sla', 'first-response-tracking'),
    ], $resetOpen);
?>
<section class="mt-5 border-t pt-5"><h3 class="text-sm font-semibold">Owner dependencies</h3><p class="mt-2 text-sm leading-6 text-muted-foreground">Operations holiday dates and Sales customer segmentation are consumed only through their published owner contracts. Default local SLA clocks remain functional while either contract is unavailable.</p></section>
<script>(() => { const select = document.querySelector('[data-support-sla-reset-issue]'); const version = document.querySelector('[data-support-sla-reset-version]'); if (!select || !version) return; const sync = () => { version.value = select.selectedOptions[0]?.dataset.version || '0'; }; select.addEventListener('change', sync); sync(); })();</script>
<?php endif; ?>
