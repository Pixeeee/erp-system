<?php
declare(strict_types=1);

$assetsData = is_array($activeModuleData ?? null) ? $activeModuleData : [];
$assetsControllerState = is_array($activeModuleFormState ?? null) ? $activeModuleFormState : [];
$assetsSection = (string) ($assetsData['section'] ?? ($activeModuleSection ?? 'asset-records'));
if ($assetsControllerState !== [] && (string) ($assetsControllerState['section'] ?? '') === $assetsSection) {
    $assetsData['form_state'] = [
        'open' => true,
        'action' => (string) ($assetsControllerState['action'] ?? ''),
        'input' => is_array($assetsControllerState['input'] ?? null) ? $assetsControllerState['input'] : [],
        'error' => (string) ($assetsControllerState['error'] ?? ''),
    ];
}
$assetsSections = is_array($assetsData['sections'] ?? null) ? $assetsData['sections'] : yovel_admin_assets_maintenance_sections();
$assetsState = is_array($assetsData['state'] ?? null) ? $assetsData['state'] : [];
$assetsEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$assetsSelectedForm = is_array($assetsData['selected_form'] ?? null) ? $assetsData['selected_form'] : [];
?>
<style>
    .yovel-assets-approved-layout { grid-template-columns: minmax(0, 1fr); }
    [data-assets-maintenance-workspace] [data-record-modal] { z-index: 70; }
    [data-confirm-dialog] { z-index: 100; }
    @media (min-width: 1280px) { .yovel-assets-approved-layout { grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr); } }
