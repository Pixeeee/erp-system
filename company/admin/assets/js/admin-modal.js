(() => {
    'use strict';

    const confirmation = document.querySelector('[data-confirm-dialog]');
    const confirmAction = confirmation?.querySelector('[data-confirm-action]');
    const confirmCancel = confirmation?.querySelector('[data-confirm-cancel]');
    const confirmDescription = confirmation?.querySelector('[data-confirm-description]');
    let pendingForm = null;
    let pendingSubmitter = null;
    let suspendedModal = null;
    let confirmed = false;

    const focusable = (container) => Array.from(container?.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    ) || []).filter((element) => !element.hidden && element.getClientRects().length > 0);

    const setRecordModalOpen = (modal, open, opener = null) => {
        if (!modal) return;
        modal.hidden = !open;
        modal.toggleAttribute('inert', !open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.classList.toggle('yovel-shell-modal-active', Boolean(document.querySelector('[data-record-modal]:not([hidden])')));
        if (open) {
            modal.dataset.recordModalOpener = opener?.id || '';
            window.setTimeout(() => focusable(modal)[0]?.focus(), 0);
        } else if (opener) {
            opener.focus();
        }
    };

    document.querySelectorAll('[data-record-modal-open]').forEach((button, index) => {
        if (!button.id) button.id = `record-modal-opener-${index + 1}`;
        button.addEventListener('click', () => setRecordModalOpen(document.getElementById(button.dataset.recordModalOpen || ''), true, button));
    });
    document.querySelectorAll('[data-record-modal-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = button.closest('[data-record-modal]');
            const opener = modal?.dataset.recordModalOpener ? document.getElementById(modal.dataset.recordModalOpener) : null;
            setRecordModalOpen(modal, false, opener);
        });
    });
    document.querySelectorAll('[data-record-modal-open-on-load]').forEach((modal) => setRecordModalOpen(modal, true));

    const restorePendingModal = () => {
        if (!suspendedModal) return;
        suspendedModal.removeAttribute('inert');
        suspendedModal.setAttribute('aria-hidden', 'false');
    };

    const closeConfirmation = (restoreFocus) => {
        confirmation.hidden = true;
        confirmation.setAttribute('aria-hidden', 'true');
        restorePendingModal();
        if (restoreFocus) pendingSubmitter?.focus();
    };

    document.querySelectorAll('form[data-confirm-submit]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                delete form.dataset.confirmed;
                confirmed = false;
                return;
            }
            if (!form.checkValidity()) return;

            event.preventDefault();
            pendingForm = form;
            pendingSubmitter = event.submitter || form.querySelector('[type="submit"]');
            suspendedModal = form.closest('[data-record-modal], [role="dialog"]');
            if (suspendedModal && suspendedModal !== confirmation) {
                suspendedModal.setAttribute('inert', '');
                suspendedModal.setAttribute('aria-hidden', 'true');
            }
            if (confirmDescription) {
                confirmDescription.textContent = form.dataset.confirmMessage || 'Review and confirm this submission before it is saved.';
            }
            confirmation.hidden = false;
            confirmation.setAttribute('aria-hidden', 'false');
            confirmAction.disabled = false;
            confirmAction.focus();
        });
    });

    confirmAction?.addEventListener('click', () => {
        if (!pendingForm || confirmed) return;
        confirmed = true;
        confirmAction.disabled = true;
        const form = pendingForm;
        closeConfirmation(false);
        form.dataset.confirmed = 'true';
        form.requestSubmit();
        pendingForm = null;
        pendingSubmitter = null;
        suspendedModal = null;
    });

    confirmCancel?.addEventListener('click', () => {
        closeConfirmation(true);
        confirmed = false;
        pendingForm = null;
        pendingSubmitter = null;
        suspendedModal = null;
    });

    confirmation?.addEventListener('click', (event) => {
        if (event.target === confirmation) confirmCancel?.click();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && confirmation && !confirmation.hidden) {
            event.preventDefault();
            confirmCancel?.click();
        }
    });
})();
