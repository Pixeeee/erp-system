<?php
/** Workspace variables are prepared by bootstrap/controller.php. */
?>
                                <style>
                                    @media (min-width: 1280px) {
                                        .yovel-sales-crm-two-panel {
                                            grid-template-columns: minmax(0, 12fr) minmax(20rem, 8fr);
                                            height: min(48rem, calc(100svh - 11.5rem));
                                        }
                                    }
                                </style>
                                <?php
                                    $schemaFields = is_array($activeSalesCrmSchema['fields'] ?? null) ? $activeSalesCrmSchema['fields'] : [];
                                    $visibleSchemaFields = array_values(array_filter($schemaFields, static fn (array $field): bool => filter_var($field['visible'] ?? true, FILTER_VALIDATE_BOOLEAN)));
                                    $schemaSectionKeys = [];
                                    foreach ($visibleSchemaFields as $field) {
                                        $schemaSectionKeys[(string) ($field['section'] ?? 'overview')] = true;
                                    }
                                    $salesCrmWorkspace = is_array($salesCrmData['workspace'] ?? null) ? $salesCrmData['workspace'] : [];
                                    $salesCrmSettings = is_array($salesCrmWorkspace['settings'] ?? null) ? $salesCrmWorkspace['settings'] : yovel_admin_sales_crm_settings_defaults();
                                    $salesCrmPreference = is_array($salesCrmWorkspace['preference'] ?? null) ? $salesCrmWorkspace['preference'] : ['preference_version' => 0, 'setup_dismissed' => 0, 'tour_status' => 'NEW'];
                                    $salesCrmAccess = is_array($salesCrmWorkspace['access'] ?? null) ? $salesCrmWorkspace['access'] : ['crm' => true, 'selling' => true, 'manage_settings' => true];
                                    $salesCrmSetupSteps = is_array($salesCrmWorkspace['setup_steps'] ?? null) ? $salesCrmWorkspace['setup_steps'] : [];
                                    $requestedSetupStep = yovel_admin_slug((string) ($_GET['setup_step'] ?? ($salesCrmSetupSteps[0]['key'] ?? 'access')));
                                    $selectedSetupStep = $salesCrmSetupSteps[0] ?? ['key' => 'access', 'label' => 'Set workspace access', 'detail' => 'Choose workspace access.'];
                                    foreach ($salesCrmSetupSteps as $setupStep) {
                                        if ((string) ($setupStep['key'] ?? '') === $requestedSetupStep) {
                                            $selectedSetupStep = $setupStep;
                                            break;
                                        }
                                    }
                                    $salesCrmDestinations = [];
                                    foreach (array_merge($salesCrmWorkspace['shortcuts'] ?? [], ...array_values($salesCrmWorkspace['directory'] ?? [])) as $destination) {
                                        if (is_array($destination) && isset($destination['section'])) {
                                            $salesCrmDestinations[(string) $destination['section']] = $destination;
                                        }
                                    }
                                    $activeSalesCrmScope = in_array($activeSalesCrmSection, ['leads', 'opportunities', 'campaigns', 'salesperson-performance'], true) ? 'crm' : 'selling';
                                    $activeSalesCrmAllowed = !empty($salesCrmAccess[$activeSalesCrmScope]);
                                ?>
                                <div class="grid min-h-0 gap-3">
                                    <div class="flex min-h-0 flex-wrap items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <span class="inline-flex w-fit items-center rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= bx_h($viewEyebrow) ?></span>
                                            <h2 class="mt-2 text-xl font-semibold tracking-normal">Sales / CRM</h2>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-muted-foreground"><?= !empty($salesCrmAccess['crm']) ? 'CRM enabled' : 'CRM restricted' ?> · <?= !empty($salesCrmAccess['selling']) ? 'Selling enabled' : 'Selling restricted' ?></span>
                                            <?php if (!empty($salesCrmAccess['manage_settings'])): ?>
                                                <button type="button" data-record-modal-open="yovel-sales-crm-settings-modal" class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Settings</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Sales CRM sections">
                                        <?php foreach ($salesCrmSections as $sectionKey => $sectionMeta): ?>
                                            <?php $navDestination = $salesCrmDestinations[(string) $sectionKey] ?? null; ?>
                                            <?php if ($navDestination && !empty($navDestination['available'])): ?>
                                                <a class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md border px-3 text-sm font-medium <?= $activeSalesCrmSection === $sectionKey ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="<?= bx_h((string) $navDestination['href']) ?>">
                                                    <span aria-hidden="true"><?= bx_h((string) $sectionMeta['icon']) ?></span><?= bx_h((string) $sectionMeta['label']) ?>
                                                </a>
                                            <?php elseif ($navDestination): ?>
                                                <span class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md border bg-muted/40 px-3 text-sm text-muted-foreground" aria-disabled="true" title="<?= bx_h((string) $navDestination['dependency_state']) ?>">
                                                    <span aria-hidden="true"><?= bx_h((string) $sectionMeta['icon']) ?></span><?= bx_h((string) $sectionMeta['label']) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </nav>

                                    <?php if ((int) ($salesCrmPreference['setup_dismissed'] ?? 0) !== 1): ?>
                                        <section data-sales-crm-setup class="overflow-hidden rounded-lg border bg-card">
                                            <div class="flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4">
                                                <div>
                                                    <h3 class="text-base font-semibold">Sales / CRM setup</h3>
                                                    <p class="mt-1 text-sm text-muted-foreground">Prepare access, pipeline capture, forms, and Selling defaults.</p>
                                                </div>
                                                <form method="post" data-confirm-submit data-confirm-message="Dismiss the Sales / CRM setup guide for this administrator?">
                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                    <input type="hidden" name="module_view" value="sales-crm">
                                                    <input type="hidden" name="action" value="sales_crm_save_preference">
                                                    <input type="hidden" name="section" value="<?= bx_h($activeSalesCrmSection) ?>">
                                                    <input type="hidden" name="expected_version" value="<?= (int) ($salesCrmPreference['preference_version'] ?? 0) ?>">
                                                    <input type="hidden" name="tour_status" value="<?= bx_h((string) ($salesCrmPreference['tour_status'] ?? 'NEW')) ?>">
                                                    <input type="hidden" name="setup_dismissed" value="1">
                                                    <button type="submit" data-confirm-submit-action class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium hover:bg-muted">Dismiss</button>
                                                </form>
                                            </div>
                                            <div data-sales-crm-setup-columns class="grid lg:grid-cols-[minmax(15rem,5fr)_minmax(0,7fr)]">
                                                <div class="divide-y border-b lg:border-b-0 lg:border-r">
                                                    <?php foreach ($salesCrmSetupSteps as $setupStep): ?>
                                                        <button type="button" data-sales-crm-step="<?= bx_h((string) $setupStep['key']) ?>" data-sales-crm-step-label="<?= bx_h((string) $setupStep['label']) ?>" data-sales-crm-step-detail="<?= bx_h((string) $setupStep['detail']) ?>" class="flex w-full items-center justify-between gap-3 px-5 py-3 text-left text-sm hover:bg-muted/50 <?= (string) $selectedSetupStep['key'] === (string) $setupStep['key'] ? 'bg-muted/60 font-medium' : '' ?>">
                                                            <span><?= bx_h((string) $setupStep['label']) ?></span>
                                                            <span class="text-xs <?= !empty($setupStep['complete']) ? 'text-emerald-600' : 'text-muted-foreground' ?>"><?= !empty($setupStep['complete']) ? 'Complete' : 'Pending' ?></span>
                                                        </button>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="flex min-h-44 flex-col justify-between gap-5 p-5" data-sales-crm-selected-detail>
                                                    <div>
                                                        <p class="text-sm font-semibold" data-sales-crm-selected-title><?= bx_h((string) $selectedSetupStep['label']) ?></p>
                                                        <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground" data-sales-crm-selected-copy><?= bx_h((string) $selectedSetupStep['detail']) ?></p>
                                                    </div>
                                                    <button type="button" data-sales-crm-tour-open class="inline-flex h-9 w-fit items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Show Tour</button>
                                                </div>
                                            </div>
                                        </section>
                                    <?php endif; ?>

                                    <section data-sales-crm-shortcuts class="rounded-lg border bg-card">
                                        <div class="border-b px-5 py-3"><h3 class="text-sm font-semibold">Shortcuts</h3></div>
                                        <div class="grid sm:grid-cols-2 xl:grid-cols-4">
                                            <?php foreach ($salesCrmWorkspace['shortcuts'] ?? [] as $shortcut): ?>
                                                <?php if (!empty($shortcut['available'])): ?>
                                                    <a class="flex min-h-16 items-center justify-between gap-3 border-b px-5 py-3 text-sm font-medium hover:bg-muted/40 sm:border-r" href="<?= bx_h((string) $shortcut['href']) ?>"><span><?= bx_h((string) $shortcut['label']) ?></span><span aria-hidden="true">↗</span></a>
                                                <?php else: ?>
                                                    <span class="flex min-h-16 items-center justify-between gap-3 border-b px-5 py-3 text-sm text-muted-foreground sm:border-r" aria-disabled="true"><span><?= bx_h((string) $shortcut['label']) ?></span><span class="text-xs"><?= bx_h((string) $shortcut['dependency_state']) ?></span></span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>

                                    <div class="yovel-sales-crm-two-panel grid min-h-0 gap-4 xl:grid-cols-[minmax(0,12fr)_minmax(20rem,8fr)]">
                                        <section class="yovel-hr-panel flex min-h-0 flex-col overflow-hidden rounded-lg border bg-card">
                                            <div class="border-b px-5 py-4">
                                                <div class="flex flex-wrap items-start justify-between gap-3">
                                                    <div>
                                                        <h3 class="text-base font-semibold tracking-normal"><?= bx_h((string) $activeSalesCrmMeta['label']) ?></h3>
                                                        <p class="mt-1 text-sm leading-6 text-muted-foreground"><?= bx_h((string) $activeSalesCrmMeta['description']) ?></p>
                                                    </div>
                                                    <?php if ($activeSalesCrmSection === 'leads' && $activeSalesCrmAllowed): ?>
                                                        <div class="flex flex-wrap justify-end gap-2">
                                                            <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($salesLeads) ?> leads</span>
                                                            <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($openSalesLeads) ?> open</span>
                                                            <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($convertedSalesLeads) ?> converted</span>
                                                            <button type="button" id="yovel-sales-lead-modal-open" data-record-modal-open="yovel-sales-lead-modal" class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Add Lead</button>
                                                        </div>
                                                    <?php elseif ($activeSalesCrmSection === 'campaigns' && $activeSalesCrmAllowed): ?>
                                                        <?php $campaignHeaderRows = is_array($salesCrmData['campaigns'] ?? null) ? $salesCrmData['campaigns'] : []; ?>
                                                        <div class="flex flex-wrap justify-end gap-2">
                                                            <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($campaignHeaderRows) ?> campaigns</span>
                                                            <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count(array_filter($campaignHeaderRows, static fn (array $row): bool => (string) ($row['campaign_status'] ?? '') === 'ACTIVE')) ?> active</span>
                                                            <button type="button" id="yovel-sales-campaign-modal-open" data-record-modal-open="yovel-sales-campaign-modal" class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Add Campaign</button>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if (!$activeSalesCrmAllowed): ?>
                                                <div class="yovel-hr-panel-body p-5">
                                                    <div class="border-l-2 border-destructive/60 pl-4">
                                                        <p class="text-sm font-semibold">Access unavailable</p>
                                                        <p class="mt-1 text-sm leading-6 text-muted-foreground">This administrator is not assigned to the <?= bx_h(strtoupper($activeSalesCrmScope)) ?> workspace.</p>
                                                    </div>
                                                </div>
                                            <?php elseif ($activeSalesCrmSection === 'leads'): ?>
                                                <div class="grid gap-3 border-b p-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                                                    <div class="grid gap-2 sm:grid-cols-3">
                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" type="search" placeholder="Lead" aria-label="Filter lead">
                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" type="search" placeholder="Organization" aria-label="Filter organization">
                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" type="search" placeholder="Status" aria-label="Filter status">
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <button type="button" class="inline-flex h-9 items-center rounded-md border bg-secondary px-3 text-sm font-medium text-secondary-foreground">Pipeline <span class="ml-2 rounded-full bg-background px-1.5 text-xs"><?= count($openSalesLeads) ?></span></button>
                                                        <button type="button" class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Updated</button>
                                                    </div>
                                                </div>
                                                <?php if (!$salesLeads): ?>
                                                    <div class="yovel-hr-panel-body p-5">
                                                        <div class="rounded-md bg-muted/40 p-4">
                                                            <p class="text-sm font-semibold">No leads yet</p>
                                                            <p class="mt-1 text-xs leading-5 text-muted-foreground">Create the first Sales/CRM lead for <?= bx_h($companyName) ?>.</p>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="yovel-hr-panel-body overflow-auto">
                                                        <table class="w-full min-w-[980px] text-left text-sm">
                                                            <thead class="sticky top-0 z-10 border-b bg-card text-xs text-muted-foreground">
                                                                <tr>
                                                                    <th class="px-4 py-3 font-medium">Lead</th>
                                                                    <th class="px-4 py-3 font-medium">Status</th>
                                                                    <th class="px-4 py-3 font-medium">Source</th>
                                                                    <th class="px-4 py-3 font-medium">Owner</th>
                                                                    <th class="px-4 py-3 font-medium">Value</th>
                                                                    <th class="px-4 py-3 text-right font-medium">Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y">
                                                                <?php foreach ($salesLeads as $lead): ?>
                                                                    <?php $leadKey = (string) $lead['lead_key']; ?>
                                                                    <tr>
                                                                        <td class="px-4 py-4">
                                                                            <p class="font-medium"><?= bx_h((string) $lead['lead_name']) ?></p>
                                                                            <p class="mt-1 text-xs text-muted-foreground"><?= bx_h((string) $lead['lead_code']) ?> · <?= bx_h((string) ($lead['organization_name'] ?: 'No organization')) ?></p>
                                                                            <p class="mt-1 text-xs text-muted-foreground"><?= bx_h((string) ($lead['email'] ?: $lead['mobile'] ?: $lead['phone'])) ?></p>
                                                                        </td>
                                                                        <td class="px-4 py-4"><span class="rounded-full bg-secondary px-2 py-0.5 text-xs"><?= bx_h((string) $lead['lead_status']) ?></span></td>
                                                                        <td class="px-4 py-4 text-xs text-muted-foreground"><?= bx_h((string) ($lead['lead_source'] ?: $lead['campaign_name'] ?: 'Unassigned')) ?></td>
                                                                        <td class="px-4 py-4 text-xs text-muted-foreground"><?= bx_h((string) ($lead['salesperson_name'] ?: $lead['territory_name'] ?: 'Unassigned')) ?></td>
                                                                        <td class="px-4 py-4 text-xs text-muted-foreground"><?= (string) ($lead['estimated_value'] ?? '') !== '' ? bx_h(number_format((float) $lead['estimated_value'], 2)) : '0.00' ?></td>
                                                                        <td class="px-4 py-4">
                                                                            <div class="flex flex-wrap justify-end gap-2">
                                                                                <a class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium hover:bg-muted" href="./?view=sales-crm&amp;section=leads&amp;edit=<?= bx_h($leadKey) ?>">Edit</a>
                                                                                <?php foreach (['OPEN', 'QUALIFIED', 'CONVERTED', 'LOST', 'INACTIVE', 'DELETED'] as $statusAction): ?>
                                                                                    <?php if ((string) $lead['lead_status'] === $statusAction) { continue; } ?>
                                                                                    <form method="post" data-confirm-submit data-confirm-message="Confirm this Lead status change. The update begins only after confirmation.">
                                                                                        <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                                                        <input type="hidden" name="action" value="set_sales_lead_status">
                                                                                        <input type="hidden" name="section" value="leads">
                                                                                        <input type="hidden" name="lead_key" value="<?= bx_h($leadKey) ?>">
                                                                                        <input type="hidden" name="lead_status" value="<?= bx_h($statusAction) ?>">
                                                                                        <button type="submit" data-confirm-submit-action class="inline-flex h-8 items-center rounded-md border px-2 text-xs font-medium <?= $statusAction === 'DELETED' ? 'text-destructive hover:bg-destructive/10' : 'hover:bg-muted' ?>"><?= bx_h($statusAction === 'DELETED' ? 'Delete' : $statusAction) ?></button>
                                                                                    </form>
                                                                                <?php endforeach; ?>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php endif; ?>
                                            <?php elseif ($activeSalesCrmSection === 'campaigns'): ?>
                                                <?php require __DIR__ . '/campaigns.php'; ?>
                                            <?php else: ?>
                                                <div class="yovel-hr-panel-body p-5">
                                                    <div class="grid gap-4">
                                                        <?php $activeDependency = $salesCrmDestinations[$activeSalesCrmSection]['dependency_state'] ?? 'This destination is not available in the current Sales / CRM package.'; ?>
                                                        <div class="border-l-2 border-muted-foreground/40 pl-4">
                                                            <p class="text-sm font-semibold"><?= bx_h((string) $activeSalesCrmMeta['label']) ?> is unavailable</p>
                                                            <p class="mt-1 text-sm leading-6 text-muted-foreground"><?= bx_h((string) $activeDependency) ?></p>
                                                        </div>
                                                        <a class="inline-flex h-9 w-fit items-center rounded-md border px-3 text-sm font-medium hover:bg-muted" href="./?view=sales-crm&amp;section=leads">Return to Leads</a>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </section>

                                        <aside class="yovel-hr-panel flex min-h-0 flex-col overflow-hidden rounded-lg border bg-card">
                                            <div class="border-b px-5 py-4">
                                                <h3 class="text-base font-semibold tracking-normal"><?= $activeSalesCrmRecordType !== '' ? 'Form Builder' : 'Report Tools' ?></h3>
                                                <p class="mt-1 text-sm leading-6 text-muted-foreground"><?= $activeSalesCrmRecordType !== '' ? 'Customize visible fields, labels, required flags, and order for this Sales/CRM form.' : 'Use this panel for report filters and layout controls.' ?></p>
                                            </div>
                                            <div class="yovel-hr-panel-body grid content-start gap-4 p-5">
                                                <?php if (!$activeSalesCrmAllowed): ?>
                                                    <p class="text-sm text-muted-foreground">Form tools are hidden until this administrator receives <?= bx_h(strtoupper($activeSalesCrmScope)) ?> access.</p>
                                                <?php elseif ($activeSalesCrmRecordType !== ''): ?>
                                                    <form method="post" data-confirm-submit data-confirm-message="Confirm this Sales form layout. The published layout changes only after confirmation." data-sales-schema-form class="grid gap-3">
                                                        <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                        <input type="hidden" name="action" value="save_sales_form_schema">
                                                        <input type="hidden" name="section" value="<?= bx_h($activeSalesCrmSection) ?>">
                                                        <input type="hidden" name="record_type" value="<?= bx_h($activeSalesCrmRecordType) ?>">
                                                        <input type="hidden" name="schema_json" value="">
                                                        <div id="yovel-sales-form-builder" class="grid gap-3">
                                                            <?php foreach ($schemaFields as $field): ?>
                                                                <?php $fieldKey = (string) ($field['key'] ?? ''); ?>
                                                                <section class="yovel-widget-item grid gap-3 rounded-md bg-muted/40 p-4" draggable="true" data-sales-field='<?= bx_h(json_encode($field, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>'>
                                                                    <div class="flex items-start justify-between gap-3">
                                                                        <div class="min-w-0">
                                                                            <p class="text-sm font-semibold"><?= bx_h((string) ($field['label'] ?? $fieldKey)) ?></p>
                                                                            <p class="mt-1 text-xs text-muted-foreground"><?= bx_h($fieldKey) ?> · <?= bx_h((string) ($field['section'] ?? 'overview')) ?></p>
                                                                        </div>
                                                                        <div class="flex shrink-0 items-center gap-1">
                                                                            <button type="button" class="yovel-sales-field-up inline-flex size-7 items-center justify-center rounded-md hover:bg-background" aria-label="Move field up">↑</button>
                                                                            <button type="button" class="yovel-sales-field-down inline-flex size-7 items-center justify-center rounded-md hover:bg-background" aria-label="Move field down">↓</button>
                                                                            <span class="inline-flex size-7 cursor-grab items-center justify-center rounded-md text-muted-foreground" aria-hidden="true">☰</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                                        <div class="grid gap-1.5">
                                                                            <label class="text-xs font-medium">Label</label>
                                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" data-sales-field-label value="<?= bx_h((string) ($field['label'] ?? $fieldKey)) ?>" maxlength="120">
                                                                        </div>
                                                                        <div class="grid gap-1.5">
                                                                            <label class="text-xs font-medium">Section</label>
                                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" data-sales-field-section value="<?= bx_h((string) ($field['section'] ?? 'overview')) ?>" maxlength="80">
                                                                        </div>
                                                                    </div>
                                                                    <div class="grid gap-2 sm:grid-cols-3">
                                                                        <label class="inline-flex items-center gap-2 text-xs"><input type="checkbox" data-sales-field-required <?= filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' ?> <?= in_array($fieldKey, $activeSalesCrmSchema['requiredSystemFields'] ?? [], true) ? 'disabled' : '' ?>>Required</label>
                                                                        <label class="inline-flex items-center gap-2 text-xs"><input type="checkbox" data-sales-field-visible <?= filter_var($field['visible'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' ?> <?= in_array($fieldKey, $activeSalesCrmSchema['requiredSystemFields'] ?? [], true) ? 'disabled' : '' ?>>Visible</label>
                                                                        <select class="h-8 rounded-md border bg-background px-2 text-xs" data-sales-field-width>
                                                                            <?php foreach (['third' => 'Third', 'half' => 'Half', 'full' => 'Full'] as $widthKey => $widthLabel): ?>
                                                                                <option value="<?= bx_h($widthKey) ?>" <?= (string) ($field['width'] ?? 'half') === $widthKey ? 'selected' : '' ?>><?= bx_h($widthLabel) ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                </section>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <button type="submit" data-confirm-submit-action class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Form Layout</button>
                                                    </form>
                                                    <form method="post" data-confirm-submit data-confirm-message="Confirm restoring the default Sales form layout.">
                                                        <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                        <input type="hidden" name="action" value="reset_sales_form_schema">
                                                        <input type="hidden" name="section" value="<?= bx_h($activeSalesCrmSection) ?>">
                                                        <input type="hidden" name="record_type" value="<?= bx_h($activeSalesCrmRecordType) ?>">
                                                        <button type="submit" data-confirm-submit-action class="inline-flex h-9 w-full items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Restore Default Layout</button>
                                                    </form>
                                                <?php else: ?>
                                                    <div class="grid gap-3">
                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" type="date" aria-label="Report date from">
                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" type="date" aria-label="Report date to">
                                                        <button type="button" class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Refresh Report</button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </aside>
                                    </div>

                                    <section data-sales-crm-directory class="rounded-lg border bg-card">
                                        <div class="border-b px-5 py-3"><h3 class="text-sm font-semibold">Masters and reports</h3></div>
                                        <div class="grid divide-y md:grid-cols-3 md:divide-x md:divide-y-0">
                                            <?php foreach ($salesCrmWorkspace['directory'] ?? [] as $groupLabel => $items): ?>
                                                <div class="p-5">
                                                    <h4 class="text-xs font-semibold uppercase text-muted-foreground"><?= bx_h((string) $groupLabel) ?></h4>
                                                    <div class="mt-3 grid gap-2">
                                                        <?php foreach ($items as $item): ?>
                                                            <?php if (!empty($item['available'])): ?>
                                                                <a class="flex min-h-9 items-center justify-between gap-3 text-sm hover:underline" href="<?= bx_h((string) $item['href']) ?>"><span><?= bx_h((string) $item['label']) ?></span><span aria-hidden="true">↗</span></a>
                                                            <?php else: ?>
                                                                <span class="flex min-h-9 items-center justify-between gap-3 text-sm text-muted-foreground" aria-disabled="true" title="<?= bx_h((string) $item['dependency_state']) ?>"><span><?= bx_h((string) $item['label']) ?></span><span class="text-xs">Unavailable</span></span>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                        <?php if (!$items): ?><span class="text-sm text-muted-foreground">No destinations for this access scope.</span><?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>

                                    <?php if ($activeSalesCrmSection === 'leads' && $activeSalesCrmAllowed): ?>
                                        <?php
                                            $leadRehydration = is_array($salesCrmData['rehydration']['lead'] ?? null) ? $salesCrmData['rehydration']['lead'] : [];
                                            $leadFormRecord = $editSalesLead ?: ($leadRehydration['values'] ?? []);
                                            $leadFormShouldOpen = (bool) $editSalesLead || $leadRehydration !== [];
                                            $fieldsBySection = [];
                                            foreach ($visibleSchemaFields as $field) {
                                                $fieldsBySection[(string) ($field['section'] ?? 'overview')][] = $field;
                                            }
                                        ?>
                                        <div id="yovel-sales-lead-modal" data-record-modal <?= $leadFormShouldOpen ? 'data-record-modal-open-on-load' : '' ?> class="yovel-employee-modal fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-sales-lead-modal-title" aria-describedby="yovel-sales-lead-modal-description" hidden>
                                            <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-5xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
                                                <form id="yovel-sales-lead-form" method="post" data-record-modal-form data-confirm-submit data-confirm-message="Confirm this Lead. It will not be saved until you confirm." class="contents">
                                                    <div class="flex shrink-0 items-start justify-between gap-4 border-b px-5 py-4">
                                                        <div>
                                                            <h3 id="yovel-sales-lead-modal-title" class="text-base font-semibold tracking-normal"><?= $editSalesLead ? 'Edit Lead' : 'Add Lead' ?></h3>
                                                            <p id="yovel-sales-lead-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Lead form follows the active Sales/CRM form-builder layout.</p>
                                                        </div>
                                                        <button type="button" id="yovel-sales-lead-modal-close" data-record-modal-close class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted" aria-label="Close lead form">×</button>
                                                    </div>
                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="save_sales_lead">
                                                    <input type="hidden" name="section" value="leads">
                                                    <input type="hidden" name="lead_key" value="<?= bx_h((string) ($leadFormRecord['lead_key'] ?? '')) ?>">
                                                    <div class="yovel-hr-panel-body min-h-0 overflow-y-auto p-5">
                                                        <?php if ($leadRehydration !== []): ?>
                                                            <div class="mb-5 rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive" role="alert">The Lead was not saved. Review the values below and submit again.</div>
                                                        <?php endif; ?>
                                                        <div class="grid gap-6">
                                                        <?php foreach (array_keys($fieldsBySection) as $sectionKey): ?>
                                                            <section class="grid gap-4">
                                                                <h4 class="text-sm font-semibold"><?= bx_h(yovel_admin_sales_schema_section_label($activeSalesCrmSchema, $sectionKey)) ?></h4>
                                                                <div class="grid gap-3 lg:grid-cols-3">
                                                                    <?php foreach ($fieldsBySection[$sectionKey] as $field): ?>
                                                                        <?php yovel_admin_render_sales_form_field($field, $leadFormRecord, $salesCrmData); ?>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </section>
                                                        <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <div class="flex shrink-0 flex-wrap justify-between gap-2 border-t px-5 py-4">
                                                        <span class="text-xs text-muted-foreground">Yovel East · Sales/CRM Leads</span>
                                                        <div class="flex gap-2">
                                                            <button type="button" id="yovel-sales-lead-modal-cancel" data-record-modal-close class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Cancel</button>
                                                            <button type="submit" data-confirm-submit-action class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Lead</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </section>
                                        </div>
                                    <?php endif; ?>

                                    <?php require __DIR__ . '/settings.php'; ?>

                                    <div data-sales-crm-tour class="fixed inset-0 z-40 grid place-items-center bg-background/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-sales-crm-tour-title" aria-describedby="yovel-sales-crm-tour-copy" hidden>
                                        <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-lg flex-col overflow-hidden rounded-lg border bg-popover shadow-lg">
                                            <div class="flex items-start justify-between gap-4 border-b px-5 py-4">
                                                <div>
                                                    <p class="text-xs font-medium text-muted-foreground" data-sales-crm-tour-progress>Step 1 of 5</p>
                                                    <h3 id="yovel-sales-crm-tour-title" data-sales-crm-tour-title class="mt-1 text-base font-semibold">Setup checklist</h3>
                                                </div>
                                                <button type="button" data-sales-crm-tour-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close Sales CRM tour">×</button>
                                            </div>
                                            <div class="min-h-36 overflow-y-auto p-5">
                                                <p id="yovel-sales-crm-tour-copy" data-sales-crm-tour-copy class="text-sm leading-6 text-muted-foreground">Use the checklist to prepare access, pipeline capture, forms, and Selling defaults.</p>
                                            </div>
                                            <div class="flex flex-wrap items-center justify-between gap-2 border-t px-5 py-4">
                                                <form method="post" data-confirm-submit data-confirm-message="Skip this Sales / CRM tour and remember the choice?">
                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                    <input type="hidden" name="module_view" value="sales-crm">
                                                    <input type="hidden" name="action" value="sales_crm_save_preference">
                                                    <input type="hidden" name="section" value="<?= bx_h($activeSalesCrmSection) ?>">
                                                    <input type="hidden" name="expected_version" value="<?= (int) ($salesCrmPreference['preference_version'] ?? 0) ?>">
                                                    <input type="hidden" name="tour_status" value="DISMISSED">
                                                    <input type="hidden" name="setup_dismissed" value="<?= (int) ($salesCrmPreference['setup_dismissed'] ?? 0) ?>">
                                                    <button type="submit" data-confirm-submit-action class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted">Skip</button>
                                                </form>
                                                <div class="flex gap-2">
                                                    <button type="button" data-sales-crm-tour-back class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted" disabled>Back</button>
                                                    <button type="button" data-sales-crm-tour-next class="inline-flex h-9 items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Next</button>
                                                    <form method="post" data-sales-crm-tour-finish-form data-confirm-submit data-confirm-message="Finish the Sales / CRM tour and mark it complete?" hidden>
                                                        <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                        <input type="hidden" name="module_view" value="sales-crm">
                                                        <input type="hidden" name="action" value="sales_crm_save_preference">
                                                        <input type="hidden" name="section" value="<?= bx_h($activeSalesCrmSection) ?>">
                                                        <input type="hidden" name="expected_version" value="<?= (int) ($salesCrmPreference['preference_version'] ?? 0) ?>">
                                                        <input type="hidden" name="tour_status" value="COMPLETED">
                                                        <input type="hidden" name="setup_dismissed" value="<?= (int) ($salesCrmPreference['setup_dismissed'] ?? 0) ?>">
                                                        <button type="submit" data-confirm-submit-action class="inline-flex h-9 items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Finish</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </section>
                                    </div>
                                </div>
                                <script>
                                    (() => {
                                        const stepButtons = Array.from(document.querySelectorAll('[data-sales-crm-step]'));
                                        const selectedTitle = document.querySelector('[data-sales-crm-selected-title]');
                                        const selectedCopy = document.querySelector('[data-sales-crm-selected-copy]');
                                        stepButtons.forEach((button) => button.addEventListener('click', () => {
                                            stepButtons.forEach((item) => item.classList.remove('bg-muted/60', 'font-medium'));
                                            button.classList.add('bg-muted/60', 'font-medium');
                                            if (selectedTitle) selectedTitle.textContent = button.dataset.salesCrmStepLabel || '';
                                            if (selectedCopy) selectedCopy.textContent = button.dataset.salesCrmStepDetail || '';
                                        }));

                                        const tour = document.querySelector('[data-sales-crm-tour]');
                                        const openers = Array.from(document.querySelectorAll('[data-sales-crm-tour-open]'));
                                        const closeButton = tour?.querySelector('[data-sales-crm-tour-close]');
                                        const backButton = tour?.querySelector('[data-sales-crm-tour-back]');
                                        const nextButton = tour?.querySelector('[data-sales-crm-tour-next]');
                                        const finishForm = tour?.querySelector('[data-sales-crm-tour-finish-form]');
                                        const title = tour?.querySelector('[data-sales-crm-tour-title]');
                                        const copy = tour?.querySelector('[data-sales-crm-tour-copy]');
                                        const progress = tour?.querySelector('[data-sales-crm-tour-progress]');
                                        const steps = [
                                            ['Setup checklist', 'Use the checklist to prepare access, pipeline capture, forms, and Selling defaults.'],
                                            ['Workspace access', 'Settings managers can grant CRM, Selling, or settings access to active company administrators.'],
                                            ['Operational shortcuts', 'Available destinations open directly; later-package dependencies stay disabled and explain why.'],
                                            ['Record workspace', 'The main panel keeps operational records prominent while tools and form controls stay on the right.'],
                                            ['Masters and reports', 'Grouped directories keep CRM masters, Selling masters, and reports easy to scan.'],
                                        ];
                                        let activeStep = 0;
                                        let lastTrigger = null;
                                        const focusable = () => Array.from(tour?.querySelectorAll('button:not([disabled]), [href], input:not([disabled])') || []).filter((element) => !element.hidden && element.getClientRects().length > 0);
                                        const render = () => {
                                            if (!tour) return;
                                            title.textContent = steps[activeStep][0];
                                            copy.textContent = steps[activeStep][1];
                                            progress.textContent = `Step ${activeStep + 1} of ${steps.length}`;
                                            backButton.disabled = activeStep === 0;
                                            nextButton.hidden = activeStep === steps.length - 1;
                                            finishForm.hidden = activeStep !== steps.length - 1;
                                        };
                                        const open = (trigger) => {
                                            if (!tour) return;
                                            activeStep = 0;
                                            lastTrigger = trigger;
                                            render();
                                            tour.hidden = false;
                                            document.body.classList.add('yovel-shell-modal-active');
                                            setTimeout(() => closeButton?.focus(), 0);
                                        };
                                        const close = () => {
                                            if (!tour) return;
                                            tour.hidden = true;
                                            document.body.classList.toggle('yovel-shell-modal-active', Boolean(document.querySelector('[data-record-modal]:not([hidden])')));
                                            lastTrigger?.focus();
                                        };
                                        openers.forEach((button) => button.addEventListener('click', () => open(button)));
                                        closeButton?.addEventListener('click', close);
                                        backButton?.addEventListener('click', () => { activeStep = Math.max(0, activeStep - 1); render(); });
                                        nextButton?.addEventListener('click', () => { activeStep = Math.min(steps.length - 1, activeStep + 1); render(); });
                                        document.addEventListener('keydown', (event) => {
                                            if (!tour || tour.hidden) return;
                                            if (event.key === 'Escape') { event.preventDefault(); close(); return; }
                                            if (event.key !== 'Tab') return;
                                            const items = focusable();
                                            if (!items.length) return;
                                            const first = items[0];
                                            const last = items[items.length - 1];
                                            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                                            if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
                                        });
                                    })();
                                </script>
