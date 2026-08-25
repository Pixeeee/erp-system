<?php
declare(strict_types=1);

$supportIssueRegion = (string) ($supportIssueViewRegion ?? 'main');
$supportIssues = is_array($supportData['issues'] ?? null) ? $supportData['issues'] : [];
$supportPriorities = is_array($supportData['issue_priorities'] ?? null) ? $supportData['issue_priorities'] : [];
$supportIssueTypes = is_array($supportData['issue_types'] ?? null) ? $supportData['issue_types'] : [];
$supportSelectedIssue = is_array($supportData['selected_issue'] ?? null) ? $supportData['selected_issue'] : [];
$supportSelectedPriority = is_array($supportData['selected_issue_priority'] ?? null) ? $supportData['selected_issue_priority'] : [];
$supportSelectedType = is_array($supportData['selected_issue_type'] ?? null) ? $supportData['selected_issue_type'] : [];
$supportIssueState = is_array($activeModuleFormState ?? null) ? $activeModuleFormState : [];
$supportIssueAction = (string) ($supportIssueState['action'] ?? '');
$supportIssuePrior = is_array($supportIssueState['input'] ?? null) ? $supportIssueState['input'] : [];
$supportIssueError = trim((string) ($supportIssueState['error'] ?? ''));
$supportIssueEscape = static fn (mixed $value): string => bx_h((string) $value);

if ($supportIssueRegion === 'main'):
?>
<section data-support-issue-directory aria-labelledby="support-issue-directory-heading">
    <div class="flex flex-wrap items-end justify-between gap-3 border-b pb-3">
        <div><h3 id="support-issue-directory-heading" class="text-sm font-semibold">Issue queue</h3><p class="mt-1 text-sm text-muted-foreground">Company-owned records without external owner references.</p></div>
        <span class="text-xs text-muted-foreground"><?= count($supportIssues) ?> records</span>
    </div>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full min-w-[42rem] text-left text-sm">
            <thead class="border-b text-xs text-muted-foreground"><tr><th class="py-2 pr-3">Issue</th><th class="py-2 pr-3">Subject</th><th class="py-2 pr-3">Priority</th><th class="py-2 pr-3">Status</th><th class="py-2 text-right">Action</th></tr></thead>
            <tbody class="divide-y">
                <?php if ($supportIssues === []): ?><tr><td colspan="5" class="py-8 text-center text-muted-foreground">No local Issues yet.</td></tr><?php endif; ?>
                <?php foreach ($supportIssues as $issue): ?>
                    <tr>
                        <td class="py-3 pr-3 font-mono text-xs"><?= $supportIssueEscape($issue['issue_code'] ?? '') ?></td>
                        <td class="max-w-72 truncate py-3 pr-3 font-medium"><?= $supportIssueEscape($issue['subject'] ?? '') ?></td>
                        <td class="py-3 pr-3"><?= $supportIssueEscape($issue['priority_name'] ?? 'Not set') ?></td>
                        <td class="py-3 pr-3"><?= (string) ($issue['archived_at'] ?? '') !== '' ? 'ARCHIVED' : $supportIssueEscape($issue['issue_status'] ?? '') ?></td>
                        <td class="py-3 text-right"><a class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium" href="./?view=support-service&amp;section=issues-tickets&amp;issue=<?= $supportIssueEscape($issue['issue_key'] ?? '') ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="mt-6 grid gap-6 border-t pt-5 lg:grid-cols-2" aria-label="Issue masters">
    <div><div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold">Issue Priorities</h3><span class="text-xs text-muted-foreground"><?= count($supportPriorities) ?></span></div><div class="mt-2 divide-y"><?php if ($supportPriorities === []): ?><p class="py-3 text-sm text-muted-foreground">No priorities yet.</p><?php endif; ?><?php foreach ($supportPriorities as $priority): ?><a href="./?view=support-service&amp;section=issues-tickets&amp;priority=<?= $supportIssueEscape($priority['issue_priority_key'] ?? '') ?>" class="flex items-center justify-between gap-3 py-2.5 text-sm hover:underline"><span class="truncate font-medium"><?= $supportIssueEscape($priority['priority_name'] ?? '') ?></span><span class="text-xs text-muted-foreground"><?= $supportIssueEscape($priority['priority_status'] ?? '') ?></span></a><?php endforeach; ?></div></div>
    <div><div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold">Issue Types</h3><span class="text-xs text-muted-foreground"><?= count($supportIssueTypes) ?></span></div><div class="mt-2 divide-y"><?php if ($supportIssueTypes === []): ?><p class="py-3 text-sm text-muted-foreground">No types yet.</p><?php endif; ?><?php foreach ($supportIssueTypes as $type): ?><a href="./?view=support-service&amp;section=issues-tickets&amp;issue_type=<?= $supportIssueEscape($type['issue_type_key'] ?? '') ?>" class="flex items-center justify-between gap-3 py-2.5 text-sm hover:underline"><span class="truncate font-medium"><?= $supportIssueEscape($type['issue_type_name'] ?? '') ?></span><span class="text-xs text-muted-foreground"><?= $supportIssueEscape($type['issue_type_status'] ?? '') ?></span></a><?php endforeach; ?></div></div>
