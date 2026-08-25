<script>
(() => {
    'use strict';

    document.querySelectorAll('[data-record-modal][role="dialog"]:not([aria-labelledby]):not([aria-label])').forEach((modal) => {
        const title = modal.querySelector('h1, h2, h3, h4');
        modal.setAttribute('aria-label', title?.textContent?.trim() || 'Finance dialog');
    });

    const assignValue = (field, value) => {
        if (field.type === 'checkbox' || field.type === 'radio') {
            const values = Array.isArray(value) ? value.map(String) : [String(value ?? '')];
            field.checked = values.includes(String(field.value)) || value === true || value === 1;
            return;
        }
        if (field.type === 'file') return;
        field.value = Array.isArray(value) ? value.map(String) : String(value ?? '');
        field.dispatchEvent(new Event('change', { bubbles: true }));
    };

    document.querySelectorAll('[data-record-modal][data-finance-rehydrate]').forEach((modal) => {
        let values = {};
        try {
            values = JSON.parse(modal.dataset.financeRehydrate || '{}');
        } catch (error) {
            values = {};
        }
        Object.entries(values).forEach(([name, value]) => {
            const escapedName = window.CSS?.escape ? CSS.escape(name) : name.replaceAll('"', '\\"');
            modal.querySelectorAll(`[name="${escapedName}"]`).forEach((field) => assignValue(field, value));
        });
    });
})();
</script>