</style>
<div data-assets-maintenance-workspace class="grid min-h-0 gap-3">
    <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Assets and Maintenance sections">
        <?php foreach ($assetsSections as $sectionKey => $sectionMeta): ?>
            <a class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md border px-3 text-sm font-medium <?= $assetsSection === $sectionKey ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=assets-maintenance&amp;section=<?= $assetsEscape((string) $sectionKey) ?>"><span class="material-symbols-rounded text-base" aria-hidden="true"><?= $assetsEscape((string) ($sectionMeta['icon'] ?? 'handyman')) ?></span><?= $assetsEscape((string) ($sectionMeta['label'] ?? $sectionKey)) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="yovel-assets-approved-layout grid min-h-0 gap-4">
        <main data-assets-main data-grid-span="12" class="flex min-h-[28rem] min-w-0 flex-col overflow-hidden rounded-lg border bg-card">
            <header class="border-b px-5 py-4"><p class="text-xs font-medium text-muted-foreground">Assets / Maintenance</p><h2 class="mt-1 text-base font-semibold"><?= $assetsEscape((string) ($assetsData['section_meta']['label'] ?? 'Asset records')) ?></h2></header>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                <?php if ($assetsSection === 'dashboard'): ?>
                    <?php require __DIR__ . '/dashboard.php'; ?>
                <?php elseif ($assetsSection === 'form-builder'): ?>
                    <?php $assetsFormSchema = is_array($assetsData['active_form_schema'] ?? null) ? $assetsData['active_form_schema'] : []; $assetsFormAdapter = is_array($assetsData['form_adapter'] ?? null) ? $assetsData['form_adapter'] : []; require __DIR__ . '/form-builder.php'; ?>
                <?php else: ?>
                    <div class="grid min-h-[20rem] place-items-center text-center"><div class="max-w-md"><span class="material-symbols-rounded text-3xl text-muted-foreground" aria-hidden="true"><?= ($assetsState['kind'] ?? '') === 'empty' ? 'precision_manufacturing' : 'hourglass_top' ?></span><h3 class="mt-3 text-base font-semibold"><?= $assetsEscape((string) ($assetsState['title'] ?? 'Assets workspace')) ?></h3><p class="mt-1 text-sm leading-6 text-muted-foreground"><?= $assetsEscape((string) ($assetsState['message'] ?? '')) ?></p></div></div>
                <?php endif; ?>
            </div>
        </main>
        <aside data-assets-tools data-grid-span="8" class="flex min-h-[28rem] min-w-0 flex-col overflow-hidden rounded-lg border bg-card">
            <header class="border-b px-5 py-4"><h2 class="text-base font-semibold">Actions and tools</h2><p class="mt-1 text-sm text-muted-foreground"><?= $assetsEscape((string) ($assetsData['company_name'] ?? $companyName ?? 'Company')) ?></p></header>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                <?php if ($assetsSection === 'form-builder'): ?>
                    <button type="button" data-record-modal-open="assets-form-modal" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"><span class="material-symbols-rounded text-base" aria-hidden="true">add</span>New Form</button>
                    <dl class="mt-5 grid grid-cols-2 gap-3 bg-muted/30 p-3 text-sm"><div><dt class="text-xs text-muted-foreground">Forms</dt><dd class="mt-1 font-medium"><?= count($assetsData['forms'] ?? []) ?></dd></div><div><dt class="text-xs text-muted-foreground">Record type</dt><dd class="mt-1 break-all font-medium"><?= $assetsEscape((string) ($assetsData['record_type'] ?? 'ASSET')) ?></dd></div><div><dt class="text-xs text-muted-foreground">Version</dt><dd class="mt-1 font-medium"><?= (int) ($assetsData['active_form_schema']['version'] ?? 1) ?></dd></div><div><dt class="text-xs text-muted-foreground">Fields</dt><dd class="mt-1 font-medium"><?= count($assetsData['active_form_schema']['fields'] ?? []) ?></dd></div></dl>
                    <?php if (($assetsData['forms'] ?? []) !== []): ?><div class="mt-5 grid gap-2" aria-label="Assets forms"><?php foreach ($assetsData['forms'] as $form): ?><a class="flex items-center justify-between gap-3 border-b py-2 text-sm" href="./?view=assets-maintenance&amp;section=form-builder&amp;form=<?= $assetsEscape((string) $form['form_key']) ?>"><span class="min-w-0 truncate"><?= $assetsEscape((string) $form['form_title']) ?></span><span class="text-xs text-muted-foreground"><?= $assetsEscape((string) $form['form_status']) ?></span></a><?php endforeach; ?></div><?php endif; ?>
                    <?php if ($assetsSelectedForm !== [] && (string) ($assetsSelectedForm['form_status'] ?? '') !== 'ARCHIVED'): ?>
                        <div class="mt-5 grid gap-2">
                            <?php if ((string) ($assetsSelectedForm['form_status'] ?? '') !== 'PUBLISHED'): ?><form method="post" data-confirm-submit data-confirm-message="Confirm publication of this immutable Assets form version."><input type="hidden" name="csrf" value="<?= $assetsEscape(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="assets-maintenance"><input type="hidden" name="action" value="publish_assets_form"><input type="hidden" name="section" value="form-builder"><input type="hidden" name="form_key" value="<?= $assetsEscape((string) $assetsSelectedForm['form_key']) ?>"><button type="submit" data-confirm-submit-action class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">publish</span>Publish</button></form><?php endif; ?>
                            <form method="post" data-confirm-submit data-confirm-message="Confirm archival of this Assets form. Published versions remain readable."><input type="hidden" name="csrf" value="<?= $assetsEscape(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="assets-maintenance"><input type="hidden" name="action" value="archive_assets_form"><input type="hidden" name="section" value="form-builder"><input type="hidden" name="form_key" value="<?= $assetsEscape((string) $assetsSelectedForm['form_key']) ?>"><input type="hidden" name="archive_reason" value="Archived from Form Builder"><button type="submit" data-confirm-submit-action class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">archive</span>Archive</button></form>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="grid gap-4"><div class="bg-muted/30 p-3"><p class="text-xs font-medium text-muted-foreground">State</p><p class="mt-1 text-sm font-semibold"><?= $assetsEscape(ucfirst((string) ($assetsState['kind'] ?? 'dependency'))) ?></p></div><?php if (($assetsState['dependencies'] ?? []) !== []): ?><div><p class="text-xs font-medium text-muted-foreground">Work package</p><div class="mt-2 flex flex-wrap gap-2"><?php foreach ($assetsState['dependencies'] as $dependency): ?><span class="rounded-sm bg-secondary px-2 py-1 text-xs font-medium text-secondary-foreground"><?= $assetsEscape((string) $dependency) ?></span><?php endforeach; ?></div></div><?php endif; ?><a class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium" href="./?view=assets-maintenance&amp;section=form-builder"><span class="material-symbols-rounded text-base" aria-hidden="true">dynamic_form</span>Form Builder</a></div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
    <?php if ($assetsSection === 'form-builder'): ?><?php require __DIR__ . '/record-modal.php'; ?><?php endif; ?>
</div>