</section>
<?php
else:
    $supportIssueOpen = static fn (string $action): bool => $supportIssueError !== '' && $supportIssueAction === $action;
    $supportIssueValue = static function (array $record, string $key, string $default = '') use (
        $supportIssueOpen,
        $supportIssueAction,
        $supportIssuePrior
    ): string {
        $recordAction = match ($key) {
            'priority_name', 'priority_description', 'priority_status' => 'support_save_issue_priority',
            'issue_type_name', 'issue_type_description', 'issue_type_status' => 'support_save_issue_type',
            'next_status', 'reason' => 'support_transition_issue',
            default => 'support_save_issue',
        };
        if ($supportIssueOpen($recordAction) && array_key_exists($key, $supportIssuePrior)) {
            return (string) $supportIssuePrior[$key];
        }

        return (string) ($record[$key] ?? $default);
    };
    $supportIssueErrorHtml = static function (string $action) use ($supportIssueOpen, $supportIssueError, $supportIssueEscape): string {
        return $supportIssueOpen($action)
            ? '<p role="alert" class="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">' . $supportIssueEscape($supportIssueError) . '</p>'
            : '';
    };
    $supportIssueHidden = static function (string $action): string {
        return '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '">' .
            '<input type="hidden" name="module_view" value="support-service">' .
            '<input type="hidden" name="section" value="issues-tickets">' .
            '<input type="hidden" name="action" value="' . bx_h($action) . '">';
    };
    $supportIssueRenderModal = static function (array $definition, bool $open): void {
        $recordModal = $definition;
        ob_start();
        require dirname(__DIR__, 3) . '/views/partials/record-modal.php';
        $markup = (string) ob_get_clean();
        if ($open) {
            $markup = preg_replace(
                '/data-record-modal hidden/',
                'data-record-modal data-record-modal-open-on-load hidden',
                $markup,
                1
            ) ?? $markup;
        }
        echo $markup;
    };

    $issueRecord = $supportSelectedIssue;
    $priorityRecord = $supportSelectedPriority;
    $typeRecord = $supportSelectedType;
    $issueOpen = $supportIssueOpen('support_save_issue');
    $priorityOpen = $supportIssueOpen('support_save_issue_priority');
    $typeOpen = $supportIssueOpen('support_save_issue_type');
    $transitionOpen = $supportIssueOpen('support_transition_issue');
    $issueStableKey = $issueOpen ? (string) ($supportIssuePrior['issue_key'] ?? '') : (string) ($issueRecord['issue_key'] ?? '');
    $issueVersion = $issueOpen ? (string) ($supportIssuePrior['expected_version'] ?? '0') : (string) ($issueRecord['issue_version'] ?? '0');
    $issueStatus = $issueOpen ? (string) ($supportIssuePrior['issue_status'] ?? 'OPEN') : (string) ($issueRecord['issue_status'] ?? 'OPEN');
    $priorityStableKey = $priorityOpen ? (string) ($supportIssuePrior['issue_priority_key'] ?? '') : (string) ($priorityRecord['issue_priority_key'] ?? '');
    $priorityVersion = $priorityOpen ? (string) ($supportIssuePrior['expected_version'] ?? '0') : (string) ($priorityRecord['priority_version'] ?? '0');
    $typeStableKey = $typeOpen ? (string) ($supportIssuePrior['issue_type_key'] ?? '') : (string) ($typeRecord['issue_type_key'] ?? '');
    $typeVersion = $typeOpen ? (string) ($supportIssuePrior['expected_version'] ?? '0') : (string) ($typeRecord['issue_type_version'] ?? '0');

    ob_start();
    echo $supportIssueErrorHtml('support_save_issue');
    $selectedPriority = $supportIssueValue($issueRecord, 'issue_priority_key');
    $selectedType = $supportIssueValue($issueRecord, 'issue_type_key');
