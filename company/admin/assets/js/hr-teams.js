(() => {
    const workspace = document.querySelector('.yovel-team-layout');
    if (!workspace) {
        return;
    }

    const modal = document.getElementById('yovel-team-modal');
    const modalOpen = document.getElementById('yovel-team-modal-open');
    const modalClose = document.getElementById('yovel-team-modal-close');
    const modalCancel = document.getElementById('yovel-team-modal-cancel');
    const featureButtons = Array.from(document.querySelectorAll('[data-team-section-target]'));
    const modalSections = Array.from(document.querySelectorAll('[data-team-modal-section]'));
    const openShortcuts = Array.from(document.querySelectorAll('[data-team-open-shortcut]'));
    const widgetBoard = document.getElementById('yovel-team-widget-board');
    const dropTargets = Array.from(document.querySelectorAll('[data-team-drop-target]'));
    const filterControls = Array.from(document.querySelectorAll('[data-team-filter]'));
    const clearFilters = document.querySelector('[data-team-clear-filters]');
    const resultCount = document.querySelector('[data-team-result-count]');
    const noResults = document.querySelector('[data-team-no-results]');
    const editMode = modal?.dataset.teamEditMode === 'true';
    const initialSection = modal?.dataset.teamInitialSection || 'overview';
    const storageKey = workspace.dataset.teamWidgetStorageKey || 'builderx:team-widgets';
    let modalLastTrigger = modalOpen;
    let draggedWidget = null;

    const syncShellModalState = () => {
        const openModal = document.querySelector('.yovel-employee-modal:not([hidden]), .yovel-form-builder-modal:not([hidden]), .yovel-tour-overlay:not([hidden])');
        document.body.classList.toggle('yovel-shell-modal-active', Boolean(openModal));
    };

    const setModalSection = (sectionKey) => {
        const nextSection = modalSections.some((section) => section.dataset.teamModalSection === sectionKey)
            ? sectionKey
            : 'overview';
        modalSections.forEach((section) => {
            section.hidden = section.dataset.teamModalSection !== nextSection;
        });
        featureButtons.forEach((button) => {
            const active = button.dataset.teamSectionTarget === nextSection;
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            if (button.getAttribute('role') === 'tab') {
                button.setAttribute('aria-selected', active ? 'true' : 'false');
                button.tabIndex = active ? 0 : -1;
            }
        });
        return nextSection;
    };

    const openModalAt = (sectionKey = 'overview', trigger = modalOpen) => {
        if (!modal) {
            return;
        }
        modalLastTrigger = trigger || modalOpen;
        const activeSection = setModalSection(sectionKey);
        modal.hidden = false;
        syncShellModalState();
        const firstField = modal.querySelector(`[data-team-modal-section="${activeSection}"] input:not([disabled]), [data-team-modal-section="${activeSection}"] select:not([disabled]), [data-team-modal-section="${activeSection}"] textarea:not([disabled])`);
        setTimeout(() => firstField?.focus(), 0);
    };

    const closeModal = () => {
        if (!modal) {
            return;
        }
        if (editMode) {
            window.location.href = './?view=hr&section=teams';
            return;
        }
        modal.hidden = true;
        syncShellModalState();
        setTimeout(() => modalLastTrigger?.focus?.(), 0);
    };

    const teamSectionUrl = (teamKey, sectionKey = 'overview') => {
        const params = new URLSearchParams({
            view: 'hr',
            section: 'teams',
            edit: teamKey,
            team_section: sectionKey,
        });
        return `./?${params.toString()}`;
    };

    const selectedTeamTarget = () => {
        const checkbox = document.querySelector('[data-team-drop-target] input[type="checkbox"]:checked');
        return checkbox?.closest('[data-team-drop-target]') || null;
    };

    const openSection = (sectionKey = 'overview', trigger = modalOpen) => {
        if (editMode || (modal && !modal.hidden)) {
            openModalAt(sectionKey, trigger);
            return;
        }
        const selected = selectedTeamTarget();
        const teamKey = selected?.dataset.teamKey || '';
        if (teamKey) {
            window.location.href = teamSectionUrl(teamKey, sectionKey);
            return;
        }
        openModalAt(sectionKey, trigger);
    };

    const applyFilters = () => {
        const rows = Array.from(document.querySelectorAll('[data-team-row]'));
        const values = Object.fromEntries(filterControls.map((control) => [control.dataset.teamFilter || '', String(control.value || '').trim().toLowerCase()]));
        let visible = 0;
        rows.forEach((row) => {
            const matches = Object.entries(values).every(([key, value]) => !key || !value || String(row.dataset[`team${key.charAt(0).toUpperCase()}${key.slice(1)}`] || '').includes(value));
            row.hidden = !matches;
            if (matches) {
                visible += 1;
            }
        });
        if (resultCount) {
            resultCount.textContent = String(visible);
        }
        if (noResults) {
            noResults.hidden = visible !== 0;
        }
    };

    const widgetItems = () => Array.from(widgetBoard?.querySelectorAll('[data-team-widget-key]') || []);
    const widgetSection = (item) => item?.querySelector('[data-team-section-target]')?.dataset.teamSectionTarget || 'overview';
    const widgetTitle = (item) => item?.querySelector('[data-team-section-target]')?.textContent?.trim() || 'section';
    const saveOrder = () => {
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(widgetItems().map((item) => item.dataset.teamWidgetKey || '')));
        } catch (error) {
        }
    };
    const restoreOrder = () => {
        if (!widgetBoard) {
            return;
        }
        try {
            const order = JSON.parse(window.localStorage.getItem(storageKey) || '[]');
            if (!Array.isArray(order)) {
                return;
            }
            order.forEach((key) => {
                const safeKey = window.CSS?.escape ? CSS.escape(String(key)) : String(key).replace(/"/g, '\\"');
                const item = widgetBoard.querySelector(`[data-team-widget-key="${safeKey}"]`);
                if (item) {
                    widgetBoard.appendChild(item);
                }
            });
        } catch (error) {
        }
    };
    const setIndicator = (row, text) => {
        const indicator = row.querySelector('[data-team-drop-indicator]');
        if (indicator) {
            indicator.textContent = text;
        }
    };
    const clearDropFeedback = () => {
        dropTargets.forEach((row) => {
            row.classList.remove('yovel-employee-drop-ready', 'yovel-employee-drop-active');
            setIndicator(row, 'Apply section here');
        });
    };

    modalOpen?.addEventListener('click', () => openModalAt('overview', modalOpen));
    openShortcuts.forEach((button) => button.addEventListener('click', () => openModalAt('overview', button)));
    modalClose?.addEventListener('click', closeModal);
    modalCancel?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hidden) {
            closeModal();
        }
    });
    featureButtons.forEach((button) => button.addEventListener('click', () => openSection(button.dataset.teamSectionTarget || 'overview', button)));
    filterControls.forEach((control) => control.addEventListener(control.tagName === 'SELECT' ? 'change' : 'input', applyFilters));
    clearFilters?.addEventListener('click', () => {
        filterControls.forEach((control) => {
            control.value = '';
        });
        applyFilters();
        filterControls[0]?.focus();
    });
    dropTargets.forEach((row) => {
        const checkbox = row.querySelector('input[type="checkbox"]');
        checkbox?.addEventListener('change', () => {
            if (!checkbox.checked) {
                return;
            }
            dropTargets.forEach((candidate) => {
                if (candidate !== row) {
                    const candidateCheckbox = candidate.querySelector('input[type="checkbox"]');
                    if (candidateCheckbox) {
                        candidateCheckbox.checked = false;
                    }
                }
            });
        });
        row.addEventListener('dragover', (event) => {
            if (!draggedWidget) {
                return;
            }
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            row.classList.add('yovel-employee-drop-active');
            setIndicator(row, `Release to open ${widgetTitle(draggedWidget)}`);
        });
        row.addEventListener('dragleave', (event) => {
            if (!row.contains(event.relatedTarget)) {
                row.classList.remove('yovel-employee-drop-active');
                if (draggedWidget) {
                    setIndicator(row, `Apply ${widgetTitle(draggedWidget)} here`);
                }
            }
        });
        row.addEventListener('drop', (event) => {
            if (!draggedWidget) {
                return;
            }
            event.preventDefault();
            const teamKey = row.dataset.teamKey || '';
            const sectionKey = widgetSection(draggedWidget);
            clearDropFeedback();
            if (teamKey) {
                window.location.href = teamSectionUrl(teamKey, sectionKey);
            }
        });
    });

    if (widgetBoard) {
        restoreOrder();
        widgetItems().forEach((item) => {
            item.tabIndex = 0;
            item.addEventListener('click', (event) => {
                if (!event.target.closest('.yovel-widget-move-up, .yovel-widget-move-down, [data-team-section-target]')) {
                    openSection(widgetSection(item), item);
                }
            });
            item.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openSection(widgetSection(item), item);
                }
            });
            item.addEventListener('dragstart', (event) => {
                draggedWidget = item;
                item.setAttribute('aria-grabbed', 'true');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', widgetSection(item));
                dropTargets.forEach((row) => {
                    row.classList.add('yovel-employee-drop-ready');
                    setIndicator(row, `Apply ${widgetTitle(item)} here`);
                });
            });
            item.addEventListener('dragend', () => {
                item.setAttribute('aria-grabbed', 'false');
                draggedWidget = null;
                clearDropFeedback();
                saveOrder();
            });
        });
        widgetBoard.querySelectorAll('.yovel-widget-move-up').forEach((button) => button.addEventListener('click', () => {
            const item = button.closest('[data-team-widget-key]');
            if (item?.previousElementSibling) {
                widgetBoard.insertBefore(item, item.previousElementSibling);
                saveOrder();
                item.focus();
            }
        }));
        widgetBoard.querySelectorAll('.yovel-widget-move-down').forEach((button) => button.addEventListener('click', () => {
            const item = button.closest('[data-team-widget-key]');
            if (item?.nextElementSibling) {
                widgetBoard.insertBefore(item.nextElementSibling, item);
                saveOrder();
                item.focus();
            }
        }));
    }

    setModalSection(initialSection);
    applyFilters();
    syncShellModalState();
})();
