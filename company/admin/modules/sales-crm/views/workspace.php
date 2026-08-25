<?php
/** Workspace variables are prepared by bootstrap/controller.php. */
?>
                                <?php
                                    $schemaFields = is_array($activeSalesCrmSchema['fields'] ?? null) ? $activeSalesCrmSchema['fields'] : [];
                                    $visibleSchemaFields = array_values(array_filter($schemaFields, static fn (array $field): bool => filter_var($field['visible'] ?? true, FILTER_VALIDATE_BOOLEAN)));
                                    $schemaSectionKeys = [];
                                    foreach ($visibleSchemaFields as $field) {
                                        $schemaSectionKeys[(string) ($field['section'] ?? 'overview')] = true;
                                    }
                                ?>
                                <div class="grid min-h-0 gap-3">
                                    <div class="flex min-h-0 items-center">
                                        <span class="inline-flex w-fit items-center rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= bx_h($viewEyebrow) ?></span>
                                    </div>
                                    <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Sales CRM sections">
                                        <?php foreach ($salesCrmSections as $sectionKey => $sectionMeta): ?>
                                            <a class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md border px-3 text-sm font-medium <?= $activeSalesCrmSection === $sectionKey ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=sales-crm&amp;section=<?= bx_h((string) $sectionKey) ?>">
                                                <span aria-hidden="true"><?= bx_h((string) $sectionMeta['icon']) ?></span><?= bx_h((string) $sectionMeta['label']) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </nav>

                                    <div class="yovel-hr-two-panel grid min-h-0 gap-4 xl:grid-cols-[minmax(0,8fr)_minmax(18rem,4fr)]">
                                        <section class="yovel-hr-panel flex flex-col rounded-lg border bg-card">
                                            <div class="border-b px-5 py-4">
                                                <div class="flex flex-wrap items-start justify-between gap-3">
                                                    <div>
                                                        <h3 class="text-base font-semibold tracking-normal"><?= bx_h((string) $activeSalesCrmMeta['label']) ?></h3>
                                                        <p class="mt-1 text-sm leading-6 text-muted-foreground"><?= bx_h((string) $activeSalesCrmMeta['description']) ?></p>
                                                    </div>
                                                    <?php if ($activeSalesCrmSection === 'leads'): ?>
                                                        <div class="flex flex-wrap justify-end gap-2">
                                                            <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($salesLeads) ?> leads</span>
                                                            <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($openSalesLeads) ?> open</span>
                                                            <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($convertedSalesLeads) ?> converted</span>
                                                            <button type="button" id="yovel-sales-lead-modal-open" class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Add Lead</button>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if ($activeSalesCrmSection === 'leads'): ?>
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
                                                                                    <form method="post" data-confirm-submit>
                                                                                        <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                                                        <input type="hidden" name="action" value="set_sales_lead_status">
                                                                                        <input type="hidden" name="section" value="leads">
                                                                                        <input type="hidden" name="lead_key" value="<?= bx_h($leadKey) ?>">
                                                                                        <input type="hidden" name="lead_status" value="<?= bx_h($statusAction) ?>">
                                                                                        <button type="submit" class="inline-flex h-8 items-center rounded-md border px-2 text-xs font-medium <?= $statusAction === 'DELETED' ? 'text-destructive hover:bg-destructive/10' : 'hover:bg-muted' ?>"><?= bx_h($statusAction === 'DELETED' ? 'Delete' : $statusAction) ?></button>
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
                                            <?php else: ?>
                                                <div class="yovel-hr-panel-body p-5">
                                                    <div class="grid gap-4">
                                                        <div class="rounded-md bg-muted/40 p-4">
                                                            <p class="text-sm font-semibold"><?= bx_h((string) $activeSalesCrmMeta['label']) ?> workspace routed</p>
                                                            <p class="mt-1 text-xs leading-5 text-muted-foreground">This Sales/CRM section now has a real route and will be built after Leads in the approved one-by-one order.</p>
                                                        </div>
                                                        <div class="grid gap-3 sm:grid-cols-3">
                                                            <div class="rounded-md bg-muted/40 p-3"><p class="text-xs text-muted-foreground">Route</p><p class="mt-1 text-sm font-semibold">sales-crm</p></div>
                                                            <div class="rounded-md bg-muted/40 p-3"><p class="text-xs text-muted-foreground">Section</p><p class="mt-1 text-sm font-semibold"><?= bx_h($activeSalesCrmSection) ?></p></div>
                                                            <div class="rounded-md bg-muted/40 p-3"><p class="text-xs text-muted-foreground">Form schema</p><p class="mt-1 text-sm font-semibold"><?= $activeSalesCrmRecordType !== '' ? 'Editable' : 'Report filters' ?></p></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </section>

                                        <aside class="yovel-hr-panel flex flex-col rounded-lg border bg-card">
                                            <div class="border-b px-5 py-4">
                                                <h3 class="text-base font-semibold tracking-normal"><?= $activeSalesCrmRecordType !== '' ? 'Form Builder' : 'Report Tools' ?></h3>
                                                <p class="mt-1 text-sm leading-6 text-muted-foreground"><?= $activeSalesCrmRecordType !== '' ? 'Customize visible fields, labels, required flags, and order for this Sales/CRM form.' : 'Use this panel for report filters and layout controls.' ?></p>
                                            </div>
                                            <div class="yovel-hr-panel-body grid content-start gap-4 p-5">
                                                <?php if ($activeSalesCrmRecordType !== ''): ?>
                                                    <form method="post" data-confirm-submit data-sales-schema-form class="grid gap-3">
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
                                                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Form Layout</button>
                                                    </form>
                                                    <form method="post" data-confirm-submit>
                                                        <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                        <input type="hidden" name="action" value="reset_sales_form_schema">
                                                        <input type="hidden" name="section" value="<?= bx_h($activeSalesCrmSection) ?>">
                                                        <input type="hidden" name="record_type" value="<?= bx_h($activeSalesCrmRecordType) ?>">
                                                        <button type="submit" class="inline-flex h-9 w-full items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Restore Default Layout</button>
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

                                    <?php if ($activeSalesCrmSection === 'leads'): ?>
                                        <?php
                                            $leadFormRecord = $editSalesLead ?: [];
                                            $fieldsBySection = [];
                                            foreach ($visibleSchemaFields as $field) {
                                                $fieldsBySection[(string) ($field['section'] ?? 'overview')][] = $field;
                                            }
                                        ?>
                                        <div id="yovel-sales-lead-modal" class="yovel-employee-modal fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-sales-lead-modal-title" aria-describedby="yovel-sales-lead-modal-description" <?= $editSalesLead ? '' : 'hidden' ?>>
                                            <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-5xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
                                                <div class="flex shrink-0 items-start justify-between gap-4 border-b px-5 py-4">
                                                    <div>
                                                        <h3 id="yovel-sales-lead-modal-title" class="text-base font-semibold tracking-normal"><?= $editSalesLead ? 'Edit Lead' : 'Add Lead' ?></h3>
                                                        <p id="yovel-sales-lead-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Lead form follows the active Sales/CRM form-builder layout.</p>
                                                    </div>
                                                    <button type="button" id="yovel-sales-lead-modal-close" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted" aria-label="Close lead form">×</button>
                                                </div>
                                                <form id="yovel-sales-lead-form" method="post" data-confirm-submit class="yovel-hr-panel-body p-5">
                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="save_sales_lead">
                                                    <input type="hidden" name="section" value="leads">
                                                    <input type="hidden" name="lead_key" value="<?= bx_h((string) ($leadFormRecord['lead_key'] ?? '')) ?>">
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
                                                    <div class="mt-6 flex flex-wrap justify-between gap-2 border-t pt-4">
                                                        <span class="text-xs text-muted-foreground">Yovel East · Sales/CRM Leads</span>
                                                        <div class="flex gap-2">
                                                            <button type="button" id="yovel-sales-lead-modal-cancel" class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Cancel</button>
                                                            <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Lead</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </section>
                                        </div>
                                    <?php endif; ?>
                                </div>