?>
<div class="grid gap-4 sm:grid-cols-2">
    <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Subject<input name="subject" required maxlength="255" class="h-9 rounded-md border bg-background px-3" value="<?= $supportIssueEscape($supportIssueValue($issueRecord, 'subject')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Description<textarea name="description" rows="5" maxlength="20000" class="min-h-28 rounded-md border bg-background p-3"><?= $supportIssueEscape($supportIssueValue($issueRecord, 'description')) ?></textarea></label>
    <label class="grid gap-1.5 text-sm font-medium">Priority<select name="issue_priority_key" class="h-9 rounded-md border bg-background px-3"><option value="">Not set</option><?php foreach ($supportPriorities as $priority): if (($priority['priority_status'] ?? '') !== 'ACTIVE') continue; ?><option value="<?= $supportIssueEscape($priority['issue_priority_key'] ?? '') ?>"<?= $selectedPriority === (string) ($priority['issue_priority_key'] ?? '') ? ' selected' : '' ?>><?= $supportIssueEscape($priority['priority_name'] ?? '') ?></option><?php endforeach; ?></select></label>
    <label class="grid gap-1.5 text-sm font-medium">Issue Type<select name="issue_type_key" class="h-9 rounded-md border bg-background px-3"><option value="">Not set</option><?php foreach ($supportIssueTypes as $type): if (($type['issue_type_status'] ?? '') !== 'ACTIVE') continue; ?><option value="<?= $supportIssueEscape($type['issue_type_key'] ?? '') ?>"<?= $selectedType === (string) ($type['issue_type_key'] ?? '') ? ' selected' : '' ?>><?= $supportIssueEscape($type['issue_type_name'] ?? '') ?></option><?php endforeach; ?></select></label>
    <?php foreach (['customer_key' => 'Customer', 'contact_key' => 'Contact', 'project_key' => 'Project or service work', 'assigned_admin_key' => 'Assignment', 'communication_key' => 'Communication', 'portal_owner_key' => 'Portal owner'] as $field => $label): ?><label class="grid gap-1.5 text-sm font-medium"><?= $supportIssueEscape($label) ?><input name="<?= $supportIssueEscape($field) ?>" maxlength="1500" class="h-9 rounded-md border bg-background px-3" value="<?= $supportIssueEscape($supportIssueValue($issueRecord, $field)) ?>"><span class="text-xs font-normal text-muted-foreground">Blank only. Nonblank values return UNAVAILABLE_DEPENDENCY.</span></label><?php endforeach; ?>
