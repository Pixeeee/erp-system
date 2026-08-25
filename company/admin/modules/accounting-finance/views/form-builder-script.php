<script>
(() => {
    'use strict';

    const typeLabels = {
        SHORT_TEXT: 'Short text', PARAGRAPH: 'Paragraph', NUMBER: 'Number', CURRENCY: 'Currency',
        DATE: 'Date', DROPDOWN: 'Dropdown', CHECKBOXES: 'Checkboxes', ACCOUNT: 'Account',
        PARTY: 'Party', SECTION: 'Section'
    };
    let sequence = 0;
    const key = (prefix) => `${prefix}_${Date.now()}_${++sequence}`;
    const element = (tag, className = '', text = '') => {
        const node = document.createElement(tag);
        node.className = className;
        if (text) node.textContent = text;
        return node;
    };

    document.querySelectorAll('[data-finance-layout-builder]').forEach((builder) => {
        const input = builder.querySelector('[data-finance-builder-schema]');
        const canvas = builder.querySelector('[data-finance-builder-canvas]');
        const settings = builder.querySelector('[data-finance-builder-settings-container]');
        const count = builder.querySelector('[data-finance-builder-count]');
        const previewPanel = builder.querySelector('[data-finance-builder-preview-panel]');
        const previewCanvas = builder.querySelector('[data-finance-builder-preview-canvas]');
        if (!input || !canvas || !settings) return;

        let schema;
        try { schema = JSON.parse(input.value || '{}'); } catch (error) { schema = {}; }
        schema.version = 2;
        schema.rows = Array.isArray(schema.rows) && schema.rows.length ? schema.rows : [{key: 'default-row', order: 10, columns: [{key: 'default-column', width: 12, order: 10}]}];
        schema.questions = Array.isArray(schema.questions) ? schema.questions : [];
        schema.rows.forEach((row, rowIndex) => {
            row.key ||= key('row');
            row.order = (rowIndex + 1) * 10;
            row.columns = Array.isArray(row.columns) && row.columns.length ? row.columns.slice(0, 3) : [{key: key('column'), width: 12, order: 10}];
            row.columns.forEach((column, columnIndex) => {
                column.key ||= key('column');
                column.order = (columnIndex + 1) * 10;
                column.width = Math.max(1, Math.min(12, Number(column.width || 12)));
            });
        });
        const allColumns = () => schema.rows.flatMap((row) => row.columns.map((column) => ({row, column})));
        let selectedColumn = allColumns()[0]?.column.key || '';
        let selectedKey = schema.questions[0]?.key || '';
        let draggedKey = '';
        schema.questions = schema.questions.map((question, index) => {
            const location = allColumns().find(({column}) => column.key === question.column_key) || allColumns()[0];
            return {
                key: String(question.key || key('field')), label: String(question.label || 'Untitled field'),
                help: String(question.help || ''), type: Object.hasOwn(typeLabels, question.type) ? question.type : 'SHORT_TEXT',
                required: Boolean(question.required), visible: question.visible !== false,
                options: Array.isArray(question.options) ? question.options.map(String) : [],
                precision: Math.max(0, Math.min(6, Number(question.precision || 0))), default: question.default ?? '',
                validation: question.validation && typeof question.validation === 'object' ? question.validation : {},
                row_key: location.row.key, column_key: location.column.key, order: (index + 1) * 10
            };
        });

        const sync = () => {
            schema.rows.forEach((row, rowIndex) => {
                row.order = (rowIndex + 1) * 10;
                row.columns.forEach((column, columnIndex) => column.order = (columnIndex + 1) * 10);
            });
            schema.questions.forEach((question, index) => question.order = (index + 1) * 10);
            input.value = JSON.stringify(schema);
            if (count) count.textContent = String(schema.questions.length);
        };

        const moveRow = (row, direction) => {
            const index = schema.rows.indexOf(row);
            const target = direction === 'up' ? index - 1 : index + 1;
            if (target < 0 || target >= schema.rows.length) return;
            schema.rows.splice(target, 0, schema.rows.splice(index, 1)[0]);
            sync(); render();
        };

        const setColumns = (row, total) => {
            total = Math.max(1, Math.min(3, total));
            while (row.columns.length < total) row.columns.push({key: key('column'), width: Math.floor(12 / total), order: row.columns.length * 10 + 10});
            if (row.columns.length > total) {
                const removed = row.columns.splice(total);
                const destination = row.columns[row.columns.length - 1];
                schema.questions.forEach((question) => {
                    if (removed.some((column) => column.key === question.column_key)) {
                        question.row_key = row.key; question.column_key = destination.key;
                    }
                });
            }
            row.columns.forEach((column) => column.width = Math.floor(12 / total));
            selectedColumn = row.columns[0].key;
            sync(); render();
        };

        const moveQuestion = (questionKey, columnKey, beforeKey = '') => {
            const questionIndex = schema.questions.findIndex((question) => question.key === questionKey);
            const destination = allColumns().find(({column}) => column.key === columnKey);
            if (questionIndex < 0 || !destination) return;
            const [question] = schema.questions.splice(questionIndex, 1);
            question.row_key = destination.row.key;
            question.column_key = destination.column.key;
            const beforeIndex = beforeKey ? schema.questions.findIndex((candidate) => candidate.key === beforeKey) : -1;
            schema.questions.splice(beforeIndex >= 0 ? beforeIndex : schema.questions.length, 0, question);
            selectedColumn = columnKey;
            sync(); render();
        };

        const renderCard = (question) => {
            const card = element('article', 'rounded-md border bg-background p-3');
            card.draggable = true;
            card.tabIndex = 0;
            card.dataset.questionKey = question.key;
            card.setAttribute('aria-selected', question.key === selectedKey ? 'true' : 'false');
            const title = element('p', 'truncate text-sm font-semibold', question.label);
            const meta = element('p', 'mt-1 text-xs text-muted-foreground', `${typeLabels[question.type]} · ${question.key}`);
            card.append(title, meta);
            card.addEventListener('click', () => { selectedKey = question.key; renderSettings(); renderCanvas(); });
            card.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); selectedKey = question.key; renderSettings(); renderCanvas(); }
            });
            card.addEventListener('dragstart', (event) => { draggedKey = question.key; event.dataTransfer?.setData('text/plain', question.key); });
            card.addEventListener('dragover', (event) => event.preventDefault());
            card.addEventListener('drop', (event) => { event.preventDefault(); moveQuestion(draggedKey, question.column_key, question.key); draggedKey = ''; });
            return card;
        };

        const renderCanvas = () => {
            canvas.replaceChildren();
            schema.rows.forEach((row, rowIndex) => {
                const rowNode = element('section', 'grid gap-2 rounded-md bg-muted/40 p-3');
                rowNode.dataset.financeBuilderRow = row.key;
                const header = element('div', 'flex items-center justify-between gap-2');
                header.appendChild(element('span', 'text-xs font-semibold', `Row ${rowIndex + 1}`));
                const controls = element('div', 'flex items-center gap-1');
                const columns = element('select', 'h-8 rounded-md border bg-background px-2 text-xs');
                columns.setAttribute('aria-label', `Columns in row ${rowIndex + 1}`);
                [1, 2, 3].forEach((value) => { const option = element('option', '', `${value} column${value > 1 ? 's' : ''}`); option.value = String(value); option.selected = row.columns.length === value; columns.appendChild(option); });
                columns.addEventListener('change', () => setColumns(row, Number(columns.value)));
                ['up', 'down'].forEach((direction) => { const button = element('button', 'size-8 rounded-md border bg-background text-xs', direction === 'up' ? '↑' : '↓'); button.type = 'button'; button.setAttribute('aria-label', `Move row ${direction}`); button.addEventListener('click', () => moveRow(row, direction)); controls.appendChild(button); });
                controls.prepend(columns); header.appendChild(controls); rowNode.appendChild(header);
                const columnsNode = element('div', 'grid gap-2');
                columnsNode.style.gridTemplateColumns = `repeat(${row.columns.length}, minmax(0, 1fr))`;
                row.columns.forEach((column, columnIndex) => {
                    const columnNode = element('div', 'grid min-h-24 content-start gap-2 rounded-md border border-dashed bg-background/60 p-2');
                    columnNode.dataset.financeBuilderColumn = column.key;
                    columnNode.setAttribute('aria-label', `Row ${rowIndex + 1}, column ${columnIndex + 1}`);
                    columnNode.addEventListener('click', () => { selectedColumn = column.key; });
                    columnNode.addEventListener('dragover', (event) => event.preventDefault());
                    columnNode.addEventListener('drop', (event) => { event.preventDefault(); moveQuestion(draggedKey, column.key); draggedKey = ''; });
                    schema.questions.filter((question) => question.column_key === column.key).forEach((question) => columnNode.appendChild(renderCard(question)));
                    if (!columnNode.children.length) columnNode.appendChild(element('span', 'p-3 text-center text-xs text-muted-foreground', 'Drop fields here'));
                    columnsNode.appendChild(columnNode);
                });
                rowNode.appendChild(columnsNode); canvas.appendChild(rowNode);
            });
        };

        const control = (label, node) => { const group = element('label', 'grid gap-1.5 text-xs font-medium', label); group.appendChild(node); return group; };
        const renderSettings = () => {
            settings.replaceChildren();
            const question = schema.questions.find((item) => item.key === selectedKey);
            if (!question) { settings.appendChild(element('div', 'rounded-md border border-dashed p-4 text-sm text-muted-foreground', 'No field selected.')); return; }
            const panel = element('section', 'grid gap-3');
            const textInput = (value = '') => { const node = element('input', 'h-9 rounded-md border bg-background px-3 text-sm'); node.value = String(value ?? ''); return node; };
            const label = textInput(question.label); label.maxLength = 180;
            const fieldKey = textInput(question.key); fieldKey.maxLength = 120;
            const type = element('select', 'h-9 rounded-md border bg-background px-3 text-sm');
            Object.entries(typeLabels).forEach(([value, text]) => { const option = element('option', '', text); option.value = value; option.selected = value === question.type; type.appendChild(option); });
            const help = element('textarea', 'min-h-16 rounded-md border bg-background p-2 text-sm'); help.value = question.help;
            const options = element('textarea', 'min-h-16 rounded-md border bg-background p-2 text-sm'); options.value = question.options.join('\n');
            const defaultValue = textInput(Array.isArray(question.default) ? question.default.join(', ') : question.default);
            const pattern = textInput(question.validation.pattern || '');
            const required = document.createElement('input'); required.type = 'checkbox'; required.checked = question.required;
            const visible = document.createElement('input'); visible.type = 'checkbox'; visible.checked = question.visible;
            panel.append(control('Field label', label), control('Stable field key', fieldKey), control('Field type', type), control('Help text', help), control('Options, one per line', options), control('Default value', defaultValue), control('Validation pattern', pattern));
            const toggles = element('div', 'flex flex-wrap gap-4');
            const requiredLabel = element('label', 'inline-flex items-center gap-2 text-sm', 'Required'); requiredLabel.prepend(required);
            const visibleLabel = element('label', 'inline-flex items-center gap-2 text-sm', 'Visible'); visibleLabel.prepend(visible); toggles.append(requiredLabel, visibleLabel); panel.appendChild(toggles);
            const actions = element('div', 'flex gap-2 border-t pt-3');
            const duplicate = element('button', 'h-8 rounded-md border px-2 text-xs', 'Duplicate'); duplicate.type = 'button';
            const remove = element('button', 'h-8 rounded-md border border-destructive/40 px-2 text-xs text-destructive', 'Delete'); remove.type = 'button'; actions.append(duplicate, remove); panel.appendChild(actions); settings.appendChild(panel);
            label.addEventListener('input', () => { question.label = label.value; sync(); renderCanvas(); renderPreview(); });
            fieldKey.addEventListener('change', () => { const next = fieldKey.value.trim(); if (!next || schema.questions.some((item) => item !== question && item.key === next)) { fieldKey.setCustomValidity('Field keys must be unique.'); fieldKey.reportValidity(); return; } fieldKey.setCustomValidity(''); question.key = next; selectedKey = next; sync(); renderCanvas(); });
            type.addEventListener('change', () => { question.type = type.value; if (question.type === 'SECTION') question.required = false; sync(); render(); });
            help.addEventListener('input', () => { question.help = help.value; sync(); });
            options.addEventListener('input', () => { question.options = options.value.split(/\r?\n/).map((item) => item.trim()).filter(Boolean); sync(); renderPreview(); });
            defaultValue.addEventListener('input', () => { question.default = defaultValue.value; sync(); renderPreview(); });
            pattern.addEventListener('input', () => { question.validation.pattern = pattern.value; sync(); });
            required.addEventListener('change', () => { question.required = required.checked && question.type !== 'SECTION'; sync(); renderPreview(); });
            visible.addEventListener('change', () => { question.visible = visible.checked; sync(); renderPreview(); });
            duplicate.addEventListener('click', () => { const copy = structuredClone(question); copy.key = key('field'); copy.label += ' copy'; schema.questions.push(copy); selectedKey = copy.key; sync(); render(); });
            remove.addEventListener('click', () => { schema.questions = schema.questions.filter((item) => item !== question); selectedKey = schema.questions[0]?.key || ''; sync(); render(); });
        };

        const renderPreview = () => {
            if (!previewCanvas) return;
            previewCanvas.replaceChildren();
            schema.rows.forEach((row) => row.columns.forEach((column) => schema.questions.filter((question) => question.visible && question.column_key === column.key).forEach((question) => {
                if (question.type === 'SECTION') { previewCanvas.appendChild(element('h6', 'col-span-full border-b pb-2 text-sm font-semibold', question.label)); return; }
                const group = element('label', 'grid gap-1 text-xs font-medium', `${question.label}${question.required ? ' *' : ''}`);
                const field = question.type === 'PARAGRAPH' ? element('textarea', 'min-h-16 rounded-md border bg-background p-2') : element('input', 'h-9 rounded-md border bg-background px-3');
                field.disabled = true; field.value = Array.isArray(question.default) ? question.default.join(', ') : String(question.default || ''); group.appendChild(field); previewCanvas.appendChild(group);
            })));
        };
        const render = () => { sync(); renderCanvas(); renderSettings(); renderPreview(); };

        builder.querySelectorAll('[data-finance-builder-preset]').forEach((button) => button.addEventListener('click', () => {
            const location = allColumns().find(({column}) => column.key === selectedColumn) || allColumns()[0];
            if (!location) return;
            const type = button.dataset.financeBuilderPreset || 'SHORT_TEXT';
            const question = {key: key('field'), label: button.dataset.financeBuilderPresetLabel || 'Untitled field', help: '', type, required: false, visible: true, options: ['DROPDOWN', 'CHECKBOXES'].includes(type) ? ['Option 1'] : [], precision: ['NUMBER', 'CURRENCY'].includes(type) ? 2 : 0, default: '', validation: {}, row_key: location.row.key, column_key: location.column.key, order: 0};
            schema.questions.push(question); selectedKey = question.key; render();
        }));
        builder.querySelector('[data-finance-builder-add-row]')?.addEventListener('click', () => { const row = {key: key('row'), order: 0, columns: [{key: key('column'), width: 12, order: 10}]}; schema.rows.push(row); selectedColumn = row.columns[0].key; render(); });
        builder.querySelector('[data-finance-builder-preview]')?.addEventListener('click', () => { renderPreview(); if (previewPanel) previewPanel.hidden = false; });
        builder.querySelector('[data-finance-builder-preview-close]')?.addEventListener('click', () => { if (previewPanel) previewPanel.hidden = true; });
        builder.addEventListener('submit', sync);
        render();
    });
})();
</script>
