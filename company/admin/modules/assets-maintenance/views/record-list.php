<?php
declare(strict_types=1);

$assetsRecordData = is_array($assetsData ?? null) ? $assetsData : [];
$assetsRecordRows = is_array($assetsRecordData['assets'] ?? null) ? $assetsRecordData['assets'] : [];
$assetsMovementRows = is_array($assetsRecordData['movements'] ?? null) ? $assetsRecordData['movements'] : [];
$assetsActivityRows = is_array($assetsRecordData['activities'] ?? null) ? $assetsRecordData['activities'] : [];
$assetsRecordEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$assetsRecordStatusClass = static fn (string $status): string => match ($status) {
    'SUBMITTED', 'ACTIVE', 'IN_USE', 'AVAILABLE' => 'text-emerald-600',
    'CANCELLED', 'SOLD', 'SCRAPPED' => 'text-destructive',
    'DRAFT', 'IDLE' => 'text-amber-600',
    default => 'text-muted-foreground',
};
?>
<div data-assets-record-list class="grid gap-7">
    <section aria-labelledby="assets-register-title">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h3 id="assets-register-title" class="text-sm font-semibold">Asset register</h3><p class="mt-1 text-sm text-muted-foreground">Immutable owner references and current company custody.</p></div>
            <dl class="flex gap-5 text-right text-xs text-muted-foreground"><div><dt>Total</dt><dd class="mt-1 text-base font-semibold text-foreground"><?= count($assetsRecordRows) ?></dd></div><div><dt>Submitted</dt><dd class="mt-1 text-base font-semibold text-foreground"><?= count(array_filter($assetsRecordRows, static fn (array $row): bool => (string) ($row['document_status'] ?? '') === 'SUBMITTED')) ?></dd></div></dl>
        </div>
        <?php if ($assetsRecordRows === []): ?>
            <div class="mt-4 py-10 text-center"><span class="material-symbols-rounded text-3xl text-muted-foreground" aria-hidden="true">precision_manufacturing</span><h4 class="mt-3 text-sm font-semibold">No asset records yet</h4><p class="mt-1 text-xs text-muted-foreground">Create a category and location, then add the first Asset.</p></div>
        <?php else: ?>
            <div class="mt-4 overflow-x-auto border-y">
                <table class="w-full min-w-[48rem] text-left text-sm">
                    <thead class="bg-muted/40 text-xs text-muted-foreground"><tr><th class="px-3 py-2 font-medium">Asset</th><th class="px-3 py-2 font-medium">Item</th><th class="px-3 py-2 font-medium">Location</th><th class="px-3 py-2 font-medium">Custodian</th><th class="px-3 py-2 font-medium">Status</th><th class="px-3 py-2 text-right font-medium">Workflow</th></tr></thead>
                    <tbody class="divide-y">
                    <?php foreach ($assetsRecordRows as $asset): ?>
                        <tr data-assets-record-key="<?= $assetsRecordEscape((string) $asset['asset_key']) ?>">
                            <td class="px-3 py-3"><p class="font-medium"><?= $assetsRecordEscape((string) $asset['asset_code']) ?></p><p class="mt-0.5 text-xs text-muted-foreground"><?= $assetsRecordEscape((string) $asset['asset_name']) ?></p></td>
                            <td class="px-3 py-3"><p><?= $assetsRecordEscape((string) $asset['item_name_snapshot']) ?></p><p class="mt-0.5 text-xs text-muted-foreground"><?= $assetsRecordEscape((string) $asset['item_code_snapshot']) ?></p></td>
                            <td class="px-3 py-3"><?= $assetsRecordEscape((string) $asset['location_name_snapshot']) ?></td>
                            <td class="px-3 py-3"><?= $assetsRecordEscape((string) ($asset['custodian_name_snapshot'] ?: 'Unassigned')) ?></td>
                            <td class="px-3 py-3"><span class="text-xs font-medium <?= $assetsRecordStatusClass((string) $asset['document_status']) ?>"><?= $assetsRecordEscape((string) $asset['document_status']) ?></span><span class="mt-1 block text-[10px] text-muted-foreground"><?= $assetsRecordEscape((string) $asset['lifecycle_status']) ?></span></td>
                            <td class="px-3 py-3 text-right">
                                <?php if ((string) $asset['document_status'] === 'DRAFT'): ?>
                                    <form method="post" class="inline" data-confirm-submit data-confirm-message="Confirm submission of this Asset. Owner references become immutable."><input type="hidden" name="csrf" value="<?= $assetsRecordEscape(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="assets-maintenance"><input type="hidden" name="section" value="asset-records"><input type="hidden" name="action" value="submit_asset"><input type="hidden" name="asset_key" value="<?= $assetsRecordEscape((string) $asset['asset_key']) ?>"><button type="submit" data-confirm-submit-action class="h-8 rounded-md border px-2.5 text-xs font-medium">Submit</button></form>
                                <?php elseif ((string) $asset['document_status'] === 'SUBMITTED'): ?>
                                    <form method="post" class="inline-flex items-center gap-2" data-confirm-submit data-confirm-message="Confirm cancellation of this Asset. The cancellation is permanent."><input type="hidden" name="csrf" value="<?= $assetsRecordEscape(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="assets-maintenance"><input type="hidden" name="section" value="asset-records"><input type="hidden" name="action" value="cancel_asset"><input type="hidden" name="asset_key" value="<?= $assetsRecordEscape((string) $asset['asset_key']) ?>"><input class="h-8 w-36 rounded-md border bg-background px-2 text-xs" name="cancellation_reason" required maxlength="500" placeholder="Cancellation reason"><button type="submit" data-confirm-submit-action class="h-8 rounded-md border px-2.5 text-xs font-medium">Cancel</button></form>
                                <?php else: ?><span class="text-xs text-muted-foreground">Immutable</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="border-t pt-5" aria-labelledby="assets-movements-title">
        <div class="flex items-center justify-between gap-3"><h3 id="assets-movements-title" class="text-sm font-semibold">Movements</h3><span class="text-xs text-muted-foreground"><?= count($assetsMovementRows) ?> records</span></div>
        <?php if ($assetsMovementRows === []): ?><p class="mt-3 py-4 text-sm text-muted-foreground">No custody or location movements yet.</p><?php else: ?>
            <div class="mt-3 divide-y border-y"><?php foreach ($assetsMovementRows as $movement): ?><div class="flex flex-wrap items-center gap-3 py-3"><div class="min-w-0 flex-1"><p class="text-sm font-medium"><?= $assetsRecordEscape((string) $movement['movement_code']) ?></p><p class="mt-0.5 text-xs text-muted-foreground"><?= $assetsRecordEscape((string) $movement['posting_at']) ?> · <?= $assetsRecordEscape((string) $movement['reason']) ?></p></div><span class="text-xs font-medium <?= $assetsRecordStatusClass((string) $movement['document_status']) ?>"><?= $assetsRecordEscape((string) $movement['document_status']) ?></span><?php if ((string) $movement['document_status'] === 'DRAFT'): ?><form method="post" data-confirm-submit data-confirm-message="Confirm this Asset Movement. Custody will update only after confirmation."><input type="hidden" name="csrf" value="<?= $assetsRecordEscape(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="assets-maintenance"><input type="hidden" name="section" value="asset-records"><input type="hidden" name="action" value="submit_asset_movement"><input type="hidden" name="movement_key" value="<?= $assetsRecordEscape((string) $movement['movement_key']) ?>"><button type="submit" data-confirm-submit-action class="h-8 rounded-md border px-2.5 text-xs font-medium">Submit</button></form><?php elseif ((string) $movement['document_status'] === 'SUBMITTED'): ?><form method="post" class="inline-flex items-center gap-2" data-confirm-submit data-confirm-message="Confirm reversal of this Asset Movement. The previous custody will be restored."><input type="hidden" name="csrf" value="<?= $assetsRecordEscape(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="assets-maintenance"><input type="hidden" name="section" value="asset-records"><input type="hidden" name="action" value="cancel_asset_movement"><input type="hidden" name="movement_key" value="<?= $assetsRecordEscape((string) $movement['movement_key']) ?>"><input class="h-8 w-36 rounded-md border bg-background px-2 text-xs" name="cancellation_reason" required maxlength="500" placeholder="Reversal reason"><button type="submit" data-confirm-submit-action class="h-8 rounded-md border px-2.5 text-xs font-medium">Reverse</button></form><?php endif; ?></div><?php endforeach; ?></div>
        <?php endif; ?>
    </section>

    <section data-assets-activity class="border-t pt-5" aria-labelledby="assets-activity-title">
        <div class="flex items-center justify-between gap-3"><h3 id="assets-activity-title" class="text-sm font-semibold">Asset activity</h3><span class="text-xs text-muted-foreground">Latest 200</span></div>
        <?php if ($assetsActivityRows === []): ?><p class="mt-3 py-4 text-sm text-muted-foreground">Activity appears after the first lifecycle change.</p><?php else: ?>
            <ol class="mt-3 divide-y border-y"><?php foreach (array_slice($assetsActivityRows, 0, 40) as $activity): ?><li class="grid gap-1 py-3 text-sm sm:grid-cols-[minmax(0,1fr)_auto]"><span><span class="font-medium"><?= $assetsRecordEscape((string) $activity['activity_type']) ?></span><span class="ml-2 break-all text-xs text-muted-foreground"><?= $assetsRecordEscape((string) $activity['asset_key']) ?></span></span><time class="text-xs text-muted-foreground" datetime="<?= $assetsRecordEscape((string) $activity['activity_at']) ?>"><?= $assetsRecordEscape((string) $activity['activity_at']) ?></time></li><?php endforeach; ?></ol>
        <?php endif; ?>
    </section>
</div>
