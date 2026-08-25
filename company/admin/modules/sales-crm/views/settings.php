<?php
$settingsFormState = ($activeModuleFormState['action'] ?? '') === 'sales_crm_save_settings' ? $activeModuleFormState : [];
$settingsFormValues = array_merge($salesCrmSettings, is_array($settingsFormState['input'] ?? null) ? $settingsFormState['input'] : []);
$settingsModalOpen = $settingsFormState !== [] || (string) ($_GET['modal'] ?? '') === 'settings';
$settingsSelectedKeys = static function (string $name, string $column) use ($settingsFormValues, $salesCrmSettings, $admin): array {
    if (array_key_exists($name, $settingsFormValues) && is_array($settingsFormValues[$name])) {
        return array_values(array_unique(array_map('strval', $settingsFormValues[$name])));
    }
    $selected = [];
    foreach ($salesCrmSettings['allowed_users'] ?? [] as $allowed) {
        if ((int) ($allowed[$column] ?? 0) === 1) {
            $selected[] = (string) $allowed['admin_key'];
        }
    }
    if ((int) ($salesCrmSettings['settings_version'] ?? 0) === 0 && isset($admin['admin_key'])) {
        $selected[] = (string) $admin['admin_key'];
    }
    return array_values(array_unique($selected));
};
$crmUserKeys = $settingsSelectedKeys('crm_user_keys', 'allow_crm');
$sellingUserKeys = $settingsSelectedKeys('selling_user_keys', 'allow_selling');
$settingsManagerKeys = $settingsSelectedKeys('settings_manager_keys', 'manage_settings');
?>
<?php if (!empty($salesCrmAccess['manage_settings'])): ?>
<div id="yovel-sales-crm-settings-modal" data-record-modal <?= $settingsModalOpen ? 'data-record-modal-open-on-load' : '' ?> class="fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-sales-crm-settings-title" aria-describedby="yovel-sales-crm-settings-description" hidden>
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-4xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg">
        <form method="post" data-record-modal-form data-confirm-submit data-confirm-message="Confirm these Sales / CRM settings. Access and defaults change only after confirmation." class="contents">
            <header class="flex shrink-0 items-start justify-between gap-4 border-b bg-popover px-6 py-5">
                <div>
                    <h3 id="yovel-sales-crm-settings-title" class="text-base font-semibold">Sales / CRM Settings</h3>
                    <p id="yovel-sales-crm-settings-description" class="mt-1 text-sm leading-6 text-muted-foreground">Control CRM access, Selling defaults, and company-wide validation behavior.</p>
                </div>
                <button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close Sales CRM settings">×</button>
            </header>
            <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
            <input type="hidden" name="module_view" value="sales-crm">
            <input type="hidden" name="action" value="sales_crm_save_settings">
            <input type="hidden" name="section" value="<?= bx_h($activeSalesCrmSection) ?>">
            <input type="hidden" name="expected_version" value="<?= bx_h((string) ($settingsFormValues['expected_version'] ?? $settingsFormValues['settings_version'] ?? 0)) ?>">
            <div class="min-h-0 overflow-y-auto p-6">
                <?php if ($settingsFormState !== []): ?>
                    <div class="mb-6 border-l-2 border-destructive pl-4 text-sm text-destructive" role="alert"><?= bx_h((string) ($settingsFormState['error'] ?? 'The settings were not saved.')) ?></div>
                <?php endif; ?>
                <div class="grid gap-8">
                    <section>
                        <h4 class="text-sm font-semibold">CRM Settings</h4>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <label class="flex min-h-10 items-center gap-3 text-sm"><input type="hidden" name="crm_enabled" value="0"><input type="checkbox" name="crm_enabled" value="1" <?= isset($settingsFormValues['crm_enabled']) && (string) $settingsFormValues['crm_enabled'] !== '0' ? 'checked' : '' ?>>Enable CRM workspace</label>
                            <label class="flex min-h-10 items-center gap-3 text-sm"><input type="hidden" name="allow_duplicate_lead_email" value="0"><input type="checkbox" name="allow_duplicate_lead_email" value="1" <?= isset($settingsFormValues['allow_duplicate_lead_email']) && (string) $settingsFormValues['allow_duplicate_lead_email'] !== '0' ? 'checked' : '' ?>>Allow duplicate Lead email</label>
                            <label class="grid gap-1.5 text-sm">
                                <span class="font-medium">Default Lead status</span>
                                <select class="h-10 rounded-md border bg-background px-3" name="default_lead_status">
                                    <?php foreach (['DRAFT', 'OPEN', 'QUALIFIED'] as $status): ?><option value="<?= $status ?>" <?= (string) ($settingsFormValues['default_lead_status'] ?? 'OPEN') === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?>
                                </select>
                            </label>
                            <label class="grid gap-1.5 text-sm">
                                <span class="font-medium">Default opportunity stage</span>
                                <input class="h-10 rounded-md border bg-background px-3" name="default_opportunity_stage" maxlength="120" required value="<?= bx_h((string) ($settingsFormValues['default_opportunity_stage'] ?? 'Qualification')) ?>">
                            </label>
                        </div>
                    </section>
                    <section class="border-t pt-6">
                        <h4 class="text-sm font-semibold">Selling Settings</h4>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <label class="flex min-h-10 items-center gap-3 text-sm"><input type="hidden" name="selling_enabled" value="0"><input type="checkbox" name="selling_enabled" value="1" <?= isset($settingsFormValues['selling_enabled']) && (string) $settingsFormValues['selling_enabled'] !== '0' ? 'checked' : '' ?>>Enable Selling workspace</label>
                            <label class="flex min-h-10 items-center gap-3 text-sm"><input type="hidden" name="validate_selling_price" value="0"><input type="checkbox" name="validate_selling_price" value="1" <?= isset($settingsFormValues['validate_selling_price']) && (string) $settingsFormValues['validate_selling_price'] !== '0' ? 'checked' : '' ?>>Validate selling price</label>
                            <label class="grid gap-1.5 text-sm"><span class="font-medium">Default customer group</span><input class="h-10 rounded-md border bg-background px-3" name="default_customer_group" maxlength="120" value="<?= bx_h((string) ($settingsFormValues['default_customer_group'] ?? '')) ?>"></label>
                            <label class="grid gap-1.5 text-sm"><span class="font-medium">Default price list</span><input class="h-10 rounded-md border bg-background px-3" name="default_price_list" maxlength="120" value="<?= bx_h((string) ($settingsFormValues['default_price_list'] ?? '')) ?>"></label>
                        </div>
                    </section>
                    <section class="border-t pt-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div><h4 class="text-sm font-semibold">Allowed administrators</h4><p class="mt-1 text-xs text-muted-foreground">Assign CRM, Selling, and settings-management access independently.</p></div>
                            <label class="flex min-h-10 items-center gap-3 text-sm"><input type="hidden" name="restrict_to_allowed_users" value="0"><input type="checkbox" name="restrict_to_allowed_users" value="1" <?= isset($settingsFormValues['restrict_to_allowed_users']) && (string) $settingsFormValues['restrict_to_allowed_users'] !== '0' ? 'checked' : '' ?>>Restrict access</label>
                        </div>
                        <div class="mt-4 overflow-x-auto border-y">
                            <input type="hidden" name="crm_user_keys[]" value="">
                            <input type="hidden" name="selling_user_keys[]" value="">
                            <input type="hidden" name="settings_manager_keys[]" value="">
                            <table class="w-full min-w-[40rem] text-left text-sm">
                                <thead class="bg-muted/40 text-xs text-muted-foreground"><tr><th class="px-3 py-2 font-medium">Administrator</th><th class="px-3 py-2 text-center font-medium">CRM</th><th class="px-3 py-2 text-center font-medium">Selling</th><th class="px-3 py-2 text-center font-medium">Settings</th></tr></thead>
                                <tbody class="divide-y">
                                    <?php foreach ($salesCrmWorkspace['admin_directory'] ?? [] as $directoryAdmin): ?>
                                        <?php $directoryAdminKey = (string) $directoryAdmin['admin_key']; ?>
                                        <tr>
                                            <td class="px-3 py-3"><span class="font-medium"><?= bx_h((string) ($directoryAdmin['admin_name'] ?: $directoryAdmin['admin_login'])) ?></span><span class="block text-xs text-muted-foreground"><?= bx_h((string) $directoryAdmin['admin_login']) ?></span></td>
                                            <td class="px-3 py-3 text-center"><input type="checkbox" name="crm_user_keys[]" value="<?= bx_h($directoryAdminKey) ?>" aria-label="Allow CRM for <?= bx_h((string) $directoryAdmin['admin_login']) ?>" <?= in_array($directoryAdminKey, $crmUserKeys, true) ? 'checked' : '' ?>></td>
                                            <td class="px-3 py-3 text-center"><input type="checkbox" name="selling_user_keys[]" value="<?= bx_h($directoryAdminKey) ?>" aria-label="Allow Selling for <?= bx_h((string) $directoryAdmin['admin_login']) ?>" <?= in_array($directoryAdminKey, $sellingUserKeys, true) ? 'checked' : '' ?>></td>
                                            <td class="px-3 py-3 text-center"><input type="checkbox" name="settings_manager_keys[]" value="<?= bx_h($directoryAdminKey) ?>" aria-label="Allow settings management for <?= bx_h((string) $directoryAdmin['admin_login']) ?>" <?= in_array($directoryAdminKey, $settingsManagerKeys, true) ? 'checked' : '' ?>></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
            <footer class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t bg-popover px-6 py-4">
                <span class="text-xs text-muted-foreground">Settings version <?= (int) ($salesCrmSettings['settings_version'] ?? 0) ?></span>
                <div class="flex gap-2">
                    <button type="button" data-record-modal-close class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted">Cancel</button>
                    <button type="submit" data-confirm-submit-action class="inline-flex h-9 items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Save Settings</button>
                </div>
            </footer>
        </form>
    </section>
</div>
<?php endif; ?>
