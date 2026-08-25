<?php
/** Finance Form Builder variables are prepared by bootstrap/controller.php. */
$financeFormsForTarget = array_values(array_filter(
    $financeBuilderForms,
    static fn (array $form): bool => (string) ($form['target_section'] ?? '') === $activeFinanceBuilderTarget
));
$financeBuilderTargetMeta = $financeBuilderTargetSections[$activeFinanceBuilderTarget] ?? ['label' => 'Chart of accounts'];
$financeQuestionTypes = [
    'SHORT_TEXT' => 'Short text',
    'PARAGRAPH' => 'Paragraph',
    'NUMBER' => 'Number',
    'CURRENCY' => 'Currency',
    'DATE' => 'Date',
    'DROPDOWN' => 'Dropdown',
    'CHECKBOXES' => 'Checkboxes',
    'ACCOUNT' => 'Account reference',
    'PARTY' => 'Party reference',
    'SECTION' => 'Section header',
];
$financeQuestionTools = [
    ['type' => 'SHORT_TEXT', 'label' => 'Short text', 'icon' => 'short_text'],
    ['type' => 'PARAGRAPH', 'label' => 'Paragraph', 'icon' => 'notes'],
    ['type' => 'NUMBER', 'label' => 'Number', 'icon' => 'tag'],
    ['type' => 'CURRENCY', 'label' => 'Currency', 'icon' => 'payments'],
    ['type' => 'DATE', 'label' => 'Date', 'icon' => 'calendar_month'],
    ['type' => 'DROPDOWN', 'label' => 'Dropdown', 'icon' => 'arrow_drop_down_circle'],
    ['type' => 'CHECKBOXES', 'label' => 'Checkboxes', 'icon' => 'check_box'],
    ['type' => 'ACCOUNT', 'label' => 'Account', 'icon' => 'account_balance'],
    ['type' => 'PARTY', 'label' => 'Party', 'icon' => 'groups'],
    ['type' => 'SECTION', 'label' => 'Section', 'icon' => 'view_agenda'],
];
$financeQuestions = $activeFinanceBuilderSchema['questions'] ?? [];
$financeCloseHref = './?view=accounting-finance&section=dashboard';
?>
<div id="yovel-finance-builder-modal" class="yovel-finance-builder-modal yovel-form-builder-modal" role="dialog" aria-modal="true" aria-labelledby="yovel-finance-builder-title" aria-describedby="yovel-finance-builder-description" data-close-href="<?= bx_h($financeCloseHref) ?>" <?= $activeFinanceBuilderOpen ? '' : 'hidden' ?>>
    <section class="yovel-finance-builder-dialog" tabindex="-1">
        <header class="yovel-finance-sticky-modal-header flex items-start justify-between gap-4 border-b bg-popover px-5 py-4">
            <div>
                <h3 id="yovel-finance-builder-title" class="text-base font-semibold">Finance Form Builder</h3>
                <p id="yovel-finance-builder-description" class="mt-1 text-sm leading-6 text-muted-foreground">Create Finance forms or maintain forms already used by Accounting and Finance.</p>
            </div>
            <a class="inline-flex size-9 shrink-0 items-center justify-center rounded-md border bg-background hover:bg-muted" href="<?= bx_h($financeCloseHref) ?>" aria-label="Close Finance Form Builder" title="Close">
                <span class="material-symbols-rounded text-base" aria-hidden="true">close</span>
            </a>
        </header>

        <div class="yovel-finance-builder-body">
            <div class="yovel-finance-builder-tabs grid gap-3">
                <div class="yovel-finance-scroll-shell">
                    <nav class="yovel-finance-scroll yovel-finance-builder-feature-tabs flex gap-2 overflow-x-auto" aria-label="Finance feature forms">
                        <?php foreach ($financeBuilderTargetSections as $sectionKey => $sectionMeta): ?>
                            <a class="inline-flex h-8 shrink-0 items-center rounded-md border px-3 text-xs font-semibold <?= $activeFinanceBuilderTarget === (string) $sectionKey ? 'bg-secondary text-secondary-foreground' : 'bg-background text-muted-foreground hover:bg-muted hover:text-foreground' ?>" href="./?view=accounting-finance&amp;section=dashboard&amp;finance_builder=1&amp;builder_mode=<?= bx_h($activeFinanceBuilderMode) ?>&amp;builder_target=<?= bx_h((string) $sectionKey) ?>"><?= bx_h((string) $sectionMeta['label']) ?></a>
                        <?php endforeach; ?>
                    </nav>
                </div>
                <div class="flex flex-wrap gap-2 border-t pt-3" role="tablist" aria-label="Finance Form Builder mode">
                    <a role="tab" aria-selected="<?= $activeFinanceBuilderMode === 'new' && !$activeFinanceBuilderForm ? 'true' : 'false' ?>" class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium <?= $activeFinanceBuilderMode === 'new' && !$activeFinanceBuilderForm ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=accounting-finance&amp;section=dashboard&amp;finance_builder=1&amp;builder_mode=new&amp;builder_target=<?= bx_h($activeFinanceBuilderTarget) ?>">
                        <span class="material-symbols-rounded text-base" aria-hidden="true">add</span>New Form
                    </a>
                    <a role="tab" aria-selected="<?= $activeFinanceBuilderMode === 'existing' ? 'true' : 'false' ?>" class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium <?= $activeFinanceBuilderMode === 'existing' ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=accounting-finance&amp;section=dashboard&amp;finance_builder=1&amp;builder_mode=existing&amp;builder_target=<?= bx_h($activeFinanceBuilderTarget) ?>">
                        <span class="material-symbols-rounded text-base" aria-hidden="true">folder_open</span>Existing Forms
                    </a>
                </div>
            </div>

            <?php if ($activeFinanceBuilderMode === 'existing' && !$activeFinanceBuilderForm && $activeFinanceBuiltInSection === ''): ?>
                <div class="grid gap-6">
                    <section>
                        <div class="flex flex-wrap items-end justify-between gap-3 border-b pb-3">
                            <div>
                                <h4 class="text-sm font-semibold">Built-in Forms</h4>
                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Live ERP sections for <?= bx_h((string) $financeBuilderTargetMeta['label']) ?>.</p>
                            </div>
                            <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($activeFinanceBuiltInSections) ?> forms</span>
                        </div>
                        <div class="grid gap-2 pt-4 md:grid-cols-2 xl:grid-cols-4">
                            <?php foreach ($activeFinanceBuiltInSections as $sectionKey => $sectionMeta): ?>
                                <a class="yovel-finance-builder-existing-row rounded-md border bg-card p-3" href="./?view=accounting-finance&amp;section=dashboard&amp;finance_builder=1&amp;builder_mode=existing&amp;builder_target=<?= bx_h($activeFinanceBuilderTarget) ?>&amp;builtin_form=<?= bx_h((string) $sectionKey) ?>">
                                    <span class="flex items-center justify-between gap-2"><span class="text-sm font-semibold"><?= bx_h((string) $sectionMeta['label']) ?></span><span class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-semibold">BUILT-IN</span></span>
                                    <span class="mt-2 block text-xs text-muted-foreground"><?= (int) $sectionMeta['field_count'] ?> fields</span>
                                    <span class="mt-2 block text-xs leading-5 text-muted-foreground"><?= bx_h((string) $sectionMeta['description']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section>
                        <div class="flex flex-wrap items-end justify-between gap-3 border-b pb-3">
                            <div>
                                <h4 class="text-sm font-semibold">Custom Forms</h4>
                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Forms created by administrators for this Finance feature.</p>
                            </div>
                            <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($financeFormsForTarget) ?> forms</span>
                        </div>
                        <?php if ($financeFormsForTarget): ?>
                            <div class="grid gap-2 pt-4 md:grid-cols-2 xl:grid-cols-4">
                                <?php foreach ($financeFormsForTarget as $form): ?>
                                    <a class="yovel-finance-builder-existing-row rounded-md border bg-card p-3" href="./?view=accounting-finance&amp;section=dashboard&amp;finance_builder=1&amp;builder_mode=existing&amp;builder_target=<?= bx_h($activeFinanceBuilderTarget) ?>&amp;form=<?= bx_h((string) $form['builder_form_key']) ?>">
                                        <span class="flex items-center justify-between gap-2"><span class="truncate text-sm font-semibold"><?= bx_h((string) $form['form_title']) ?></span><span class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-semibold">CUSTOM</span></span>
                                        <span class="mt-2 block text-xs text-muted-foreground"><?= (int) ($form['question_count'] ?? 0) ?> fields · <?= bx_h((string) ($form['form_status'] ?? 'DRAFT')) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="pt-4 text-sm text-muted-foreground">No custom forms for this Finance feature yet.</div>
                        <?php endif; ?>
                    </section>
                </div>
            <?php elseif ($activeFinanceBuiltInSection !== ''): ?>
                <?php
                $selectedBuiltInMeta = $activeFinanceBuiltInSections[$activeFinanceBuiltInSection];
                $selectedBuiltInFields = array_values(array_filter(
                    $activeFinanceBuiltInSchema['fields'] ?? [],
                    static fn (array $field): bool => yovel_admin_slug((string) ($field['section'] ?? 'overview')) === $activeFinanceBuiltInSection
                ));
                ?>
                <section class="grid gap-4" data-finance-built-in-form>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                        <div>
                            <h4 class="text-sm font-semibold"><?= bx_h((string) $selectedBuiltInMeta['label']) ?></h4>
                            <p class="mt-1 text-xs text-muted-foreground"><?= count($selectedBuiltInFields) ?> live fields in <?= bx_h((string) $financeBuilderTargetMeta['label']) ?>.</p>
                        </div>
                        <a class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90" href="./?view=accounting-finance&amp;section=<?= bx_h($activeFinanceBuilderTarget) ?>&amp;customize=1"><span class="material-symbols-rounded text-base" aria-hidden="true">tune</span>Open Customize Form</a>
                    </div>
                    <div class="grid divide-y rounded-md border">
                        <?php foreach ($selectedBuiltInFields as $field): ?>
                            <div class="grid gap-1 px-3 py-2 sm:grid-cols-[minmax(0,1fr)_8rem_8rem] sm:items-center">
                                <span class="text-sm font-medium"><?= bx_h((string) ($field['label'] ?? $field['key'])) ?></span>
                                <span class="text-xs text-muted-foreground"><?= bx_h((string) ($field['type'] ?? 'text')) ?></span>
                                <span class="text-xs text-muted-foreground"><?= !empty($field['required']) ? 'Required' : 'Optional' ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php else: ?>
                <form method="post" data-confirm-submit data-finance-builder-add-form data-finance-google-builder class="grid gap-4">
                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_finance_builder_form">
                    <input type="hidden" name="section" value="dashboard">
                    <input type="hidden" name="builder_form_key" value="<?= bx_h((string) ($activeFinanceBuilderForm['builder_form_key'] ?? '')) ?>">
                    <input type="hidden" name="schema_json" value="<?= bx_h(json_encode($activeFinanceBuilderSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>" data-finance-builder-schema>

                    <div class="grid gap-3 border-b pb-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_12rem_10rem]">
                        <div class="grid gap-1.5">
                            <label class="text-xs font-medium" for="finance_builder_form_title">Form title</label>
                            <input class="h-9 rounded-md border bg-background px-3 text-sm" id="finance_builder_form_title" name="form_title" maxlength="180" required value="<?= bx_h((string) ($activeFinanceBuilderForm['form_title'] ?? 'Untitled Finance Form')) ?>">
                        </div>
                        <div class="grid gap-1.5">
                            <label class="text-xs font-medium" for="finance_builder_form_description">Description</label>
                            <input class="h-9 rounded-md border bg-background px-3 text-sm" id="finance_builder_form_description" name="form_description" maxlength="2000" value="<?= bx_h((string) ($activeFinanceBuilderForm['form_description'] ?? '')) ?>">
                        </div>
                        <div class="grid gap-1.5">
                            <label class="text-xs font-medium" for="finance_builder_target_section">Finance feature</label>
                            <select class="h-9 rounded-md border bg-background px-3 text-sm" id="finance_builder_target_section" name="target_section"><?php foreach ($financeBuilderTargetSections as $sectionKey => $sectionMeta): ?><option value="<?= bx_h((string) $sectionKey) ?>" <?= $activeFinanceBuilderTarget === (string) $sectionKey ? 'selected' : '' ?>><?= bx_h((string) $sectionMeta['label']) ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="grid gap-1.5">
                            <label class="text-xs font-medium" for="finance_builder_form_status">Status</label>
                            <select class="h-9 rounded-md border bg-background px-3 text-sm" id="finance_builder_form_status" name="form_status"><?php foreach (['DRAFT', 'ACTIVE', 'ARCHIVED'] as $status): ?><option value="<?= bx_h($status) ?>" <?= (string) ($activeFinanceBuilderForm['form_status'] ?? 'DRAFT') === $status ? 'selected' : '' ?>><?= bx_h(ucfirst(strtolower($status))) ?></option><?php endforeach; ?></select>
                        </div>
                    </div>

                    <div class="yovel-finance-builder-workbench">
                        <aside class="yovel-finance-builder-pane grid content-start gap-3" aria-label="Field Toolbox">
                            <div><h5 class="text-xs font-semibold uppercase">Field Toolbox</h5><p class="mt-1 text-xs leading-5 text-muted-foreground">Choose a field type to add it to the form.</p></div>
                            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                                <?php foreach ($financeQuestionTools as $tool): ?>
                                    <button type="button" class="yovel-finance-builder-tool inline-flex min-h-9 items-center gap-2 rounded-md border bg-background px-3 text-left text-xs font-semibold hover:bg-muted" data-finance-builder-preset="<?= bx_h((string) $tool['type']) ?>" data-finance-builder-preset-label="<?= bx_h((string) $tool['label']) ?>"><span class="material-symbols-rounded text-base" aria-hidden="true"><?= bx_h((string) $tool['icon']) ?></span><span><?= bx_h((string) $tool['label']) ?></span></button>
                                <?php endforeach; ?>
                            </div>
                        </aside>

                        <section class="yovel-finance-builder-pane grid content-start gap-3" aria-label="Form Layout">
                            <div class="flex items-center justify-between gap-3"><div><h5 class="text-xs font-semibold uppercase">Form Layout</h5><p class="mt-1 text-xs text-muted-foreground"><span data-finance-builder-count><?= count($financeQuestions) ?></span> fields</p></div><button type="button" class="inline-flex h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium hover:bg-muted" data-finance-builder-preview><span class="material-symbols-rounded text-base" aria-hidden="true">visibility</span>Preview</button></div>
                            <div class="grid gap-2" data-finance-builder-canvas>
                                <?php foreach ($financeQuestions as $questionIndex => $question): ?>
                                    <article class="yovel-finance-builder-question rounded-md border bg-background p-3" draggable="true" tabindex="0" aria-selected="<?= $questionIndex === 0 ? 'true' : 'false' ?>" data-finance-builder-question data-question-key="<?= bx_h((string) $question['key']) ?>">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0"><p class="truncate text-sm font-semibold" data-finance-builder-card-label><?= bx_h((string) $question['label']) ?></p><p class="mt-1 text-xs text-muted-foreground"><span data-finance-builder-card-type><?= bx_h((string) ($financeQuestionTypes[$question['type']] ?? $question['type'])) ?></span> · <span data-finance-builder-card-key><?= bx_h((string) $question['key']) ?></span></p></div>
                                            <div class="flex items-center gap-1"><button type="button" class="inline-flex size-7 items-center justify-center rounded-md hover:bg-muted" data-finance-builder-move="up" aria-label="Move field up">↑</button><button type="button" class="inline-flex size-7 items-center justify-center rounded-md hover:bg-muted" data-finance-builder-move="down" aria-label="Move field down">↓</button><span class="material-symbols-rounded cursor-grab text-base text-muted-foreground" aria-hidden="true">drag_indicator</span></div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                                <div class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground" data-finance-builder-empty <?= $financeQuestions ? 'hidden' : '' ?>>Choose a field type to start this Finance form.</div>
                            </div>
                        </section>

                        <aside class="yovel-finance-builder-pane grid content-start gap-3" aria-label="Field Properties">
                            <div><h5 class="text-xs font-semibold uppercase">Field Properties</h5><p class="mt-1 text-xs leading-5 text-muted-foreground">Select a field to edit its identity, options, and validation.</p></div>
                            <div data-finance-builder-settings-container>
                                <div class="rounded-md border border-dashed p-4 text-sm text-muted-foreground" data-finance-builder-properties-empty <?= $financeQuestions ? 'hidden' : '' ?>>No field selected.</div>
                                <?php foreach ($financeQuestions as $questionIndex => $question): ?>
                                    <section class="grid gap-3" data-finance-builder-settings-panel data-question-key="<?= bx_h((string) $question['key']) ?>" <?= $questionIndex === 0 ? '' : 'hidden' ?>>
                                    <div class="grid gap-1.5"><label class="text-xs font-medium">Field label</label><input class="h-9 rounded-md border bg-background px-3 text-sm" maxlength="180" value="<?= bx_h((string) $question['label']) ?>" data-finance-builder-property="label"></div>
                                    <div class="grid gap-1.5"><label class="text-xs font-medium">Field key</label><input class="h-9 rounded-md border bg-background px-3 text-sm" maxlength="120" pattern="[A-Za-z0-9][A-Za-z0-9_.:-]{0,119}" value="<?= bx_h((string) $question['key']) ?>" data-finance-builder-property="key"></div>
                                    <div class="grid gap-1.5"><label class="text-xs font-medium">Field type</label><select class="h-9 rounded-md border bg-background px-3 text-sm" data-finance-builder-property="type"><?php foreach ($financeQuestionTypes as $type => $label): ?><option value="<?= bx_h($type) ?>" <?= (string) $question['type'] === $type ? 'selected' : '' ?>><?= bx_h($label) ?></option><?php endforeach; ?></select></div>
                                    <div class="grid gap-1.5"><label class="text-xs font-medium">Help text</label><textarea class="min-h-20 rounded-md border bg-background px-3 py-2 text-sm" maxlength="1000" data-finance-builder-property="help"><?= bx_h((string) ($question['help'] ?? '')) ?></textarea></div>
                                    <div class="grid gap-1.5"><label class="text-xs font-medium">Options, one per line</label><textarea class="min-h-20 rounded-md border bg-background px-3 py-2 text-sm" data-finance-builder-property="options"><?= bx_h(implode("\n", array_map('strval', $question['options'] ?? []))) ?></textarea></div>
                                    <div class="grid gap-1.5"><label class="text-xs font-medium">Decimal precision</label><input class="h-9 rounded-md border bg-background px-3 text-sm" type="number" min="0" max="6" value="<?= (int) ($question['precision'] ?? 0) ?>" data-finance-builder-property="precision"></div>
                                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" <?= !empty($question['required']) ? 'checked' : '' ?> data-finance-builder-property="required">Required</label>
                                    <div class="flex flex-wrap gap-2 border-t pt-3"><button type="button" class="inline-flex h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium hover:bg-muted" data-finance-builder-duplicate><span class="material-symbols-rounded text-base" aria-hidden="true">content_copy</span>Duplicate</button><button type="button" class="inline-flex h-8 items-center gap-1 rounded-md border border-destructive/40 px-2 text-xs font-medium text-destructive hover:bg-destructive/10" data-finance-builder-delete><span class="material-symbols-rounded text-base" aria-hidden="true">delete</span>Delete</button></div>
                                    </section>
                                <?php endforeach; ?>
                            </div>
                        </aside>
                    </div>

                    <section class="grid gap-3 border-t pt-4" data-finance-builder-preview-panel hidden>
                        <div class="flex items-center justify-between gap-3"><div><h5 class="text-sm font-semibold">Form Preview</h5><p class="mt-1 text-xs text-muted-foreground">Respondent-facing preview for the current Finance form.</p></div><button type="button" class="inline-flex size-8 items-center justify-center rounded-md border hover:bg-muted" data-finance-builder-preview-close aria-label="Close preview"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button></div>
                        <div class="grid gap-3 md:grid-cols-2" data-finance-builder-preview-canvas></div>
                    </section>

                    <div class="flex justify-end border-t pt-4"><button type="submit" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90"><span class="material-symbols-rounded text-base" aria-hidden="true">save</span>Save Finance Form</button></div>
                </form>
            <?php endif; ?>
        </div>

        <footer class="flex items-center justify-between gap-3 border-t bg-popover px-5 py-4">
            <span class="text-xs text-muted-foreground"><?= count($activeFinanceBuiltInSections) ?> built-in forms · <?= count($financeFormsForTarget) ?> custom forms</span>
            <a class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" href="<?= bx_h($financeCloseHref) ?>">Close</a>
        </footer>
    </section>
</div>
