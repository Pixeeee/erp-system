<?php
declare(strict_types=1);

$complianceData = is_array($activeModuleData ?? null) ? $activeModuleData : [];
$complianceSections = is_array($complianceData['sections'] ?? null)
    ? $complianceData['sections']
    : (is_array($activeModuleSections ?? null) ? $activeModuleSections : yovel_admin_compliance_sections());
$complianceSection = (string) ($complianceData['section'] ?? ($activeModuleSection ?? 'dashboard'));
$complianceSection = yovel_admin_compliance_section($complianceSection);
$complianceMeta = is_array($complianceData['section_meta'] ?? null)
    ? $complianceData['section_meta']
    : ($complianceSections[$complianceSection] ?? $complianceSections['dashboard']);
$complianceState = is_array($complianceData['state'] ?? null)
    ? $complianceData['state']
    : yovel_admin_compliance_state($complianceSection, $complianceMeta);
$complianceAdapter = is_array($complianceData['form_adapter'] ?? null)
    ? $complianceData['form_adapter']
    : yovel_admin_compliance_form_adapter();
$complianceCompanyName = (string) ($complianceData['company_name'] ?? ($companyName ?? 'Company'));
$complianceTargets = is_array($complianceAdapter['target_record_types'] ?? null) ? $complianceAdapter['target_record_types'] : [];
$complianceDashboard = is_array($complianceData['dashboard'] ?? null) ? $complianceData['dashboard'] : [];
$complianceDashboardSetup = is_array($complianceDashboard['setup'] ?? null) ? $complianceDashboard['setup'] : [];
$complianceDashboardAlerts = is_array($complianceDashboard['alerts'] ?? null) ? $complianceDashboard['alerts'] : [];
$complianceDashboardDependencies = is_array($complianceDashboard['dependencies'] ?? null) ? $complianceDashboard['dependencies'] : [];
?>
<style>
    @media (min-width: 1280px) {
        [data-compliance-two-panel] {
            grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr);
        }
    }
