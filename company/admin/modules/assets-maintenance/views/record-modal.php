<?php
declare(strict_types=1);

$assetsRecordModalData = is_array($assetsData ?? null) ? $assetsData : [];
if ((string) ($assetsRecordModalData['section'] ?? '') === 'asset-records') {
    $assetsLifecycleState = is_array($assetsRecordModalData['form_state'] ?? null) ? $assetsRecordModalData['form_state'] : [];
    $assetsLifecyclePrior = is_array($assetsLifecycleState['input'] ?? null) ? $assetsLifecycleState['input'] : [];
    $assetsLifecycleAction = (string) ($assetsLifecycleState['action'] ?? '');
    $assetsLifecycleError = (string) ($assetsLifecycleState['error'] ?? '');
    $assetsLifecycleEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $assetsLifecycleValueFor = static fn (array $actions): Closure => static fn (string $key, string $fallback = ''): string => in_array($assetsLifecycleAction, $actions, true) ? (string) ($assetsLifecyclePrior[$key] ?? $fallback) : $fallback;
    $assetsAssetValue = $assetsLifecycleValueFor(['save_asset']);
    $assetsMovementValue = $assetsLifecycleValueFor(['save_asset_movement']);
    $assetsLocationValue = $assetsLifecycleValueFor(['save_asset_location']);
    $assetsCategoryValue = $assetsLifecycleValueFor(['save_asset_category']);
    $assetsLifecycleOptions = static function (array $rows, string $keyField, string $labelField, string $selected, string $placeholder) use ($assetsLifecycleEscape): string {
        $html = '<option value="">' . $assetsLifecycleEscape($placeholder) . '</option>';
        $found = false;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = (string) ($row[$keyField] ?? '');
            $label = (string) ($row[$labelField] ?? $key);
            if ($key === '') {
                continue;
            }
            $found = $found || $key === $selected;
            $html .= '<option value="' . $assetsLifecycleEscape($key) . '"' . ($key === $selected ? ' selected' : '') . '>' . $assetsLifecycleEscape($label) . '</option>';
        }
        if ($selected !== '' && !$found) {
            $html .= '<option value="' . $assetsLifecycleEscape($selected) . '" selected>' . $assetsLifecycleEscape($selected) . '</option>';
        }
        return $html;
    };
    $renderAssetsLifecycleModal = static function (array $modal, string $marker, array $openActions) use ($assetsLifecycleState, $assetsLifecycleAction): void {
        $recordModal = $modal;
        ob_start();
        require dirname(__DIR__, 3) . '/views/partials/record-modal.php';
        $markup = (string) ob_get_clean();
        $markup = preg_replace('/^<button\b.*?<\/button>\s*/s', '', $markup, 1) ?? $markup;
        $markup = preg_replace('/data-record-modal/', 'data-record-modal ' . $marker, $markup, 1) ?? $markup;
        if (!empty($assetsLifecycleState['open']) && in_array($assetsLifecycleAction, $openActions, true)) {
            $markup = preg_replace('/data-record-modal ' . preg_quote($marker, '/') . ' hidden/', 'data-record-modal ' . $marker . ' data-record-modal-open-on-load hidden', $markup, 1) ?? $markup;
        }
        echo $markup;
    };
    $errorFor = static fn (array $actions): string => in_array($assetsLifecycleAction, $actions, true) ? $assetsLifecycleError : '';
    $csrf = $assetsLifecycleEscape(bx_csrf_token());

    ob_start();
    if ($errorFor(['save_asset']) !== ''): ?><div role="alert" class="bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= $assetsLifecycleEscape($errorFor(['save_asset'])) ?></div><?php endif; ?>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="grid gap-1.5 text-sm font-medium">Asset code<input class="h-9 rounded-md border bg-background px-3" name="asset_code" required maxlength="100" value="<?= $assetsLifecycleEscape($assetsAssetValue('asset_code')) ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Asset name<input class="h-9 rounded-md border bg-background px-3" name="asset_name" required maxlength="180" value="<?= $assetsLifecycleEscape($assetsAssetValue('asset_name')) ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Inventory item<select class="h-9 rounded-md border bg-background px-3" name="item_owner_key" required><?= $assetsLifecycleOptions($assetsRecordModalData['inventory_items'] ?? [], 'item_key', 'item_name', $assetsAssetValue('item_owner_key'), 'Select active item') ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Category<select class="h-9 rounded-md border bg-background px-3" name="category_key" required><?= $assetsLifecycleOptions($assetsRecordModalData['categories'] ?? [], 'category_key', 'category_name', $assetsAssetValue('category_key'), 'Select category') ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Location<select class="h-9 rounded-md border bg-background px-3" name="location_key" required><?= $assetsLifecycleOptions($assetsRecordModalData['locations'] ?? [], 'location_key', 'location_name', $assetsAssetValue('location_key'), 'Select location') ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Custodian<select class="h-9 rounded-md border bg-background px-3" name="custodian_employee_key"><?= $assetsLifecycleOptions($assetsRecordModalData['employees'] ?? [], 'employee_key', 'employee_name', $assetsAssetValue('custodian_employee_key'), 'Unassigned') ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Acquisition date<input class="h-9 rounded-md border bg-background px-3" type="date" name="acquisition_date" required value="<?= $assetsLifecycleEscape($assetsAssetValue('acquisition_date', date('Y-m-d'))) ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Available for use<input class="h-9 rounded-md border bg-background px-3" type="date" name="available_for_use_date" required value="<?= $assetsLifecycleEscape($assetsAssetValue('available_for_use_date', date('Y-m-d'))) ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Source type<select class="h-9 rounded-md border bg-background px-3" name="purchase_reference_type"><?php foreach (['NONE' => 'No owner contract', 'PURCHASE_RECEIPT' => 'Purchase receipt reference', 'PURCHASE_INVOICE' => 'Purchase invoice reference', 'OPENING' => 'Opening balance'] as $value => $label): ?><option value="<?= $value ?>"<?= $assetsAssetValue('purchase_reference_type', 'NONE') === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Source owner key<input class="h-9 rounded-md border bg-background px-3" name="purchase_reference_key" maxlength="1500" value="<?= $assetsLifecycleEscape($assetsAssetValue('purchase_reference_key')) ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Quantity<input class="h-9 rounded-md border bg-background px-3" type="number" min="0.000001" step="0.000001" name="purchase_quantity" required value="<?= $assetsLifecycleEscape($assetsAssetValue('purchase_quantity', '1.000000')) ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Gross amount<input class="h-9 rounded-md border bg-background px-3" type="number" min="0" step="0.01" name="gross_purchase_amount" required value="<?= $assetsLifecycleEscape($assetsAssetValue('gross_purchase_amount', '0.00')) ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Lifecycle<select class="h-9 rounded-md border bg-background px-3" name="lifecycle_status"><?php foreach (['AVAILABLE', 'IN_USE', 'IDLE', 'SOLD', 'SCRAPPED'] as $value): ?><option value="<?= $value ?>"<?= $assetsAssetValue('lifecycle_status', 'AVAILABLE') === $value ? ' selected' : '' ?>><?= ucwords(strtolower(str_replace('_', ' ', $value))) ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Notes<textarea class="min-h-20 rounded-md border bg-background p-3" name="notes" maxlength="5000"><?= $assetsLifecycleEscape($assetsAssetValue('notes')) ?></textarea></label>
    </div>
    <?php $assetBody = (string) ob_get_clean();
    $renderAssetsLifecycleModal([
        'id' => 'assets-lifecycle-modal', 'title' => 'New Asset', 'description' => 'Create a company-owned Asset with immutable Inventory and HR reference snapshots.', 'open_label' => 'New Asset', 'submit_label' => 'Save Asset draft', 'confirm_message' => 'Confirm this Asset draft before saving it.', 'body_html' => $assetBody,
        'hidden_html' => '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="module_view" value="assets-maintenance"><input type="hidden" name="section" value="asset-records"><input type="hidden" name="action" value="save_asset"><input type="hidden" name="asset_key" value="' . $assetsLifecycleEscape($assetsAssetValue('asset_key')) . '"><input type="hidden" name="amended_from_asset_key" value="' . $assetsLifecycleEscape($assetsAssetValue('amended_from_asset_key')) . '">',
    ], 'data-assets-lifecycle-modal', ['save_asset']);

    ob_start();
    if ($errorFor(['save_asset_movement']) !== ''): ?><div role="alert" class="bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= $assetsLifecycleEscape($errorFor(['save_asset_movement'])) ?></div><?php endif; ?>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="grid gap-1.5 text-sm font-medium">Movement code<input class="h-9 rounded-md border bg-background px-3" name="movement_code" required maxlength="100" value="<?= $assetsLifecycleEscape($assetsMovementValue('movement_code')) ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Type<select class="h-9 rounded-md border bg-background px-3" name="movement_type"><?php foreach (['TRANSFER', 'CUSTODY', 'LOCATION'] as $value): ?><option value="<?= $value ?>"<?= $assetsMovementValue('movement_type', 'TRANSFER') === $value ? ' selected' : '' ?>><?= ucfirst(strtolower($value)) ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Posting time<input class="h-9 rounded-md border bg-background px-3" type="datetime-local" name="posting_at_local" required value="<?= $assetsLifecycleEscape(str_replace(' ', 'T', substr($assetsMovementValue('posting_at', date('Y-m-d H:i:s')), 0, 16))) ?>"><input type="hidden" name="posting_at" data-assets-posting-at value="<?= $assetsLifecycleEscape($assetsMovementValue('posting_at', date('Y-m-d H:i:s'))) ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Destination location<select class="h-9 rounded-md border bg-background px-3" name="to_location_key"><?= $assetsLifecycleOptions($assetsRecordModalData['locations'] ?? [], 'location_key', 'location_name', $assetsMovementValue('to_location_key'), 'Keep current location') ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Destination custodian<select class="h-9 rounded-md border bg-background px-3" name="to_custodian_employee_key"><?= $assetsLifecycleOptions($assetsRecordModalData['employees'] ?? [], 'employee_key', 'employee_name', $assetsMovementValue('to_custodian_employee_key'), 'Keep current custodian') ?></select></label>
        <?php $assetsMovementSelected = is_array($assetsLifecyclePrior['asset_keys'] ?? null) && $assetsLifecycleAction === 'save_asset_movement' ? array_map('strval', $assetsLifecyclePrior['asset_keys']) : []; ?>
        <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Assets<select class="min-h-28 rounded-md border bg-background p-2" name="asset_keys[]" multiple required><?php foreach ($assetsRecordModalData['assets'] ?? [] as $row): ?><?php if ((string) ($row['document_status'] ?? '') === 'SUBMITTED'): ?><option value="<?= $assetsLifecycleEscape((string) $row['asset_key']) ?>"<?= in_array((string) $row['asset_key'], $assetsMovementSelected, true) ? ' selected' : '' ?>><?= $assetsLifecycleEscape((string) $row['asset_code'] . ' · ' . $row['asset_name']) ?></option><?php endif; ?><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Reason<textarea class="min-h-20 rounded-md border bg-background p-3" name="reason" required maxlength="500"><?= $assetsLifecycleEscape($assetsMovementValue('reason')) ?></textarea></label>
    </div>
    <script>document.currentScript?.previousElementSibling?.querySelector('[name="posting_at_local"]')?.addEventListener('change', event => { const hidden = event.currentTarget.parentElement.querySelector('[data-assets-posting-at]'); if (hidden) hidden.value = event.currentTarget.value.replace('T', ' ') + ':00'; });</script>
    <?php $movementBody = (string) ob_get_clean();
    $renderAssetsLifecycleModal([
        'id' => 'assets-movement-modal', 'title' => 'New Asset Movement', 'description' => 'Move submitted Assets chronologically between company locations or custodians.', 'open_label' => 'New Movement', 'submit_label' => 'Save Movement draft', 'confirm_message' => 'Confirm this Asset Movement draft before saving it.', 'body_html' => $movementBody,
        'hidden_html' => '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="module_view" value="assets-maintenance"><input type="hidden" name="section" value="asset-records"><input type="hidden" name="action" value="save_asset_movement"><input type="hidden" name="movement_key" value="' . $assetsLifecycleEscape($assetsMovementValue('movement_key')) . '">',
    ], 'data-assets-movement-modal', ['save_asset_movement']);

    ob_start();
    if ($errorFor(['save_asset_location']) !== ''): ?><div role="alert" class="bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= $assetsLifecycleEscape($errorFor(['save_asset_location'])) ?></div><?php endif; ?>
    <?php $assetsLocationSelected = is_array($assetsLifecyclePrior['linked_location_keys'] ?? null) && $assetsLifecycleAction === 'save_asset_location' ? array_map('strval', $assetsLifecyclePrior['linked_location_keys']) : []; ?>
    <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm font-medium">Location code<input class="h-9 rounded-md border bg-background px-3" name="location_code" required maxlength="80" value="<?= $assetsLifecycleEscape($assetsLocationValue('location_code')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Location name<input class="h-9 rounded-md border bg-background px-3" name="location_name" required maxlength="180" value="<?= $assetsLifecycleEscape($assetsLocationValue('location_name')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Parent<select class="h-9 rounded-md border bg-background px-3" name="parent_location_key"><?= $assetsLifecycleOptions($assetsRecordModalData['locations'] ?? [], 'location_key', 'location_name', $assetsLocationValue('parent_location_key'), 'Root location') ?></select></label><label class="grid gap-1.5 text-sm font-medium">Status<select class="h-9 rounded-md border bg-background px-3" name="location_status"><option value="ACTIVE">Active</option><option value="INACTIVE"<?= $assetsLocationValue('location_status') === 'INACTIVE' ? ' selected' : '' ?>>Inactive</option></select></label><label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Linked locations<select class="min-h-28 rounded-md border bg-background p-2" name="linked_location_keys[]" multiple><?php foreach ($assetsRecordModalData['locations'] ?? [] as $row): ?><option value="<?= $assetsLifecycleEscape((string) $row['location_key']) ?>"<?= in_array((string) $row['location_key'], $assetsLocationSelected, true) ? ' selected' : '' ?>><?= $assetsLifecycleEscape((string) $row['location_name']) ?></option><?php endforeach; ?></select></label></div>
    <?php $locationBody = (string) ob_get_clean();
    $renderAssetsLifecycleModal(['id' => 'assets-location-modal', 'title' => 'New Asset Location', 'description' => 'Maintain a tree-safe company location and optional linked locations.', 'open_label' => 'New Location', 'submit_label' => 'Save Location', 'confirm_message' => 'Confirm this Asset Location before saving it.', 'body_html' => $locationBody, 'hidden_html' => '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="module_view" value="assets-maintenance"><input type="hidden" name="section" value="asset-records"><input type="hidden" name="action" value="save_asset_location"><input type="hidden" name="location_key" value="' . $assetsLifecycleEscape($assetsLocationValue('location_key')) . '">'], 'data-assets-location-modal', ['save_asset_location']);

    ob_start();
    if ($errorFor(['save_asset_category']) !== ''): ?><div role="alert" class="bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= $assetsLifecycleEscape($errorFor(['save_asset_category'])) ?></div><?php endif; ?>
    <?php $assetsCategoryAccounts = is_array($assetsLifecyclePrior['accounts'] ?? null) && $assetsLifecycleAction === 'save_asset_category' ? $assetsLifecyclePrior['accounts'] : []; $assetsCategoryAccountValue = static fn (int $index, string $key): string => (string) ($assetsCategoryAccounts[$index][$key] ?? ''); ?>
    <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm font-medium">Category code<input class="h-9 rounded-md border bg-background px-3" name="category_code" required maxlength="80" value="<?= $assetsLifecycleEscape($assetsCategoryValue('category_code')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Category name<input class="h-9 rounded-md border bg-background px-3" name="category_name" required maxlength="180" value="<?= $assetsLifecycleEscape($assetsCategoryValue('category_name')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Status<select class="h-9 rounded-md border bg-background px-3" name="category_status"><option value="ACTIVE">Active</option><option value="INACTIVE"<?= $assetsCategoryValue('category_status') === 'INACTIVE' ? ' selected' : '' ?>>Inactive</option></select></label><div class="sm:col-span-2 border-t pt-4"><p class="text-sm font-semibold">Account references</p><p class="mt-1 text-xs text-muted-foreground">Stable Finance owner keys only. Expense and gain/loss accounts are required before posting depreciation or adjustments.</p><div class="mt-3 grid gap-3 sm:grid-cols-2"><input type="hidden" name="accounts[0][account_role]" value="ASSET"><label class="grid gap-1.5 text-sm font-medium">Asset account key<input class="h-9 rounded-md border bg-background px-3" name="accounts[0][account_owner_key]" required maxlength="1500" value="<?= $assetsLifecycleEscape($assetsCategoryAccountValue(0, 'account_owner_key')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Asset account label<input class="h-9 rounded-md border bg-background px-3" name="accounts[0][account_label]" required maxlength="180" value="<?= $assetsLifecycleEscape($assetsCategoryAccountValue(0, 'account_label')) ?>"></label><input type="hidden" name="accounts[1][account_role]" value="ACCUMULATED_DEPRECIATION"><label class="grid gap-1.5 text-sm font-medium">Accumulated account key<input class="h-9 rounded-md border bg-background px-3" name="accounts[1][account_owner_key]" required maxlength="1500" value="<?= $assetsLifecycleEscape($assetsCategoryAccountValue(1, 'account_owner_key')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Accumulated account label<input class="h-9 rounded-md border bg-background px-3" name="accounts[1][account_label]" required maxlength="180" value="<?= $assetsLifecycleEscape($assetsCategoryAccountValue(1, 'account_label')) ?>"></label><input type="hidden" name="accounts[2][account_role]" value="DEPRECIATION_EXPENSE"><label class="grid gap-1.5 text-sm font-medium">Depreciation expense key<input class="h-9 rounded-md border bg-background px-3" name="accounts[2][account_owner_key]" maxlength="1500" value="<?= $assetsLifecycleEscape($assetsCategoryAccountValue(2, 'account_owner_key')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Depreciation expense label<input class="h-9 rounded-md border bg-background px-3" name="accounts[2][account_label]" maxlength="180" value="<?= $assetsLifecycleEscape($assetsCategoryAccountValue(2, 'account_label')) ?>"></label><input type="hidden" name="accounts[3][account_role]" value="GAIN_LOSS"><label class="grid gap-1.5 text-sm font-medium">Gain/loss account key<input class="h-9 rounded-md border bg-background px-3" name="accounts[3][account_owner_key]" maxlength="1500" value="<?= $assetsLifecycleEscape($assetsCategoryAccountValue(3, 'account_owner_key')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Gain/loss account label<input class="h-9 rounded-md border bg-background px-3" name="accounts[3][account_label]" maxlength="180" value="<?= $assetsLifecycleEscape($assetsCategoryAccountValue(3, 'account_label')) ?>"></label></div></div></div>
    <?php $categoryBody = (string) ob_get_clean();
    $renderAssetsLifecycleModal(['id' => 'assets-category-modal', 'title' => 'New Asset Category', 'description' => 'Define a company category and immutable external account references.', 'open_label' => 'New Category', 'submit_label' => 'Save Category', 'confirm_message' => 'Confirm this Asset Category before saving it.', 'body_html' => $categoryBody, 'hidden_html' => '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="module_view" value="assets-maintenance"><input type="hidden" name="section" value="asset-records"><input type="hidden" name="action" value="save_asset_category"><input type="hidden" name="category_key" value="' . $assetsLifecycleEscape($assetsCategoryValue('category_key')) . '">'], 'data-assets-category-modal', ['save_asset_category']);
    ?>
    <script>
    (() => {
        const selector = '[data-assets-lifecycle-modal], [data-assets-movement-modal], [data-assets-location-modal], [data-assets-category-modal]';
        const focusable = modal => [...modal.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]')].filter(element => !element.hidden && element.getClientRects().length > 0);
        document.querySelectorAll(selector).forEach(modal => modal.addEventListener('keydown', event => {
            if (event.key === 'Escape' && document.querySelector('[data-confirm-dialog]')?.hidden !== false) {
                event.preventDefault();
                modal.querySelector('[data-record-modal-close]')?.click();
                return;
            }
            if (event.key !== 'Tab') return;
            const controls = focusable(modal);
            if (controls.length === 0) return;
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }));
    })();
    </script>
    <?php
    return;
}

if ((string) ($assetsRecordModalData['section'] ?? '') === 'asset-depreciation-schedule') {
    $task3State = is_array($assetsRecordModalData['form_state'] ?? null) ? $assetsRecordModalData['form_state'] : [];
    $task3Prior = is_array($task3State['input'] ?? null) ? $task3State['input'] : [];
    $task3Action = (string) ($task3State['action'] ?? '');
    $task3Error = (string) ($task3State['error'] ?? '');
    $task3Escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $task3Value = static fn (array $actions, string $key, string $fallback = ''): string => in_array($task3Action, $actions, true) ? (string) ($task3Prior[$key] ?? $fallback) : $fallback;
    $task3Options = static function (array $rows, string $keyField, callable $label, string $selected, string $placeholder) use ($task3Escape): string {
        $html = '<option value="">' . $task3Escape($placeholder) . '</option>';
        $found = false;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $key = (string) ($row[$keyField] ?? '');
            if ($key === '') continue;
            $found = $found || $key === $selected;
            $html .= '<option value="' . $task3Escape($key) . '"' . ($key === $selected ? ' selected' : '') . '>' . $task3Escape((string) $label($row)) . '</option>';
        }
        if ($selected !== '' && !$found) $html .= '<option value="' . $task3Escape($selected) . '" selected>' . $task3Escape($selected) . '</option>';
        return $html;
    };
    $renderTask3Modal = static function (array $modal, string $marker, array $actions) use ($task3State, $task3Action): void {
        $recordModal = $modal;
        ob_start();
        require dirname(__DIR__, 3) . '/views/partials/record-modal.php';
        $markup = (string) ob_get_clean();
        $markup = preg_replace('/^<button\b.*?<\/button>\s*/s', '', $markup, 1) ?? $markup;
        $markup = preg_replace('/data-record-modal/', 'data-record-modal ' . $marker, $markup, 1) ?? $markup;
        if (!empty($task3State['open']) && in_array($task3Action, $actions, true)) {
            $markup = preg_replace('/data-record-modal ' . preg_quote($marker, '/') . ' hidden/', 'data-record-modal ' . $marker . ' data-record-modal-open-on-load hidden', $markup, 1) ?? $markup;
        }
        echo $markup;
    };
    $task3ErrorFor = static fn (array $actions): string => in_array($task3Action, $actions, true) ? $task3Error : '';
    $task3Csrf = $task3Escape(bx_csrf_token());
    $task3Hidden = static fn (string $action): string => '<input type="hidden" name="csrf" value="' . htmlspecialchars(bx_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><input type="hidden" name="module_view" value="assets-maintenance"><input type="hidden" name="section" value="asset-depreciation-schedule"><input type="hidden" name="action" value="' . $action . '">';

    ob_start(); if ($task3ErrorFor(['save_asset_finance_book']) !== ''): ?><div role="alert" class="bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= $task3Escape($task3ErrorFor(['save_asset_finance_book'])) ?></div><?php endif; ?>
    <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Submitted Asset<select class="h-9 rounded-md border bg-background px-3" name="asset_key" required><?= $task3Options(array_values(array_filter($assetsRecordModalData['assets'] ?? [], static fn (array $row): bool => (string) ($row['document_status'] ?? '') === 'SUBMITTED')), 'asset_key', static fn (array $row): string => (string) $row['asset_code'] . ' · ' . (string) $row['asset_name'], $task3Value(['save_asset_finance_book'], 'asset_key'), 'Select Asset') ?></select></label><label class="grid gap-1.5 text-sm font-medium">Book code<input class="h-9 rounded-md border bg-background px-3" name="book_code" required maxlength="80" value="<?= $task3Escape($task3Value(['save_asset_finance_book'], 'book_code', 'PRIMARY')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Book name<input class="h-9 rounded-md border bg-background px-3" name="book_name" required maxlength="180" value="<?= $task3Escape($task3Value(['save_asset_finance_book'], 'book_name', 'Primary Finance Book')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Method<select class="h-9 rounded-md border bg-background px-3" name="method"><?php foreach (['STRAIGHT_LINE','DOUBLE_DECLINING','WRITTEN_DOWN_VALUE','MANUAL'] as $method): ?><option value="<?= $method ?>"<?= $task3Value(['save_asset_finance_book'], 'method', 'STRAIGHT_LINE') === $method ? ' selected' : '' ?>><?= ucwords(strtolower(str_replace('_', ' ', $method))) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm font-medium">Gross amount<input class="h-9 rounded-md border bg-background px-3" type="number" min="0.01" step="0.01" name="gross_amount" required value="<?= $task3Escape($task3Value(['save_asset_finance_book'], 'gross_amount', '0.00')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Salvage value<input class="h-9 rounded-md border bg-background px-3" type="number" min="0" step="0.01" name="salvage_value" required value="<?= $task3Escape($task3Value(['save_asset_finance_book'], 'salvage_value', '0.00')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Opening depreciation<input class="h-9 rounded-md border bg-background px-3" type="number" min="0" step="0.01" name="opening_depreciation" required value="<?= $task3Escape($task3Value(['save_asset_finance_book'], 'opening_depreciation', '0.00')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Periods<input class="h-9 rounded-md border bg-background px-3" type="number" min="1" max="1000" name="period_count" required value="<?= $task3Escape($task3Value(['save_asset_finance_book'], 'period_count', '60')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Frequency (months)<input class="h-9 rounded-md border bg-background px-3" type="number" min="1" max="120" name="frequency_months" required value="<?= $task3Escape($task3Value(['save_asset_finance_book'], 'frequency_months', '1')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">WDV rate (basis points)<input class="h-9 rounded-md border bg-background px-3" type="number" min="0" max="9999" name="rate_basis_points" value="<?= $task3Escape($task3Value(['save_asset_finance_book'], 'rate_basis_points', '0')) ?>"></label><label class="flex items-center gap-2 self-end pb-2 text-sm font-medium"><input type="checkbox" name="daily_prorata" value="1"<?= $task3Value(['save_asset_finance_book'], 'daily_prorata') !== '' ? ' checked' : '' ?>>Daily prorating</label></div>
    <?php $bookBody = (string) ob_get_clean(); $renderTask3Modal(['id' => 'assets-finance-book-modal', 'title' => 'New Asset Finance Book', 'description' => 'Define exact depreciation terms for one submitted Asset.', 'open_label' => 'Finance Book', 'submit_label' => 'Save Finance Book', 'confirm_message' => 'Confirm these Finance Book terms before saving.', 'body_html' => $bookBody, 'hidden_html' => $task3Hidden('save_asset_finance_book') . '<input type="hidden" name="finance_book_key" value="' . $task3Escape($task3Value(['save_asset_finance_book'], 'finance_book_key')) . '">'], 'data-assets-finance-book-modal', ['save_asset_finance_book']);

    ob_start(); if ($task3ErrorFor(['save_asset_depreciation_schedule']) !== ''): ?><div role="alert" class="bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= $task3Escape($task3ErrorFor(['save_asset_depreciation_schedule'])) ?></div><?php endif; ?>
    <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Finance Book<select class="h-9 rounded-md border bg-background px-3" name="finance_book_key" required><?= $task3Options($assetsRecordModalData['finance_books'] ?? [], 'finance_book_key', static fn (array $row): string => (string) $row['asset_code'] . ' · ' . (string) $row['book_code'], $task3Value(['save_asset_depreciation_schedule'], 'finance_book_key'), 'Select Finance Book') ?></select></label><label class="grid gap-1.5 text-sm font-medium">Start date<input class="h-9 rounded-md border bg-background px-3" type="date" name="start_date" required value="<?= $task3Escape($task3Value(['save_asset_depreciation_schedule'], 'start_date', date('Y-m-d'))) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Post through<input class="h-9 rounded-md border bg-background px-3" type="date" name="posting_through_date" required value="<?= $task3Escape($task3Value(['save_asset_depreciation_schedule'], 'posting_through_date', date('Y-m-d'))) ?>"></label><label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Replaces cancelled Schedule<select class="h-9 rounded-md border bg-background px-3" name="replacement_of_schedule_key"><?= $task3Options(array_values(array_filter($assetsRecordModalData['depreciation_schedules'] ?? [], static fn (array $row): bool => (string) ($row['document_status'] ?? '') === 'CANCELLED')), 'schedule_key', static fn (array $row): string => (string) $row['asset_code'] . ' · v' . (string) $row['schedule_version'], $task3Value(['save_asset_depreciation_schedule'], 'replacement_of_schedule_key'), 'New active Schedule') ?></select></label><label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Manual rows JSON<textarea class="min-h-24 rounded-md border bg-background p-3 font-mono text-xs" name="manual_lines_json" maxlength="20000" placeholder='[{"posting_date":"2026-01-31","amount":"100.00"}]'><?= $task3Escape($task3Value(['save_asset_depreciation_schedule'], 'manual_lines_json')) ?></textarea><span class="text-xs font-normal text-muted-foreground">Used only by Manual Finance Books; row count and total must match the Book exactly.</span></label><label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Notes<textarea class="min-h-20 rounded-md border bg-background p-3" name="notes" maxlength="2000"><?= $task3Escape($task3Value(['save_asset_depreciation_schedule'], 'notes')) ?></textarea></label></div>
    <?php $scheduleBody = (string) ob_get_clean(); $renderTask3Modal(['id' => 'assets-depreciation-modal', 'title' => 'New Depreciation Schedule', 'description' => 'Calculate an exact immutable Schedule from Finance Book terms.', 'open_label' => 'Schedule', 'submit_label' => 'Save Schedule draft', 'confirm_message' => 'Confirm this calculated Schedule draft before saving.', 'body_html' => $scheduleBody, 'hidden_html' => $task3Hidden('save_asset_depreciation_schedule') . '<input type="hidden" name="schedule_key" value="' . $task3Escape($task3Value(['save_asset_depreciation_schedule'], 'schedule_key')) . '">'], 'data-assets-depreciation-modal', ['save_asset_depreciation_schedule']);

    ob_start(); if ($task3ErrorFor(['save_asset_shift_factor']) !== ''): ?><div role="alert" class="bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= $task3Escape($task3ErrorFor(['save_asset_shift_factor'])) ?></div><?php endif; ?>
    <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm font-medium">Factor code<input class="h-9 rounded-md border bg-background px-3" name="factor_code" required maxlength="80" value="<?= $task3Escape($task3Value(['save_asset_shift_factor'], 'factor_code')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Factor name<input class="h-9 rounded-md border bg-background px-3" name="factor_name" required maxlength="180" value="<?= $task3Escape($task3Value(['save_asset_shift_factor'], 'factor_name')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Multiplier<input class="h-9 rounded-md border bg-background px-3" type="number" min="0.1" max="10" step="0.0001" name="multiplier" required value="<?= $task3Escape($task3Value(['save_asset_shift_factor'], 'multiplier', '1.0000')) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Status<select class="h-9 rounded-md border bg-background px-3" name="factor_status"><option value="ACTIVE">Active</option><option value="INACTIVE"<?= $task3Value(['save_asset_shift_factor'], 'factor_status') === 'INACTIVE' ? ' selected' : '' ?>>Inactive</option></select></label></div>
    <?php $factorBody = (string) ob_get_clean(); $renderTask3Modal(['id' => 'assets-shift-factor-modal', 'title' => 'New Asset Shift Factor', 'description' => 'Define a bounded multiplier used to replace remaining depreciation.', 'open_label' => 'Shift Factor', 'submit_label' => 'Save Shift Factor', 'confirm_message' => 'Confirm this Shift Factor before saving.', 'body_html' => $factorBody, 'hidden_html' => $task3Hidden('save_asset_shift_factor') . '<input type="hidden" name="shift_factor_key" value="' . $task3Escape($task3Value(['save_asset_shift_factor'], 'shift_factor_key')) . '">'], 'data-assets-shift-factor-modal', ['save_asset_shift_factor']);

    ob_start(); if ($task3ErrorFor(['save_asset_shift_allocation']) !== ''): ?><div role="alert" class="bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= $task3Escape($task3ErrorFor(['save_asset_shift_allocation'])) ?></div><?php endif; ?>
    <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Submitted Schedule<select class="h-9 rounded-md border bg-background px-3" name="schedule_key" required><?= $task3Options(array_values(array_filter($assetsRecordModalData['depreciation_schedules'] ?? [], static fn (array $row): bool => (string) ($row['document_status'] ?? '') === 'SUBMITTED' && (string) ($row['lifecycle_status'] ?? '') === 'ACTIVE')), 'schedule_key', static fn (array $row): string => (string) $row['asset_code'] . ' · ' . (string) $row['book_code'] . ' v' . (string) $row['schedule_version'], $task3Value(['save_asset_shift_allocation'], 'schedule_key'), 'Select active Schedule') ?></select></label><label class="grid gap-1.5 text-sm font-medium">Asset<select class="h-9 rounded-md border bg-background px-3" name="asset_key" required><?= $task3Options($assetsRecordModalData['assets'] ?? [], 'asset_key', static fn (array $row): string => (string) $row['asset_code'], $task3Value(['save_asset_shift_allocation'], 'asset_key'), 'Select Asset') ?></select></label><label class="grid gap-1.5 text-sm font-medium">Finance Book<select class="h-9 rounded-md border bg-background px-3" name="finance_book_key" required><?= $task3Options($assetsRecordModalData['finance_books'] ?? [], 'finance_book_key', static fn (array $row): string => (string) $row['book_code'], $task3Value(['save_asset_shift_allocation'], 'finance_book_key'), 'Select Book') ?></select></label><label class="grid gap-1.5 text-sm font-medium">Shift Factor<select class="h-9 rounded-md border bg-background px-3" name="shift_factor_key" required><?= $task3Options(array_values(array_filter($assetsRecordModalData['shift_factors'] ?? [], static fn (array $row): bool => (string) ($row['factor_status'] ?? '') === 'ACTIVE')), 'shift_factor_key', static fn (array $row): string => (string) $row['factor_code'] . ' · ' . number_format((int) $row['multiplier_basis_points'] / 10000, 4), $task3Value(['save_asset_shift_allocation'], 'shift_factor_key'), 'Select Factor') ?></select></label><label class="grid gap-1.5 text-sm font-medium">Effective date<input class="h-9 rounded-md border bg-background px-3" type="date" name="effective_date" required value="<?= $task3Escape($task3Value(['save_asset_shift_allocation'], 'effective_date', date('Y-m-d'))) ?>"></label><label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Reason<textarea class="min-h-20 rounded-md border bg-background p-3" name="reason" required maxlength="500"><?= $task3Escape($task3Value(['save_asset_shift_allocation'], 'reason')) ?></textarea></label></div>
    <?php $allocationBody = (string) ob_get_clean(); $renderTask3Modal(['id' => 'assets-shift-allocation-modal', 'title' => 'New Shift Allocation', 'description' => 'Replace only the remaining unposted Schedule after confirmation.', 'open_label' => 'Allocation', 'submit_label' => 'Save Allocation draft', 'confirm_message' => 'Confirm this Shift Allocation draft before saving.', 'body_html' => $allocationBody, 'hidden_html' => $task3Hidden('save_asset_shift_allocation') . '<input type="hidden" name="shift_allocation_key" value="' . $task3Escape($task3Value(['save_asset_shift_allocation'], 'shift_allocation_key')) . '">'], 'data-assets-shift-allocation-modal', ['save_asset_shift_allocation']);

    ob_start(); if ($task3ErrorFor(['save_asset_value_adjustment']) !== ''): ?><div role="alert" class="bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= $task3Escape($task3ErrorFor(['save_asset_value_adjustment'])) ?></div><?php endif; ?>
    <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Submitted Asset<select class="h-9 rounded-md border bg-background px-3" name="asset_key" required><?= $task3Options(array_values(array_filter($assetsRecordModalData['assets'] ?? [], static fn (array $row): bool => (string) ($row['document_status'] ?? '') === 'SUBMITTED')), 'asset_key', static fn (array $row): string => (string) $row['asset_code'] . ' · ' . (string) $row['asset_name'], $task3Value(['save_asset_value_adjustment'], 'asset_key'), 'Select Asset') ?></select></label><label class="grid gap-1.5 text-sm font-medium">Adjustment<select class="h-9 rounded-md border bg-background px-3" name="adjustment_type"><option value="INCREASE">Increase</option><option value="DECREASE"<?= $task3Value(['save_asset_value_adjustment'], 'adjustment_type') === 'DECREASE' ? ' selected' : '' ?>>Decrease</option></select></label><label class="grid gap-1.5 text-sm font-medium">Posting date<input class="h-9 rounded-md border bg-background px-3" type="date" name="posting_date" required value="<?= $task3Escape($task3Value(['save_asset_value_adjustment'], 'posting_date', date('Y-m-d'))) ?>"></label><label class="grid gap-1.5 text-sm font-medium">Amount<input class="h-9 rounded-md border bg-background px-3" type="number" min="0.01" step="0.01" name="amount" required value="<?= $task3Escape($task3Value(['save_asset_value_adjustment'], 'amount', '0.00')) ?>"></label><label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Reason<textarea class="min-h-20 rounded-md border bg-background p-3" name="reason" required maxlength="500"><?= $task3Escape($task3Value(['save_asset_value_adjustment'], 'reason')) ?></textarea></label></div>
    <?php $adjustmentBody = (string) ob_get_clean(); $renderTask3Modal(['id' => 'assets-value-adjustment-modal', 'title' => 'New Asset Value Adjustment', 'description' => 'Prepare a balanced increase or decrease for Finance posting.', 'open_label' => 'Value Adjustment', 'submit_label' => 'Save Adjustment draft', 'confirm_message' => 'Confirm this Value Adjustment draft before saving.', 'body_html' => $adjustmentBody, 'hidden_html' => $task3Hidden('save_asset_value_adjustment') . '<input type="hidden" name="adjustment_key" value="' . $task3Escape($task3Value(['save_asset_value_adjustment'], 'adjustment_key')) . '">'], 'data-assets-value-adjustment-modal', ['save_asset_value_adjustment']);
    ?>
    <script>(() => { const selector='[data-assets-finance-book-modal],[data-assets-depreciation-modal],[data-assets-shift-factor-modal],[data-assets-shift-allocation-modal],[data-assets-value-adjustment-modal]'; const focusable=modal=>[...modal.querySelectorAll('button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled])')].filter(element=>!element.hidden&&element.getClientRects().length>0); document.querySelectorAll(selector).forEach(modal=>modal.addEventListener('keydown',event=>{ if(event.key==='Escape'&&document.querySelector('[data-confirm-dialog]')?.hidden!==false){event.preventDefault();modal.querySelector('[data-record-modal-close]')?.click();return;} if(event.key!=='Tab')return;const controls=focusable(modal);if(!controls.length)return;const first=controls[0],last=controls[controls.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}})); })();</script>
    <?php
    return;
}

$assetsModalData = is_array($assetsData ?? null) ? $assetsData : [];
$assetsModalState = is_array($assetsModalData['form_state'] ?? null) ? $assetsModalData['form_state'] : [];
$assetsModalPrior = is_array($assetsModalState['input'] ?? null) ? $assetsModalState['input'] : [];
$assetsSelectedForm = is_array($assetsModalData['selected_form'] ?? null) ? $assetsModalData['selected_form'] : [];
$assetsFormAdapter = is_array($assetsModalData['form_adapter'] ?? null) ? $assetsModalData['form_adapter'] : yovel_admin_assets_form_adapter();
$assetsRecordTypes = is_array($assetsFormAdapter['target_record_types'] ?? null) ? $assetsFormAdapter['target_record_types'] : yovel_admin_assets_form_record_types();
$assetsFormSchema = is_array($assetsModalData['active_form_schema'] ?? null) ? $assetsModalData['active_form_schema'] : yovel_admin_assets_default_form_schemas()['ASSET'];
$assetsModalEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$assetsModalValue = static fn (string $key, string $fallback = ''): string => (string) ($assetsModalPrior[$key] ?? $assetsSelectedForm[$key] ?? $fallback);
$assetsEditing = (string) ($assetsSelectedForm['form_key'] ?? '') !== '';
$assetsTarget = $assetsModalValue('target_section', (string) ($assetsSelectedForm['target_section'] ?? 'asset-records'));
$assetsRecordType = $assetsModalValue('record_type', (string) ($assetsSelectedForm['record_type'] ?? ($assetsRecordTypes[$assetsTarget] ?? 'ASSET')));
$assetsSchemaJson = $assetsModalValue('schema_json', yovel_admin_assets_form_json($assetsFormSchema));

ob_start();
?>
<?php if (trim((string) ($assetsModalState['error'] ?? '')) !== ''): ?><div role="alert" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= $assetsModalEscape((string) $assetsModalState['error']) ?></div><?php endif; ?>
<div class="grid gap-4 sm:grid-cols-2">
    <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Form title<input class="h-9 rounded-md border bg-background px-3" name="form_title" required maxlength="180" value="<?= $assetsModalEscape($assetsModalValue('form_title', 'Asset form')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Description<textarea class="min-h-20 rounded-md border bg-background px-3 py-2" name="form_description" maxlength="2000"><?= $assetsModalEscape($assetsModalValue('form_description')) ?></textarea></label>
    <label class="grid gap-1.5 text-sm font-medium">Target<select data-assets-target class="h-9 rounded-md border bg-background px-3" name="target_section"<?= $assetsEditing ? ' disabled' : '' ?>><?php foreach ($assetsRecordTypes as $target => $type): ?><option value="<?= $assetsModalEscape((string) $target) ?>" data-record-type="<?= $assetsModalEscape((string) $type) ?>"<?= $assetsTarget === $target ? ' selected' : '' ?>><?= $assetsModalEscape(ucwords(str_replace('-', ' ', (string) $target))) ?></option><?php endforeach; ?></select><?php if ($assetsEditing): ?><input type="hidden" name="target_section" value="<?= $assetsModalEscape($assetsTarget) ?>"><?php endif; ?></label>
    <label class="grid gap-1.5 text-sm font-medium">Stable field key<input class="h-9 rounded-md border bg-background px-3 font-mono text-sm" name="new_field_key" maxlength="80" value="<?= $assetsModalEscape($assetsModalValue('new_field_key')) ?>" placeholder="service_interval_days"></label>
    <label class="grid gap-1.5 text-sm font-medium">New field label<input class="h-9 rounded-md border bg-background px-3" name="new_field_label" maxlength="180" value="<?= $assetsModalEscape($assetsModalValue('new_field_label')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium">Field type<select class="h-9 rounded-md border bg-background px-3" name="new_field_type"><?php foreach ($assetsFormAdapter['field_types'] ?? [] as $type): ?><option value="<?= $assetsModalEscape((string) $type) ?>"<?= $assetsModalValue('new_field_type', 'SHORT_TEXT') === $type ? ' selected' : '' ?>><?= $assetsModalEscape(ucwords(strtolower(str_replace('_', ' ', (string) $type)))) ?></option><?php endforeach; ?></select></label>
    <label class="grid gap-1.5 text-sm font-medium">Section<input class="h-9 rounded-md border bg-background px-3" name="new_field_section" maxlength="80" value="<?= $assetsModalEscape($assetsModalValue('new_field_section', 'custom')) ?>"></label>
    <label class="grid gap-1.5 text-sm font-medium">Column<select class="h-9 rounded-md border bg-background px-3" name="new_field_column"><?php foreach ([1, 2, 3] as $column): ?><option value="<?= $column ?>"<?= (int) $assetsModalValue('new_field_column', '1') === $column ? ' selected' : '' ?>><?= $column ?></option><?php endforeach; ?></select></label>
    <label class="grid gap-1.5 text-sm font-medium">Width<select class="h-9 rounded-md border bg-background px-3" name="new_field_width"><?php foreach (['THIRD', 'HALF', 'FULL'] as $width): ?><option value="<?= $width ?>"<?= $assetsModalValue('new_field_width', 'FULL') === $width ? ' selected' : '' ?>><?= ucfirst(strtolower($width)) ?></option><?php endforeach; ?></select></label>
    <label class="flex items-center gap-2 self-end pb-2 text-sm font-medium"><input type="checkbox" name="new_field_required" value="1"<?= !empty($assetsModalPrior['new_field_required']) ? ' checked' : '' ?>>Required field</label>
</div>
<div class="border-t pt-4"><?php require __DIR__ . '/form-builder.php'; ?></div>
<?php
$assetsModalBody = (string) ob_get_clean();
$recordModal = [
    'id' => 'assets-form-modal',
    'title' => $assetsEditing ? 'Edit Assets Form' : 'New Assets Form',
    'description' => 'Configure a company-owned, immutable form version.',
    'open_label' => 'New Form',
    'submit_label' => 'Save form version',
    'confirm_message' => 'Confirm this Assets form version before saving it.',
    'body_html' => $assetsModalBody,
    'hidden_html' => '<input type="hidden" name="csrf" value="' . $assetsModalEscape(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="assets-maintenance"><input type="hidden" name="action" value="save_assets_form"><input type="hidden" name="section" value="form-builder"><input type="hidden" name="form_key" value="' . $assetsModalEscape((string) ($assetsSelectedForm['form_key'] ?? '')) . '"><input type="hidden" data-assets-record-type name="record_type" value="' . $assetsModalEscape($assetsRecordType) . '"><input type="hidden" name="schema_json" value="' . $assetsModalEscape($assetsSchemaJson) . '">',
];
ob_start();
require dirname(__DIR__, 3) . '/views/partials/record-modal.php';
$assetsModalMarkup = (string) ob_get_clean();
$assetsModalMarkup = preg_replace('/^<button\b.*?<\/button>\s*/s', '', $assetsModalMarkup, 1) ?? $assetsModalMarkup;
if (!empty($assetsModalState['open'])) {
    $assetsModalMarkup = preg_replace('/data-record-modal hidden/', 'data-record-modal data-record-modal-open-on-load hidden', $assetsModalMarkup, 1) ?? $assetsModalMarkup;
}
echo $assetsModalMarkup;
?>
<script>
(() => {
    const modal = document.getElementById('assets-form-modal');
    const target = modal?.querySelector('[data-assets-target]');
    const recordType = modal?.querySelector('[data-assets-record-type]');
    if (!target || !recordType) return;
    target.addEventListener('change', () => {
        recordType.value = target.selectedOptions[0]?.dataset.recordType || 'ASSET';
    });
})();
</script>