</div>
<?php
    $issueBody = (string) ob_get_clean();
    $supportIssueRenderModal([
        'id' => 'support-issue-modal',
        'title' => $issueRecord === [] ? 'Add Issue' : 'Edit Issue',
        'description' => 'Save a Support-owned Issue without external owner references.',
        'open_label' => $issueRecord === [] ? 'Add Issue' : 'Edit Issue',
        'submit_label' => 'Submit',
        'confirm_message' => 'Confirm this Issue before saving it.',
        'body_html' => $issueBody,
        'hidden_html' => $supportIssueHidden('support_save_issue') .
            '<input type="hidden" name="issue_key" value="' . $supportIssueEscape($issueStableKey) . '">' .
            '<input type="hidden" name="expected_version" value="' . $supportIssueEscape($issueVersion) . '">' .
            '<input type="hidden" name="issue_status" value="' . $supportIssueEscape($issueStatus) . '">' .
            '<input type="hidden" name="form_schema_checksum" value="' . $supportIssueEscape($issueRecord['form_schema_checksum'] ?? yovel_admin_support_service_issue_checksum()) . '">',
    ], $issueOpen);

    ob_start();
    echo $supportIssueErrorHtml('support_save_issue_priority');
    $priorityStatus = $supportIssueValue($priorityRecord, 'priority_status', 'ACTIVE');
?>
<div class="grid gap-4">
    <label class="grid gap-1.5 text-sm font-medium">Priority name<input name="priority_name" required maxlength="120" class="h-9 rounded-md border bg-background px-3" value="<?= $supportIssueEscape($supportIssueValue($priorityRecord, 'priority_name')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium">Description<textarea name="priority_description" rows="4" maxlength="500" class="min-h-24 rounded-md border bg-background p-3"><?= $supportIssueEscape($supportIssueValue($priorityRecord, 'priority_description')) ?></textarea></label>
    <label class="grid gap-1.5 text-sm font-medium">Status<select name="priority_status" class="h-9 rounded-md border bg-background px-3"><?php foreach (['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive', 'ARCHIVED' => 'Archived'] as $value => $label): ?><option value="<?= $value ?>"<?= $priorityStatus === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
</div>
<?php
    $priorityBody = (string) ob_get_clean();
    $supportIssueRenderModal([
        'id' => 'support-issue-priority-modal',
        'title' => $priorityRecord === [] ? 'Add Issue Priority' : 'Edit Issue Priority',
        'description' => 'Maintain a reusable company Issue Priority.',
        'open_label' => $priorityRecord === [] ? 'Add Priority' : 'Edit Priority',
        'submit_label' => 'Submit',
        'confirm_message' => 'Confirm this Issue Priority before saving it.',
        'body_html' => $priorityBody,
        'hidden_html' => $supportIssueHidden('support_save_issue_priority') .
            '<input type="hidden" name="issue_priority_key" value="' . $supportIssueEscape($priorityStableKey) . '">' .
            '<input type="hidden" name="expected_version" value="' . $supportIssueEscape($priorityVersion) . '">',
    ], $priorityOpen);

    ob_start();
    echo $supportIssueErrorHtml('support_save_issue_type');
    $typeStatus = $supportIssueValue($typeRecord, 'issue_type_status', 'ACTIVE');
?>
<div class="grid gap-4">
    <label class="grid gap-1.5 text-sm font-medium">Issue Type name<input name="issue_type_name" required maxlength="120" class="h-9 rounded-md border bg-background px-3" value="<?= $supportIssueEscape($supportIssueValue($typeRecord, 'issue_type_name')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium">Description<textarea name="issue_type_description" rows="4" maxlength="500" class="min-h-24 rounded-md border bg-background p-3"><?= $supportIssueEscape($supportIssueValue($typeRecord, 'issue_type_description')) ?></textarea></label>
    <label class="grid gap-1.5 text-sm font-medium">Status<select name="issue_type_status" class="h-9 rounded-md border bg-background px-3"><?php foreach (['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive', 'ARCHIVED' => 'Archived'] as $value => $label): ?><option value="<?= $value ?>"<?= $typeStatus === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
