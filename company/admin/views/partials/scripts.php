<?php
/** Shared browser behavior; runtime values are inherited from the layout scope. */
?>
    <script>
        (() => {
            const dialog = document.getElementById('yovel-confirm-dialog');
            const confirmButton = document.getElementById('yovel-confirm-action');
            const cancelButton = document.getElementById('yovel-confirm-cancel');
            const sidebarToggle = document.getElementById('yovel-sidebar-toggle');
            const sidebarStorageKey = '<?= bx_h($companySidebarKey) ?>';
            const employeeWidgetStorageKey = <?= json_encode($companySidebarKey . ':employee-widgets', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const departmentWidgetStorageKey = <?= json_encode($companySidebarKey . ':department-widgets', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const jobPositionWidgetStorageKey = <?= json_encode($companySidebarKey . ':job-position-widgets', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const jobPositionSetupStorageKey = <?= json_encode($companySidebarKey . ':job-position-setup-dismissed', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const jobPositionTourStorageKey = <?= json_encode($companySidebarKey . ':job-position-tour-complete', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const accountWidgetStorageKey = <?= json_encode($companySidebarKey . ':account-widgets', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
	            const employeeModal = document.getElementById('yovel-employee-modal');
	            const employeeModalOpen = document.getElementById('yovel-employee-modal-open');
	            const employeeModalClose = document.getElementById('yovel-employee-modal-close');
	            const employeeModalCancel = document.getElementById('yovel-employee-modal-cancel');
	            const employeeForm = document.getElementById('yovel-employee-form');
	            const hrFormBuilderModal = document.getElementById('yovel-hr-form-builder-modal');
	            const hrFormBuilderModalOpen = document.getElementById('yovel-hr-form-builder-modal-open');
	            const hrFormBuilderModalClose = document.getElementById('yovel-hr-form-builder-modal-close');
	            const hrFormBuilderModalCancel = document.getElementById('yovel-hr-form-builder-modal-cancel');
	            const hrFormCreateModal = document.getElementById('yovel-hr-form-create-modal');
	            const hrFormCreateModalOpen = document.getElementById('yovel-hr-form-create-modal-open');
	            const hrFormCreateModalClose = document.getElementById('yovel-hr-form-create-modal-close');
	            const hrFormCreateModalCancel = document.getElementById('yovel-hr-form-create-modal-cancel');
            const departmentModal = document.getElementById('yovel-department-modal');
            const departmentModalOpen = document.getElementById('yovel-department-modal-open');
            const departmentModalClose = document.getElementById('yovel-department-modal-close');
            const departmentModalCancel = document.getElementById('yovel-department-modal-cancel');
            const departmentFormBuilderModal = document.getElementById('yovel-department-form-builder-modal');
            const departmentFormBuilderModalOpen = document.getElementById('yovel-department-form-builder-modal-open');
            const departmentFormBuilderModalClose = document.getElementById('yovel-department-form-builder-modal-close');
            const departmentFormBuilderModalCancel = document.getElementById('yovel-department-form-builder-modal-cancel');
            const departmentFormCreateModal = document.getElementById('yovel-department-form-create-modal');
            const departmentFormCreateModalOpen = document.getElementById('yovel-department-form-create-modal-open');
            const departmentFormCreateModalClose = document.getElementById('yovel-department-form-create-modal-close');
            const departmentFormCreateModalCancel = document.getElementById('yovel-department-form-create-modal-cancel');
            const jobPositionModal = document.getElementById('yovel-job-position-modal');
            const jobPositionModalOpen = document.getElementById('yovel-job-position-modal-open');
            const jobPositionModalClose = document.getElementById('yovel-job-position-modal-close');
            const jobPositionModalCancel = document.getElementById('yovel-job-position-modal-cancel');
            const jobPositionFormBuilderModal = document.getElementById('yovel-job-position-form-builder-modal');
            const jobPositionFormBuilderModalOpen = document.getElementById('yovel-job-position-form-builder-modal-open');
            const jobPositionFormBuilderModalClose = document.getElementById('yovel-job-position-form-builder-modal-close');
            const jobPositionFormBuilderModalCancel = document.getElementById('yovel-job-position-form-builder-modal-cancel');
            const jobPositionFormCreateModal = document.getElementById('yovel-job-position-form-create-modal');
            const jobPositionFormCreateModalOpen = document.getElementById('yovel-job-position-form-create-modal-open');
            const jobPositionFormCreateModalClose = document.getElementById('yovel-job-position-form-create-modal-close');
            const jobPositionFormCreateModalCancel = document.getElementById('yovel-job-position-form-create-modal-cancel');
            const jobPositionCodeInput = document.getElementById('job_position_code');
            const jobPositionSetupCard = document.getElementById('yovel-job-position-setup-card');
            const jobPositionSetupDismiss = document.getElementById('yovel-job-position-setup-dismiss');
            const jobPositionTour = document.getElementById('yovel-job-position-tour');
            const jobPositionTourTitle = document.getElementById('yovel-job-position-tour-title');
            const jobPositionTourBody = document.getElementById('yovel-job-position-tour-body');
            const jobPositionTourCount = document.getElementById('yovel-job-position-tour-count');
            const jobPositionTourClose = document.getElementById('yovel-job-position-tour-close');
            const jobPositionTourSkip = document.getElementById('yovel-job-position-tour-skip');
            const jobPositionTourBack = document.getElementById('yovel-job-position-tour-back');
            const jobPositionTourNext = document.getElementById('yovel-job-position-tour-next');
            const employeeCodeInput = document.getElementById('employee_code');
            const employeeFirstNameInput = document.getElementById('first_name');
            const employeeMiddleNameInput = document.getElementById('middle_name');
            const employeeLastNameInput = document.getElementById('last_name');
            const employeeNameInput = document.getElementById('employee_name');
            const employeeModalFocusable = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
            const employeeWidgetBoard = document.getElementById('yovel-employee-widget-board');
            const employeeDropTargets = Array.from(document.querySelectorAll('[data-employee-drop-target]'));
            const employeeRows = Array.from(document.querySelectorAll('[data-employee-drop-target]'));
            const employeeFilterControls = Array.from(document.querySelectorAll('[data-employee-filter]'));
            const employeeClearFilters = document.querySelector('[data-employee-clear-filters]');
            const employeeSortControl = document.querySelector('[data-employee-sort]');
            const employeeTableBody = document.querySelector('[data-employee-table-body]');
            const employeeResultCount = document.querySelector('[data-employee-result-count]');
            const employeeNoResults = document.querySelector('[data-employee-no-results]');
            const employeeContextTabs = Array.from(document.querySelectorAll('[data-employee-context-tab]'));
            const employeeContextPanels = Array.from(document.querySelectorAll('[data-employee-context-panel]'));
            const employeeContextName = document.querySelector('[data-employee-context-name]');
            const employeeContextStatus = document.querySelector('[data-employee-context-status]');
            const employeeContextCompleteness = document.querySelector('[data-employee-context-completeness]');
            const employeeContextProgress = document.querySelector('[data-employee-context-progress]');
            const employeeContextProgressBar = document.querySelector('[data-employee-context-progress-bar]');
            const employeeContextMissing = document.querySelector('[data-employee-context-missing]');
            const employeeContextAssignment = document.querySelector('[data-employee-context-assignment]');
            const employeeContextContact = document.querySelector('[data-employee-context-contact]');
            const employeeActionMenus = Array.from(document.querySelectorAll('[data-employee-action-menu]'));
            const departmentWidgetBoard = document.getElementById('yovel-department-widget-board');
            const departmentDropTargets = Array.from(document.querySelectorAll('[data-department-drop-target]'));
            const jobPositionWidgetBoard = document.getElementById('yovel-job-position-widget-board');
            const jobPositionDropTargets = Array.from(document.querySelectorAll('[data-job-position-drop-target]'));
            const employeeFeatureButtons = Array.from(document.querySelectorAll('[data-employee-section-target]'));
            const employeeModalSections = Array.from(document.querySelectorAll('[data-employee-modal-section]'));
            const employeeModalEditMode = <?= $editHrEmployee ? 'true' : 'false' ?>;
            const employeeInitialSection = <?= json_encode($activeEmployeeModalSection, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const employeeStatusInput = document.getElementById('employee_status');
            const employeeFormState = document.querySelector('[data-employee-form-state]');
            const employeeExitGuidance = document.querySelector('[data-employee-exit-guidance]');
            const departmentFeatureButtons = Array.from(document.querySelectorAll('[data-department-section-target]'));
            const departmentModalSections = Array.from(document.querySelectorAll('[data-department-modal-section]'));
            const departmentModalEditMode = <?= $editHrDepartment ? 'true' : 'false' ?>;
            const departmentInitialSection = <?= json_encode($activeDepartmentModalSection, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const jobPositionFeatureButtons = Array.from(document.querySelectorAll('[data-job-position-section-target]'));
            const jobPositionModalSections = Array.from(document.querySelectorAll('[data-job-position-modal-section]'));
            const jobPositionModalEditMode = <?= $editHrJobPosition ? 'true' : 'false' ?>;
            const jobPositionInitialSection = <?= json_encode($activeJobPositionModalSection, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const salesLeadModal = document.getElementById('yovel-sales-lead-modal');
            const salesLeadModalOpen = document.getElementById('yovel-sales-lead-modal-open');
            const salesLeadModalClose = document.getElementById('yovel-sales-lead-modal-close');
            const salesLeadModalCancel = document.getElementById('yovel-sales-lead-modal-cancel');
            const salesLeadCodeInput = document.getElementById('sales_lead_code');
            const salesLeadModalEditMode = <?= $editSalesLead ? 'true' : 'false' ?>;
            const salesSchemaForm = document.querySelector('[data-sales-schema-form]');
            const salesFormBuilder = document.getElementById('yovel-sales-form-builder');
            const accountModal = document.getElementById('yovel-account-modal');
            const accountModalOpen = document.getElementById('yovel-account-modal-open');
            const accountModalOpenSecondary = document.getElementById('yovel-account-modal-open-secondary');
            const accountModalClose = document.getElementById('yovel-account-modal-close');
            const accountModalCancel = document.getElementById('yovel-account-modal-cancel');
            const accountBuilderModal = document.getElementById('yovel-account-builder-modal');
            const accountBuilderModalOpen = document.getElementById('yovel-account-builder-modal-open');
            const accountBuilderModalClose = document.getElementById('yovel-account-builder-modal-close');
            const accountBuilderModalCancel = document.getElementById('yovel-account-builder-modal-cancel');
            const accountCodeInput = document.getElementById('account_code');
            const accountModalEditMode = <?= $editAccountingAccount ? 'true' : 'false' ?>;
            const accountRootType = document.getElementById('root_type');
            const accountReportType = document.getElementById('report_type');
            const accountWidgetBoard = document.getElementById('yovel-account-widget-board');
            const accountSchemaForm = document.querySelector('[data-account-schema-form]');
            const accountFormBuilder = document.getElementById('yovel-account-form-builder');
            const financeScrollers = Array.from(document.querySelectorAll('.yovel-finance-scroll'));
            const financeBuilderModal = document.getElementById('yovel-finance-builder-modal');
            const financeGoogleBuilders = Array.from(document.querySelectorAll('[data-finance-google-builder]'));
            const financeGridViewModal = document.querySelector('[data-finance-grid-view-modal]');
            const financeGridFormulaModal = document.querySelector('[data-finance-grid-formula-modal]');
            const financeGridImportModal = document.querySelector('[data-finance-grid-import-modal]');
            const financeOperationModals = [financeGridViewModal, financeGridFormulaModal, financeGridImportModal].filter(Boolean);
            const hrBuilderTargetSelect = document.querySelector('[data-hr-builder-target-select]');
            const hrGoogleBuilders = Array.from(document.querySelectorAll('[data-hr-google-builder]'));
            const hrDashboardBuilderModal = document.getElementById('yovel-hr-dashboard-builder-modal');
            const departmentSource = document.getElementById('department_source');
            const departmentTemplate = document.getElementById('default_department_key');
            const departmentCode = document.getElementById('department_code');
            const departmentName = document.getElementById('department_name');
            const departmentType = document.getElementById('department_type');
            const departmentDescription = document.getElementById('department_description');
            let pendingForm = null;
            let allowSubmit = false;
            const syncShellModalState = () => {
                const hasOpenShellModal = [employeeModal, hrFormBuilderModal, hrFormCreateModal, departmentModal, departmentFormBuilderModal, departmentFormCreateModal, jobPositionModal, jobPositionFormBuilderModal, jobPositionFormCreateModal, salesLeadModal, accountModal, accountBuilderModal, hrDashboardBuilderModal, financeBuilderModal, ...financeOperationModals]
                    .some((modal) => modal && !modal.hidden);
                document.body.classList.toggle('yovel-shell-modal-active', hasOpenShellModal);
            };

            const closeDialog = () => {
                pendingForm = null;
                dialog.hidden = true;
            };

            if (hrDashboardBuilderModal) {
                const builderDialog = hrDashboardBuilderModal.querySelector('[role="document"], .yovel-hr-dashboard-builder-dialog');
                const builderFocusable = () => Array.from(hrDashboardBuilderModal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
                    .filter((element) => !element.hidden && element.getClientRects().length > 0);
                setTimeout(() => builderFocusable()[0]?.focus() || builderDialog?.focus(), 0);
                hrDashboardBuilderModal.addEventListener('click', (event) => {
                    if (event.target === hrDashboardBuilderModal) {
                        window.location.href = './?view=hr&section=dashboard';
                    }
                });
                hrDashboardBuilderModal.addEventListener('keydown', (event) => {
                    if (event.key !== 'Tab') {
                        return;
                    }
                    const focusable = builderFocusable();
                    if (!focusable.length) {
                        event.preventDefault();
                        builderDialog?.focus();
                        return;
                    }
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                });
            }

            if (financeBuilderModal && !financeBuilderModal.hidden) {
                const financeDialog = financeBuilderModal.querySelector('.yovel-finance-builder-dialog');
                const financeFocusable = () => Array.from(financeBuilderModal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
                    .filter((element) => !element.hidden && element.getClientRects().length > 0);
                const financeCloseHref = financeBuilderModal.dataset.closeHref || './?view=accounting-finance&section=dashboard';
                setTimeout(() => financeFocusable()[0]?.focus() || financeDialog?.focus(), 0);
                financeBuilderModal.addEventListener('click', (event) => {
                    if (event.target === financeBuilderModal) {
                        window.location.href = financeCloseHref;
                    }
                });
                financeBuilderModal.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        window.location.href = financeCloseHref;
                        return;
                    }
                    if (event.key !== 'Tab') {
                        return;
                    }
                    const focusable = financeFocusable();
                    if (!focusable.length) {
                        event.preventDefault();
                        financeDialog?.focus();
                        return;
                    }
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                });
            }

            const hrGoogleQuestionTypes = {
                SHORT_TEXT: 'Short answer',
                PARAGRAPH: 'Paragraph',
                DROPDOWN: 'Dropdown',
                CHECKBOXES: 'Checkboxes',
                DATE: 'Date',
                NUMBER: 'Number',
                EMAIL: 'Email',
                PHONE: 'Phone',
                SECTION: 'Section header',
            };

            hrBuilderTargetSelect?.addEventListener('change', () => {
                const target = hrBuilderTargetSelect.value || 'employee-profiles';
                const params = new URLSearchParams({ view: 'hr', section: 'dashboard', builder_target: target });
                window.location.href = `./?${params.toString()}`;
            });

            const createHrGoogleKey = (prefix) => {
                const randomKey = globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`;
                return `${prefix}-${randomKey}`;
            };

            const createHrGoogleQuestionCard = (builder, values = {}) => {
                const card = document.createElement('section');
                card.className = 'yovel-hr-google-question rounded-md border bg-background/70 p-3';
                card.dataset.hrGoogleQuestion = '';
                card.dataset.hrGoogleQuestionKey = values.key || createHrGoogleKey('question');
                const typeOptions = Object.entries(hrGoogleQuestionTypes)
                    .map(([value, label]) => `<option value="${value}" ${values.type === value ? 'selected' : ''}>${label}</option>`)
                    .join('');
                const options = Array.isArray(values.options) ? values.options.join('\n') : '';
                card.innerHTML = `
                    <div class="yovel-hr-google-question-layout">
                        <div class="yovel-hr-google-question-main">
                            <input class="h-9 rounded-md border bg-background px-3 text-sm font-medium" data-hr-google-label placeholder="Question">
                            <input class="h-9 rounded-md border bg-background px-3 text-xs" data-hr-google-help placeholder="Help text">
                            <textarea class="min-h-16 rounded-md border bg-background px-3 py-2 text-xs" data-hr-google-options placeholder="One option per line"></textarea>
                        </div>
                        <div class="yovel-hr-google-question-controls">
                            <select class="h-9 rounded-md border bg-background px-3 text-sm" data-hr-google-type>${typeOptions}</select>
                            <label class="inline-flex items-center gap-2 text-xs font-medium"><input type="checkbox" data-hr-google-required> Required</label>
                            <div class="flex flex-wrap gap-1.5">
                                <button type="button" class="yovel-action-icon yovel-action-icon--info" data-hr-google-duplicate title="Duplicate question" aria-label="Duplicate question"><span class="material-symbols-rounded" aria-hidden="true">content_copy</span></button>
                                <button type="button" class="yovel-action-icon yovel-action-icon--archive" data-hr-google-up title="Move question up" aria-label="Move question up"><span class="material-symbols-rounded" aria-hidden="true">keyboard_arrow_up</span></button>
                                <button type="button" class="yovel-action-icon yovel-action-icon--archive" data-hr-google-down title="Move question down" aria-label="Move question down"><span class="material-symbols-rounded" aria-hidden="true">keyboard_arrow_down</span></button>
                                <button type="button" class="yovel-action-icon yovel-action-icon--danger" data-hr-google-delete title="Delete question" aria-label="Delete question"><span class="material-symbols-rounded" aria-hidden="true">delete</span></button>
                            </div>
                        </div>
                    </div>
                `;
                card.querySelector('[data-hr-google-label]').value = values.label || '';
                card.querySelector('[data-hr-google-help]').value = values.help || '';
                card.querySelector('[data-hr-google-options]').value = options;
                card.querySelector('[data-hr-google-required]').checked = Boolean(values.required);
                syncHrGoogleQuestionCard(card);
                return card;
            };

            const syncHrGoogleColumnEmpty = (column) => {
                const emptyHint = column.querySelector(':scope > [data-hr-google-column-empty]');
                const isEmpty = !column.querySelector(':scope > [data-hr-google-question]');
                column.dataset.hrGoogleEmpty = isEmpty ? 'true' : 'false';
                if (emptyHint) {
                    emptyHint.hidden = !isEmpty;
                }
            };

            const createHrGoogleColumn = (builder, values = {}, questionsByKey = new Map()) => {
                const column = document.createElement('div');
                column.className = 'yovel-hr-google-column';
                column.dataset.hrGoogleColumn = values.key || createHrGoogleKey('column');
                column.tabIndex = 0;
                column.setAttribute('role', 'group');
                column.setAttribute('aria-selected', 'false');
                column.innerHTML = '<p class="yovel-hr-google-column-empty" data-hr-google-column-empty>Drop questions here</p>';
                (Array.isArray(values.question_keys) ? values.question_keys : []).forEach((questionKey) => {
                    const question = questionsByKey.get(questionKey);
                    if (question) {
                        column.appendChild(createHrGoogleQuestionCard(builder, question));
                    }
                });
                syncHrGoogleColumnEmpty(column);
                return column;
            };

            const syncHrGoogleRow = (builder, row, requestedCount = null) => {
                const columnsContainer = row.querySelector('[data-hr-google-columns]');
                if (!columnsContainer) {
                    return;
                }
                const existingColumns = Array.from(columnsContainer.querySelectorAll(':scope > [data-hr-google-column]'));
                const columnCount = Math.max(1, Math.min(3, Number(requestedCount || existingColumns.length || 1)));
                if (columnCount > existingColumns.length) {
                    for (let index = existingColumns.length; index < columnCount; index += 1) {
                        columnsContainer.appendChild(createHrGoogleColumn(builder));
                    }
                } else if (columnCount < existingColumns.length) {
                    const keptColumns = existingColumns.slice(0, columnCount);
                    const destination = keptColumns[keptColumns.length - 1];
                    existingColumns.slice(columnCount).forEach((column) => {
                        column.querySelectorAll(':scope > [data-hr-google-question]').forEach((card) => destination.appendChild(card));
                        column.remove();
                    });
                    syncHrGoogleColumnEmpty(destination);
                }
                const columns = Array.from(columnsContainer.querySelectorAll(':scope > [data-hr-google-column]'));
                columnsContainer.style.setProperty('--hr-google-column-count', String(columns.length));
                columns.forEach((column, index) => {
                    column.setAttribute('aria-label', `Column ${index + 1} of ${columns.length}`);
                    syncHrGoogleColumnEmpty(column);
                });
                row.querySelectorAll('[data-hr-google-column-count]').forEach((button) => {
                    const active = Number(button.dataset.hrGoogleColumnCount) === columns.length;
                    button.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            };

            const createHrGoogleRow = (builder, values = {}, questionsByKey = new Map()) => {
                const row = document.createElement('section');
                row.className = 'yovel-hr-google-row rounded-md border bg-card/45';
                row.dataset.hrGoogleRow = values.key || createHrGoogleKey('row');
                row.innerHTML = `
                    <header class="yovel-hr-google-row-header flex flex-wrap items-center justify-between gap-2 border-b px-3 py-2" data-hr-google-row-drag-surface>
                        <span class="text-xs font-semibold">Row</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[11px] text-muted-foreground">Columns</span>
                            <div class="inline-flex rounded-md border bg-background p-0.5" role="group" aria-label="Columns in row">
                                <button type="button" class="yovel-hr-google-column-count" data-hr-google-column-count="1" aria-label="One column">1</button>
                                <button type="button" class="yovel-hr-google-column-count" data-hr-google-column-count="2" aria-label="Two columns">2</button>
                                <button type="button" class="yovel-hr-google-column-count" data-hr-google-column-count="3" aria-label="Three columns">3</button>
                            </div>
                            <button type="button" class="yovel-action-icon yovel-action-icon--archive" data-hr-google-row-up title="Move row up" aria-label="Move row up"><span class="material-symbols-rounded" aria-hidden="true">keyboard_arrow_up</span></button>
                            <button type="button" class="yovel-action-icon yovel-action-icon--archive" data-hr-google-row-down title="Move row down" aria-label="Move row down"><span class="material-symbols-rounded" aria-hidden="true">keyboard_arrow_down</span></button>
                            <button type="button" class="yovel-action-icon yovel-action-icon--danger" data-hr-google-row-delete title="Delete row" aria-label="Delete row"><span class="material-symbols-rounded" aria-hidden="true">delete</span></button>
                        </div>
                    </header>
                    <div class="yovel-hr-google-columns" data-hr-google-columns></div>
                `;
                const columnsContainer = row.querySelector('[data-hr-google-columns]');
                const sourceColumns = Array.isArray(values.columns) && values.columns.length > 0
                    ? values.columns.slice(0, 3)
                    : [{ key: createHrGoogleKey('column'), question_keys: [] }];
                sourceColumns.forEach((column) => columnsContainer.appendChild(createHrGoogleColumn(builder, column, questionsByKey)));
                syncHrGoogleRow(builder, row);
                return row;
            };

            const syncHrGoogleQuestionCard = (card) => {
                const type = card.querySelector('[data-hr-google-type]')?.value || 'SHORT_TEXT';
                const options = card.querySelector('[data-hr-google-options]');
                const required = card.querySelector('[data-hr-google-required]');
                if (options) {
                    options.hidden = type !== 'DROPDOWN' && type !== 'CHECKBOXES';
                }
                if (required) {
                    required.disabled = type === 'SECTION';
                    if (type === 'SECTION') {
                        required.checked = false;
                    }
                }
            };

            const serializeHrGoogleBuilder = (builder) => {
                const questions = [];
                const rows = Array.from(builder.querySelectorAll('[data-hr-google-row]')).map((row) => ({
                    key: row.dataset.hrGoogleRow || createHrGoogleKey('row'),
                    columns: Array.from(row.querySelectorAll(':scope > [data-hr-google-columns] > [data-hr-google-column]')).map((column) => {
                        const questionKeys = Array.from(column.querySelectorAll(':scope > [data-hr-google-question]')).map((card) => {
                            const type = card.querySelector('[data-hr-google-type]')?.value || 'SHORT_TEXT';
                            const options = (card.querySelector('[data-hr-google-options]')?.value || '')
                                .split(/\r?\n/)
                                .map((option) => option.trim())
                                .filter(Boolean);
                            const key = card.dataset.hrGoogleQuestionKey || createHrGoogleKey('question');
                            card.dataset.hrGoogleQuestionKey = key;
                            questions.push({
                                key,
                                label: card.querySelector('[data-hr-google-label]')?.value || '',
                                help: card.querySelector('[data-hr-google-help]')?.value || '',
                                type,
                                required: Boolean(card.querySelector('[data-hr-google-required]')?.checked) && type !== 'SECTION',
                                options,
                                order: (questions.length + 1) * 10,
                            });
                            return key;
                        });
                        return {
                            key: column.dataset.hrGoogleColumn || createHrGoogleKey('column'),
                            question_keys: questionKeys,
                        };
                    }),
                }));
                const schemaInput = builder.querySelector('[data-hr-google-schema-json]');
                const schema = { version: 2, questions, rows };
                if (schemaInput) {
                    schemaInput.value = JSON.stringify(schema);
                }
                const empty = builder.querySelector('[data-hr-google-empty]');
                if (empty) {
                    empty.hidden = rows.length > 0;
                }
                return schema;
            };

            const escapeHtml = (value) => String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const renderHrGooglePreview = (builder) => {
                const schema = serializeHrGoogleBuilder(builder);
                const questionsByKey = new Map(schema.questions.map((question) => [question.key, question]));
                const title = builder.querySelector('[name="form_title"]')?.value || 'Untitled HR Form';
                const description = builder.querySelector('[name="form_description"]')?.value || '';
                const previewTitle = builder.querySelector('[data-hr-google-preview-title]');
                const previewDescription = builder.querySelector('[data-hr-google-preview-description]');
                const previewList = builder.querySelector('[data-hr-google-preview-list]');
                if (previewTitle) {
                    previewTitle.textContent = title;
                }
                if (previewDescription) {
                    previewDescription.textContent = description;
                }
                if (!previewList) {
                    return;
                }
                previewList.innerHTML = '';
                schema.rows.forEach((layoutRow) => {
                    const row = document.createElement('div');
                    row.className = 'yovel-hr-google-preview-row';
                    row.style.setProperty('--hr-google-column-count', String(layoutRow.columns.length));
                    let renderedQuestionCount = 0;
                    layoutRow.columns.forEach((layoutColumn) => {
                        const column = document.createElement('div');
                        column.className = 'grid content-start gap-3';
                        layoutColumn.question_keys.forEach((questionKey) => {
                            const question = questionsByKey.get(questionKey);
                            if (!question) {
                                return;
                            }
                            const field = document.createElement('div');
                            field.className = question.type === 'SECTION' ? 'border-b pb-2 pt-1' : 'rounded-md border bg-card p-3';
                    const label = escapeHtml(question.label || 'Untitled question');
                    const help = escapeHtml(question.help || '');
                    if (question.type === 'SECTION') {
                                field.innerHTML = `<h5 class="text-sm font-semibold">${escapeHtml(question.label || 'Section title')}</h5><p class="mt-1 text-xs text-muted-foreground">${help}</p>`;
                    } else if (question.type === 'DROPDOWN') {
                                field.innerHTML = `<label class="text-sm font-semibold">${label}${question.required ? ' *' : ''}</label><select class="mt-2 h-9 w-full rounded-md border bg-background px-3 text-sm"><option>Select option</option>${question.options.map((option) => `<option>${escapeHtml(option)}</option>`).join('')}</select>`;
                    } else if (question.type === 'CHECKBOXES') {
                                field.innerHTML = `<p class="text-sm font-semibold">${label}${question.required ? ' *' : ''}</p><div class="mt-2 grid gap-2">${question.options.map((option) => `<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox"> ${escapeHtml(option)}</label>`).join('')}</div>`;
                            } else if (question.type === 'PARAGRAPH') {
                                field.innerHTML = `<label class="text-sm font-semibold">${label}${question.required ? ' *' : ''}</label><textarea class="mt-2 min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm" placeholder="${help || 'Enter answer'}"></textarea>`;
                    } else {
                        const typeMap = { DATE: 'date', NUMBER: 'number', EMAIL: 'email', PHONE: 'tel' };
                                field.innerHTML = `<label class="text-sm font-semibold">${label}${question.required ? ' *' : ''}</label><input class="mt-2 h-9 w-full rounded-md border bg-background px-3 text-sm" type="${typeMap[question.type] || 'text'}" placeholder="${help || 'Enter answer'}">`;
                    }
                            column.appendChild(field);
                            renderedQuestionCount += 1;
                        });
                        row.appendChild(column);
                    });
                    if (renderedQuestionCount > 0) {
                        previewList.appendChild(row);
                    }
                });
            };

	            let employeeModalLastTrigger = employeeModalOpen;
	            let hrFormBuilderModalLastTrigger = hrFormBuilderModalOpen;
	            let hrFormCreateModalLastTrigger = hrFormCreateModalOpen;
            let departmentModalLastTrigger = departmentModalOpen;
            let departmentFormBuilderModalLastTrigger = departmentFormBuilderModalOpen;
            let departmentFormCreateModalLastTrigger = departmentFormCreateModalOpen;
            let jobPositionModalLastTrigger = jobPositionModalOpen;
            let jobPositionFormBuilderModalLastTrigger = jobPositionFormBuilderModalOpen;
            let jobPositionFormCreateModalLastTrigger = jobPositionFormCreateModalOpen;

            const syncEmployeeFullName = () => {
                if (!employeeNameInput) {
                    return;
                }
                employeeNameInput.value = [employeeFirstNameInput?.value, employeeMiddleNameInput?.value, employeeLastNameInput?.value]
                    .map((value) => (value || '').trim())
                    .filter(Boolean)
                    .join(' ');
            };

            const setEmployeeModalSection = (sectionKey) => {
                const nextSection = employeeModalSections.some((section) => section.dataset.employeeModalSection === sectionKey)
                    ? sectionKey
                    : 'overview';
                employeeModalSections.forEach((section) => {
                    section.hidden = section.dataset.employeeModalSection !== nextSection;
                });
	                employeeFeatureButtons.forEach((button) => {
	                    const active = button.dataset.employeeSectionTarget === nextSection;
	                    button.setAttribute('aria-pressed', active ? 'true' : 'false');
	                    if (button.getAttribute('role') === 'tab') {
	                        button.setAttribute('aria-selected', active ? 'true' : 'false');
	                        button.tabIndex = active ? 0 : -1;
	                    }
	                });
                return nextSection;
            };

            const openEmployeeModal = (sectionKey = 'overview', trigger = employeeModalOpen) => {
                if (!employeeModal) {
                    return;
                }
                employeeModalLastTrigger = trigger || employeeModalOpen;
                const activeSectionKey = setEmployeeModalSection(sectionKey);
                employeeModal.hidden = false;
                syncShellModalState();
                const activeSection = employeeModal.querySelector(`[data-employee-modal-section="${activeSectionKey}"]`);
                const firstField = activeSection?.querySelector('input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
                setTimeout(() => (firstField || employeeCodeInput)?.focus(), 0);
            };

            const employeeSectionUrl = (employeeKey, sectionKey = 'overview') => {
                const params = new URLSearchParams({
                    view: 'hr',
                    section: 'employee-profiles',
                    edit: employeeKey,
                    employee_section: sectionKey,
                });
                return `./?${params.toString()}`;
            };

            const selectedEmployeeDropTarget = () => {
                const activeRow = document.querySelector('[data-employee-drop-target][aria-selected="true"]');
                if (activeRow && !activeRow.hidden) {
                    return activeRow;
                }
                const selectedCheckbox = document.querySelector('[data-employee-drop-target] input[type="checkbox"]:checked');
                return selectedCheckbox?.closest('[data-employee-drop-target]') || null;
            };

            const syncEmployeeContext = (row) => {
                const hasEmployee = Boolean(row);
                const completeness = Math.max(0, Math.min(100, Number(row?.dataset.employeeCompleteness || 0)));
                if (employeeContextName) {
                    employeeContextName.textContent = row?.dataset.employeeDisplayName || 'Select an employee';
                }
                if (employeeContextStatus) {
                    employeeContextStatus.textContent = row?.dataset.employeeStatusLabel || 'No selection';
                }
                if (employeeContextCompleteness) {
                    employeeContextCompleteness.textContent = `${completeness}%`;
                }
                if (employeeContextProgress) {
                    employeeContextProgress.setAttribute('aria-valuenow', String(completeness));
                }
                if (employeeContextProgressBar) {
                    employeeContextProgressBar.style.width = `${completeness}%`;
                }
                if (employeeContextMissing) {
                    employeeContextMissing.textContent = hasEmployee
                        ? row.dataset.employeeMissing || 'Core profile complete'
                        : 'Select an employee to review profile readiness.';
                }
                if (employeeContextAssignment) {
                    employeeContextAssignment.textContent = hasEmployee
                        ? `${row.dataset.employeeDesignation || 'Unassigned'} · ${row.dataset.employeeDepartmentLabel || 'Unassigned'}`
                        : 'No employee selected';
                }
                if (employeeContextContact) {
                    employeeContextContact.textContent = hasEmployee
                        ? `${row.dataset.employeeBranchLabel || 'Unassigned branch'} · ${row.dataset.employeeEmail || 'No email'}`
                        : 'Contact details will appear here.';
                }
            };

            const selectEmployeeRow = (row) => {
                employeeRows.forEach((candidate) => candidate.setAttribute('aria-selected', candidate === row ? 'true' : 'false'));
                syncEmployeeContext(row || null);
            };

            const applyEmployeeFilters = () => {
                const filters = Object.fromEntries(employeeFilterControls.map((control) => [
                    control.dataset.employeeFilter || '',
                    String(control.value || '').trim().toLowerCase(),
                ]));
                let visibleCount = 0;
                employeeRows.forEach((row) => {
                    const matches = Object.entries(filters).every(([key, value]) => {
                        if (!key || value === '') {
                            return true;
                        }
                        return String(row.dataset[`employee${key.charAt(0).toUpperCase()}${key.slice(1)}`] || '').includes(value);
                    });
                    row.hidden = !matches;
                    if (matches) {
                        visibleCount += 1;
                    }
                });
                const [sortKey, sortDirection] = String(employeeSortControl?.value || 'name-asc').split('-');
                const sortDataKey = {
                    id: 'employeeId',
                    name: 'employeeName',
                    department: 'employeeDepartment',
                    status: 'employeeStatus',
                }[sortKey] || 'employeeName';
                const sortedRows = [...employeeRows].sort((left, right) => {
                    const comparison = String(left.dataset[sortDataKey] || '').localeCompare(String(right.dataset[sortDataKey] || ''), undefined, { numeric: true, sensitivity: 'base' });
                    return sortDirection === 'desc' ? -comparison : comparison;
                });
                sortedRows.forEach((row) => employeeTableBody?.insertBefore(row, employeeNoResults || null));
                if (employeeResultCount) {
                    employeeResultCount.textContent = String(visibleCount);
                }
                if (employeeNoResults) {
                    employeeNoResults.hidden = visibleCount !== 0;
                }
                const selectedRow = selectedEmployeeDropTarget();
                if (!selectedRow || selectedRow.hidden) {
                    selectEmployeeRow(employeeRows.find((row) => !row.hidden) || null);
                }
            };

            const setEmployeeContextPanel = (panelKey) => {
                const nextKey = employeeContextPanels.some((panel) => panel.dataset.employeeContextPanel === panelKey)
                    ? panelKey
                    : 'sections';
                employeeContextTabs.forEach((tab) => tab.setAttribute('aria-selected', tab.dataset.employeeContextTab === nextKey ? 'true' : 'false'));
                employeeContextPanels.forEach((panel) => {
                    panel.hidden = panel.dataset.employeeContextPanel !== nextKey;
                });
            };

            const openEmployeeSectionFromFeature = (sectionKey = 'overview', trigger = employeeModalOpen) => {
                if (employeeModalEditMode || (employeeModal && !employeeModal.hidden)) {
                    openEmployeeModal(sectionKey, trigger);
                    return;
                }
                const selectedRow = selectedEmployeeDropTarget();
                const selectedEmployeeKey = selectedRow?.dataset.employeeKey || '';
                if (selectedEmployeeKey !== '') {
                    window.location.href = employeeSectionUrl(selectedEmployeeKey, sectionKey);
                    return;
                }
                openEmployeeModal(sectionKey, trigger);
            };

            const setDepartmentModalSection = (sectionKey) => {
                const nextSection = departmentModalSections.some((section) => section.dataset.departmentModalSection === sectionKey)
                    ? sectionKey
                    : 'overview';
                departmentModalSections.forEach((section) => {
                    section.hidden = section.dataset.departmentModalSection !== nextSection;
                });
                departmentFeatureButtons.forEach((button) => {
                    const active = button.dataset.departmentSectionTarget === nextSection;
                    button.setAttribute('aria-pressed', active ? 'true' : 'false');
                    if (button.getAttribute('role') === 'tab') {
                        button.setAttribute('aria-selected', active ? 'true' : 'false');
                    }
                });
                return nextSection;
            };

            const openDepartmentModal = (sectionKey = 'overview', trigger = departmentModalOpen) => {
                if (!departmentModal) {
                    return;
                }
                departmentModalLastTrigger = trigger || departmentModalOpen;
                const activeSectionKey = setDepartmentModalSection(sectionKey);
                departmentModal.hidden = false;
                syncShellModalState();
                const activeSection = departmentModal.querySelector(`[data-department-modal-section="${activeSectionKey}"]`);
                const firstField = activeSection?.querySelector('input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
                setTimeout(() => firstField?.focus(), 0);
            };

            const departmentSectionUrl = (departmentKey, sectionKey = 'overview') => {
                const params = new URLSearchParams({
                    view: 'hr',
                    section: 'departments',
                    edit: departmentKey,
                    department_section: sectionKey,
                });
                return `./?${params.toString()}`;
            };

            const selectedDepartmentTarget = () => {
                const selectedCheckbox = document.querySelector('[data-department-drop-target] input[type="checkbox"]:checked');
                return selectedCheckbox?.closest('[data-department-drop-target]') || null;
            };

            const openDepartmentSectionFromFeature = (sectionKey = 'overview', trigger = departmentModalOpen) => {
                if (departmentModalEditMode || (departmentModal && !departmentModal.hidden)) {
                    openDepartmentModal(sectionKey, trigger);
                    return;
                }
                const selectedRow = selectedDepartmentTarget();
                const selectedDepartmentKey = selectedRow?.dataset.departmentKey || '';
                if (selectedDepartmentKey !== '') {
                    window.location.href = departmentSectionUrl(selectedDepartmentKey, sectionKey);
                    return;
                }
                openDepartmentModal(sectionKey, trigger);
            };

            const setJobPositionModalSection = (sectionKey) => {
                const nextSection = jobPositionModalSections.some((section) => section.dataset.jobPositionModalSection === sectionKey)
                    ? sectionKey
                    : 'overview';
                jobPositionModalSections.forEach((section) => {
                    section.hidden = section.dataset.jobPositionModalSection !== nextSection;
                });
                jobPositionFeatureButtons.forEach((button) => {
                    const active = button.dataset.jobPositionSectionTarget === nextSection;
                    button.setAttribute('aria-pressed', active ? 'true' : 'false');
                    if (button.getAttribute('role') === 'tab') {
                        button.setAttribute('aria-selected', active ? 'true' : 'false');
                    }
                });
                return nextSection;
            };

            const openJobPositionModal = (sectionKey = 'overview', trigger = jobPositionModalOpen) => {
                if (!jobPositionModal) {
                    return;
                }
                jobPositionModalLastTrigger = trigger || jobPositionModalOpen;
                const activeSectionKey = setJobPositionModalSection(sectionKey);
                jobPositionModal.hidden = false;
                syncShellModalState();
                const activeSection = jobPositionModal.querySelector(`[data-job-position-modal-section="${activeSectionKey}"]`);
                const firstField = activeSection?.querySelector('input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
                setTimeout(() => (firstField || jobPositionCodeInput)?.focus(), 0);
            };

            const jobPositionSectionUrl = (jobPositionKey, sectionKey = 'overview') => {
                const params = new URLSearchParams({
                    view: 'hr',
                    section: 'job-positions',
                    edit: jobPositionKey,
                    job_position_section: sectionKey,
                });
                return `./?${params.toString()}`;
            };

            const selectedJobPositionTarget = () => {
                const selectedCheckbox = document.querySelector('[data-job-position-drop-target] input[type="checkbox"]:checked');
                return selectedCheckbox?.closest('[data-job-position-drop-target]') || null;
            };

            const openJobPositionSectionFromFeature = (sectionKey = 'overview', trigger = jobPositionModalOpen) => {
                if (jobPositionModalEditMode || (jobPositionModal && !jobPositionModal.hidden)) {
                    openJobPositionModal(sectionKey, trigger);
                    return;
                }
                const selectedRow = selectedJobPositionTarget();
                const selectedJobPositionKey = selectedRow?.dataset.jobPositionKey || '';
                if (selectedJobPositionKey !== '') {
                    window.location.href = jobPositionSectionUrl(selectedJobPositionKey, sectionKey);
                    return;
                }
                openJobPositionModal(sectionKey, trigger);
            };

	            const closeEmployeeModal = () => {
	                if (!employeeModal) {
	                    return;
	                }
                if (employeeModalEditMode) {
                    window.location.href = './?view=hr&section=employee-profiles';
                    return;
                }
                employeeModal.hidden = true;
                syncShellModalState();
	                employeeModalLastTrigger?.focus?.();
	            };

            const closeDepartmentModal = () => {
                if (!departmentModal) {
                    return;
                }
                if (departmentModalEditMode) {
                    window.location.href = './?view=hr&section=departments';
                    return;
                }
                departmentModal.hidden = true;
                syncShellModalState();
                departmentModalLastTrigger?.focus?.();
            };

            const closeJobPositionModal = () => {
                if (!jobPositionModal) {
                    return;
                }
                if (jobPositionModalEditMode) {
                    window.location.href = './?view=hr&section=job-positions';
                    return;
                }
                jobPositionModal.hidden = true;
                syncShellModalState();
                jobPositionModalLastTrigger?.focus?.();
            };

	            const openHrFormBuilderModal = (trigger = hrFormBuilderModalOpen) => {
	                if (!hrFormBuilderModal) {
	                    return;
	                }
	                hrFormBuilderModalLastTrigger = trigger || hrFormBuilderModalOpen;
	                hrFormBuilderModal.hidden = false;
	                syncShellModalState();
	                const firstField = hrFormBuilderModal.querySelector('input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
	                setTimeout(() => firstField?.focus(), 0);
	            };

	            const closeHrFormBuilderModal = () => {
	                if (!hrFormBuilderModal) {
	                    return;
	                }
	                hrFormBuilderModal.hidden = true;
	                syncShellModalState();
	                hrFormBuilderModalLastTrigger?.focus?.();
	            };

	            const openHrFormCreateModal = (trigger = hrFormCreateModalOpen) => {
	                if (!hrFormCreateModal) {
	                    return;
	                }
	                hrFormCreateModalLastTrigger = trigger || hrFormCreateModalOpen;
	                hrFormCreateModal.hidden = false;
	                syncShellModalState();
	                const firstField = hrFormCreateModal.querySelector('input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
	                setTimeout(() => firstField?.focus(), 0);
	            };

	            const closeHrFormCreateModal = () => {
	                if (!hrFormCreateModal) {
	                    return;
	                }
	                hrFormCreateModal.hidden = true;
	                syncShellModalState();
	                hrFormCreateModalLastTrigger?.focus?.();
	            };

            const openDepartmentFormBuilderModal = (trigger = departmentFormBuilderModalOpen) => {
                if (!departmentFormBuilderModal) {
                    return;
                }
                departmentFormBuilderModalLastTrigger = trigger || departmentFormBuilderModalOpen;
                departmentFormBuilderModal.hidden = false;
                syncShellModalState();
                const firstField = departmentFormBuilderModal.querySelector('input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
                setTimeout(() => firstField?.focus(), 0);
            };

            const closeDepartmentFormBuilderModal = () => {
                if (!departmentFormBuilderModal) {
                    return;
                }
                departmentFormBuilderModal.hidden = true;
                syncShellModalState();
                departmentFormBuilderModalLastTrigger?.focus?.();
            };

            const openDepartmentFormCreateModal = (trigger = departmentFormCreateModalOpen) => {
                if (!departmentFormCreateModal) {
                    return;
                }
                departmentFormCreateModalLastTrigger = trigger || departmentFormCreateModalOpen;
                departmentFormCreateModal.hidden = false;
                syncShellModalState();
                const firstField = departmentFormCreateModal.querySelector('input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
                setTimeout(() => firstField?.focus(), 0);
            };

            const closeDepartmentFormCreateModal = () => {
                if (!departmentFormCreateModal) {
                    return;
                }
                departmentFormCreateModal.hidden = true;
                syncShellModalState();
                departmentFormCreateModalLastTrigger?.focus?.();
            };

            const openJobPositionFormBuilderModal = (trigger = jobPositionFormBuilderModalOpen) => {
                if (!jobPositionFormBuilderModal) {
                    return;
                }
                jobPositionFormBuilderModalLastTrigger = trigger || jobPositionFormBuilderModalOpen;
                jobPositionFormBuilderModal.hidden = false;
                syncShellModalState();
                const firstField = jobPositionFormBuilderModal.querySelector('input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
                setTimeout(() => firstField?.focus(), 0);
            };

            const closeJobPositionFormBuilderModal = () => {
                if (!jobPositionFormBuilderModal) {
                    return;
                }
                jobPositionFormBuilderModal.hidden = true;
                syncShellModalState();
                jobPositionFormBuilderModalLastTrigger?.focus?.();
            };

            const openJobPositionFormCreateModal = (trigger = jobPositionFormCreateModalOpen) => {
                if (!jobPositionFormCreateModal) {
                    return;
                }
                jobPositionFormCreateModalLastTrigger = trigger || jobPositionFormCreateModalOpen;
                jobPositionFormCreateModal.hidden = false;
                syncShellModalState();
                const firstField = jobPositionFormCreateModal.querySelector('input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
                setTimeout(() => firstField?.focus(), 0);
            };

            const closeJobPositionFormCreateModal = () => {
                if (!jobPositionFormCreateModal) {
                    return;
                }
                jobPositionFormCreateModal.hidden = true;
                syncShellModalState();
                jobPositionFormCreateModalLastTrigger?.focus?.();
            };

            const jobPositionTourSteps = [
                {
                    target: 'setup-card',
                    title: 'Start with the setup card',
                    body: 'Use this checklist to keep job position setup focused on designations, assignments, and responsibility notes.',
                },
                {
                    target: 'setup-steps',
                    title: 'Follow the position steps',
                    body: 'The active step shows the next useful action, while completed and ready steps keep the workflow compact.',
                },
                {
                    target: 'add-position',
                    title: 'Create a position',
                    body: 'Add Position opens the Job Position modal where admins define the code, name, status, and role details.',
                },
                {
                    target: 'forms-dashboard',
                    title: 'Manage custom forms',
                    body: 'Open the Dashboard Form Builder to edit the Job Position form or start a new form when needed.',
                },
                {
                    target: 'feature-board',
                    title: 'Use feature widgets',
                    body: 'Click or drag a Job Position feature to open the matching modal section for the selected position.',
                },
                {
                    target: 'directory',
                    title: 'Use position tools',
                    body: 'The right panel keeps working shortcuts and the real position form sections together without duplicate or placeholder items.',
                },
            ];
            let jobPositionTourIndex = 0;
            let jobPositionTourLastTrigger = null;

            const jobPositionTourTarget = (targetKey) => document.querySelector(`[data-job-position-tour-target="${targetKey}"]`);

            const clearJobPositionTourHighlight = () => {
                document.querySelectorAll('.yovel-tour-highlight').forEach((element) => {
                    element.classList.remove('yovel-tour-highlight');
                });
            };

            const setJobPositionTourStep = (index) => {
                if (!jobPositionTour) {
                    return;
                }
                jobPositionTourIndex = Math.max(0, Math.min(index, jobPositionTourSteps.length - 1));
                const step = jobPositionTourSteps[jobPositionTourIndex];
                clearJobPositionTourHighlight();
                const target = jobPositionTourTarget(step.target);
                if (target) {
                    target.classList.add('yovel-tour-highlight');
                    target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
                }
                if (jobPositionTourTitle) {
                    jobPositionTourTitle.textContent = step.title;
                }
                if (jobPositionTourBody) {
                    jobPositionTourBody.textContent = step.body;
                }
                if (jobPositionTourCount) {
                    jobPositionTourCount.textContent = `Step ${jobPositionTourIndex + 1} of ${jobPositionTourSteps.length}`;
                }
                if (jobPositionTourBack) {
                    jobPositionTourBack.disabled = jobPositionTourIndex === 0;
                }
                if (jobPositionTourNext) {
                    jobPositionTourNext.textContent = jobPositionTourIndex === jobPositionTourSteps.length - 1 ? 'Finish' : 'Next';
                }
            };

            const openJobPositionTour = (trigger = null) => {
                if (!jobPositionTour) {
                    return;
                }
                jobPositionTourLastTrigger = trigger || document.activeElement;
                jobPositionTour.hidden = false;
                setJobPositionTourStep(0);
                setTimeout(() => jobPositionTourNext?.focus(), 0);
            };

            const closeJobPositionTour = (completed = false) => {
                if (!jobPositionTour) {
                    return;
                }
                jobPositionTour.hidden = true;
                clearJobPositionTourHighlight();
                if (completed) {
                    try {
                        window.localStorage.setItem(jobPositionTourStorageKey, '1');
                    } catch (error) {
                    }
                }
                jobPositionTourLastTrigger?.focus?.();
            };

            let salesLeadModalLastTrigger = salesLeadModalOpen;
            const openSalesLeadModal = (trigger = salesLeadModalOpen) => {
                if (!salesLeadModal) {
                    return;
                }
                salesLeadModalLastTrigger = trigger || salesLeadModalOpen;
                salesLeadModal.hidden = false;
                syncShellModalState();
                setTimeout(() => salesLeadCodeInput?.focus(), 0);
            };
            const closeSalesLeadModal = () => {
                if (!salesLeadModal) {
                    return;
                }
                if (salesLeadModalEditMode) {
                    window.location.href = './?view=sales-crm&section=leads';
                    return;
                }
                salesLeadModal.hidden = true;
                syncShellModalState();
                salesLeadModalLastTrigger?.focus?.();
            };
            let accountModalLastTrigger = accountModalOpen;
            let accountBuilderModalLastTrigger = accountBuilderModalOpen;
            const openAccountModal = (trigger = accountModalOpen) => {
                if (!accountModal) {
                    return;
                }
                accountModalLastTrigger = trigger || accountModalOpen;
                accountModal.hidden = false;
                syncShellModalState();
                setTimeout(() => accountCodeInput?.focus(), 0);
            };
            const closeAccountModal = () => {
                if (!accountModal) {
                    return;
                }
                if (accountModalEditMode) {
                    window.location.href = './?view=accounting-finance&section=chart-of-accounts';
                    return;
                }
                accountModal.hidden = true;
                syncShellModalState();
                accountModalLastTrigger?.focus?.();
            };
            const openAccountBuilderModal = (trigger = accountBuilderModalOpen) => {
                if (!accountBuilderModal) {
                    return;
                }
                accountBuilderModalLastTrigger = trigger || accountBuilderModalOpen;
                accountBuilderModal.hidden = false;
                syncShellModalState();
                const firstField = accountBuilderModal.querySelector('input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
                setTimeout(() => firstField?.focus(), 0);
            };
            const closeAccountBuilderModal = () => {
                if (!accountBuilderModal) {
                    return;
                }
                accountBuilderModal.hidden = true;
                syncShellModalState();
                accountBuilderModalLastTrigger?.focus?.();
            };
            syncShellModalState();
            const syncAccountReportType = () => {
                if (!accountRootType || !accountReportType) {
                    return;
                }
                const root = accountRootType.value;
                accountReportType.value = ['ASSET', 'LIABILITY', 'EQUITY'].includes(root) ? 'BALANCE_SHEET' : 'PROFIT_LOSS';
            };
            financeScrollers.forEach((scroller) => {
                let activePointerId = null;
                let startX = 0;
                let startScrollLeft = 0;
                let didDrag = false;
                let suppressClick = false;
                const stopDrag = (event) => {
                    if (activePointerId !== event.pointerId) {
                        return;
                    }
                    if (didDrag) {
                        suppressClick = true;
                        window.setTimeout(() => {
                            suppressClick = false;
                        }, 0);
                    }
                    scroller.classList.remove('is-dragging');
                    scroller.releasePointerCapture?.(event.pointerId);
                    activePointerId = null;
                    didDrag = false;
                };
                scroller.addEventListener('pointerdown', (event) => {
                    if (event.button !== undefined && event.button !== 0) {
                        return;
                    }
                    activePointerId = event.pointerId;
                    startX = event.clientX;
                    startScrollLeft = scroller.scrollLeft;
                    didDrag = false;
                    scroller.setPointerCapture?.(event.pointerId);
                });
                scroller.addEventListener('pointermove', (event) => {
                    if (activePointerId !== event.pointerId) {
                        return;
                    }
                    const deltaX = event.clientX - startX;
                    if (Math.abs(deltaX) <= 4) {
                        return;
                    }
                    didDrag = true;
                    scroller.classList.add('is-dragging');
                    scroller.scrollLeft = startScrollLeft - deltaX;
                    event.preventDefault();
                });
                scroller.addEventListener('pointerup', stopDrag);
                scroller.addEventListener('pointercancel', stopDrag);
                scroller.addEventListener('click', (event) => {
                    if (!suppressClick) {
                        return;
                    }
                    event.preventDefault();
                    event.stopPropagation();
                }, true);
                scroller.addEventListener('wheel', (event) => {
                    if (scroller.scrollWidth <= scroller.clientWidth || Math.abs(event.deltaY) <= Math.abs(event.deltaX)) {
                        return;
                    }
                    scroller.scrollLeft += event.deltaY;
                    event.preventDefault();
                }, { passive: false });
            });

            const financeBuilderTypeLabels = {
                SHORT_TEXT: 'Short text',
                PARAGRAPH: 'Paragraph',
                NUMBER: 'Number',
                CURRENCY: 'Currency',
                DATE: 'Date',
                DROPDOWN: 'Dropdown',
                CHECKBOXES: 'Checkboxes',
                ACCOUNT: 'Account reference',
                PARTY: 'Party reference',
                SECTION: 'Section header',
            };
            let financeBuilderKeySequence = 0;
            const financeBuilderQuestionKey = (type = 'field') => {
                financeBuilderKeySequence += 1;
                return `${String(type).toLowerCase()}_${Date.now()}_${financeBuilderKeySequence}`;
            };
            const financeBuilderElement = (tag, className = '', textValue = '') => {
                const element = document.createElement(tag);
                if (className) element.className = className;
                if (textValue !== '') element.textContent = textValue;
                return element;
            };

            financeGoogleBuilders.forEach((builder) => {
                const schemaInput = builder.querySelector('[data-finance-builder-schema]');
                const canvas = builder.querySelector('[data-finance-builder-canvas]');
                const settingsContainer = builder.querySelector('[data-finance-builder-settings-container]');
                const countLabel = builder.querySelector('[data-finance-builder-count]');
                const previewButton = builder.querySelector('[data-finance-builder-preview]');
                const previewPanel = builder.querySelector('[data-finance-builder-preview-panel]');
                const previewCanvas = builder.querySelector('[data-finance-builder-preview-canvas]');
                const previewClose = builder.querySelector('[data-finance-builder-preview-close]');
                if (!schemaInput || !canvas || !settingsContainer) {
                    return;
                }

                let schema = { version: 1, questions: [] };
                try {
                    const parsed = JSON.parse(schemaInput.value || '{}');
                    if (parsed && Array.isArray(parsed.questions)) {
                        schema = { version: 1, questions: parsed.questions };
                    }
                } catch (error) {
                    schema = { version: 1, questions: [] };
                }
                schema.questions = schema.questions.map((question, index) => ({
                    key: String(question.key || financeBuilderQuestionKey(question.type)),
                    label: String(question.label || 'Untitled field'),
                    help: String(question.help || ''),
                    type: Object.hasOwn(financeBuilderTypeLabels, question.type) ? question.type : 'SHORT_TEXT',
                    required: Boolean(question.required) && question.type !== 'SECTION',
                    options: Array.isArray(question.options) ? question.options.map(String) : [],
                    precision: Math.max(0, Math.min(6, Number.parseInt(question.precision || 0, 10) || 0)),
                    order: (index + 1) * 10,
                }));
                let selectedKey = schema.questions[0]?.key || '';
                let draggedKey = '';

                const syncSchema = () => {
                    schema.questions.forEach((question, index) => {
                        question.order = (index + 1) * 10;
                    });
                    schemaInput.value = JSON.stringify({ version: 1, questions: schema.questions });
                    if (countLabel) countLabel.textContent = String(schema.questions.length);
                };
                const selectedQuestion = () => schema.questions.find((question) => question.key === selectedKey) || null;
                const setSelected = (key) => {
                    selectedKey = key;
                    renderCanvas();
                    renderSettings();
                };
                const moveQuestion = (key, direction) => {
                    const index = schema.questions.findIndex((question) => question.key === key);
                    const nextIndex = direction === 'up' ? index - 1 : index + 1;
                    if (index < 0 || nextIndex < 0 || nextIndex >= schema.questions.length) {
                        return;
                    }
                    const [question] = schema.questions.splice(index, 1);
                    schema.questions.splice(nextIndex, 0, question);
                    syncSchema();
                    renderCanvas();
                    renderPreview();
                };
                const addQuestion = (type, label) => {
                    const question = {
                        key: financeBuilderQuestionKey(type),
                        label: label || (type === 'SECTION' ? 'Section title' : 'Untitled field'),
                        help: '',
                        type: Object.hasOwn(financeBuilderTypeLabels, type) ? type : 'SHORT_TEXT',
                        required: false,
                        options: ['DROPDOWN', 'CHECKBOXES'].includes(type) ? ['Option 1'] : [],
                        precision: ['NUMBER', 'CURRENCY'].includes(type) ? 2 : 0,
                        order: (schema.questions.length + 1) * 10,
                    };
                    schema.questions.push(question);
                    selectedKey = question.key;
                    syncSchema();
                    renderCanvas();
                    renderSettings();
                    renderPreview();
                };

                const renderCanvas = () => {
                    canvas.replaceChildren();
                    if (!schema.questions.length) {
                        const empty = financeBuilderElement('div', 'rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground', 'Choose a field type to start this Finance form.');
                        empty.dataset.financeBuilderEmpty = '';
                        canvas.appendChild(empty);
                        return;
                    }
                    schema.questions.forEach((question) => {
                        const card = financeBuilderElement('article', 'yovel-finance-builder-question rounded-md border bg-background p-3');
                        card.tabIndex = 0;
                        card.draggable = true;
                        card.dataset.financeBuilderQuestion = '';
                        card.dataset.questionKey = question.key;
                        card.setAttribute('aria-selected', question.key === selectedKey ? 'true' : 'false');
                        const row = financeBuilderElement('div', 'flex items-start justify-between gap-3');
                        const copy = financeBuilderElement('div', 'min-w-0');
                        const title = financeBuilderElement('p', 'truncate text-sm font-semibold', question.label);
                        title.dataset.financeBuilderCardLabel = '';
                        const meta = financeBuilderElement('p', 'mt-1 text-xs text-muted-foreground', `${financeBuilderTypeLabels[question.type] || question.type} · ${question.key}`);
                        copy.append(title, meta);
                        const actions = financeBuilderElement('div', 'flex items-center gap-1');
                        ['up', 'down'].forEach((direction) => {
                            const button = financeBuilderElement('button', 'inline-flex size-7 items-center justify-center rounded-md hover:bg-muted', direction === 'up' ? '↑' : '↓');
                            button.type = 'button';
                            button.dataset.financeBuilderMove = direction;
                            button.setAttribute('aria-label', `Move ${question.label} ${direction}`);
                            actions.appendChild(button);
                        });
                        const grip = financeBuilderElement('span', 'material-symbols-rounded cursor-grab text-base text-muted-foreground', 'drag_indicator');
                        grip.setAttribute('aria-hidden', 'true');
                        actions.appendChild(grip);
                        row.append(copy, actions);
                        card.appendChild(row);
                        card.addEventListener('click', (event) => {
                            const move = event.target.closest('[data-finance-builder-move]');
                            if (move) {
                                event.stopPropagation();
                                moveQuestion(question.key, move.dataset.financeBuilderMove || 'down');
                                return;
                            }
                            setSelected(question.key);
                        });
                        card.addEventListener('keydown', (event) => {
                            if (event.key === 'Enter' || event.key === ' ') {
                                event.preventDefault();
                                setSelected(question.key);
                            }
                        });
                        card.addEventListener('dragstart', (event) => {
                            draggedKey = question.key;
                            event.dataTransfer?.setData('text/plain', question.key);
                        });
                        card.addEventListener('dragover', (event) => event.preventDefault());
                        card.addEventListener('drop', (event) => {
                            event.preventDefault();
                            if (!draggedKey || draggedKey === question.key) return;
                            const from = schema.questions.findIndex((item) => item.key === draggedKey);
                            const to = schema.questions.findIndex((item) => item.key === question.key);
                            if (from < 0 || to < 0) return;
                            const [moved] = schema.questions.splice(from, 1);
                            schema.questions.splice(to, 0, moved);
                            draggedKey = '';
                            syncSchema();
                            renderCanvas();
                            renderPreview();
                        });
                        card.addEventListener('dragend', () => { draggedKey = ''; });
                        canvas.appendChild(card);
                    });
                };

                const propertyField = (labelText, control) => {
                    const group = financeBuilderElement('div', 'grid gap-1.5');
                    const label = financeBuilderElement('label', 'text-xs font-medium', labelText);
                    group.append(label, control);
                    return group;
                };
                const renderSettings = () => {
                    settingsContainer.replaceChildren();
                    const question = selectedQuestion();
                    if (!question) {
                        settingsContainer.appendChild(financeBuilderElement('div', 'rounded-md border border-dashed p-4 text-sm text-muted-foreground', 'No field selected.'));
                        return;
                    }
                    const panel = financeBuilderElement('section', 'grid gap-3');
                    panel.dataset.financeBuilderSettingsPanel = '';
                    panel.dataset.questionKey = question.key;
                    const labelInput = financeBuilderElement('input', 'h-9 rounded-md border bg-background px-3 text-sm');
                    labelInput.value = question.label;
                    labelInput.maxLength = 180;
                    const keyInput = financeBuilderElement('input', 'h-9 rounded-md border bg-background px-3 text-sm');
                    keyInput.value = question.key;
                    keyInput.maxLength = 120;
                    keyInput.pattern = '[A-Za-z0-9][A-Za-z0-9_.:-]{0,119}';
                    const typeSelect = financeBuilderElement('select', 'h-9 rounded-md border bg-background px-3 text-sm');
                    Object.entries(financeBuilderTypeLabels).forEach(([value, label]) => {
                        const option = financeBuilderElement('option', '', label);
                        option.value = value;
                        option.selected = question.type === value;
                        typeSelect.appendChild(option);
                    });
                    const helpInput = financeBuilderElement('textarea', 'min-h-20 rounded-md border bg-background px-3 py-2 text-sm');
                    helpInput.value = question.help;
                    helpInput.maxLength = 1000;
                    const optionsInput = financeBuilderElement('textarea', 'min-h-20 rounded-md border bg-background px-3 py-2 text-sm');
                    optionsInput.value = question.options.join('\n');
                    const precisionInput = financeBuilderElement('input', 'h-9 rounded-md border bg-background px-3 text-sm');
                    precisionInput.type = 'number';
                    precisionInput.min = '0';
                    precisionInput.max = '6';
                    precisionInput.value = String(question.precision);
                    const requiredLabel = financeBuilderElement('label', 'inline-flex items-center gap-2 text-sm');
                    const requiredInput = document.createElement('input');
                    requiredInput.type = 'checkbox';
                    requiredInput.checked = question.required;
                    requiredInput.disabled = question.type === 'SECTION';
                    requiredLabel.append(requiredInput, document.createTextNode('Required'));
                    panel.append(
                        propertyField('Field label', labelInput),
                        propertyField('Field key', keyInput),
                        propertyField('Field type', typeSelect),
                        propertyField('Help text', helpInput),
                        propertyField('Options, one per line', optionsInput),
                        propertyField('Decimal precision', precisionInput),
                        requiredLabel
                    );
                    const actions = financeBuilderElement('div', 'flex flex-wrap gap-2 border-t pt-3');
                    const duplicate = financeBuilderElement('button', 'inline-flex h-8 items-center rounded-md border px-2 text-xs font-medium hover:bg-muted', 'Duplicate');
                    duplicate.type = 'button';
                    duplicate.dataset.financeBuilderDuplicate = '';
                    const remove = financeBuilderElement('button', 'inline-flex h-8 items-center rounded-md border border-destructive/40 px-2 text-xs font-medium text-destructive hover:bg-destructive/10', 'Delete');
                    remove.type = 'button';
                    remove.dataset.financeBuilderDelete = '';
                    actions.append(duplicate, remove);
                    panel.appendChild(actions);
                    settingsContainer.appendChild(panel);

                    labelInput.addEventListener('input', () => {
                        question.label = labelInput.value;
                        syncSchema();
                        const cardLabel = canvas.querySelector(`[data-question-key="${CSS.escape(question.key)}"] [data-finance-builder-card-label]`);
                        if (cardLabel) cardLabel.textContent = question.label || 'Untitled field';
                        renderPreview();
                    });
                    keyInput.addEventListener('change', () => {
                        const nextKey = keyInput.value.trim();
                        if (!nextKey || schema.questions.some((item) => item !== question && item.key === nextKey)) {
                            keyInput.setCustomValidity('Field keys must be unique.');
                            keyInput.reportValidity();
                            return;
                        }
                        keyInput.setCustomValidity('');
                        question.key = nextKey;
                        selectedKey = nextKey;
                        syncSchema();
                        renderCanvas();
                        renderSettings();
                    });
                    typeSelect.addEventListener('change', () => {
                        question.type = typeSelect.value;
                        if (!['DROPDOWN', 'CHECKBOXES'].includes(question.type)) question.options = [];
                        if (!['NUMBER', 'CURRENCY'].includes(question.type)) question.precision = 0;
                        if (question.type === 'SECTION') question.required = false;
                        syncSchema();
                        renderCanvas();
                        renderSettings();
                        renderPreview();
                    });
                    helpInput.addEventListener('input', () => { question.help = helpInput.value; syncSchema(); renderPreview(); });
                    optionsInput.addEventListener('input', () => { question.options = optionsInput.value.split(/\r?\n/).map((option) => option.trim()).filter(Boolean); syncSchema(); renderPreview(); });
                    precisionInput.addEventListener('input', () => { question.precision = Math.max(0, Math.min(6, Number.parseInt(precisionInput.value || '0', 10) || 0)); syncSchema(); });
                    requiredInput.addEventListener('change', () => { question.required = requiredInput.checked && question.type !== 'SECTION'; syncSchema(); renderPreview(); });
                    duplicate.addEventListener('click', () => {
                        const index = schema.questions.indexOf(question);
                        const copy = { ...question, key: financeBuilderQuestionKey(question.type), label: `${question.label} copy`, options: [...question.options] };
                        schema.questions.splice(index + 1, 0, copy);
                        selectedKey = copy.key;
                        syncSchema();
                        renderCanvas();
                        renderSettings();
                        renderPreview();
                    });
                    remove.addEventListener('click', () => {
                        const index = schema.questions.indexOf(question);
                        schema.questions.splice(index, 1);
                        selectedKey = schema.questions[Math.min(index, schema.questions.length - 1)]?.key || '';
                        syncSchema();
                        renderCanvas();
                        renderSettings();
                        renderPreview();
                    });
                };

                const renderPreview = () => {
                    if (!previewCanvas) return;
                    previewCanvas.replaceChildren();
                    schema.questions.forEach((question) => {
                        if (question.type === 'SECTION') {
                            const heading = financeBuilderElement('h6', 'border-b pb-2 text-sm font-semibold md:col-span-2', question.label);
                            previewCanvas.appendChild(heading);
                            return;
                        }
                        const group = financeBuilderElement('div', 'grid gap-1.5');
                        const label = financeBuilderElement('label', 'text-xs font-medium', `${question.label}${question.required ? ' *' : ''}`);
                        let control;
                        if (question.type === 'PARAGRAPH') {
                            control = financeBuilderElement('textarea', 'min-h-20 rounded-md border bg-background px-3 py-2 text-sm');
                        } else if (question.type === 'DROPDOWN') {
                            control = financeBuilderElement('select', 'h-9 rounded-md border bg-background px-3 text-sm');
                            question.options.forEach((value) => {
                                const option = financeBuilderElement('option', '', value);
                                control.appendChild(option);
                            });
                        } else if (question.type === 'CHECKBOXES') {
                            control = financeBuilderElement('div', 'grid gap-1 text-sm', question.options.join(', ') || 'Checkbox options');
                        } else {
                            control = financeBuilderElement('input', 'h-9 rounded-md border bg-background px-3 text-sm');
                            control.type = question.type === 'DATE' ? 'date' : ['NUMBER', 'CURRENCY'].includes(question.type) ? 'number' : 'text';
                        }
                        if ('disabled' in control) control.disabled = true;
                        group.append(label, control);
                        if (question.help) group.appendChild(financeBuilderElement('p', 'text-xs text-muted-foreground', question.help));
                        previewCanvas.appendChild(group);
                    });
                    if (!schema.questions.length) {
                        previewCanvas.appendChild(financeBuilderElement('p', 'text-sm text-muted-foreground md:col-span-2', 'Add fields to preview this Finance form.'));
                    }
                };

                builder.querySelectorAll('[data-finance-builder-preset]').forEach((button) => {
                    button.addEventListener('click', () => addQuestion(button.dataset.financeBuilderPreset || 'SHORT_TEXT', button.dataset.financeBuilderPresetLabel || 'Untitled field'));
                });
                previewButton?.addEventListener('click', () => {
                    renderPreview();
                    if (previewPanel) previewPanel.hidden = false;
                });
                previewClose?.addEventListener('click', () => { if (previewPanel) previewPanel.hidden = true; });
                builder.addEventListener('submit', syncSchema);
                syncSchema();
                renderCanvas();
                renderSettings();
                renderPreview();
            });
            const accountBuilderItems = () => accountFormBuilder ? Array.from(accountFormBuilder.querySelectorAll('[data-account-field]')) : [];
            const syncAccountBuilderSort = () => {
                accountBuilderItems().forEach((item, index) => {
                    const sortInput = item.querySelector('input[name$="[sortOrder]"]');
                    if (sortInput) {
                        sortInput.value = String((index + 1) * 10);
                    }
                });
            };
            const moveAccountBuilderItem = (button, direction) => {
                const item = button.closest('[data-account-field]');
                if (!item || !accountFormBuilder) {
                    return;
                }
                if (direction === 'up' && item.previousElementSibling) {
                    accountFormBuilder.insertBefore(item, item.previousElementSibling);
                }
                if (direction === 'down' && item.nextElementSibling) {
                    accountFormBuilder.insertBefore(item.nextElementSibling, item);
                }
                syncAccountBuilderSort();
            };

	            employeeModalOpen?.addEventListener('click', () => openEmployeeModal('overview', employeeModalOpen));
	            hrFormBuilderModalOpen?.addEventListener('click', () => openHrFormBuilderModal(hrFormBuilderModalOpen));
	            hrFormCreateModalOpen?.addEventListener('click', () => openHrFormCreateModal(hrFormCreateModalOpen));
            departmentModalOpen?.addEventListener('click', () => openDepartmentModal('overview', departmentModalOpen));
            departmentFormBuilderModalOpen?.addEventListener('click', () => openDepartmentFormBuilderModal(departmentFormBuilderModalOpen));
            departmentFormCreateModalOpen?.addEventListener('click', () => openDepartmentFormCreateModal(departmentFormCreateModalOpen));
            jobPositionModalOpen?.addEventListener('click', () => openJobPositionModal('overview', jobPositionModalOpen));
            jobPositionFormBuilderModalOpen?.addEventListener('click', () => openJobPositionFormBuilderModal(jobPositionFormBuilderModalOpen));
            jobPositionFormCreateModalOpen?.addEventListener('click', () => openJobPositionFormCreateModal(jobPositionFormCreateModalOpen));
	            salesLeadModalOpen?.addEventListener('click', () => openSalesLeadModal(salesLeadModalOpen));
            accountModalOpen?.addEventListener('click', () => openAccountModal(accountModalOpen));
            accountModalOpenSecondary?.addEventListener('click', () => openAccountModal(accountModalOpenSecondary));
            accountBuilderModalOpen?.addEventListener('click', () => openAccountBuilderModal(accountBuilderModalOpen));
            accountRootType?.addEventListener('change', syncAccountReportType);
            [employeeFirstNameInput, employeeMiddleNameInput, employeeLastNameInput].forEach((input) => {
                input?.addEventListener('input', syncEmployeeFullName);
            });
            employeeFeatureButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    openEmployeeSectionFromFeature(button.dataset.employeeSectionTarget || 'overview', button);
                });
            });
            const employeeModalTabs = employeeFeatureButtons.filter((button) => button.getAttribute('role') === 'tab');
            employeeModalTabs.forEach((button, index) => {
                button.addEventListener('keydown', (event) => {
                    if (!['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) {
                        return;
                    }
                    event.preventDefault();
                    let nextIndex = index;
                    if (event.key === 'ArrowRight') nextIndex = (index + 1) % employeeModalTabs.length;
                    if (event.key === 'ArrowLeft') nextIndex = (index - 1 + employeeModalTabs.length) % employeeModalTabs.length;
                    if (event.key === 'Home') nextIndex = 0;
                    if (event.key === 'End') nextIndex = employeeModalTabs.length - 1;
                    employeeModalTabs[nextIndex]?.focus();
                    employeeModalTabs[nextIndex]?.click();
                });
            });
            departmentFeatureButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    openDepartmentSectionFromFeature(button.dataset.departmentSectionTarget || 'overview', button);
                });
            });
            jobPositionFeatureButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    openJobPositionSectionFromFeature(button.dataset.jobPositionSectionTarget || 'overview', button);
                });
            });
            document.querySelectorAll('[data-job-position-tour-start]').forEach((button) => {
                button.addEventListener('click', () => openJobPositionTour(button));
            });
            document.querySelectorAll('[data-job-position-open-from-setup], [data-job-position-open-shortcut]').forEach((button) => {
                button.addEventListener('click', () => openJobPositionModal('overview', button));
            });
            document.querySelectorAll('[data-job-position-customize-shortcut]').forEach((button) => {
                button.addEventListener('click', () => openJobPositionFormBuilderModal(button));
            });
            document.querySelectorAll('[data-job-position-create-form-shortcut]').forEach((button) => {
                button.addEventListener('click', () => openJobPositionFormCreateModal(button));
            });
            jobPositionSetupDismiss?.addEventListener('click', () => {
                if (jobPositionSetupCard) {
                    jobPositionSetupCard.hidden = true;
                }
                try {
                    window.localStorage.setItem(jobPositionSetupStorageKey, '1');
                } catch (error) {
                }
            });
            jobPositionTourClose?.addEventListener('click', () => closeJobPositionTour(false));
            jobPositionTourSkip?.addEventListener('click', () => closeJobPositionTour(true));
            jobPositionTourBack?.addEventListener('click', () => setJobPositionTourStep(jobPositionTourIndex - 1));
            jobPositionTourNext?.addEventListener('click', () => {
                if (jobPositionTourIndex >= jobPositionTourSteps.length - 1) {
                    closeJobPositionTour(true);
                    return;
                }
                setJobPositionTourStep(jobPositionTourIndex + 1);
            });
	            employeeModalClose?.addEventListener('click', closeEmployeeModal);
	            employeeModalCancel?.addEventListener('click', closeEmployeeModal);
	            employeeModal?.addEventListener('click', (event) => {
	                if (event.target === employeeModal) {
	                    closeEmployeeModal();
	                }
	            });
            departmentModalClose?.addEventListener('click', closeDepartmentModal);
            departmentModalCancel?.addEventListener('click', closeDepartmentModal);
            departmentModal?.addEventListener('click', (event) => {
                if (event.target === departmentModal) {
                    closeDepartmentModal();
                }
            });
            jobPositionModalClose?.addEventListener('click', closeJobPositionModal);
            jobPositionModalCancel?.addEventListener('click', closeJobPositionModal);
            jobPositionModal?.addEventListener('click', (event) => {
                if (event.target === jobPositionModal) {
                    closeJobPositionModal();
                }
            });
	            hrFormBuilderModalClose?.addEventListener('click', closeHrFormBuilderModal);
	            hrFormBuilderModalCancel?.addEventListener('click', closeHrFormBuilderModal);
	            hrFormBuilderModal?.addEventListener('click', (event) => {
	                if (event.target === hrFormBuilderModal) {
	                    closeHrFormBuilderModal();
	                }
	            });
	            hrFormCreateModalClose?.addEventListener('click', closeHrFormCreateModal);
	            hrFormCreateModalCancel?.addEventListener('click', closeHrFormCreateModal);
	            hrFormCreateModal?.addEventListener('click', (event) => {
	                if (event.target === hrFormCreateModal) {
	                    closeHrFormCreateModal();
	                }
	            });
            departmentFormBuilderModalClose?.addEventListener('click', closeDepartmentFormBuilderModal);
            departmentFormBuilderModalCancel?.addEventListener('click', closeDepartmentFormBuilderModal);
            departmentFormBuilderModal?.addEventListener('click', (event) => {
                if (event.target === departmentFormBuilderModal) {
                    closeDepartmentFormBuilderModal();
                }
            });
            departmentFormCreateModalClose?.addEventListener('click', closeDepartmentFormCreateModal);
            departmentFormCreateModalCancel?.addEventListener('click', closeDepartmentFormCreateModal);
            departmentFormCreateModal?.addEventListener('click', (event) => {
                if (event.target === departmentFormCreateModal) {
                    closeDepartmentFormCreateModal();
                }
            });
            jobPositionFormBuilderModalClose?.addEventListener('click', closeJobPositionFormBuilderModal);
            jobPositionFormBuilderModalCancel?.addEventListener('click', closeJobPositionFormBuilderModal);
            jobPositionFormBuilderModal?.addEventListener('click', (event) => {
                if (event.target === jobPositionFormBuilderModal) {
                    closeJobPositionFormBuilderModal();
                }
            });
            jobPositionFormCreateModalClose?.addEventListener('click', closeJobPositionFormCreateModal);
            jobPositionFormCreateModalCancel?.addEventListener('click', closeJobPositionFormCreateModal);
            jobPositionFormCreateModal?.addEventListener('click', (event) => {
                if (event.target === jobPositionFormCreateModal) {
                    closeJobPositionFormCreateModal();
                }
            });
            const slugifyHrBuilderField = (value) => (value || '')
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '')
                .replace(/^[^a-z]+/, '')
                .slice(0, 80);

            document.querySelectorAll('[data-hr-form-builder]').forEach((builder) => {
                const addForm = builder.querySelector('[data-hr-builder-add-form]');
                const addLabel = addForm?.querySelector('[name="field_label"]');
                const addName = addForm?.querySelector('[name="field_name"]');
                const addType = addForm?.querySelector('[name="field_type"]');
                const addSection = addForm?.querySelector('[name="field_section"]');
                const addOptions = addForm?.querySelector('[name="field_options"]');
                const addPlaceholder = addForm?.querySelector('[name="field_placeholder"]');
                const settingPanels = Array.from(builder.querySelectorAll('[data-hr-builder-settings-panel]'));
                const fieldCards = Array.from(builder.querySelectorAll('[data-hr-builder-field-card]'));

                const showNewFieldSettings = () => {
                    if (addForm) {
                        addForm.hidden = false;
                    }
                    settingPanels.forEach((panel) => {
                        panel.hidden = true;
                    });
                    fieldCards.forEach((card) => {
                        card.setAttribute('aria-selected', 'false');
                    });
                };

                const showFieldSettings = (fieldKey) => {
                    if (addForm) {
                        addForm.hidden = true;
                    }
                    settingPanels.forEach((panel) => {
                        panel.hidden = panel.dataset.hrBuilderSettingsPanel !== fieldKey;
                    });
                    fieldCards.forEach((card) => {
                        card.setAttribute('aria-selected', card.dataset.hrBuilderFieldCard === fieldKey ? 'true' : 'false');
                    });
                };

                fieldCards.forEach((card) => {
                    card.addEventListener('click', () => showFieldSettings(card.dataset.hrBuilderFieldCard || ''));
                    card.addEventListener('dragstart', (event) => {
                        const fieldKey = card.dataset.hrBuilderFieldCard || '';
                        card.dataset.hrBuilderDragging = 'true';
                        if (event.dataTransfer) {
                            event.dataTransfer.effectAllowed = 'move';
                            event.dataTransfer.setData('text/plain', fieldKey);
                        }
                    });
                    card.addEventListener('dragend', () => {
                        card.removeAttribute('data-hr-builder-dragging');
                        builder.querySelectorAll('[data-hr-builder-drop-active]').forEach((section) => {
                            section.removeAttribute('data-hr-builder-drop-active');
                        });
                    });
                });

                builder.querySelectorAll('[data-hr-builder-canvas-section]').forEach((section) => {
                    section.addEventListener('dragover', (event) => {
                        event.preventDefault();
                        section.dataset.hrBuilderDropActive = 'true';
                        if (event.dataTransfer) {
                            event.dataTransfer.dropEffect = 'move';
                        }
                    });
                    section.addEventListener('dragleave', (event) => {
                        if (!section.contains(event.relatedTarget)) {
                            section.removeAttribute('data-hr-builder-drop-active');
                        }
                    });
                    section.addEventListener('drop', (event) => {
                        event.preventDefault();
                        section.removeAttribute('data-hr-builder-drop-active');
                        const fieldKey = event.dataTransfer?.getData('text/plain') || '';
                        const card = fieldCards.find((candidate) => candidate.dataset.hrBuilderFieldCard === fieldKey);
                        const list = section.querySelector('.yovel-hr-builder-field-list');
                        if (!fieldKey || !card || !list) {
                            return;
                        }
                        list.appendChild(card);
                        const panel = settingPanels.find((candidate) => candidate.dataset.hrBuilderSettingsPanel === fieldKey);
                        const sectionKey = section.dataset.hrBuilderCanvasSection || 'overview';
                        const sectionInput = panel?.querySelector('[name="field_section"]');
                        const sortInput = panel?.querySelector('[name="sort_order"]');
                        if (sectionInput) {
                            sectionInput.value = sectionKey;
                        }
                        if (sortInput) {
                            sortInput.value = String((Array.from(list.children).indexOf(card) + 1) * 10);
                        }
                        showFieldSettings(fieldKey);
                    });
                });

                builder.querySelectorAll('[data-hr-builder-preset]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const type = button.dataset.hrBuilderPreset || 'TEXT';
                        const label = button.dataset.hrBuilderPresetLabel || 'Custom Field';
                        showNewFieldSettings();
                        if (addType) {
                            addType.value = type;
                        }
                        if (addLabel && addLabel.value.trim() === '') {
                            addLabel.value = label;
                        }
                        if (addName && addName.value.trim() === '') {
                            addName.value = slugifyHrBuilderField(label) || 'custom_field';
                        }
                        if (addOptions && type === 'SELECT' && addOptions.value.trim() === '') {
                            addOptions.value = "Option 1\nOption 2\nOption 3";
                        }
                        if (addPlaceholder && addPlaceholder.value.trim() === '') {
                            addPlaceholder.value = type === 'SELECT' ? 'Select option' : 'Enter value';
                        }
                        addLabel?.focus();
                    });
                });

                builder.querySelectorAll('[data-hr-builder-section]').forEach((button) => {
                    button.addEventListener('click', () => {
                        showNewFieldSettings();
                        if (addSection) {
                            addSection.value = button.dataset.hrBuilderSection || 'overview';
                        }
                        addLabel?.focus();
                    });
                });

                addLabel?.addEventListener('input', () => {
                    if (addName && addName.value.trim() === '') {
                        addName.value = slugifyHrBuilderField(addLabel.value);
                    }
                });
            });

	            document.querySelectorAll('[data-hr-builder-target]').forEach((button) => {
	                button.addEventListener('click', () => {
	                    const targetId = button.dataset.hrBuilderTarget || '';
	                    const target = document.getElementById(targetId);
	                    if (!target) {
	                        return;
	                    }
	                    const modal = button.closest('.yovel-form-builder-modal') || document;
	                    modal.querySelectorAll('[data-hr-builder-panel]').forEach((panel) => {
	                        panel.hidden = panel.id !== targetId;
	                    });
	                    modal.querySelectorAll('[data-hr-builder-target]').forEach((navButton) => navButton.removeAttribute('aria-current'));
	                    button.setAttribute('aria-current', 'true');
	                    const modalBody = modal.querySelector('.yovel-hr-panel-body');
	                    if (modalBody) {
	                        modalBody.scrollTop = 0;
	                    }
	                    const firstField = target.querySelector('input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
	                    setTimeout(() => firstField?.focus(), 0);
	                });
	            });
	            salesLeadModalClose?.addEventListener('click', closeSalesLeadModal);
            salesLeadModalCancel?.addEventListener('click', closeSalesLeadModal);
            salesLeadModal?.addEventListener('click', (event) => {
                if (event.target === salesLeadModal) {
                    closeSalesLeadModal();
                }
            });
            accountModalClose?.addEventListener('click', closeAccountModal);
            accountModalCancel?.addEventListener('click', closeAccountModal);
            accountModal?.addEventListener('click', (event) => {
                if (event.target === accountModal) {
                    closeAccountModal();
                }
            });
            accountBuilderModalClose?.addEventListener('click', closeAccountBuilderModal);
            accountBuilderModalCancel?.addEventListener('click', closeAccountBuilderModal);
            accountBuilderModal?.addEventListener('click', (event) => {
                if (event.target === accountBuilderModal) {
                    closeAccountBuilderModal();
                }
            });
            if (employeeModal && !employeeModal.hidden) {
                openEmployeeModal(employeeInitialSection, employeeModalOpen);
            } else {
                setEmployeeModalSection('overview');
            }
            if (departmentModal && !departmentModal.hidden) {
                openDepartmentModal(departmentInitialSection, departmentModalOpen);
            } else {
                setDepartmentModalSection('overview');
            }
            if (jobPositionModal && !jobPositionModal.hidden) {
                openJobPositionModal(jobPositionInitialSection, jobPositionModalOpen);
            } else {
                setJobPositionModalSection('overview');
            }
            try {
                if (jobPositionSetupCard && window.localStorage.getItem(jobPositionSetupStorageKey) === '1') {
                    jobPositionSetupCard.hidden = true;
                }
            } catch (error) {
            }
            if (salesLeadModal && !salesLeadModal.hidden) {
                setTimeout(() => salesLeadCodeInput?.focus(), 0);
            }
            if (accountModal && !accountModal.hidden) {
                setTimeout(() => accountCodeInput?.focus(), 0);
            }
            syncAccountReportType();

            const salesBuilderItems = () => salesFormBuilder ? Array.from(salesFormBuilder.querySelectorAll('[data-sales-field]')) : [];
            const syncSalesSchemaJson = () => {
                if (!salesSchemaForm || !salesFormBuilder) {
                    return;
                }
                const schemaInput = salesSchemaForm.querySelector('input[name="schema_json"]');
                const recordType = salesSchemaForm.querySelector('input[name="record_type"]')?.value || 'lead';
                const fields = salesBuilderItems().map((item, index) => {
                    let field = {};
                    try {
                        field = JSON.parse(item.dataset.salesField || '{}');
                    } catch (error) {
                        field = {};
                    }
                    return {
                        ...field,
                        label: item.querySelector('[data-sales-field-label]')?.value || field.label || field.key,
                        section: item.querySelector('[data-sales-field-section]')?.value || field.section || 'overview',
                        required: item.querySelector('[data-sales-field-required]')?.checked || false,
                        visible: item.querySelector('[data-sales-field-visible]')?.checked || false,
                        width: item.querySelector('[data-sales-field-width]')?.value || field.width || 'half',
                        sortOrder: (index + 1) * 10,
                    };
                });
                if (schemaInput) {
                    schemaInput.value = JSON.stringify({ recordType, fields });
                }
            };
            const moveSalesBuilderItem = (button, direction) => {
                const item = button.closest('[data-sales-field]');
                if (!item || !salesFormBuilder) {
                    return;
                }
                if (direction === 'up' && item.previousElementSibling) {
                    salesFormBuilder.insertBefore(item, item.previousElementSibling);
                }
                if (direction === 'down' && item.nextElementSibling) {
                    salesFormBuilder.insertBefore(item.nextElementSibling, item);
                }
                syncSalesSchemaJson();
            };
            if (salesFormBuilder) {
                let draggedSalesField = null;
                const getSalesFieldAfter = (y) => salesBuilderItems()
                    .filter((item) => item !== draggedSalesField)
                    .reduce((closest, item) => {
                        const box = item.getBoundingClientRect();
                        const offset = y - box.top - box.height / 2;
                        if (offset < 0 && offset > closest.offset) {
                            return { offset, element: item };
                        }
                        return closest;
                    }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
                salesBuilderItems().forEach((item) => {
                    item.addEventListener('dragstart', (event) => {
                        draggedSalesField = item;
                        event.dataTransfer.effectAllowed = 'move';
                    });
                    item.addEventListener('dragend', () => {
                        draggedSalesField = null;
                        syncSalesSchemaJson();
                    });
                });
                salesFormBuilder.addEventListener('dragover', (event) => {
                    if (!draggedSalesField) {
                        return;
                    }
                    event.preventDefault();
                    const after = getSalesFieldAfter(event.clientY);
                    if (after) {
                        salesFormBuilder.insertBefore(draggedSalesField, after);
                    } else {
                        salesFormBuilder.appendChild(draggedSalesField);
                    }
                });
                salesFormBuilder.querySelectorAll('.yovel-sales-field-up').forEach((button) => button.addEventListener('click', () => moveSalesBuilderItem(button, 'up')));
                salesFormBuilder.querySelectorAll('.yovel-sales-field-down').forEach((button) => button.addEventListener('click', () => moveSalesBuilderItem(button, 'down')));
                salesFormBuilder.addEventListener('input', syncSalesSchemaJson);
                salesFormBuilder.addEventListener('change', syncSalesSchemaJson);
                syncSalesSchemaJson();
            }

            if (accountFormBuilder) {
                let draggedAccountField = null;
                const getAccountFieldAfter = (y) => accountBuilderItems()
                    .filter((item) => item !== draggedAccountField)
                    .reduce((closest, item) => {
                        const box = item.getBoundingClientRect();
                        const offset = y - box.top - box.height / 2;
                        if (offset < 0 && offset > closest.offset) {
                            return { offset, element: item };
                        }
                        return closest;
                    }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
                accountBuilderItems().forEach((item) => {
                    item.addEventListener('dragstart', (event) => {
                        draggedAccountField = item;
                        event.dataTransfer.effectAllowed = 'move';
                    });
                    item.addEventListener('dragend', () => {
                        draggedAccountField = null;
                        syncAccountBuilderSort();
                    });
                });
                accountFormBuilder.addEventListener('dragover', (event) => {
                    if (!draggedAccountField) {
                        return;
                    }
                    event.preventDefault();
                    const after = getAccountFieldAfter(event.clientY);
                    if (after) {
                        accountFormBuilder.insertBefore(draggedAccountField, after);
                    } else {
                        accountFormBuilder.appendChild(draggedAccountField);
                    }
                });
                accountFormBuilder.querySelectorAll('.yovel-account-field-up').forEach((button) => button.addEventListener('click', () => moveAccountBuilderItem(button, 'up')));
                accountFormBuilder.querySelectorAll('.yovel-account-field-down').forEach((button) => button.addEventListener('click', () => moveAccountBuilderItem(button, 'down')));
                accountFormBuilder.addEventListener('change', syncAccountBuilderSort);
            }

            hrGoogleBuilders.forEach((builder) => {
                const rowList = builder.querySelector('[data-hr-google-row-list]');
                const buildPanel = builder.querySelector('[data-hr-google-build-panel]');
                const previewPanel = builder.querySelector('[data-hr-google-preview-panel]');
                const modeButtons = Array.from(builder.querySelectorAll('[data-hr-google-mode]'));
                const schemaInput = builder.querySelector('[data-hr-google-schema-json]');
                let initialSchema = { version: 2, questions: [], rows: [] };
                try {
                    const parsedSchema = JSON.parse(schemaInput?.value || '{}');
                    if (parsedSchema && Array.isArray(parsedSchema.questions)) {
                        initialSchema = parsedSchema;
                    }
                } catch (error) {
                    initialSchema = { version: 2, questions: [], rows: [] };
                }
                const initialQuestions = new Map(initialSchema.questions.map((question) => [question.key, question]));
                const initialRows = Array.isArray(initialSchema.rows) ? initialSchema.rows : [];
                initialRows.forEach((row) => rowList?.appendChild(createHrGoogleRow(builder, row, initialQuestions)));
                let toolboxDragState = null;
                let toolboxGhost = null;
                let toolboxInsertionMarker = null;

                const setMode = (mode) => {
                    const isPreview = mode === 'preview';
                    if (buildPanel) {
                        buildPanel.hidden = isPreview;
                    }
                    if (previewPanel) {
                        previewPanel.hidden = !isPreview;
                    }
                    modeButtons.forEach((button) => {
                        const active = button.dataset.hrGoogleMode === mode;
                        button.setAttribute('aria-pressed', active ? 'true' : 'false');
                        button.classList.toggle('bg-primary', active);
                        button.classList.toggle('text-primary-foreground', active);
                    });
                    if (isPreview) {
                        renderHrGooglePreview(builder);
                    }
                };

                const selectColumn = (column) => {
                    builder.querySelectorAll('[data-hr-google-column]').forEach((candidate) => {
                        candidate.setAttribute('aria-selected', candidate === column ? 'true' : 'false');
                    });
                };

                const addRow = (columnCount = 1, afterRow = null) => {
                    if (!rowList) {
                        return null;
                    }
                    const row = createHrGoogleRow(builder);
                    syncHrGoogleRow(builder, row, columnCount);
                    if (afterRow) {
                        afterRow.insertAdjacentElement('afterend', row);
                    } else {
                        rowList.appendChild(row);
                    }
                    const firstColumn = row.querySelector('[data-hr-google-column]');
                    if (firstColumn) {
                        selectColumn(firstColumn);
                    }
                    serializeHrGoogleBuilder(builder);
                    return row;
                };

                const selectedOrFallbackColumn = () => {
                    const selected = builder.querySelector('[data-hr-google-column][aria-selected="true"]');
                    if (selected) {
                        return selected;
                    }
                    const columns = Array.from(builder.querySelectorAll('[data-hr-google-column]'));
                    if (columns.length > 0) {
                        return columns[columns.length - 1];
                    }
                    return addRow(1)?.querySelector('[data-hr-google-column]') || null;
                };

                const questionValuesForType = (type) => ({
                    type,
                    label: type === 'SECTION' ? 'New section' : 'Untitled question',
                    help: '',
                    options: type === 'DROPDOWN' || type === 'CHECKBOXES' ? ['Option 1'] : [],
                    required: false,
                });

                const clearToolboxDrag = () => {
                    builder.removeAttribute('data-hr-google-toolbox-dragging');
                    toolboxDragState?.toolItem?.removeAttribute('data-hr-google-dragging');
                    builder.querySelectorAll('[data-hr-google-drop-active]').forEach((element) => delete element.dataset.hrGoogleDropActive);
                    toolboxGhost?.remove();
                    toolboxInsertionMarker?.remove();
                    toolboxGhost = null;
                    toolboxInsertionMarker = null;
                    toolboxDragState = null;
                };

                const moveToolboxDrag = (event) => {
                    if (!toolboxDragState || toolboxDragState.pointerId !== event.pointerId) {
                        return false;
                    }
                    const distance = Math.hypot(event.clientX - toolboxDragState.startX, event.clientY - toolboxDragState.startY);
                    if (!toolboxDragState.active && distance < 6) {
                        return true;
                    }
                    event.preventDefault();
                    if (!toolboxDragState.active) {
                        toolboxDragState.active = true;
                        builder.dataset.hrGoogleToolboxDragging = 'true';
                        toolboxDragState.toolItem?.setAttribute('data-hr-google-dragging', 'true');
                        toolboxGhost = document.createElement('div');
                        toolboxGhost.className = 'yovel-hr-google-tool-ghost';
                        const ghostIcon = document.createElement('span');
                        ghostIcon.className = 'material-symbols-rounded text-base';
                        ghostIcon.setAttribute('aria-hidden', 'true');
                        ghostIcon.textContent = 'drag_indicator';
                        const ghostLabel = document.createElement('span');
                        ghostLabel.textContent = toolboxDragState.label;
                        toolboxGhost.append(ghostIcon, ghostLabel);
                        document.body.appendChild(toolboxGhost);
                        toolboxInsertionMarker = document.createElement('div');
                        toolboxInsertionMarker.className = 'yovel-hr-google-insertion-marker';
                        toolboxInsertionMarker.setAttribute('aria-hidden', 'true');
                    }
                    if (toolboxGhost) {
                        toolboxGhost.style.left = `${event.clientX}px`;
                        toolboxGhost.style.top = `${event.clientY}px`;
                    }
                    const pointerTarget = document.elementFromPoint(event.clientX, event.clientY);
                    const destinationColumn = pointerTarget instanceof Element ? pointerTarget.closest('[data-hr-google-column]') : null;
                    builder.querySelectorAll('[data-hr-google-drop-active]').forEach((element) => delete element.dataset.hrGoogleDropActive);
                    toolboxInsertionMarker?.remove();
                    toolboxDragState.targetColumn = destinationColumn;
                    if (!destinationColumn || !toolboxInsertionMarker) {
                        return true;
                    }
                    destinationColumn.dataset.hrGoogleDropActive = 'true';
                    const cards = Array.from(destinationColumn.querySelectorAll(':scope > [data-hr-google-question]'));
                    const beforeCard = cards.find((candidate) => event.clientY < candidate.getBoundingClientRect().top + candidate.getBoundingClientRect().height / 2);
                    destinationColumn.insertBefore(toolboxInsertionMarker, beforeCard || null);
                    return true;
                };

                const finishToolboxDrag = (event) => {
                    if (!toolboxDragState || toolboxDragState.pointerId !== event.pointerId) {
                        return false;
                    }
                    const releaseDistance = Math.hypot(event.clientX - toolboxDragState.startX, event.clientY - toolboxDragState.startY);
                    const wasDrag = toolboxDragState.active || releaseDistance >= 6;
                    if (!wasDrag) {
                        clearToolboxDrag();
                        return true;
                    }
                    event.preventDefault();
                    const releaseTarget = document.elementFromPoint(event.clientX, event.clientY);
                    const releaseColumn = releaseTarget instanceof Element ? releaseTarget.closest('[data-hr-google-column]') : null;
                    const destinationColumn = releaseColumn || toolboxDragState.targetColumn;
                    const type = toolboxDragState.type;
                    if (destinationColumn) {
                        const card = createHrGoogleQuestionCard(builder, questionValuesForType(type));
                        if (type === 'SECTION') {
                            const targetRow = destinationColumn.closest('[data-hr-google-row]');
                            const sectionRow = addRow(1, targetRow);
                            const sectionColumn = sectionRow?.querySelector('[data-hr-google-column]');
                            sectionColumn?.appendChild(card);
                            if (sectionColumn) {
                                syncHrGoogleColumnEmpty(sectionColumn);
                                selectColumn(sectionColumn);
                            }
                        } else {
                            destinationColumn.insertBefore(card, toolboxInsertionMarker?.parentElement === destinationColumn ? toolboxInsertionMarker : null);
                            syncHrGoogleColumnEmpty(destinationColumn);
                            selectColumn(destinationColumn);
                        }
                        serializeHrGoogleBuilder(builder);
                        setMode('build');
                    }
                    clearToolboxDrag();
                    return true;
                };

                const appendQuestion = (values = {}) => {
                    if (!rowList) {
                        return;
                    }
                    const card = createHrGoogleQuestionCard(builder, values);
                    let column = null;
                    if ((values.type || 'SHORT_TEXT') === 'SECTION') {
                        column = addRow(1)?.querySelector('[data-hr-google-column]') || null;
                    } else {
                        column = selectedOrFallbackColumn();
                    }
                    if (!column) {
                        return;
                    }
                    column.appendChild(card);
                    syncHrGoogleColumnEmpty(column);
                    selectColumn(column);
                    serializeHrGoogleBuilder(builder);
                };
                builder.querySelector('[data-hr-google-add-row]')?.addEventListener('click', () => {
                    addRow(1);
                    setMode('build');
                });
                builder.querySelector('[data-hr-google-add-question]')?.addEventListener('click', () => appendQuestion({
                    type: 'SHORT_TEXT',
                    label: 'Untitled question',
                    help: '',
                    options: [],
                    required: false,
                }));
                builder.querySelector('[data-hr-google-add-section]')?.addEventListener('click', () => appendQuestion({
                    type: 'SECTION',
                    label: 'New section',
                    help: '',
                    options: [],
                    required: false,
                }));
                builder.querySelectorAll('[data-hr-google-add-type]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const type = button.dataset.hrGoogleAddType || 'SHORT_TEXT';
                        appendQuestion(questionValuesForType(type));
                        setMode('build');
                    });
                });
                builder.addEventListener('click', (event) => {
                    const target = event.target;
                    const card = target instanceof Element ? target.closest('[data-hr-google-question]') : null;
                    const row = target instanceof Element ? target.closest('[data-hr-google-row]') : null;
                    const column = target instanceof Element ? target.closest('[data-hr-google-column]') : null;
                    if (column) {
                        selectColumn(column);
                    }
                    if (!rowList) {
                        return;
                    }
                    const columnCountButton = target instanceof Element ? target.closest('[data-hr-google-column-count]') : null;
                    if (row && columnCountButton) {
                        syncHrGoogleRow(builder, row, Number(columnCountButton.dataset.hrGoogleColumnCount || 1));
                        selectColumn(row.querySelector('[data-hr-google-column]'));
                        serializeHrGoogleBuilder(builder);
                        return;
                    }
                    if (row && target instanceof Element && target.closest('[data-hr-google-row-delete]')) {
                        row.remove();
                        serializeHrGoogleBuilder(builder);
                        return;
                    }
                    if (row && target instanceof Element && target.closest('[data-hr-google-row-up]') && row.previousElementSibling) {
                        rowList.insertBefore(row, row.previousElementSibling);
                        serializeHrGoogleBuilder(builder);
                        return;
                    }
                    if (row && target instanceof Element && target.closest('[data-hr-google-row-down]') && row.nextElementSibling) {
                        rowList.insertBefore(row.nextElementSibling, row);
                        serializeHrGoogleBuilder(builder);
                        return;
                    }
                    if (!card) {
                        return;
                    }
                    if (target instanceof Element && target.closest('[data-hr-google-delete]')) {
                        const parentColumn = card.closest('[data-hr-google-column]');
                        card.remove();
                        if (parentColumn) {
                            syncHrGoogleColumnEmpty(parentColumn);
                        }
                        serializeHrGoogleBuilder(builder);
                    }
                    if (target instanceof Element && target.closest('[data-hr-google-duplicate]')) {
                        const values = {
                            label: card.querySelector('[data-hr-google-label]')?.value || '',
                            help: card.querySelector('[data-hr-google-help]')?.value || '',
                            type: card.querySelector('[data-hr-google-type]')?.value || 'SHORT_TEXT',
                            required: Boolean(card.querySelector('[data-hr-google-required]')?.checked),
                            options: (card.querySelector('[data-hr-google-options]')?.value || '').split(/\r?\n/).filter(Boolean),
                        };
                        const duplicate = createHrGoogleQuestionCard(builder, values);
                        if (values.type === 'SECTION') {
                            const duplicateRow = addRow(1, row);
                            duplicateRow?.querySelector('[data-hr-google-column]')?.appendChild(duplicate);
                        } else {
                            card.insertAdjacentElement('afterend', duplicate);
                        }
                        serializeHrGoogleBuilder(builder);
                    }
                    const orderedCards = Array.from(rowList.querySelectorAll('[data-hr-google-question]'));
                    const cardIndex = orderedCards.indexOf(card);
                    if (target instanceof Element && target.closest('[data-hr-google-up]') && cardIndex > 0) {
                        const previousCard = orderedCards[cardIndex - 1];
                        const sourceColumn = card.closest('[data-hr-google-column]');
                        const destinationColumn = previousCard.closest('[data-hr-google-column]');
                        destinationColumn?.insertBefore(card, previousCard);
                        if (sourceColumn) syncHrGoogleColumnEmpty(sourceColumn);
                        if (destinationColumn) syncHrGoogleColumnEmpty(destinationColumn);
                        serializeHrGoogleBuilder(builder);
                    }
                    if (target instanceof Element && target.closest('[data-hr-google-down]') && cardIndex >= 0 && cardIndex < orderedCards.length - 1) {
                        const nextCard = orderedCards[cardIndex + 1];
                        const sourceColumn = card.closest('[data-hr-google-column]');
                        const destinationColumn = nextCard.closest('[data-hr-google-column]');
                        destinationColumn?.insertBefore(card, nextCard.nextElementSibling);
                        if (sourceColumn) syncHrGoogleColumnEmpty(sourceColumn);
                        if (destinationColumn) syncHrGoogleColumnEmpty(destinationColumn);
                        serializeHrGoogleBuilder(builder);
                    }
                });

                let draggedQuestion = null;
                let draggedRow = null;
                const clearDragState = () => {
                    builder.querySelectorAll('[data-hr-google-drop-active]').forEach((element) => delete element.dataset.hrGoogleDropActive);
                    builder.querySelectorAll('[data-hr-google-dragging]').forEach((element) => delete element.dataset.hrGoogleDragging);
                    draggedQuestion = null;
                    draggedRow = null;
                    builder.querySelectorAll('[data-hr-google-question], [data-hr-google-row]').forEach((element) => {
                        element.draggable = false;
                    });
                    builder.querySelectorAll('[data-hr-google-column]').forEach(syncHrGoogleColumnEmpty);
                    serializeHrGoogleBuilder(builder);
                };
                builder.addEventListener('pointerdown', (event) => {
                    const target = event.target;
                    if (!(target instanceof Element)) {
                        return;
                    }
                    const toolboxHandle = target.closest('[data-hr-google-tool-drag]');
                    const isInteractiveControl = Boolean(target.closest('a, button, input, label, select, textarea'));
                    const questionSurface = isInteractiveControl ? null : target.closest('[data-hr-google-question]');
                    const rowSurface = isInteractiveControl ? null : target.closest('[data-hr-google-row-drag-surface]');
                    if (toolboxHandle) {
                        if (event.pointerType === 'mouse' && event.button !== 0) {
                            return;
                        }
                        const toolItem = toolboxHandle.closest('[data-hr-google-tool]');
                        const toolButton = toolItem?.querySelector('[data-hr-google-add-type]');
                        if (!toolButton) {
                            return;
                        }
                        event.preventDefault();
                        toolboxDragState = {
                            button: toolButton,
                            toolItem,
                            type: toolButton.dataset.hrGoogleAddType || 'SHORT_TEXT',
                            label: toolButton.dataset.hrGoogleToolLabel || 'Field',
                            pointerId: event.pointerId,
                            startX: event.clientX,
                            startY: event.clientY,
                            active: false,
                            targetColumn: null,
                        };
                        toolboxHandle.setPointerCapture?.(event.pointerId);
                    } else if (questionSurface) {
                        draggedQuestion = questionSurface;
                        if (draggedQuestion) {
                            event.preventDefault();
                            draggedQuestion.dataset.hrGoogleDragging = 'true';
                            questionSurface.setPointerCapture?.(event.pointerId);
                        }
                    } else if (rowSurface) {
                        draggedRow = rowSurface.closest('[data-hr-google-row]');
                        if (draggedRow) {
                            event.preventDefault();
                            draggedRow.dataset.hrGoogleDragging = 'true';
                            rowSurface.setPointerCapture?.(event.pointerId);
                        }
                    }
                });
                builder.addEventListener('pointermove', (event) => {
                    if (moveToolboxDrag(event)) {
                        return;
                    }
                    if (!draggedQuestion && !draggedRow) {
                        return;
                    }
                    event.preventDefault();
                    const pointerTarget = document.elementFromPoint(event.clientX, event.clientY);
                    if (!(pointerTarget instanceof Element)) {
                        return;
                    }
                    if (draggedQuestion) {
                        const destinationColumn = pointerTarget.closest('[data-hr-google-column]');
                        if (!destinationColumn) {
                            return;
                        }
                        builder.querySelectorAll('[data-hr-google-drop-active]').forEach((element) => delete element.dataset.hrGoogleDropActive);
                        destinationColumn.dataset.hrGoogleDropActive = 'true';
                        const cards = Array.from(destinationColumn.querySelectorAll(':scope > [data-hr-google-question]')).filter((card) => card !== draggedQuestion);
                        const afterCard = cards.find((candidate) => event.clientY < candidate.getBoundingClientRect().top + candidate.getBoundingClientRect().height / 2);
                        destinationColumn.insertBefore(draggedQuestion, afterCard || null);
                        selectColumn(destinationColumn);
                        return;
                    }
                    if (draggedRow && rowList) {
                        const destinationRow = pointerTarget.closest('[data-hr-google-row]');
                        if (!destinationRow || destinationRow === draggedRow) {
                            return;
                        }
                        const bounds = destinationRow.getBoundingClientRect();
                        if (event.clientY < bounds.top + bounds.height / 2) {
                            rowList.insertBefore(draggedRow, destinationRow);
                        } else {
                            destinationRow.insertAdjacentElement('afterend', draggedRow);
                        }
                    }
                });
                builder.addEventListener('pointerup', (event) => {
                    finishToolboxDrag(event);
                    clearDragState();
                });
                builder.addEventListener('pointercancel', () => {
                    clearToolboxDrag();
                    clearDragState();
                });
                builder.addEventListener('dragstart', (event) => {
                    const target = event.target;
                    if (!(target instanceof Element)) {
                        return;
                    }
                    const questionCard = target.closest('[data-hr-google-question]');
                    const rowCard = target.closest('[data-hr-google-row]');
                    if (questionCard && questionCard.draggable) {
                        draggedQuestion = questionCard;
                        if (draggedQuestion) {
                            draggedQuestion.dataset.hrGoogleDragging = 'true';
                            event.dataTransfer.effectAllowed = 'move';
                            event.dataTransfer.setData('text/plain', draggedQuestion.dataset.hrGoogleQuestionKey || 'question');
                        }
                    } else if (rowCard && rowCard.draggable) {
                        draggedRow = rowCard;
                        if (draggedRow) {
                            draggedRow.dataset.hrGoogleDragging = 'true';
                            event.dataTransfer.effectAllowed = 'move';
                            event.dataTransfer.setData('text/plain', draggedRow.dataset.hrGoogleRow || 'row');
                        }
                    } else {
                        event.preventDefault();
                    }
                });
                builder.addEventListener('dragover', (event) => {
                    const target = event.target;
                    if (!(target instanceof Element)) {
                        return;
                    }
                    if (draggedQuestion) {
                        const destinationColumn = target.closest('[data-hr-google-column]');
                        if (!destinationColumn) {
                            return;
                        }
                        event.preventDefault();
                        builder.querySelectorAll('[data-hr-google-drop-active]').forEach((element) => delete element.dataset.hrGoogleDropActive);
                        destinationColumn.dataset.hrGoogleDropActive = 'true';
                        const cards = Array.from(destinationColumn.querySelectorAll(':scope > [data-hr-google-question]')).filter((card) => card !== draggedQuestion);
                        const afterCard = cards.find((candidate) => event.clientY < candidate.getBoundingClientRect().top + candidate.getBoundingClientRect().height / 2);
                        destinationColumn.insertBefore(draggedQuestion, afterCard || null);
                        selectColumn(destinationColumn);
                        return;
                    }
                    if (draggedRow && rowList) {
                        const destinationRow = target.closest('[data-hr-google-row]');
                        if (!destinationRow || destinationRow === draggedRow) {
                            return;
                        }
                        event.preventDefault();
                        const bounds = destinationRow.getBoundingClientRect();
                        if (event.clientY < bounds.top + bounds.height / 2) {
                            rowList.insertBefore(draggedRow, destinationRow);
                        } else {
                            destinationRow.insertAdjacentElement('afterend', draggedRow);
                        }
                    }
                });
                builder.addEventListener('drop', (event) => {
                    if (draggedQuestion || draggedRow) {
                        event.preventDefault();
                        clearDragState();
                    }
                });
                builder.addEventListener('dragend', clearDragState);
                builder.addEventListener('input', () => serializeHrGoogleBuilder(builder));
                builder.addEventListener('change', (event) => {
                    const target = event.target;
                    const card = target instanceof Element ? target.closest('[data-hr-google-question]') : null;
                    if (card) {
                        syncHrGoogleQuestionCard(card);
                        const type = card.querySelector('[data-hr-google-type]')?.value || 'SHORT_TEXT';
                        const row = card.closest('[data-hr-google-row]');
                        const rowCards = row ? row.querySelectorAll('[data-hr-google-question]') : [];
                        const rowColumns = row ? row.querySelectorAll('[data-hr-google-column]') : [];
                        if (type === 'SECTION' && row && (rowCards.length > 1 || rowColumns.length > 1)) {
                            const sectionRow = addRow(1, row);
                            const sectionColumn = sectionRow?.querySelector('[data-hr-google-column]');
                            sectionColumn?.appendChild(card);
                            if (sectionColumn) syncHrGoogleColumnEmpty(sectionColumn);
                        } else if (type === 'SECTION' && row) {
                            syncHrGoogleRow(builder, row, 1);
                        }
                    }
                    serializeHrGoogleBuilder(builder);
                });
                modeButtons.forEach((button) => {
                    button.addEventListener('click', () => setMode(button.dataset.hrGoogleMode || 'build'));
                });
                builder.querySelectorAll('[data-hr-google-question]').forEach((card) => syncHrGoogleQuestionCard(card));
                builder.querySelectorAll('[data-hr-google-column]').forEach(syncHrGoogleColumnEmpty);
                serializeHrGoogleBuilder(builder);
                setMode('build');
            });

            document.querySelectorAll('form[data-confirm-submit]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    if (form.id === 'yovel-employee-form') {
                        syncEmployeeFullName();
                    }
                    if (form === salesSchemaForm) {
                        syncSalesSchemaJson();
                    }
                    if (form === accountSchemaForm) {
                        syncAccountBuilderSort();
                    }
                    if (form.matches('[data-hr-google-builder]')) {
                        serializeHrGoogleBuilder(form);
                    }
                    if (allowSubmit) {
                        allowSubmit = false;
                        return;
                    }
                    if (!form.checkValidity()) {
                        return;
                    }
                    event.preventDefault();
                    pendingForm = form;
                    dialog.hidden = false;
                    confirmButton.focus();
                });
            });

            confirmButton?.addEventListener('click', () => {
                if (!pendingForm) {
                    return;
                }
                allowSubmit = true;
                pendingForm.requestSubmit();
            });
            cancelButton?.addEventListener('click', closeDialog);
            dialog?.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    closeDialog();
                }
            });
            jobPositionTour?.addEventListener('click', (event) => {
                if (event.target === jobPositionTour) {
                    closeJobPositionTour(false);
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !dialog.hidden) {
                    closeDialog();
                    return;
                }
                if (event.key === 'Escape' && hrDashboardBuilderModal) {
                    window.location.href = './?view=hr&section=dashboard';
                    return;
                }
                if (event.key === 'Escape' && jobPositionTour && !jobPositionTour.hidden) {
                    closeJobPositionTour(false);
                    return;
                }
	                if (event.key === 'Escape' && employeeModal && !employeeModal.hidden) {
	                    closeEmployeeModal();
	                    return;
	                }
		                if (event.key === 'Escape' && hrFormBuilderModal && !hrFormBuilderModal.hidden) {
		                    closeHrFormBuilderModal();
		                    return;
		                }
		                if (event.key === 'Escape' && hrFormCreateModal && !hrFormCreateModal.hidden) {
		                    closeHrFormCreateModal();
		                    return;
		                }
                if (event.key === 'Escape' && departmentModal && !departmentModal.hidden) {
                    closeDepartmentModal();
                    return;
                }
                if (event.key === 'Escape' && departmentFormBuilderModal && !departmentFormBuilderModal.hidden) {
                    closeDepartmentFormBuilderModal();
                    return;
                }
                if (event.key === 'Escape' && departmentFormCreateModal && !departmentFormCreateModal.hidden) {
                    closeDepartmentFormCreateModal();
                    return;
                }
                if (event.key === 'Escape' && jobPositionModal && !jobPositionModal.hidden) {
                    closeJobPositionModal();
                    return;
                }
                if (event.key === 'Escape' && jobPositionFormBuilderModal && !jobPositionFormBuilderModal.hidden) {
                    closeJobPositionFormBuilderModal();
                    return;
                }
                if (event.key === 'Escape' && jobPositionFormCreateModal && !jobPositionFormCreateModal.hidden) {
                    closeJobPositionFormCreateModal();
                    return;
                }
		                if (event.key === 'Escape' && salesLeadModal && !salesLeadModal.hidden) {
		                    closeSalesLeadModal();
		                    return;
		                }
                if (event.key === 'Escape' && accountModal && !accountModal.hidden) {
                    closeAccountModal();
                    return;
                }
                if (event.key === 'Escape' && accountBuilderModal && !accountBuilderModal.hidden) {
                    closeAccountBuilderModal();
                    return;
                }
		                const activeFocusModal = [jobPositionTour, employeeModal, hrFormBuilderModal, hrFormCreateModal, departmentModal, departmentFormBuilderModal, departmentFormCreateModal, jobPositionModal, jobPositionFormBuilderModal, jobPositionFormCreateModal, salesLeadModal, accountModal, accountBuilderModal].find((modal) => modal && !modal.hidden);
	                if (event.key === 'Tab' && activeFocusModal && dialog.hidden) {
	                    const focusable = Array.from(activeFocusModal.querySelectorAll(employeeModalFocusable))
	                        .filter((element) => element.offsetParent !== null || element === document.activeElement);
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    if (!first || !last) {
                        return;
                    }
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                }
            });

            employeeFilterControls.forEach((control) => {
                control.addEventListener(control.tagName === 'SELECT' ? 'change' : 'input', applyEmployeeFilters);
            });
            employeeSortControl?.addEventListener('change', applyEmployeeFilters);
            employeeClearFilters?.addEventListener('click', () => {
                employeeFilterControls.forEach((control) => {
                    control.value = '';
                });
                applyEmployeeFilters();
                employeeFilterControls[0]?.focus();
            });
            employeeRows.forEach((row) => {
                row.querySelector('[data-employee-select]')?.addEventListener('click', () => selectEmployeeRow(row));
                row.querySelector('input[type="checkbox"]')?.addEventListener('change', () => selectEmployeeRow(row));
                row.addEventListener('click', (event) => {
                    if (!event.target.closest('a, button, input, select, textarea, summary, details, form')) {
                        selectEmployeeRow(row);
                    }
                });
            });
            employeeContextTabs.forEach((tab, index) => {
                tab.addEventListener('click', () => setEmployeeContextPanel(tab.dataset.employeeContextTab || 'sections'));
                tab.addEventListener('keydown', (event) => {
                    if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
                        return;
                    }
                    event.preventDefault();
                    const direction = event.key === 'ArrowRight' ? 1 : -1;
                    const nextTab = employeeContextTabs[(index + direction + employeeContextTabs.length) % employeeContextTabs.length];
                    nextTab?.focus();
                    nextTab?.click();
                });
            });
            employeeActionMenus.forEach((menu) => {
                menu.addEventListener('toggle', () => {
                    if (!menu.open) {
                        return;
                    }
                    employeeActionMenus.forEach((otherMenu) => {
                        if (otherMenu !== menu) {
                            otherMenu.open = false;
                        }
                    });
                });
            });
            document.addEventListener('click', (event) => {
                employeeActionMenus.forEach((menu) => {
                    if (!menu.contains(event.target)) {
                        menu.open = false;
                    }
                });
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    employeeActionMenus.forEach((menu) => {
                        menu.open = false;
                    });
                }
            });
            employeeForm?.addEventListener('input', () => {
                if (!employeeFormState || !employeeModalEditMode) {
                    return;
                }
                employeeFormState.dataset.dirty = 'true';
                employeeFormState.textContent = 'Unsaved changes';
            });
            employeeStatusInput?.addEventListener('change', () => {
                if (employeeExitGuidance) {
                    employeeExitGuidance.hidden = employeeStatusInput.value === 'SEPARATED';
                }
            });
            selectEmployeeRow(employeeRows.find((row) => row.getAttribute('aria-selected') === 'true') || employeeRows[0] || null);
            applyEmployeeFilters();

            if (employeeWidgetBoard) {
                let draggedWidget = null;
                const widgetItems = () => Array.from(employeeWidgetBoard.querySelectorAll('.yovel-widget-item'));
                const widgetSectionKey = (item) => item?.dataset.widgetKey || 'overview';
                const widgetSectionTitle = (item) => item?.querySelector('[data-employee-section-target]')?.textContent?.trim() || 'feature';
                const openWidgetSection = (item, trigger = item) => {
                    openEmployeeSectionFromFeature(widgetSectionKey(item), trigger);
                };
                const setEmployeeDropIndicatorText = (row, text) => {
                    const indicator = row.querySelector('[data-employee-drop-indicator]');
                    if (indicator) {
                        indicator.textContent = text;
                    }
                };
                const primeEmployeeDropFeedback = (item) => {
                    const title = widgetSectionTitle(item);
                    employeeDropTargets.forEach((row) => {
                        row.classList.add('yovel-employee-drop-ready');
                        row.classList.remove('yovel-employee-drop-active');
                        setEmployeeDropIndicatorText(row, `Apply ${title} here`);
                    });
                };
                const clearEmployeeDropFeedback = () => {
                    employeeDropTargets.forEach((row) => {
                        row.classList.remove('yovel-employee-drop-ready', 'yovel-employee-drop-active');
                        setEmployeeDropIndicatorText(row, 'Apply feature here');
                    });
                };
                widgetItems().forEach((item) => {
                    item.setAttribute('tabindex', '0');
                    item.addEventListener('click', (event) => {
                        if (event.target.closest('[data-employee-section-target]')) {
                            return;
                        }
                        openWidgetSection(item, item);
                    });
                    item.addEventListener('keydown', (event) => {
                        if (event.key !== 'Enter' && event.key !== ' ') {
                            return;
                        }
                        event.preventDefault();
                        openWidgetSection(item, item);
                    });
                    item.addEventListener('dragstart', (event) => {
                        draggedWidget = item;
                        item.setAttribute('aria-grabbed', 'true');
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', item.dataset.widgetKey || '');
                        primeEmployeeDropFeedback(item);
                    });
                    item.addEventListener('dragend', () => {
                        item.setAttribute('aria-grabbed', 'false');
                        clearEmployeeDropFeedback();
                        draggedWidget = null;
                    });
                });
                employeeDropTargets.forEach((row) => {
                    row.addEventListener('dragenter', () => {
                        if (draggedWidget) {
                            row.classList.add('yovel-employee-drop-active');
                            setEmployeeDropIndicatorText(row, `Release to open ${widgetSectionTitle(draggedWidget)}`);
                        }
                    });
                    row.addEventListener('dragover', (event) => {
                        if (!draggedWidget) {
                            return;
                        }
                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'move';
                        row.classList.add('yovel-employee-drop-active');
                        setEmployeeDropIndicatorText(row, `Release to open ${widgetSectionTitle(draggedWidget)}`);
                    });
                    row.addEventListener('dragleave', (event) => {
                        if (!row.contains(event.relatedTarget)) {
                            row.classList.remove('yovel-employee-drop-active');
                            if (draggedWidget) {
                                setEmployeeDropIndicatorText(row, `Apply ${widgetSectionTitle(draggedWidget)} here`);
                            }
                        }
                    });
                    row.addEventListener('drop', (event) => {
                        if (!draggedWidget) {
                            return;
                        }
                        event.preventDefault();
                        const employeeKey = row.dataset.employeeKey || '';
                        const sectionKey = widgetSectionKey(draggedWidget);
                        clearEmployeeDropFeedback();
                        if (employeeKey !== '') {
                            window.location.href = employeeSectionUrl(employeeKey, sectionKey);
                        }
                    });
                });
	            }

            if (departmentWidgetBoard) {
                let draggedDepartmentWidget = null;
                const departmentWidgetItems = () => Array.from(departmentWidgetBoard.querySelectorAll('.yovel-widget-item'));
                const saveDepartmentWidgetOrder = () => {
                    try {
                        window.localStorage.setItem(
                            departmentWidgetStorageKey,
                            JSON.stringify(departmentWidgetItems().map((item) => item.dataset.departmentWidgetKey || ''))
                        );
                    } catch (error) {
                    }
                };
                const restoreDepartmentWidgetOrder = () => {
                    try {
                        const savedOrder = JSON.parse(window.localStorage.getItem(departmentWidgetStorageKey) || '[]');
                        if (!Array.isArray(savedOrder)) {
                            return;
                        }
                        savedOrder.forEach((key) => {
                            if (typeof key !== 'string') {
                                return;
                            }
                            const safeKey = window.CSS?.escape ? CSS.escape(key) : key.replace(/"/g, '\\"');
                            const item = departmentWidgetBoard.querySelector(`[data-department-widget-key="${safeKey}"]`);
                            if (item) {
                                departmentWidgetBoard.appendChild(item);
                            }
                        });
                    } catch (error) {
                    }
                };
                const getDepartmentWidgetAfter = (y) => {
                    return departmentWidgetItems()
                        .filter((item) => item !== draggedDepartmentWidget)
                        .reduce((closest, item) => {
                            const box = item.getBoundingClientRect();
                            const offset = y - box.top - box.height / 2;
                            if (offset < 0 && offset > closest.offset) {
                                return { offset, element: item };
                            }
                            return closest;
                        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
                };
                const moveDepartmentWidget = (button, direction) => {
                    const item = button.closest('.yovel-widget-item');
                    if (!item) {
                        return;
                    }
                    if (direction === 'up' && item.previousElementSibling) {
                        departmentWidgetBoard.insertBefore(item, item.previousElementSibling);
                    }
                    if (direction === 'down' && item.nextElementSibling) {
                        departmentWidgetBoard.insertBefore(item.nextElementSibling, item);
                    }
                    saveDepartmentWidgetOrder();
                    item.focus?.();
                };
                const departmentWidgetSectionKey = (item) => item?.querySelector('[data-department-section-target]')?.dataset.departmentSectionTarget || 'overview';
                const departmentWidgetTitle = (item) => item?.querySelector('[data-department-section-target]')?.textContent?.trim() || 'feature';
                const openDepartmentWidgetSection = (item, trigger = item) => {
                    openDepartmentSectionFromFeature(departmentWidgetSectionKey(item), trigger);
                };
                const setDepartmentDropIndicatorText = (row, text) => {
                    const indicator = row.querySelector('[data-department-drop-indicator]');
                    if (indicator) {
                        indicator.textContent = text;
                    }
                };
                const primeDepartmentDropFeedback = (item) => {
                    const title = departmentWidgetTitle(item);
                    departmentDropTargets.forEach((row) => {
                        row.classList.add('yovel-employee-drop-ready');
                        row.classList.remove('yovel-employee-drop-active');
                        setDepartmentDropIndicatorText(row, `Apply ${title} here`);
                    });
                };
                const clearDepartmentDropFeedback = () => {
                    departmentDropTargets.forEach((row) => {
                        row.classList.remove('yovel-employee-drop-ready', 'yovel-employee-drop-active');
                        setDepartmentDropIndicatorText(row, 'Apply feature here');
                    });
                };

                restoreDepartmentWidgetOrder();
                departmentWidgetItems().forEach((item) => {
                    item.setAttribute('tabindex', '0');
                    item.addEventListener('click', (event) => {
                        if (event.target.closest('.yovel-widget-move-up, .yovel-widget-move-down, [data-department-section-target]')) {
                            return;
                        }
                        openDepartmentWidgetSection(item, item);
                    });
                    item.addEventListener('keydown', (event) => {
                        if (event.key !== 'Enter' && event.key !== ' ') {
                            return;
                        }
                        event.preventDefault();
                        openDepartmentWidgetSection(item, item);
                    });
                    item.addEventListener('dragstart', (event) => {
                        draggedDepartmentWidget = item;
                        item.setAttribute('aria-grabbed', 'true');
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', departmentWidgetSectionKey(item));
                        primeDepartmentDropFeedback(item);
                    });
                    item.addEventListener('dragend', () => {
                        item.setAttribute('aria-grabbed', 'false');
                        departmentWidgetBoard.classList.remove('yovel-widget-drop-active');
                        clearDepartmentDropFeedback();
                        draggedDepartmentWidget = null;
                        saveDepartmentWidgetOrder();
                    });
                });
                departmentDropTargets.forEach((row) => {
                    row.addEventListener('dragenter', () => {
                        if (draggedDepartmentWidget) {
                            row.classList.add('yovel-employee-drop-active');
                            setDepartmentDropIndicatorText(row, `Release to open ${departmentWidgetTitle(draggedDepartmentWidget)}`);
                        }
                    });
                    row.addEventListener('dragover', (event) => {
                        if (!draggedDepartmentWidget) {
                            return;
                        }
                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'move';
                        row.classList.add('yovel-employee-drop-active');
                        setDepartmentDropIndicatorText(row, `Release to open ${departmentWidgetTitle(draggedDepartmentWidget)}`);
                    });
                    row.addEventListener('dragleave', (event) => {
                        if (!row.contains(event.relatedTarget)) {
                            row.classList.remove('yovel-employee-drop-active');
                            if (draggedDepartmentWidget) {
                                setDepartmentDropIndicatorText(row, `Apply ${departmentWidgetTitle(draggedDepartmentWidget)} here`);
                            }
                        }
                    });
                    row.addEventListener('drop', (event) => {
                        if (!draggedDepartmentWidget) {
                            return;
                        }
                        event.preventDefault();
                        const departmentKey = row.dataset.departmentKey || '';
                        const sectionKey = departmentWidgetSectionKey(draggedDepartmentWidget);
                        clearDepartmentDropFeedback();
                        if (departmentKey !== '') {
                            window.location.href = departmentSectionUrl(departmentKey, sectionKey);
                        }
                    });
                });
                departmentWidgetBoard.addEventListener('dragover', (event) => {
                    if (!draggedDepartmentWidget) {
                        return;
                    }
                    event.preventDefault();
                    departmentWidgetBoard.classList.add('yovel-widget-drop-active');
                    const after = getDepartmentWidgetAfter(event.clientY);
                    if (after) {
                        departmentWidgetBoard.insertBefore(draggedDepartmentWidget, after);
                    } else {
                        departmentWidgetBoard.appendChild(draggedDepartmentWidget);
                    }
                });
                departmentWidgetBoard.addEventListener('drop', (event) => {
                    event.preventDefault();
                    departmentWidgetBoard.classList.remove('yovel-widget-drop-active');
                    saveDepartmentWidgetOrder();
                });
                departmentWidgetBoard.addEventListener('dragleave', (event) => {
                    if (event.target === departmentWidgetBoard) {
                        departmentWidgetBoard.classList.remove('yovel-widget-drop-active');
                    }
                });
                departmentWidgetBoard.querySelectorAll('.yovel-widget-move-up').forEach((button) => {
                    button.addEventListener('click', () => moveDepartmentWidget(button, 'up'));
                });
                departmentWidgetBoard.querySelectorAll('.yovel-widget-move-down').forEach((button) => {
                    button.addEventListener('click', () => moveDepartmentWidget(button, 'down'));
                });
            }

            if (jobPositionWidgetBoard) {
                let draggedJobPositionWidget = null;
                const jobPositionWidgetItems = () => Array.from(jobPositionWidgetBoard.querySelectorAll('.yovel-widget-item'));
                const saveJobPositionWidgetOrder = () => {
                    try {
                        window.localStorage.setItem(
                            jobPositionWidgetStorageKey,
                            JSON.stringify(jobPositionWidgetItems().map((item) => item.dataset.jobPositionWidgetKey || ''))
                        );
                    } catch (error) {
                    }
                };
                const restoreJobPositionWidgetOrder = () => {
                    try {
                        const savedOrder = JSON.parse(window.localStorage.getItem(jobPositionWidgetStorageKey) || '[]');
                        if (!Array.isArray(savedOrder)) {
                            return;
                        }
                        savedOrder.forEach((key) => {
                            if (typeof key !== 'string') {
                                return;
                            }
                            const safeKey = window.CSS?.escape ? CSS.escape(key) : key.replace(/"/g, '\\"');
                            const item = jobPositionWidgetBoard.querySelector(`[data-job-position-widget-key="${safeKey}"]`);
                            if (item) {
                                jobPositionWidgetBoard.appendChild(item);
                            }
                        });
                    } catch (error) {
                    }
                };
                const getJobPositionWidgetAfter = (y) => {
                    return jobPositionWidgetItems()
                        .filter((item) => item !== draggedJobPositionWidget)
                        .reduce((closest, item) => {
                            const box = item.getBoundingClientRect();
                            const offset = y - box.top - box.height / 2;
                            if (offset < 0 && offset > closest.offset) {
                                return { offset, element: item };
                            }
                            return closest;
                        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
                };
                const moveJobPositionWidget = (button, direction) => {
                    const item = button.closest('.yovel-widget-item');
                    if (!item) {
                        return;
                    }
                    if (direction === 'up' && item.previousElementSibling) {
                        jobPositionWidgetBoard.insertBefore(item, item.previousElementSibling);
                    }
                    if (direction === 'down' && item.nextElementSibling) {
                        jobPositionWidgetBoard.insertBefore(item.nextElementSibling, item);
                    }
                    saveJobPositionWidgetOrder();
                    item.focus?.();
                };
                const jobPositionWidgetSectionKey = (item) => item?.querySelector('[data-job-position-section-target]')?.dataset.jobPositionSectionTarget || 'overview';
                const jobPositionWidgetTitle = (item) => item?.querySelector('[data-job-position-section-target]')?.textContent?.trim() || 'feature';
                const openJobPositionWidgetSection = (item, trigger = item) => {
                    openJobPositionSectionFromFeature(jobPositionWidgetSectionKey(item), trigger);
                };
                const setJobPositionDropIndicatorText = (row, text) => {
                    const indicator = row.querySelector('[data-job-position-drop-indicator]');
                    if (indicator) {
                        indicator.textContent = text;
                    }
                };
                const primeJobPositionDropFeedback = (item) => {
                    const title = jobPositionWidgetTitle(item);
                    jobPositionDropTargets.forEach((row) => {
                        row.classList.add('yovel-employee-drop-ready');
                        row.classList.remove('yovel-employee-drop-active');
                        setJobPositionDropIndicatorText(row, `Apply ${title} here`);
                    });
                };
                const clearJobPositionDropFeedback = () => {
                    jobPositionDropTargets.forEach((row) => {
                        row.classList.remove('yovel-employee-drop-ready', 'yovel-employee-drop-active');
                        setJobPositionDropIndicatorText(row, 'Apply feature here');
                    });
                };

                restoreJobPositionWidgetOrder();
                jobPositionWidgetItems().forEach((item) => {
                    item.setAttribute('tabindex', '0');
                    item.addEventListener('click', (event) => {
                        if (event.target.closest('.yovel-widget-move-up, .yovel-widget-move-down, [data-job-position-section-target]')) {
                            return;
                        }
                        openJobPositionWidgetSection(item, item);
                    });
                    item.addEventListener('keydown', (event) => {
                        if (event.key !== 'Enter' && event.key !== ' ') {
                            return;
                        }
                        event.preventDefault();
                        openJobPositionWidgetSection(item, item);
                    });
                    item.addEventListener('dragstart', (event) => {
                        draggedJobPositionWidget = item;
                        item.setAttribute('aria-grabbed', 'true');
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', jobPositionWidgetSectionKey(item));
                        primeJobPositionDropFeedback(item);
                    });
                    item.addEventListener('dragend', () => {
                        item.setAttribute('aria-grabbed', 'false');
                        jobPositionWidgetBoard.classList.remove('yovel-widget-drop-active');
                        clearJobPositionDropFeedback();
                        draggedJobPositionWidget = null;
                        saveJobPositionWidgetOrder();
                    });
                });
                jobPositionDropTargets.forEach((row) => {
                    row.addEventListener('dragenter', () => {
                        if (draggedJobPositionWidget) {
                            row.classList.add('yovel-employee-drop-active');
                            setJobPositionDropIndicatorText(row, `Release to open ${jobPositionWidgetTitle(draggedJobPositionWidget)}`);
                        }
                    });
                    row.addEventListener('dragover', (event) => {
                        if (!draggedJobPositionWidget) {
                            return;
                        }
                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'move';
                        row.classList.add('yovel-employee-drop-active');
                        setJobPositionDropIndicatorText(row, `Release to open ${jobPositionWidgetTitle(draggedJobPositionWidget)}`);
                    });
                    row.addEventListener('dragleave', (event) => {
                        if (!row.contains(event.relatedTarget)) {
                            row.classList.remove('yovel-employee-drop-active');
                            if (draggedJobPositionWidget) {
                                setJobPositionDropIndicatorText(row, `Apply ${jobPositionWidgetTitle(draggedJobPositionWidget)} here`);
                            }
                        }
                    });
                    row.addEventListener('drop', (event) => {
                        if (!draggedJobPositionWidget) {
                            return;
                        }
                        event.preventDefault();
                        const jobPositionKey = row.dataset.jobPositionKey || '';
                        const sectionKey = jobPositionWidgetSectionKey(draggedJobPositionWidget);
                        clearJobPositionDropFeedback();
                        if (jobPositionKey !== '') {
                            window.location.href = jobPositionSectionUrl(jobPositionKey, sectionKey);
                        }
                    });
                });
                jobPositionWidgetBoard.addEventListener('dragover', (event) => {
                    if (!draggedJobPositionWidget) {
                        return;
                    }
                    event.preventDefault();
                    jobPositionWidgetBoard.classList.add('yovel-widget-drop-active');
                    const after = getJobPositionWidgetAfter(event.clientY);
                    if (after) {
                        jobPositionWidgetBoard.insertBefore(draggedJobPositionWidget, after);
                    } else {
                        jobPositionWidgetBoard.appendChild(draggedJobPositionWidget);
                    }
                });
                jobPositionWidgetBoard.addEventListener('drop', (event) => {
                    event.preventDefault();
                    jobPositionWidgetBoard.classList.remove('yovel-widget-drop-active');
                    saveJobPositionWidgetOrder();
                });
                jobPositionWidgetBoard.addEventListener('dragleave', (event) => {
                    if (event.target === jobPositionWidgetBoard) {
                        jobPositionWidgetBoard.classList.remove('yovel-widget-drop-active');
                    }
                });
                jobPositionWidgetBoard.querySelectorAll('.yovel-widget-move-up').forEach((button) => {
                    button.addEventListener('click', () => moveJobPositionWidget(button, 'up'));
                });
                jobPositionWidgetBoard.querySelectorAll('.yovel-widget-move-down').forEach((button) => {
                    button.addEventListener('click', () => moveJobPositionWidget(button, 'down'));
                });
            }

            if (accountWidgetBoard) {
                let draggedWidget = null;
                const widgetItems = () => Array.from(accountWidgetBoard.querySelectorAll('.yovel-widget-item'));
                const saveWidgetOrder = () => {
                    try {
                        window.localStorage.setItem(
                            accountWidgetStorageKey,
                            JSON.stringify(widgetItems().map((item) => item.dataset.widgetKey || ''))
                        );
                    } catch (error) {
                    }
                };
                const restoreWidgetOrder = () => {
                    try {
                        const savedOrder = JSON.parse(window.localStorage.getItem(accountWidgetStorageKey) || '[]');
                        if (!Array.isArray(savedOrder)) {
                            return;
                        }
                        savedOrder.forEach((key) => {
                            if (typeof key !== 'string') {
                                return;
                            }
                            const safeKey = window.CSS?.escape ? CSS.escape(key) : key.replace(/"/g, '\\"');
                            const item = accountWidgetBoard.querySelector(`[data-widget-key="${safeKey}"]`);
                            if (item) {
                                accountWidgetBoard.appendChild(item);
                            }
                        });
                    } catch (error) {
                    }
                };
                const getWidgetAfter = (y) => {
                    return widgetItems()
                        .filter((item) => item !== draggedWidget)
                        .reduce((closest, item) => {
                            const box = item.getBoundingClientRect();
                            const offset = y - box.top - box.height / 2;
                            if (offset < 0 && offset > closest.offset) {
                                return { offset, element: item };
                            }
                            return closest;
                        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
                };
                const moveWidget = (button, direction) => {
                    const item = button.closest('.yovel-widget-item');
                    if (!item) {
                        return;
                    }
                    if (direction === 'up' && item.previousElementSibling) {
                        accountWidgetBoard.insertBefore(item, item.previousElementSibling);
                    }
                    if (direction === 'down' && item.nextElementSibling) {
                        accountWidgetBoard.insertBefore(item.nextElementSibling, item);
                    }
                    saveWidgetOrder();
                    item.focus?.();
                };

                restoreWidgetOrder();
                widgetItems().forEach((item) => {
                    item.setAttribute('tabindex', '0');
                    item.addEventListener('dragstart', (event) => {
                        draggedWidget = item;
                        item.setAttribute('aria-grabbed', 'true');
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', item.dataset.widgetKey || '');
                    });
                    item.addEventListener('dragend', () => {
                        item.setAttribute('aria-grabbed', 'false');
                        accountWidgetBoard.classList.remove('yovel-widget-drop-active');
                        draggedWidget = null;
                        saveWidgetOrder();
                    });
                });
                accountWidgetBoard.addEventListener('dragover', (event) => {
                    if (!draggedWidget) {
                        return;
                    }
                    event.preventDefault();
                    accountWidgetBoard.classList.add('yovel-widget-drop-active');
                    const after = getWidgetAfter(event.clientY);
                    if (after) {
                        accountWidgetBoard.insertBefore(draggedWidget, after);
                    } else {
                        accountWidgetBoard.appendChild(draggedWidget);
                    }
                });
                accountWidgetBoard.addEventListener('drop', (event) => {
                    event.preventDefault();
                    accountWidgetBoard.classList.remove('yovel-widget-drop-active');
                    saveWidgetOrder();
                });
                accountWidgetBoard.addEventListener('dragleave', (event) => {
                    if (event.target === accountWidgetBoard) {
                        accountWidgetBoard.classList.remove('yovel-widget-drop-active');
                    }
                });
                accountWidgetBoard.querySelectorAll('.yovel-widget-move-up').forEach((button) => {
                    button.addEventListener('click', () => moveWidget(button, 'up'));
                });
                accountWidgetBoard.querySelectorAll('.yovel-widget-move-down').forEach((button) => {
                    button.addEventListener('click', () => moveWidget(button, 'down'));
                });
            }

            const financeGrid = document.querySelector('[data-finance-grid]');
            const financeGridRows = financeGrid ? Array.from(financeGrid.querySelectorAll('[data-finance-grid-row]')) : [];
            const financeGridSearch = document.querySelector('[data-finance-grid-search]');
            const financeGridFilters = Array.from(document.querySelectorAll('[data-finance-grid-filter]'));
            const financeGridCount = document.querySelector('[data-finance-grid-count]');
            const financeGridFilteredEmpty = financeGrid?.querySelector('[data-finance-grid-filtered-empty]');
            const financeGridSortInput = document.querySelector('[data-finance-grid-sort-json]');
            let financeGridSort = [];
            try {
                financeGridSort = JSON.parse(financeGrid?.dataset.gridSort || '[]');
            } catch (error) {
                financeGridSort = [];
            }

            const financeGridCellValue = (row, column) => {
                const attribute = row.getAttribute(`data-${column.replaceAll('_', '-')}`);
                if (attribute !== null) return attribute;
                return row.querySelector(`[data-finance-grid-cell][data-column-key="${CSS.escape(column)}"]`)?.dataset.value || '';
            };
            const financeGridCompare = (left, right, column, direction) => {
                const leftValue = financeGridCellValue(left, column);
                const rightValue = financeGridCellValue(right, column);
                const numeric = leftValue !== '' && rightValue !== '' && Number.isFinite(Number(leftValue)) && Number.isFinite(Number(rightValue));
                const comparison = numeric
                    ? Number(leftValue) - Number(rightValue)
                    : leftValue.localeCompare(rightValue, undefined, { numeric: true, sensitivity: 'base' });
                return direction === 'DESC' ? -comparison : comparison;
            };
            const applyFinanceGrid = () => {
                if (!financeGrid) return;
                financeGrid.querySelectorAll('[data-finance-grid-group-row]').forEach((row) => row.remove());
                const query = (financeGridSearch?.value || '').trim().toLowerCase();
                const visible = financeGridRows.filter((row) => {
                    const matchesSearch = query === '' || row.textContent.toLowerCase().includes(query);
                    const matchesFilters = financeGridFilters.every((filter) => {
                        const expected = filter.value.trim().toLowerCase();
                        return expected === '' || financeGridCellValue(row, filter.dataset.financeGridFilter || '').toLowerCase().includes(expected);
                    });
                    row.hidden = !(matchesSearch && matchesFilters);
                    return !row.hidden;
                });
                visible.sort((left, right) => {
                    for (const sort of financeGridSort) {
                        const result = financeGridCompare(left, right, sort.column, sort.direction);
                        if (result !== 0) return result;
                    }
                    return 0;
                });
                const body = financeGrid.querySelector('tbody');
                visible.forEach((row) => body?.appendChild(row));
                const groupColumn = financeGrid.dataset.gridGroup || '';
                let priorGroup = null;
                if (groupColumn && body) {
                    visible.forEach((row) => {
                        const group = financeGridCellValue(row, groupColumn) || 'Unassigned';
                        if (group !== priorGroup) {
                            const groupRow = document.createElement('tr');
                            groupRow.dataset.financeGridGroupRow = '';
                            const cell = document.createElement('th');
                            cell.colSpan = Math.max(1, financeGrid.querySelectorAll('thead th').length);
                            cell.scope = 'rowgroup';
                            cell.className = 'bg-muted/60 px-4 py-2 text-xs font-semibold';
                            cell.textContent = group;
                            groupRow.appendChild(cell);
                            body.insertBefore(groupRow, row);
                            priorGroup = group;
                        }
                    });
                }
                if (financeGridCount) financeGridCount.textContent = `${visible.length} ${visible.length === 1 ? 'row' : 'rows'}`;
                if (financeGridFilteredEmpty) financeGridFilteredEmpty.hidden = visible.length !== 0 || financeGridRows.length === 0;
            };
            financeGridSearch?.addEventListener('input', applyFinanceGrid);
            financeGridFilters.forEach((filter) => filter.addEventListener(filter.tagName === 'SELECT' ? 'change' : 'input', applyFinanceGrid));
            document.querySelectorAll('[data-finance-grid-sort]').forEach((button) => {
                button.addEventListener('click', () => {
                    const column = button.dataset.financeGridSort || '';
                    const current = financeGridSort.find((sort) => sort.column === column);
                    financeGridSort = [{ column, direction: current?.direction === 'ASC' ? 'DESC' : 'ASC' }];
                    if (financeGridSortInput) financeGridSortInput.value = JSON.stringify(financeGridSort);
                    document.querySelectorAll('[data-finance-grid-sort]').forEach((item) => {
                        const header = item.closest('th');
                        const active = financeGridSort.find((sort) => sort.column === item.dataset.financeGridSort);
                        header?.setAttribute('aria-sort', active ? (active.direction === 'ASC' ? 'ascending' : 'descending') : 'none');
                        const icon = item.querySelector('.material-symbols-rounded');
                        if (icon) icon.textContent = active ? (active.direction === 'ASC' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more';
                    });
                    applyFinanceGrid();
                });
            });
            applyFinanceGrid();

            let financeOperationLastTrigger = null;
            const openFinanceOperation = (modal, trigger) => {
                if (!modal) return;
                financeOperationLastTrigger = trigger;
                modal.hidden = false;
                syncShellModalState();
                setTimeout(() => modal.querySelector('input:not([type="hidden"]), select, button:not([disabled])')?.focus(), 0);
            };
            const closeFinanceOperation = (modal) => {
                if (!modal) return;
                modal.hidden = true;
                syncShellModalState();
                financeOperationLastTrigger?.focus?.();
            };
            document.querySelector('[data-finance-grid-view-open]')?.addEventListener('click', (event) => openFinanceOperation(financeGridViewModal, event.currentTarget));
            document.querySelector('[data-finance-grid-formula-open]')?.addEventListener('click', (event) => openFinanceOperation(financeGridFormulaModal, event.currentTarget));
            document.querySelector('[data-finance-grid-import-open]')?.addEventListener('click', (event) => openFinanceOperation(financeGridImportModal, event.currentTarget));
            document.querySelector('[data-finance-grid-view-select]')?.addEventListener('change', (event) => {
                window.location.href = event.currentTarget.value;
            });
            financeOperationModals.forEach((modal) => {
                modal.querySelectorAll('[data-finance-operation-close]').forEach((button) => button.addEventListener('click', () => closeFinanceOperation(modal)));
                modal.addEventListener('click', (event) => {
                    if (event.target === modal) closeFinanceOperation(modal);
                });
            });

            const financeColumnList = document.querySelector('[data-finance-grid-column-list]');
            const financeColumnsInput = document.querySelector('[data-finance-grid-columns-json]');
            const syncFinanceColumns = () => {
                if (!financeColumnList || !financeColumnsInput) return;
                financeColumnsInput.value = JSON.stringify(Array.from(financeColumnList.querySelectorAll('[data-finance-grid-column]')).map((column, index) => ({
                    key: column.dataset.columnKey || '',
                    visible: Boolean(column.querySelector('[data-finance-grid-column-toggle]')?.checked),
                    width: Math.max(80, Math.min(640, Number(column.querySelector('[data-finance-grid-column-width]')?.value || 160))),
                    order: (index + 1) * 10,
                })));
            };
            if (financeColumnList) {
                let draggedColumn = null;
                financeColumnList.addEventListener('input', syncFinanceColumns);
                financeColumnList.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-finance-grid-column-move]');
                    const column = button?.closest('[data-finance-grid-column]');
                    if (!button || !column) return;
                    if (button.dataset.financeGridColumnMove === 'up' && column.previousElementSibling) financeColumnList.insertBefore(column, column.previousElementSibling);
                    if (button.dataset.financeGridColumnMove === 'down' && column.nextElementSibling) financeColumnList.insertBefore(column.nextElementSibling, column);
                    syncFinanceColumns();
                });
                financeColumnList.addEventListener('dragstart', (event) => {
                    draggedColumn = event.target.closest('[data-finance-grid-column]');
                    if (draggedColumn) event.dataTransfer.effectAllowed = 'move';
                });
                financeColumnList.addEventListener('dragover', (event) => {
                    const target = event.target.closest('[data-finance-grid-column]');
                    if (!draggedColumn || !target || target === draggedColumn) return;
                    event.preventDefault();
                    const rect = target.getBoundingClientRect();
                    financeColumnList.insertBefore(draggedColumn, event.clientY < rect.top + rect.height / 2 ? target : target.nextElementSibling);
                });
                financeColumnList.addEventListener('drop', () => syncFinanceColumns());
                financeColumnList.addEventListener('dragend', () => { draggedColumn = null; syncFinanceColumns(); });
                syncFinanceColumns();
            }

            const financeCsvEscape = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
            document.querySelectorAll('[data-finance-grid-export]').forEach((button) => button.addEventListener('click', () => {
                if (!financeGrid) return;
                const headers = Array.from(financeGrid.querySelectorAll('thead th[data-column-key]'));
                const rows = financeGridRows.filter((row) => !row.hidden);
                const csv = [headers.map((header) => financeCsvEscape(header.textContent.trim())).join(',')]
                    .concat(rows.map((row) => headers.map((header) => financeCsvEscape(financeGridCellValue(row, header.dataset.columnKey || ''))).join(',')))
                    .join('\r\n');
                const blob = new Blob(['\ufeff', csv], { type: 'text/csv;charset=utf-8' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `finance-${new Date().toISOString().slice(0, 10)}.csv`;
                link.click();
                setTimeout(() => URL.revokeObjectURL(link.href), 0);
            }));

            document.querySelector('[data-general-ledger-export]')?.addEventListener('click', () => {
                const table = document.querySelector('[data-general-ledger-table] table');
                if (!table) return;
                const headers = Array.from(table.querySelectorAll('thead th')).map((header) => header.textContent.trim());
                const rows = Array.from(table.querySelectorAll('[data-general-ledger-row]')).map((row) =>
                    Array.from(row.querySelectorAll('td')).map((cell) => cell.dataset.ledgerValue ?? cell.textContent.trim())
                );
                const csv = [headers, ...rows].map((row) => row.map(financeCsvEscape).join(',')).join('\r\n');
                const blob = new Blob(['\ufeff', csv], { type: 'text/csv;charset=utf-8' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `general-ledger-${new Date().toISOString().slice(0, 10)}.csv`;
                link.click();
                setTimeout(() => URL.revokeObjectURL(link.href), 0);
            });

            const parseFinanceCsv = (text) => {
                const rows = [];
                let row = [], cell = '', quoted = false;
                for (let index = 0; index < text.length; index += 1) {
                    const character = text[index];
                    if (quoted && character === '"' && text[index + 1] === '"') { cell += '"'; index += 1; continue; }
                    if (character === '"') { quoted = !quoted; continue; }
                    if (!quoted && character === ',') { row.push(cell); cell = ''; continue; }
                    if (!quoted && (character === '\n' || character === '\r')) {
                        if (character === '\r' && text[index + 1] === '\n') index += 1;
                        row.push(cell); cell = '';
                        if (row.some((value) => value !== '')) rows.push(row);
                        row = [];
                        continue;
                    }
                    cell += character;
                }
                row.push(cell);
                if (row.some((value) => value !== '')) rows.push(row);
                return rows;
            };
            const importFile = document.querySelector('[data-finance-grid-import-file]');
            importFile?.addEventListener('change', async () => {
                const file = importFile.files?.[0];
                const preview = document.querySelector('[data-finance-grid-import-preview]');
                const confirm = document.querySelector('[data-finance-grid-import-confirm]');
                const jsonInput = document.querySelector('[data-finance-grid-import-json]');
                if (!file || !preview || !confirm || !jsonInput) return;
                confirm.disabled = true;
                jsonInput.value = '[]';
                preview.replaceChildren();
                if (file.size > 2 * 1024 * 1024) { preview.textContent = 'The CSV exceeds the 2 MB limit.'; return; }
                const parsed = parseFinanceCsv(await file.text());
                const headers = (parsed.shift() || []).map((header) => header.trim().toLowerCase().replaceAll(' ', '_'));
                const required = ['account_code', 'account_name', 'root_type'];
                const allowed = ['account_code', 'account_number', 'account_name', 'parent_account_code', 'root_type', 'account_type', 'account_currency', 'is_group', 'tax_rate', 'balance_must_be', 'freeze_account', 'include_in_gross', 'account_status', 'sort_order', 'account_notes'];
                const invalidHeaders = headers.filter((header) => !allowed.includes(header));
                const missing = required.filter((header) => !headers.includes(header));
                if (missing.length || invalidHeaders.length || parsed.length > 500 || parsed.length === 0) {
                    preview.textContent = missing.length ? `Missing required columns: ${missing.join(', ')}.` : invalidHeaders.length ? `Unsupported columns: ${invalidHeaders.join(', ')}.` : parsed.length > 500 ? 'The CSV exceeds the 500-row limit.' : 'The CSV has no data rows.';
                    return;
                }
                const records = parsed.map((values) => Object.fromEntries(headers.map((header, index) => [header, String(values[index] || '').trim()])));
                const invalidRow = records.find((record) => !record.account_code || !record.account_name || !['ASSET', 'LIABILITY', 'INCOME', 'EXPENSE', 'EQUITY'].includes(record.root_type.toUpperCase()));
                if (invalidRow) { preview.textContent = 'Every row needs account_code, account_name, and a valid root_type.'; return; }
                const table = document.createElement('table');
                table.className = 'w-full min-w-max text-left text-xs';
                const head = document.createElement('thead');
                const headRow = document.createElement('tr');
                headers.forEach((header) => { const th = document.createElement('th'); th.className = 'border-b px-2 py-2 font-semibold'; th.textContent = header; headRow.appendChild(th); });
                head.appendChild(headRow); table.appendChild(head);
                const body = document.createElement('tbody');
                records.slice(0, 20).forEach((record) => { const tr = document.createElement('tr'); headers.forEach((header) => { const td = document.createElement('td'); td.className = 'border-b px-2 py-2'; td.textContent = record[header]; tr.appendChild(td); }); body.appendChild(tr); });
                table.appendChild(body);
                const shell = document.createElement('div'); shell.className = 'overflow-auto'; shell.appendChild(table);
                const summary = document.createElement('p'); summary.className = 'mb-3 font-medium text-foreground'; summary.textContent = `${records.length} valid rows ready to import.`;
                preview.append(summary, shell);
                jsonInput.value = JSON.stringify(records);
                confirm.disabled = false;
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') return;
                const modal = financeOperationModals.find((item) => !item.hidden);
                if (modal) { event.preventDefault(); closeFinanceOperation(modal); }
            });

            sidebarToggle?.addEventListener('click', () => {
                const collapsed = document.documentElement.dataset.sidebarCollapsed === 'true';
                if (collapsed) {
                    delete document.documentElement.dataset.sidebarCollapsed;
                    window.localStorage.setItem(sidebarStorageKey, 'expanded');
                    sidebarToggle.setAttribute('aria-pressed', 'false');
                    return;
                }
                document.documentElement.dataset.sidebarCollapsed = 'true';
                window.localStorage.setItem(sidebarStorageKey, 'collapsed');
                sidebarToggle.setAttribute('aria-pressed', 'true');
            });

            sidebarToggle?.setAttribute(
                'aria-pressed',
                document.documentElement.dataset.sidebarCollapsed === 'true' ? 'true' : 'false'
            );

            const applyDepartmentTemplate = () => {
                if (!departmentTemplate || departmentSource?.value !== 'ERP_DEFAULT') {
                    return;
                }
                const option = departmentTemplate.selectedOptions?.[0];
                if (!option) {
                    return;
                }
                if (departmentCode) departmentCode.value = option.dataset.code || '';
                if (departmentName) departmentName.value = option.dataset.name || '';
                if (departmentType) departmentType.value = option.dataset.type || 'OPERATIONS';
                if (departmentDescription) departmentDescription.value = option.dataset.description || '';
            };

            departmentTemplate?.addEventListener('change', applyDepartmentTemplate);
            departmentSource?.addEventListener('change', () => {
                if (departmentSource.value === 'ERP_DEFAULT') {
                    applyDepartmentTemplate();
                }
            });
        })();
    </script>
