<?php
declare(strict_types=1);

$manufacturingEscape = $manufacturingEscape ?? static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$setupSteps = [
    ['label' => 'Review manufacturing settings', 'section' => 'settings', 'complete' => (string) ($manufacturingData['settings']['setting_key'] ?? '') !== ''],
    ['label' => 'Publish a production form', 'section' => 'form-builder', 'complete' => count(array_filter($manufacturingData['forms'] ?? [], static fn (array $form): bool => (string) ($form['form_status'] ?? '') === 'PUBLISHED')) > 0],
    ['label' => 'Prepare item and warehouse services', 'section' => 'material-requirements', 'complete' => !empty($manufacturingData['dependencies']['item_lookup']['available']) && !empty($manufacturingData['dependencies']['warehouses']['available'])],
    ['label' => 'Configure BOM operations', 'section' => 'boms', 'complete' => false],
];
$shortcuts = [
    ['label' => 'BOM', 'section' => 'boms', 'icon' => 'account_tree'],
    ['label' => 'Production plans', 'section' => 'production-plans', 'icon' => 'event_note'],
    ['label' => 'Work orders', 'section' => 'work-orders', 'icon' => 'assignment'],
    ['label' => 'Form Builder', 'section' => 'form-builder', 'icon' => 'dynamic_form'],
];
?>
<div class="grid gap-7">
    <section aria-labelledby="manufacturing-setup-title">
        <div class="flex items-center justify-between gap-4">
            <div><h2 id="manufacturing-setup-title" class="text-base font-semibold">Setup checklist</h2><p class="mt-1 text-sm text-muted-foreground">Production readiness for this company.</p></div>
            <a href="?view=manufacturing&section=settings" class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">settings</span>Settings</a>
        </div>
        <div class="mt-4 divide-y border-y">
            <?php foreach ($setupSteps as $index => $step): ?>
                <a href="?view=manufacturing&section=<?= $manufacturingEscape((string) $step['section']) ?>" class="flex items-center gap-3 px-1 py-3 text-sm">
                    <span class="material-symbols-rounded text-base <?= $step['complete'] ? 'text-emerald-600' : 'text-muted-foreground' ?>" aria-hidden="true"><?= $step['complete'] ? 'check_circle' : 'radio_button_unchecked' ?></span>
                    <span class="min-w-0 flex-1 font-medium"><?= $manufacturingEscape((string) $step['label']) ?></span>
                    <span class="text-xs text-muted-foreground"><?= $index + 1 ?>/<?= count($setupSteps) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section aria-labelledby="manufacturing-shortcuts-title">
        <h2 id="manufacturing-shortcuts-title" class="text-base font-semibold">Shortcuts</h2>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <?php foreach ($shortcuts as $shortcut): ?>
                <a href="?view=manufacturing&section=<?= $manufacturingEscape((string) $shortcut['section']) ?>" class="flex items-center gap-3 border-b px-1 py-3 text-sm font-medium hover:bg-muted/40">
                    <span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true"><?= $manufacturingEscape((string) $shortcut['icon']) ?></span>
                    <span class="flex-1"><?= $manufacturingEscape((string) $shortcut['label']) ?></span>
                    <span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">arrow_forward</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section aria-labelledby="manufacturing-directory-title">
        <h2 id="manufacturing-directory-title" class="text-base font-semibold">Reports and masters</h2>
        <div class="mt-3 grid gap-6 sm:grid-cols-3">
            <?php foreach ([
                'Masters' => [['Operations', 'operations'], ['Workstations', 'workstations'], ['Settings', 'settings']],
                'Planning' => [['Production plans', 'production-plans'], ['Material requirements', 'material-requirements'], ['Forecasts', 'forecasts']],
                'Execution' => [['Work orders', 'work-orders'], ['Job cards', 'job-cards'], ['Reports', 'reports']],
            ] as $group => $links): ?>
                <div><h3 class="text-xs font-semibold uppercase text-muted-foreground"><?= $manufacturingEscape($group) ?></h3><div class="mt-2 grid gap-1"><?php foreach ($links as [$label, $section]): ?><a href="?view=manufacturing&section=<?= $manufacturingEscape($section) ?>" class="py-1.5 text-sm hover:underline"><?= $manufacturingEscape($label) ?></a><?php endforeach; ?></div></div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