</div>
<?php
    $typeBody = (string) ob_get_clean();
    $supportIssueRenderModal([
        'id' => 'support-issue-type-modal',
        'title' => $typeRecord === [] ? 'Add Issue Type' : 'Edit Issue Type',
        'description' => 'Maintain a reusable company Issue Type.',
        'open_label' => $typeRecord === [] ? 'Add Issue Type' : 'Edit Issue Type',
        'submit_label' => 'Submit',
        'confirm_message' => 'Confirm this Issue Type before saving it.',
        'body_html' => $typeBody,
        'hidden_html' => $supportIssueHidden('support_save_issue_type') .
            '<input type="hidden" name="issue_type_key" value="' . $supportIssueEscape($typeStableKey) . '">' .
            '<input type="hidden" name="expected_version" value="' . $supportIssueEscape($typeVersion) . '">',
    ], $typeOpen);

    ob_start();
    echo $supportIssueErrorHtml('support_transition_issue');
    $transitionIssueKey = $transitionOpen
        ? (string) ($supportIssuePrior['issue_key'] ?? '')
        : (string) ($supportSelectedIssue['issue_key'] ?? '');
    $transitionVersion = $transitionOpen
        ? (string) ($supportIssuePrior['expected_version'] ?? '0')
        : (string) ($supportSelectedIssue['issue_version'] ?? '0');
    $nextStatus = $supportIssueValue([], 'next_status', 'REPLIED');
?>
<div class="grid gap-4">
    <label class="grid gap-1.5 text-sm font-medium">Issue<select name="issue_key" data-support-transition-issue required class="h-9 rounded-md border bg-background px-3"><option value="" data-version="0">Select Issue</option><?php foreach ($supportIssues as $issue): ?><option value="<?= $supportIssueEscape($issue['issue_key'] ?? '') ?>" data-version="<?= $supportIssueEscape($issue['issue_version'] ?? '0') ?>"<?= $transitionIssueKey === (string) ($issue['issue_key'] ?? '') ? ' selected' : '' ?>><?= $supportIssueEscape(($issue['issue_code'] ?? '') . ' - ' . ($issue['subject'] ?? '')) ?></option><?php endforeach; ?></select></label>
    <label class="grid gap-1.5 text-sm font-medium">Next status<select name="next_status" required class="h-9 rounded-md border bg-background px-3"><?php foreach (['REPLIED' => 'Replied', 'ON_HOLD' => 'On hold', 'OPEN' => 'Open', 'RESOLVED' => 'Resolved', 'CLOSED' => 'Closed', 'ARCHIVED' => 'Archived', 'RESTORED' => 'Restored'] as $value => $label): ?><option value="<?= $value ?>"<?= $nextStatus === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
    <label class="grid gap-1.5 text-sm font-medium">Reason<textarea name="reason" required rows="4" maxlength="1000" class="min-h-24 rounded-md border bg-background p-3"><?= $supportIssueEscape($supportIssueValue([], 'reason')) ?></textarea></label>
    <input type="hidden" name="expected_version" data-support-transition-version value="<?= $supportIssueEscape($transitionVersion) ?>">
</div>
<?php
    $transitionBody = (string) ob_get_clean();
    $supportIssueRenderModal([
        'id' => 'support-issue-transition-modal',
        'title' => 'Change Issue Status',
        'description' => 'Apply an allowed local lifecycle transition or archive action.',
        'open_label' => 'Change Status',
        'submit_label' => 'Submit',
        'confirm_message' => 'Confirm this Issue lifecycle change.',
        'body_html' => $transitionBody,
        'hidden_html' => $supportIssueHidden('support_transition_issue'),
    ], $transitionOpen);
?>
<section class="mt-5 border-t pt-5" aria-labelledby="support-owner-dependencies-heading">
    <h3 id="support-owner-dependencies-heading" class="text-sm font-semibold">Owner dependencies</h3>
    <p class="mt-2 text-sm leading-6 text-muted-foreground">Customer, contact, project, assignment, communication, and portal-owner references remain UNAVAILABLE_DEPENDENCY. Blank-reference Issues continue locally.</p>
</section>
<script>
(() => {
    const select = document.querySelector('[data-support-transition-issue]');
    const version = document.querySelector('[data-support-transition-version]');
    if (!select || !version) return;
    select.addEventListener('change', () => {
        version.value = select.selectedOptions[0]?.dataset.version || '0';
    });
})();
</script>
<?php endif; ?>
