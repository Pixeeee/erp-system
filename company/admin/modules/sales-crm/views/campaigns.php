<?php
/** Campaign workspace variables are provided by views/workspace.php. */
$campaignRows = is_array($salesCrmData['campaigns'] ?? null) ? $salesCrmData['campaigns'] : [];
$campaignEfficiencyRows = is_array($salesCrmData['campaign_efficiencies'] ?? null) ? $salesCrmData['campaign_efficiencies'] : [];
$campaignEditKey = trim((string) ($_GET['edit'] ?? ''));
$editCampaign = $campaignEditKey !== '' ? yovel_admin_find_record($campaignRows, 'campaign_key', $campaignEditKey) : null;
$campaignFormState = is_array($activeModuleFormState ?? null)
    && (string) ($activeModuleFormState['section'] ?? '') === 'campaigns'
    && (string) ($activeModuleFormState['action'] ?? '') === 'sales_crm_save_campaign'
        ? $activeModuleFormState
        : [];
$transitionFormState = is_array($activeModuleFormState ?? null)
    && (string) ($activeModuleFormState['section'] ?? '') === 'campaigns'
    && (string) ($activeModuleFormState['action'] ?? '') === 'sales_crm_transition_campaign'
        ? $activeModuleFormState
        : [];
$campaignFormRecord = $editCampaign ?: (is_array($campaignFormState['input'] ?? null) ? $campaignFormState['input'] : []);
$campaignFormSchedules = is_array($campaignFormRecord['email_schedules'] ?? null) ? $campaignFormRecord['email_schedules'] : ($editCampaign['email_schedules'] ?? []);
$campaignFormShouldOpen = $editCampaign !== null || $campaignFormState !== [] || (string) ($_GET['modal'] ?? '') === 'campaign';
$campaignFieldsBySection = [];
$campaignScheduleSchemaFields = [];
foreach ($schemaFields as $campaignField) {
    if (str_starts_with((string) ($campaignField['key'] ?? ''), 'campaign_email_')) {
        $campaignScheduleSchemaFields[] = $campaignField;
    } elseif (filter_var($campaignField['visible'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
        $campaignFieldsBySection[(string) ($campaignField['section'] ?? 'overview')][] = $campaignField;
    }
}
$campaignVisibleScheduleFields = array_values(array_filter($campaignScheduleSchemaFields, static fn (array $field): bool => filter_var($field['visible'] ?? true, FILTER_VALIDATE_BOOLEAN)));
$renderCampaignScheduleField = static function (array $field, array $schedule, int $index, bool $template = false): void {
    $fieldKey = (string) ($field['key'] ?? '');
    $childName = match ($fieldKey) {
        'campaign_email_schedule_code' => 'schedule_code',
        'campaign_email_subject' => 'subject',
        'campaign_email_recipient_segment' => 'recipient_segment',
        'campaign_email_scheduled_at' => 'scheduled_at',
        'campaign_email_send_status' => 'send_status',
        default => '',
    };
    if ($childName === '') {
        return;
    }
    $value = (string) ($schedule[$childName] ?? ($childName === 'send_status' ? 'SCHEDULED' : ''));
    $nameAttribute = $template ? 'data-name="' . bx_h($childName) . '"' : 'name="email_schedules[' . $index . '][' . bx_h($childName) . ']"';
    if (!filter_var($field['visible'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
        ?><input type="hidden" <?= $nameAttribute ?> value="<?= bx_h($value) ?>"><?php
        return;
    }
    $required = filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'required' : '';
    $width = (string) ($field['width'] ?? 'half');
    $class = $width === 'full' ? 'lg:col-span-4' : ($width === 'half' ? 'lg:col-span-2' : '');
    ?>
    <label class="grid gap-1.5 text-xs font-medium <?= bx_h($class) ?>">
        <?= bx_h((string) ($field['label'] ?? $childName)) ?>
        <?php if ((string) ($field['type'] ?? '') === 'select'): ?>
            <select class="h-9 rounded-md border bg-background px-3 text-sm" <?= $nameAttribute ?> <?= $required ?>>
                <?php foreach (($field['options'] ?? ['DRAFT', 'SCHEDULED', 'CANCELLED']) as $option): ?>
                    <option value="<?= bx_h((string) $option) ?>" <?= $value === (string) $option ? 'selected' : '' ?>><?= bx_h((string) $option) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <input class="h-9 rounded-md border bg-background px-3 text-sm" type="<?= (string) ($field['type'] ?? '') === 'datetime-local' ? 'datetime-local' : 'text' ?>" <?= (string) ($field['type'] ?? '') === 'datetime-local' ? 'step="1"' : '' ?> <?= $nameAttribute ?> value="<?= bx_h((string) ($field['type'] ?? '') === 'datetime-local' ? str_replace(' ', 'T', $value) : $value) ?>" <?= $required ?>>
        <?php endif; ?>
    </label>
    <?php
};
?>
<div class="grid gap-3 border-b p-4 sm:grid-cols-4">
    <?php
    $campaignBudgetTotal = array_reduce($campaignRows, static fn (float $sum, array $row): float => $sum + (float) ($row['budget'] ?? 0), 0.0);
    $campaignOrderRevenue = array_reduce($campaignEfficiencyRows, static fn (float $sum, array $row): float => $sum + (float) ($row['attributed_revenue'] ?? 0), 0.0);
    $campaignLeadTotal = array_reduce($campaignEfficiencyRows, static fn (int $sum, array $row): int => $sum + (int) ($row['lead_count'] ?? 0), 0);
    ?>
    <div><p class="text-xs text-muted-foreground">Budget</p><p class="mt-1 text-sm font-semibold"><?= bx_h(number_format($campaignBudgetTotal, 2)) ?></p></div>
    <div><p class="text-xs text-muted-foreground">Attributed revenue</p><p class="mt-1 text-sm font-semibold"><?= bx_h(number_format($campaignOrderRevenue, 2)) ?></p></div>
    <div><p class="text-xs text-muted-foreground">Attributed leads</p><p class="mt-1 text-sm font-semibold"><?= $campaignLeadTotal ?></p></div>
    <div><p class="text-xs text-muted-foreground">Schedules</p><p class="mt-1 text-sm font-semibold"><?= array_reduce($campaignRows, static fn (int $sum, array $row): int => $sum + count($row['email_schedules'] ?? []), 0) ?></p></div>
</div>

<?php if (!$campaignRows): ?>
    <div class="yovel-hr-panel-body p-5">
        <div class="rounded-md bg-muted/40 p-4">
            <p class="text-sm font-semibold">No campaigns yet</p>
            <p class="mt-1 text-xs leading-5 text-muted-foreground">Plan the first Campaign and attach scheduled email touchpoints.</p>
        </div>
    </div>
<?php else: ?>
    <div class="yovel-hr-panel-body overflow-auto">
        <table class="w-full min-w-[1040px] text-left text-sm">
            <thead class="sticky top-0 z-10 border-b bg-card text-xs text-muted-foreground">
                <tr>
                    <th class="px-4 py-3 font-medium">Campaign</th>
                    <th class="px-4 py-3 font-medium">Lifecycle</th>
                    <th class="px-4 py-3 font-medium">Period</th>
                    <th class="px-4 py-3 font-medium">Budget / expected</th>
                    <th class="px-4 py-3 font-medium">Attribution</th>
                    <th class="px-4 py-3 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($campaignRows as $campaign): ?>
                    <?php $efficiency = $campaignEfficiencyRows[(string) $campaign['campaign_key']] ?? []; ?>
                    <tr>
                        <td class="px-4 py-4">
                            <p class="font-medium"><?= bx_h((string) $campaign['campaign_name']) ?></p>
                            <p class="mt-1 text-xs text-muted-foreground"><?= bx_h((string) $campaign['campaign_code']) ?> · <?= bx_h((string) ($campaign['campaign_type'] ?: 'General')) ?></p>
                            <p class="mt-1 text-xs text-muted-foreground"><?= count($campaign['email_schedules'] ?? []) ?> email schedules</p>
                        </td>
                        <td class="px-4 py-4"><span class="rounded-full bg-secondary px-2 py-0.5 text-xs"><?= bx_h((string) $campaign['campaign_status']) ?></span></td>
                        <td class="px-4 py-4 text-xs text-muted-foreground"><?= bx_h((string) ($campaign['start_date'] ?: 'Open')) ?> to <?= bx_h((string) ($campaign['end_date'] ?: 'Open')) ?></td>
                        <td class="px-4 py-4 text-xs"><p><?= bx_h((string) $campaign['budget']) ?></p><p class="mt-1 text-muted-foreground"><?= bx_h((string) $campaign['expected_revenue']) ?> expected</p></td>
                        <td class="px-4 py-4 text-xs"><p><?= (int) ($efficiency['lead_count'] ?? 0) ?> leads</p><p class="mt-1 text-muted-foreground"><?= bx_h((string) ($efficiency['attributed_revenue'] ?? '0.00')) ?> revenue · <?= bx_h((string) ($efficiency['roi_percent'] ?? '0.00')) ?>% ROI</p></td>
                        <td class="px-4 py-4">
                            <div class="flex flex-wrap justify-end gap-2">
                                <?php if ((string) $campaign['campaign_status'] !== 'COMPLETED'): ?>
                                    <a class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium hover:bg-muted" href="./?view=sales-crm&amp;section=campaigns&amp;edit=<?= bx_h((string) $campaign['campaign_key']) ?>">Edit</a>
                                    <?php
                                    $campaignTransitions = match ((string) $campaign['campaign_status']) {
                                        'DRAFT' => ['ACTIVE', 'INACTIVE'],
                                        'ACTIVE' => ['COMPLETED', 'INACTIVE'],
                                        'INACTIVE' => ['ACTIVE'],
                                        default => [],
                                    };
                                    ?>
                                    <?php foreach ($campaignTransitions as $campaignTarget): ?>
                                        <button type="button" data-record-modal-open="yovel-sales-campaign-transition-modal" data-campaign-transition-open data-campaign-key="<?= bx_h((string) $campaign['campaign_key']) ?>" data-campaign-name="<?= bx_h((string) $campaign['campaign_name']) ?>" data-campaign-target="<?= bx_h($campaignTarget) ?>" class="inline-flex h-8 items-center rounded-md border px-2 text-xs font-medium hover:bg-muted"><?= bx_h($campaignTarget) ?></button>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-xs text-muted-foreground">Completed and immutable</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div id="yovel-sales-campaign-modal" data-record-modal <?= $campaignFormShouldOpen ? 'data-record-modal-open-on-load' : '' ?> class="yovel-employee-modal fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-sales-campaign-modal-title" aria-describedby="yovel-sales-campaign-modal-description" hidden>
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-5xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
        <form id="yovel-sales-campaign-form" method="post" data-record-modal-form data-confirm-submit data-confirm-message="Confirm this Campaign. Persistence begins only after confirmation." class="contents">
            <div class="flex shrink-0 items-start justify-between gap-4 border-b px-5 py-4">
                <div>
                    <h3 id="yovel-sales-campaign-modal-title" class="text-base font-semibold tracking-normal"><?= $editCampaign ? 'Edit Campaign' : 'Add Campaign' ?></h3>
                    <p id="yovel-sales-campaign-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Campaign fields follow the published Sales / CRM form layout.</p>
                </div>
                <button type="button" data-record-modal-close class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted" aria-label="Close Campaign form">×</button>
            </div>
            <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
            <input type="hidden" name="module_view" value="sales-crm">
            <input type="hidden" name="action" value="sales_crm_save_campaign">
            <input type="hidden" name="section" value="campaigns">
            <input type="hidden" name="campaign_key" value="<?= bx_h((string) ($campaignFormRecord['campaign_key'] ?? '')) ?>">
            <input type="hidden" name="expected_version" value="<?= (int) ($campaignFormRecord['campaign_version'] ?? 0) ?>">
            <input type="hidden" name="idempotency_key" value="<?= bx_h((string) ($campaignFormRecord['idempotency_key'] ?? bx_uuid())) ?>">
            <div class="yovel-hr-panel-body min-h-0 overflow-y-auto p-5">
                <?php if ($campaignFormState !== []): ?>
                    <div class="mb-5 rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive" role="alert"><?= bx_h((string) ($campaignFormState['error'] ?? 'The Campaign was not saved. Review the values and try again.')) ?></div>
                <?php endif; ?>
                <div class="grid gap-6">
                    <?php foreach ($campaignFieldsBySection as $campaignSectionKey => $campaignSectionFields): ?>
                        <section class="grid gap-4">
                            <h4 class="text-sm font-semibold"><?= bx_h(yovel_admin_sales_schema_section_label($activeSalesCrmSchema, $campaignSectionKey)) ?></h4>
                            <div class="grid gap-3 lg:grid-cols-3">
                                <?php foreach ($campaignSectionFields as $campaignField): ?>
                                    <?php if ((string) ($campaignField['key'] ?? '') === 'campaign_status'): ?>
                                        <div class="grid gap-1.5">
                                            <label class="text-xs font-medium" for="sales_campaign_status_display"><?= bx_h((string) ($campaignField['label'] ?? 'Status')) ?></label>
                                            <input id="sales_campaign_status_display" class="h-9 rounded-md border bg-muted px-3 text-sm" value="<?= bx_h((string) ($campaignFormRecord['campaign_status'] ?? 'DRAFT')) ?>" readonly>
                                            <input type="hidden" name="campaign_status" value="<?= bx_h((string) ($campaignFormRecord['campaign_status'] ?? 'DRAFT')) ?>">
                                        </div>
                                    <?php else: ?>
                                        <?php yovel_admin_render_sales_form_field($campaignField, $campaignFormRecord, $salesCrmData); ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                    <section class="grid gap-3" aria-labelledby="campaign-email-schedule-heading">
                        <div class="flex items-center justify-between gap-3">
                            <div><h4 id="campaign-email-schedule-heading" class="text-sm font-semibold">Campaign email schedule</h4><p class="mt-1 text-xs text-muted-foreground">Each row remains a child of this Campaign stable key.</p></div>
                            <?php if ($campaignVisibleScheduleFields): ?><button type="button" data-campaign-schedule-row-insert class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium hover:bg-muted">Schedule email</button><?php endif; ?>
                        </div>
                        <div data-campaign-schedule-rows class="grid gap-3">
                            <?php foreach ($campaignFormSchedules as $scheduleIndex => $campaignSchedule): ?>
                                <div data-campaign-schedule-row class="grid gap-3 rounded-md border p-3 lg:grid-cols-4">
                                    <input type="hidden" name="email_schedules[<?= (int) $scheduleIndex ?>][campaign_email_schedule_key]" value="<?= bx_h((string) ($campaignSchedule['campaign_email_schedule_key'] ?? '')) ?>">
                                    <?php foreach ($campaignScheduleSchemaFields as $scheduleField): ?><?php $renderCampaignScheduleField($scheduleField, $campaignSchedule, (int) $scheduleIndex); ?><?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap justify-between gap-2 border-t px-5 py-4">
                <span class="text-xs text-muted-foreground">Yovel East · Sales / CRM Campaigns</span>
                <div class="flex gap-2">
                    <button type="button" data-record-modal-close class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Cancel</button>
                    <button type="submit" data-confirm-submit-action class="inline-flex h-9 items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Save Campaign</button>
                </div>
            </div>
        </form>
    </section>
</div>

<div id="yovel-sales-campaign-transition-modal" data-record-modal data-campaign-transition-modal <?= $transitionFormState !== [] ? 'data-record-modal-open-on-load' : '' ?> class="fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="campaign-transition-title" aria-describedby="campaign-transition-copy" hidden>
    <section class="flex w-[calc(100vw-2rem)] max-w-md flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
        <form method="post" data-record-modal-form data-confirm-submit data-confirm-message="Confirm this Campaign lifecycle change. The update begins only after confirmation." class="contents">
            <div class="flex items-start justify-between gap-4 border-b px-5 py-4"><div><h3 id="campaign-transition-title" class="text-base font-semibold">Change Campaign lifecycle</h3><p id="campaign-transition-copy" data-campaign-transition-copy class="mt-1 text-sm text-muted-foreground">Choose a published lifecycle action.</p></div><button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close lifecycle form">×</button></div>
            <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
            <input type="hidden" name="module_view" value="sales-crm">
            <input type="hidden" name="action" value="sales_crm_transition_campaign">
            <input type="hidden" name="section" value="campaigns">
            <input type="hidden" name="campaign_key" data-campaign-transition-key value="<?= bx_h((string) ($transitionFormState['input']['campaign_key'] ?? '')) ?>">
            <input type="hidden" name="campaign_status" data-campaign-transition-target value="<?= bx_h((string) ($transitionFormState['input']['campaign_status'] ?? '')) ?>">
            <div class="p-5"><?php if ($transitionFormState !== []): ?><div class="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive" role="alert"><?= bx_h((string) ($transitionFormState['error'] ?? 'The lifecycle change was not saved.')) ?></div><?php else: ?><p class="text-sm leading-6 text-muted-foreground">Completed Campaigns become immutable, including their email schedules.</p><?php endif; ?></div>
            <div class="flex justify-end gap-2 border-t px-5 py-4"><button type="button" data-record-modal-close class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium">Cancel</button><button type="submit" data-confirm-submit-action class="inline-flex h-9 items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Continue</button></div>
        </form>
    </section>
</div>

<template data-campaign-schedule-template>
    <div data-campaign-schedule-row class="grid gap-3 rounded-md border p-3 lg:grid-cols-4">
        <input type="hidden" data-name="campaign_email_schedule_key" value="">
        <?php foreach ($campaignScheduleSchemaFields as $scheduleField): ?><?php $renderCampaignScheduleField($scheduleField, [], 0, true); ?><?php endforeach; ?>
    </div>
</template>
<script>
(() => {
    const rows = document.querySelector('[data-campaign-schedule-rows]');
    const template = document.querySelector('[data-campaign-schedule-template]');
    document.querySelector('[data-campaign-schedule-row-insert]')?.addEventListener('click', () => {
        if (!rows || !template) return;
        const index = rows.querySelectorAll('[data-campaign-schedule-row]').length;
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[data-name]').forEach((control) => {
            control.name = `email_schedules[${index}][${control.dataset.name}]`;
            control.removeAttribute('data-name');
        });
        rows.append(fragment);
        rows.querySelector('[data-campaign-schedule-row]:last-child input:not([type="hidden"])')?.focus();
    });
    document.querySelectorAll('[data-campaign-transition-open]').forEach((button) => button.addEventListener('click', () => {
        const modal = document.querySelector('[data-campaign-transition-modal]');
        if (!modal) return;
        modal.querySelector('[data-campaign-transition-key]').value = button.dataset.campaignKey || '';
        modal.querySelector('[data-campaign-transition-target]').value = button.dataset.campaignTarget || '';
        modal.querySelector('[data-campaign-transition-copy]').textContent = `${button.dataset.campaignName || 'Campaign'} will move to ${button.dataset.campaignTarget || ''}.`;
    }));
})();
</script>