</style>
<div data-compliance-workspace class="grid min-h-0 gap-3">
    <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Compliance and Localization sections">
        <?php foreach ($complianceSections as $sectionKey => $sectionMeta): ?>
            <a
                class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md border px-3 text-sm font-medium <?= $complianceSection === $sectionKey ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>"
                href="./?view=compliance-localization&amp;section=<?= bx_h((string) $sectionKey) ?>"
            >
                <span class="material-symbols-rounded text-base" aria-hidden="true"><?= bx_h((string) ($sectionMeta['icon'] ?? 'policy')) ?></span>
                <?= bx_h((string) ($sectionMeta['label'] ?? $sectionKey)) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div data-compliance-two-panel class="grid min-h-0 gap-4 xl:grid-cols-[minmax(0,12fr)_minmax(16rem,8fr)]">
        <main data-compliance-main-panel data-grid-span="12" class="min-w-0 overflow-hidden rounded-lg border bg-card">
            <?php if ($complianceSection === 'dashboard'): ?>
                <?php require __DIR__ . '/dashboard.php'; ?>
            <?php elseif (in_array($complianceSection, ['tax-rules', 'vat-settings'], true)): ?>
                <?php require __DIR__ . '/rules.php'; ?>
            <?php elseif ($complianceSection === 'audit-evidence'): ?>
                <?php require __DIR__ . '/evidence.php'; ?>
            <?php elseif ($complianceSection === 'tax-document-templates'): ?>
                <?php require __DIR__ . '/templates.php'; ?>
            <?php elseif ($complianceSection === 'einvoice-register'): ?>
                <?php require __DIR__ . '/einvoice-register.php'; ?>
            <?php elseif ($complianceSection === 'form-builder'): ?>
                <div data-compliance-form-builder>
                    <header class="border-b px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold">Form Builder contract</h2>
                                <p class="mt-1 text-sm leading-6 text-muted-foreground">Read-only foundation for module targets and protected system fields.</p>
                            </div>
                            <span class="inline-flex h-7 items-center rounded-md bg-muted px-2 text-xs font-medium">Read-only foundation</span>
                        </div>
                    </header>
                    <div class="grid min-h-[24rem] md:grid-cols-3">
                        <section class="min-w-0 border-b p-5 md:border-b-0 md:border-r">
                            <h3 class="text-sm font-semibold">Field Toolbox</h3>
                            <div class="mt-3 grid gap-2">
                                <?php foreach (array_slice((array) ($complianceAdapter['field_types'] ?? []), 0, 8) as $fieldType): ?>
                                    <div class="rounded-md bg-muted/40 px-3 py-2 text-xs font-medium"><?= bx_h(ucwords(strtolower(str_replace('_', ' ', (string) $fieldType)))) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <section class="min-w-0 border-b p-5 md:border-b-0 md:border-r">
                            <h3 class="text-sm font-semibold">Form Layout</h3>
                            <div class="mt-3 grid gap-2">
                                <?php foreach ($complianceTargets as $targetSection => $recordType): ?>
                                    <div class="rounded-md border px-3 py-2">
                                        <p class="text-xs font-semibold"><?= bx_h((string) ($complianceSections[$targetSection]['label'] ?? $targetSection)) ?></p>
                                        <p class="mt-1 text-xs text-muted-foreground"><?= bx_h((string) $recordType) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <section class="min-w-0 p-5">
                            <h3 class="text-sm font-semibold">Field Properties</h3>
                            <dl class="mt-3 grid gap-3 text-xs">
                                <div><dt class="text-muted-foreground">Stable keys</dt><dd class="mt-1 font-medium"><?= !empty($complianceAdapter['row_column_layout']['stable_keys']) ? 'Protected' : 'Unavailable' ?></dd></div>
                                <div><dt class="text-muted-foreground">Columns</dt><dd class="mt-1 font-medium">Up to <?= (int) ($complianceAdapter['row_column_layout']['max_columns'] ?? 1) ?></dd></div>
                                <div><dt class="text-muted-foreground">Persistence</dt><dd class="mt-1 font-medium">Disabled in WP-01</dd></div>
                            </dl>
                        </section>
                    </div>
                </div>
            <?php else: ?>
                <header class="border-b px-5 py-4">
                    <h2 class="text-base font-semibold"><?= bx_h((string) ($complianceMeta['label'] ?? 'Compliance package')) ?></h2>
                    <p class="mt-1 text-sm leading-6 text-muted-foreground"><?= bx_h((string) ($complianceMeta['description'] ?? 'Governed Philippine localization workspace.')) ?></p>
                </header>
                <div class="p-5">
                    <div class="flex items-start gap-3 rounded-md bg-muted/40 p-4">
                        <span class="material-symbols-rounded text-lg text-muted-foreground" aria-hidden="true">lock_clock</span>
                        <div>
                            <p class="text-sm font-semibold"><?= bx_h((string) ($complianceState['title'] ?? 'Foundation ready')) ?></p>
                            <p class="mt-1 text-sm leading-6 text-muted-foreground"><?= bx_h((string) ($complianceState['message'] ?? '')) ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <aside data-compliance-tools-panel data-grid-span="8" class="min-w-0 overflow-hidden rounded-lg border bg-card xl:sticky xl:top-0 xl:self-start">
            <header class="border-b px-5 py-4">
                <h2 class="text-base font-semibold">Actions &amp; tools</h2>
                <p class="mt-1 text-sm text-muted-foreground"><?= bx_h($complianceCompanyName) ?> · Philippine jurisdiction</p>
            </header>
            <div class="grid gap-4 p-5">
                <?php if ($complianceSection === 'dashboard'): ?>
                    <?php
                    $complianceCompletedSetup = count(array_filter(
                        $complianceDashboardSetup,
                        static fn (array $step): bool => ($step['complete'] ?? false) === true
                    ));
                    $complianceNextSetup = null;
                    foreach ($complianceDashboardSetup as $setupStep) {
                        if (($setupStep['complete'] ?? false) !== true) {
                            $complianceNextSetup = $setupStep;
                            break;
                        }
                    }
                    ?>
                    <section data-compliance-dashboard-setup data-compliance-tour-target="tools" aria-labelledby="compliance-dashboard-setup-title">
                        <div class="flex items-center justify-between gap-3">
                            <h3 id="compliance-dashboard-setup-title" class="text-sm font-semibold">Setup progress</h3>
                            <span class="text-xs text-muted-foreground"><?= $complianceCompletedSetup ?>/<?= count($complianceDashboardSetup) ?></span>
                        </div>
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted" role="progressbar" aria-label="Compliance setup progress" aria-valuemin="0" aria-valuemax="<?= count($complianceDashboardSetup) ?>" aria-valuenow="<?= $complianceCompletedSetup ?>">
                            <span class="block h-full bg-primary" style="width: <?= count($complianceDashboardSetup) > 0 ? ($complianceCompletedSetup / count($complianceDashboardSetup)) * 100 : 0 ?>%"></span>
                        </div>
                        <div class="mt-3 divide-y border-y">
                            <?php foreach ($complianceDashboardSetup as $setupStep): ?>
                                <a href="<?= bx_h((string) ($setupStep['href'] ?? '#')) ?>" class="flex min-h-10 items-center justify-between gap-3 py-2 text-sm hover:text-primary">
                                    <span class="min-w-0 truncate"><?= bx_h((string) ($setupStep['label'] ?? 'Setup step')) ?></span>
                                    <span class="material-symbols-rounded shrink-0 text-base <?= !empty($setupStep['complete']) ? 'text-emerald-600' : 'text-muted-foreground' ?>" aria-hidden="true"><?= !empty($setupStep['complete']) ? 'check_circle' : 'radio_button_unchecked' ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if (is_array($complianceNextSetup)): ?>
                            <p class="mt-3 text-xs leading-5 text-muted-foreground">Next: <?= bx_h((string) ($complianceNextSetup['label'] ?? 'Complete setup')) ?></p>
                        <?php endif; ?>
                    </section>

                    <section data-compliance-dashboard-alerts class="border-t pt-4" aria-labelledby="compliance-dashboard-alerts-title">
                        <h3 id="compliance-dashboard-alerts-title" class="text-sm font-semibold">Alerts</h3>
                        <div class="mt-2 divide-y">
                            <?php if ($complianceDashboardAlerts === []): ?>
                                <p class="py-2 text-sm text-muted-foreground">No dashboard alerts.</p>
                            <?php else: ?>
                                <?php foreach ($complianceDashboardAlerts as $alert): ?>
                                    <a href="<?= bx_h((string) ($alert['href'] ?? '#')) ?>" class="block py-2 text-sm hover:text-primary">
                                        <span class="text-[11px] font-semibold text-muted-foreground"><?= bx_h((string) ($alert['severity'] ?? 'INFO')) ?></span>
                                        <span class="mt-1 block leading-5"><?= bx_h((string) ($alert['label'] ?? 'Compliance alert')) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section data-compliance-dashboard-dependencies class="border-t pt-4" aria-labelledby="compliance-dashboard-dependencies-title">
                        <h3 id="compliance-dashboard-dependencies-title" class="text-sm font-semibold">Dependency health</h3>
                        <?php foreach ($complianceDashboardDependencies as $dependency): ?>
                            <div class="mt-2 flex items-start justify-between gap-3 text-xs">
                                <span class="min-w-0 break-all text-muted-foreground"><?= bx_h((string) ($dependency['contract'] ?? 'Dependency')) ?></span>
                                <span class="shrink-0 font-semibold"><?= bx_h((string) ($dependency['status'] ?? 'ERROR')) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </section>

                    <section class="grid gap-2 border-t pt-4" aria-label="Compliance dashboard tools">
                        <a href="./?view=compliance-localization&amp;section=tax-rules" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">
                            <span class="material-symbols-rounded text-base" aria-hidden="true">gavel</span>Review rules
                        </a>
                        <button type="button" data-record-modal-open="compliance-form-builder-modal" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">
                            <span class="material-symbols-rounded text-base" aria-hidden="true">dynamic_form</span>Open Form Builder
                        </button>
                        <button type="button" data-compliance-tour-start class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">
                            <span class="material-symbols-rounded text-base" aria-hidden="true">tour</span>Show Tour
                        </button>
                    </section>
                <?php elseif (in_array($complianceSection, ['tax-rules', 'vat-settings'], true)): ?>
                    <div><p class="text-xs font-medium text-muted-foreground">Finance reference service</p><p class="mt-1 text-sm font-semibold"><?= !empty($complianceData['finance_account_reference_available']) ? 'Available' : 'Required live' ?></p></div>
                    <button type="button" data-record-modal-open="compliance-mapping-modal" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">account_balance</span>Add mapping</button>
                    <button type="button" data-record-modal-open="compliance-approve-modal" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">approval</span>Approve</button>
                    <button type="button" data-record-modal-open="compliance-supersede-modal" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">published_with_changes</span>Supersede</button>
                    <button type="button" data-record-modal-open="compliance-rule-archive-modal" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">archive</span>Archive</button>
                <?php elseif ($complianceSection === 'audit-evidence'): ?>
                    <div><p class="text-xs font-medium text-muted-foreground">Selected evidence</p><p class="mt-1 truncate text-sm font-semibold"><?= bx_h((string) (($complianceData['selected_evidence']['original_file_name'] ?? null) ?: 'None')) ?></p></div>
                    <button type="button" data-record-modal-open="compliance-hold-modal" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">lock</span>Place hold</button>
                    <button type="button" data-record-modal-open="compliance-release-hold-modal" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">lock_open</span>Release hold</button>
                    <button type="button" data-record-modal-open="compliance-evidence-archive-modal" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">archive</span>Archive</button>
                <?php elseif ($complianceSection === 'tax-document-templates'): ?>
                    <div><p class="text-xs font-medium text-muted-foreground">Profile versions</p><p class="mt-1 text-sm font-semibold"><?= count((array) ($complianceData['templates'] ?? [])) ?></p></div>
                    <button type="button" data-record-modal-open="compliance-profile-publish-modal" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base">publish</span>Publish</button>
                    <button type="button" data-record-modal-open="compliance-profile-archive-modal" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base">archive</span>Archive</button>
                <?php elseif ($complianceSection === 'einvoice-register'): ?>
                    <div><p class="text-xs font-medium text-muted-foreground">Finance snapshot provider</p><p class="mt-1 text-sm font-semibold"><?= !empty($complianceData['finance_invoice_snapshot_available']) ? 'Available' : 'UNAVAILABLE_DEPENDENCY' ?></p></div>
                    <form method="get" class="grid gap-3"><input type="hidden" name="view" value="compliance-localization"><input type="hidden" name="section" value="einvoice-register"><label class="grid gap-1 text-sm">From<input type="date" name="date_from" class="h-9 rounded-md border bg-background px-3" value="<?= bx_h((string) ($_GET['date_from'] ?? '')) ?>"></label><label class="grid gap-1 text-sm">To<input type="date" name="date_to" class="h-9 rounded-md border bg-background px-3" value="<?= bx_h((string) ($_GET['date_to'] ?? '')) ?>"></label><label class="grid gap-1 text-sm">Status<select name="status" class="h-9 rounded-md border bg-background px-3"><option value="">All</option><?php foreach (['VALIDATED','QUEUED','PROCESSING','ACKNOWLEDGED','REJECTED','CANCELLED'] as $status): ?><option><?= bx_h($status) ?></option><?php endforeach; ?></select></label><button class="h-9 rounded-md border px-3 text-sm font-medium">Apply filters</button></form>
                    <button type="button" data-record-modal-open="compliance-register-modal" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base">add</span>Register invoice</button>
                    <button type="button" data-record-modal-open="compliance-correction-modal" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base">published_with_changes</span>New correction</button>
                    <button type="button" data-record-modal-open="compliance-queue-modal" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base">send</span>Queue transmission</button>
                    <button type="button" data-record-modal-open="compliance-retry-modal" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base">refresh</span>Retry</button>
                    <button type="button" data-record-modal-open="compliance-cancel-modal" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base">cancel</span>Cancel</button>
                <?php else: ?>
                <div>
                    <p class="text-xs font-medium text-muted-foreground">Package state</p>
                    <p class="mt-1 text-sm font-semibold"><?= bx_h((string) ($complianceState['title'] ?? 'Foundation ready')) ?></p>
                </div>
                <div>
                    <p class="text-xs font-medium text-muted-foreground">Implementation package</p>
                    <p class="mt-1 text-sm font-semibold"><?= bx_h((string) ($complianceMeta['package'] ?? 'WP-01')) ?></p>
                </div>
                <button
                    type="button"
                    data-record-modal-open="compliance-form-builder-modal"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"
                >
                    <span class="material-symbols-rounded text-base" aria-hidden="true">dynamic_form</span>
                    Open Form Builder
                </button>
                <?php endif; ?>
            </div>
        </aside>
    </div>

    <div
        id="compliance-form-builder-modal"
        data-record-modal
        data-compliance-form-builder
        hidden
        class="fixed inset-0 z-[70] grid place-items-center bg-background/80 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="compliance-form-builder-modal-title"
        aria-describedby="compliance-form-builder-modal-description"
    >
        <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-5xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg">
            <header class="flex shrink-0 items-start justify-between gap-4 border-b bg-popover px-6 py-4">
                <div>
                    <h2 id="compliance-form-builder-modal-title" class="text-base font-semibold">Compliance Form Builder</h2>
                    <p id="compliance-form-builder-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Review module targets and protected identities before form publishing is enabled.</p>
                </div>
                <button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close Compliance Form Builder">
                    <span class="material-symbols-rounded text-base" aria-hidden="true">close</span>
                </button>
            </header>
            <form method="get" data-confirm-submit data-confirm-message="Open the read-only Compliance Form Builder workspace?" class="contents">
                <input type="hidden" name="view" value="compliance-localization">
                <input type="hidden" name="section" value="form-builder">
                <input type="hidden" name="builder_mode" value="foundation">
                <div class="min-h-0 flex-1 overflow-y-auto p-6">
                    <div class="grid md:grid-cols-3">
                        <section class="border-b pb-5 md:border-b-0 md:border-r md:pb-0 md:pr-5">
                            <h3 class="text-sm font-semibold">Field Toolbox</h3>
                            <p class="mt-2 text-sm leading-6 text-muted-foreground"><?= count((array) ($complianceAdapter['field_types'] ?? [])) ?> shared field types available.</p>
                        </section>
                        <section class="border-b py-5 md:border-b-0 md:border-r md:px-5 md:py-0">
                            <h3 class="text-sm font-semibold">Form Layout</h3>
                            <p class="mt-2 text-sm leading-6 text-muted-foreground"><?= count($complianceTargets) ?> governed record targets registered.</p>
                        </section>
                        <section class="pt-5 md:pl-5 md:pt-0">
                            <h3 class="text-sm font-semibold">Field Properties</h3>
                            <p class="mt-2 text-sm leading-6 text-muted-foreground">System identities remain protected and persistence is disabled in WP-01.</p>
                        </section>
                    </div>
                </div>
                <footer class="m-0 flex w-full shrink-0 flex-col gap-3 border-t bg-popover px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-xs text-muted-foreground">Read-only foundation</span>
                    <div class="flex justify-end gap-2">
                        <button type="button" data-record-modal-close class="h-9 rounded-md border px-3 text-sm">Cancel</button>
                        <button type="submit" data-confirm-submit-action class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Review adapter</button>
                    </div>
                </footer>
            </form>
        </section>
    </div>
</div>
