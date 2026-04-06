document.addEventListener('DOMContentLoaded', () => {
    setupRevealAnimations();
    setupAvailabilityPlanner();
    setupCategorySorting();
});

function setupRevealAnimations() {
    const revealTargets = document.querySelectorAll('.admin-card, .admin-note, .stat-card');

    if (revealTargets.length === 0) {
        return;
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
        revealTargets.forEach((element) => element.classList.add('is-visible'));
        return;
    }

    revealTargets.forEach((element) => {
        element.setAttribute('data-reveal', '');
    });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    revealTargets.forEach((element) => observer.observe(element));
}

function setupAvailabilityPlanner() {
    const planner = document.querySelector('[data-availability-planner]');
    const form = document.querySelector('[data-availability-planner-form]');

    if (!planner || !form) {
        return;
    }

    const undoButton = form.querySelector('[data-undo-change]');
    const resetButtons = Array.from(form.querySelectorAll('[data-reset-changes]'));
    const summaryTotal = form.querySelector('[data-summary-total]');
    const summaryAdded = form.querySelector('[data-summary-added]');
    const summaryRemoved = form.querySelector('[data-summary-removed]');

    const hiddenWindows = form.querySelector('input[name="planner_windows"]');
    const cells = Array.from(planner.querySelectorAll('.planner-cell'));
    const quickEntry = form.querySelector('[data-availability-quick-entry]');
    const quickDay = quickEntry ? quickEntry.querySelector('[data-quick-day]') : null;
    const quickStart = quickEntry ? quickEntry.querySelector('[data-quick-start]') : null;
    const quickEnd = quickEntry ? quickEntry.querySelector('[data-quick-end]') : null;
    const quickAdd = quickEntry ? quickEntry.querySelector('[data-quick-add]') : null;
    const quickRemove = quickEntry ? quickEntry.querySelector('[data-quick-remove]') : null;
    const quickClearDay = quickEntry ? quickEntry.querySelector('[data-quick-clear-day]') : null;
    const quickPresetDay = quickEntry ? quickEntry.querySelector('[data-quick-preset-day]') : null;
    const quickPresetWeek = quickEntry ? quickEntry.querySelector('[data-quick-preset-week]') : null;
    const quickStatus = quickEntry ? quickEntry.querySelector('[data-quick-status]') : null;
    const quickDayChipButtons = quickEntry ? Array.from(quickEntry.querySelectorAll('[data-quick-day-chip]')) : [];
    const detailWrap = form.querySelector('.availability-detail-wrap');
    const modeTriggers = Array.from(form.querySelectorAll('[data-planner-mode-trigger]'));
    const activeSlots = new Set();
    const cellsByKey = new Map();
    const undoStack = [];
    let initialEditableSlots = new Set();
    const initialWindowsRaw = planner.dataset.initialWindows || '[]';
    const bookedWindowsRaw = planner.dataset.bookedWindows || '[]';
    const now = new Date();
    let isDragging = false;
    let dragMode = 'add';

    const slotKey = (date, time) => `${date}|${time}`;
    const isEditableCell = (cell) => !cell.classList.contains('is-past') && !cell.classList.contains('is-booked');
    const editableSlotKeys = () => {
        const keys = new Set();
        cells.forEach((cell) => {
            if (!isEditableCell(cell)) {
                return;
            }
            const key = slotKey(cell.dataset.date, cell.dataset.time);
            keys.add(key);
        });
        return keys;
    };
    const isPastCell = (cell) => {
        const date = cell.dataset.date || '';
        const time = cell.dataset.time || '';
        if (!date || !time) {
            return false;
        }

        const cellStart = new Date(`${date}T${time}:00`);
        return cellStart < now;
    };

    const setCellState = (cell, isActive) => {
        const key = slotKey(cell.dataset.date, cell.dataset.time);
        cell.classList.toggle('is-active', isActive);
        cell.setAttribute('aria-pressed', isActive ? 'true' : 'false');

        if (isActive) {
            activeSlots.add(key);
        } else {
            activeSlots.delete(key);
        }
    };

    const pushUndoSnapshot = () => {
        const snapshot = Array.from(activeSlots).sort();
        undoStack.push(snapshot);
        if (undoStack.length > 30) {
            undoStack.shift();
        }
    };

    const restoreSnapshot = (snapshotKeys) => {
        const targetSet = new Set(Array.isArray(snapshotKeys) ? snapshotKeys : []);
        cells.forEach((cell) => {
            if (!isEditableCell(cell)) {
                return;
            }
            const key = slotKey(cell.dataset.date, cell.dataset.time);
            setCellState(cell, targetSet.has(key));
        });
    };

    const updateSummary = () => {
        const editableKeys = editableSlotKeys();
        let total = 0;
        let added = 0;
        let removed = 0;

        editableKeys.forEach((key) => {
            const isActive = activeSlots.has(key);
            const wasInitial = initialEditableSlots.has(key);
            if (isActive) {
                total += 1;
            }
            if (isActive && !wasInitial) {
                added += 1;
            }
            if (!isActive && wasInitial) {
                removed += 1;
            }
        });

        if (summaryTotal) summaryTotal.textContent = String(total);
        if (summaryAdded) summaryAdded.textContent = String(added);
        if (summaryRemoved) summaryRemoved.textContent = String(removed);

        const isDirty = added > 0 || removed > 0;
        if (undoButton) undoButton.disabled = undoStack.length === 0;
        resetButtons.forEach((button) => {
            button.disabled = !isDirty;
        });
    };

    const updateQuickStatus = (message) => {
        if (quickStatus) {
            quickStatus.textContent = message;
        }
    };

    const syncQuickDayChips = () => {
        if (!quickDay || quickDayChipButtons.length === 0) {
            return;
        }
        const selectedDay = String(quickDay.value || '');
        quickDayChipButtons.forEach((button) => {
            const chipDay = String(button.dataset.quickDayChip || '');
            const isActive = chipDay === selectedDay;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    };

    const setPlannerMode = (mode) => {
        const normalized = mode === 'detail' ? 'detail' : 'quick';
        form.dataset.plannerMode = normalized;
        modeTriggers.forEach((trigger) => {
            const triggerMode = String(trigger.dataset.plannerModeTrigger || 'quick');
            trigger.classList.toggle('is-active', triggerMode === normalized);
            trigger.setAttribute('aria-pressed', triggerMode === normalized ? 'true' : 'false');
        });
        if (detailWrap) {
            detailWrap.open = normalized === 'detail';
        }
    };

    const timeToMinutes = (time) => {
        const match = String(time || '').match(/^(\d{2}):(\d{2})$/);
        if (!match) {
            return null;
        }
        const hours = Number(match[1]);
        const minutes = Number(match[2]);
        return hours * 60 + minutes;
    };

    const applyRangeByQuickForm = (mode) => {
        if (!quickDay || !quickStart || !quickEnd) {
            return;
        }

        const selectedDay = String(quickDay.value || '');
        const startTime = String(quickStart.value || '');
        const endTime = String(quickEnd.value || '');

        if (!selectedDay || !startTime || !endTime) {
            updateQuickStatus('Vyberte den a oba časy.');
            return;
        }

        const startMinutes = timeToMinutes(startTime);
        const endMinutes = timeToMinutes(endTime);

        if (startMinutes === null || endMinutes === null) {
            updateQuickStatus('Časový interval má neplatný formát.');
            return;
        }

        if (endMinutes <= startMinutes) {
            updateQuickStatus('Čas "Do" musí být později než "Od".');
            return;
        }

        pushUndoSnapshot();
        let changed = 0;

        for (let minute = startMinutes; minute < endMinutes; minute += 30) {
            const hh = String(Math.floor(minute / 60)).padStart(2, '0');
            const mm = String(minute % 60).padStart(2, '0');
            const key = slotKey(selectedDay, `${hh}:${mm}`);
            const cell = cellsByKey.get(key);

            if (!cell || cell.classList.contains('is-past') || cell.classList.contains('is-booked')) {
                continue;
            }

            const shouldBeActive = mode === 'add';
            if (cell.classList.contains('is-active') === shouldBeActive) {
                continue;
            }

            setCellState(cell, shouldBeActive);
            changed += 1;
        }

        serializeWindows();
        updateSummary();
        if (changed > 0) {
            const actionLabel = mode === 'add' ? 'přidán' : 'odebrán';
            updateQuickStatus(`Interval ${startTime}-${endTime} (${selectedDay}) byl ${actionLabel}.`);
        } else {
            updateQuickStatus('V intervalu nebyly žádné editovatelné sloty.');
        }
    };

    const applyRangeForDay = (selectedDay, startTime, endTime, mode = 'add') => {
        const startMinutes = timeToMinutes(startTime);
        const endMinutes = timeToMinutes(endTime);
        if (!selectedDay || startMinutes === null || endMinutes === null || endMinutes <= startMinutes) {
            return 0;
        }

        let changed = 0;
        for (let minute = startMinutes; minute < endMinutes; minute += 30) {
            const hh = String(Math.floor(minute / 60)).padStart(2, '0');
            const mm = String(minute % 60).padStart(2, '0');
            const key = slotKey(selectedDay, `${hh}:${mm}`);
            const cell = cellsByKey.get(key);

            if (!cell || cell.classList.contains('is-past') || cell.classList.contains('is-booked')) {
                continue;
            }

            const shouldBeActive = mode === 'add';
            if (cell.classList.contains('is-active') === shouldBeActive) {
                continue;
            }

            setCellState(cell, shouldBeActive);
            changed += 1;
        }

        return changed;
    };

    const clearQuickDay = () => {
        if (!quickDay) {
            return;
        }

        const selectedDay = String(quickDay.value || '');
        if (!selectedDay) {
            updateQuickStatus('Vyberte den.');
            return;
        }

        const shouldClear = window.confirm(`Opravdu chcete vymazat dostupnost pro den ${selectedDay}?`);
        if (!shouldClear) {
            return;
        }

        pushUndoSnapshot();
        let changed = 0;
        cells.forEach((cell) => {
            if ((cell.dataset.date || '') !== selectedDay) {
                return;
            }
            if (cell.classList.contains('is-past') || cell.classList.contains('is-booked')) {
                return;
            }
            if (!cell.classList.contains('is-active')) {
                return;
            }
            setCellState(cell, false);
            changed += 1;
        });

        serializeWindows();
        updateSummary();
        if (changed > 0) {
            updateQuickStatus(`Dostupnost pro ${selectedDay} byla vymazána.`);
        } else {
            updateQuickStatus('Pro vybraný den nebyly dostupné sloty ke smazání.');
        }
    };

    const applyCell = (cell) => {
        if (!isDragging) {
            return;
        }
        if (cell.classList.contains('is-past') || cell.classList.contains('is-booked')) {
            return;
        }

        setCellState(cell, dragMode === 'add');
    };

    const initializeFromWindows = () => {
        let windows = [];

        try {
            windows = JSON.parse(initialWindowsRaw);
        } catch (error) {
            windows = [];
        }

        windows.forEach((windowItem) => {
            const startAt = windowItem.start_at || '';
            const endAt = windowItem.end_at || '';

            if (!startAt || !endAt) {
                return;
            }

            const date = startAt.slice(0, 10);
            let cursor = new Date(`${startAt.replace(' ', 'T')}`);
            const end = new Date(`${endAt.replace(' ', 'T')}`);

            while (cursor < end) {
                const hours = String(cursor.getHours()).padStart(2, '0');
                const minutes = String(cursor.getMinutes()).padStart(2, '0');
                const selector = `.planner-cell[data-date="${date}"][data-time="${hours}:${minutes}"]`;
                const cell = planner.querySelector(selector);

                if (cell) {
                    setCellState(cell, true);
                }

                cursor = new Date(cursor.getTime() + 30 * 60 * 1000);
            }
        });
    };

    const markBookedWindows = () => {
        let windows = [];

        try {
            windows = JSON.parse(bookedWindowsRaw);
        } catch (error) {
            windows = [];
        }

        windows.forEach((windowItem) => {
            const startAt = windowItem.start_at || '';
            const endAt = windowItem.end_at || '';
            const serviceName = String(windowItem.service_name || '').trim();
            const clientName = String(windowItem.client_name || '').trim();

            if (!startAt || !endAt) {
                return;
            }

            const date = startAt.slice(0, 10);
            let cursor = new Date(`${startAt.replace(' ', 'T')}`);
            const end = new Date(`${endAt.replace(' ', 'T')}`);
            const bookedTitleParts = ['Obsazený termín'];
            if (serviceName !== '') {
                bookedTitleParts.push(serviceName);
            }
            if (clientName !== '') {
                bookedTitleParts.push(clientName);
            }
            const bookedTitle = bookedTitleParts.join(' • ');

            while (cursor < end) {
                const hours = String(cursor.getHours()).padStart(2, '0');
                const minutes = String(cursor.getMinutes()).padStart(2, '0');
                const selector = `.planner-cell[data-date="${date}"][data-time="${hours}:${minutes}"]`;
                const cell = planner.querySelector(selector);

                if (cell) {
                    cell.classList.add('is-booked');
                    cell.setAttribute('aria-disabled', 'true');
                    cell.tabIndex = -1;
                    cell.dataset.bookedTitle = bookedTitle;
                    const baseTitle = cell.getAttribute('title') || '';
                    cell.setAttribute('title', baseTitle !== '' ? `${baseTitle} • ${bookedTitle}` : bookedTitle);
                    cell.setAttribute('aria-label', bookedTitle);
                }

                cursor = new Date(cursor.getTime() + 30 * 60 * 1000);
            }
        });
    };

    const serializeWindows = () => {
        const groupedByDate = new Map();

        Array.from(activeSlots)
            .sort()
            .forEach((key) => {
                const [date, time] = key.split('|');
                if (!groupedByDate.has(date)) {
                    groupedByDate.set(date, []);
                }
                groupedByDate.get(date).push(time);
            });

        const windows = [];

        groupedByDate.forEach((times, date) => {
            const sortedTimes = [...times].sort();
            let rangeStart = null;
            let previousTime = null;

            const flushRange = () => {
                if (!rangeStart || !previousTime) {
                    return;
                }

                const [prevHour, prevMinute] = previousTime.split(':').map(Number);
                const endDate = new Date(`${date}T${String(prevHour).padStart(2, '0')}:${String(prevMinute).padStart(2, '0')}:00`);
                endDate.setMinutes(endDate.getMinutes() + 30);

                const endTime = `${String(endDate.getHours()).padStart(2, '0')}:${String(endDate.getMinutes()).padStart(2, '0')}:00`;

                windows.push({
                    start_at: `${date} ${rangeStart}:00`,
                    end_at: `${date} ${endTime}`,
                });
            };

            sortedTimes.forEach((time) => {
                if (!rangeStart) {
                    rangeStart = time;
                    previousTime = time;
                    return;
                }

                const previousDate = new Date(`${date}T${previousTime}:00`);
                previousDate.setMinutes(previousDate.getMinutes() + 30);
                const expectedNext = `${String(previousDate.getHours()).padStart(2, '0')}:${String(previousDate.getMinutes()).padStart(2, '0')}`;

                if (time !== expectedNext) {
                    flushRange();
                    rangeStart = time;
                }

                previousTime = time;
            });

            flushRange();
        });

        hiddenWindows.value = JSON.stringify(windows);
    };

    cells.forEach((cell) => {
        cellsByKey.set(slotKey(cell.dataset.date, cell.dataset.time), cell);
        const pastCell = isPastCell(cell);
        if (pastCell) {
            cell.classList.add('is-past');
            cell.setAttribute('aria-disabled', 'true');
            cell.tabIndex = -1;
        } else {
            cell.removeAttribute('aria-disabled');
        }

        cell.addEventListener('pointerdown', (event) => {
            if (cell.classList.contains('is-past') || cell.classList.contains('is-booked')) {
                return;
            }
            event.preventDefault();
            pushUndoSnapshot();
            isDragging = true;
            dragMode = cell.classList.contains('is-active') ? 'remove' : 'add';
            applyCell(cell);
        });

        cell.addEventListener('pointerenter', () => {
            applyCell(cell);
        });

        cell.addEventListener('click', (event) => {
            if (isDragging) {
                event.preventDefault();
            }
        });
    });

    const finishDragging = () => {
        if (!isDragging) {
            return;
        }

        isDragging = false;
        serializeWindows();
        updateSummary();
    };

    planner.addEventListener('pointerup', finishDragging);
    planner.addEventListener('pointerleave', finishDragging);
    document.addEventListener('pointerup', finishDragging);

    initializeFromWindows();
    markBookedWindows();
    initialEditableSlots = new Set(
        Array.from(activeSlots).filter((key) => {
            const cell = cellsByKey.get(key);
            return !!cell && isEditableCell(cell);
        })
    );
    serializeWindows();
    updateSummary();

    if (quickAdd) {
        quickAdd.addEventListener('click', () => applyRangeByQuickForm('add'));
    }
    if (quickRemove) {
        quickRemove.addEventListener('click', () => applyRangeByQuickForm('remove'));
    }
    if (quickClearDay) {
        quickClearDay.addEventListener('click', clearQuickDay);
    }

    if (quickDay) {
        quickDay.addEventListener('change', syncQuickDayChips);
    }

    if (quickDayChipButtons.length > 0 && quickDay) {
        quickDayChipButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const day = String(button.dataset.quickDayChip || '');
                if (!day) {
                    return;
                }
                quickDay.value = day;
                syncQuickDayChips();
                updateQuickStatus(`Vybrán den ${day}.`);
            });
        });
    }

    if (modeTriggers.length > 0) {
        modeTriggers.forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const mode = String(trigger.dataset.plannerModeTrigger || 'quick');
                setPlannerMode(mode);
            });
        });
    }

    const isMobileViewport = window.matchMedia('(max-width: 720px)').matches;
    setPlannerMode(isMobileViewport ? 'quick' : 'detail');
    syncQuickDayChips();

    if (quickPresetDay) {
        quickPresetDay.addEventListener('click', () => {
            if (!quickDay) {
                return;
            }
            pushUndoSnapshot();
            const selectedDay = String(quickDay.value || '');
            const changed = applyRangeForDay(selectedDay, '09:00', '17:00', 'add');
            serializeWindows();
            updateSummary();
            updateQuickStatus(
                changed > 0
                    ? `Předvolba 9:00-17:00 byla nastavena pro ${selectedDay}.`
                    : 'Předvolbu nebylo možné aplikovat (sloty jsou už obsazené/minulé).'
            );
        });
    }

    if (quickPresetWeek) {
        quickPresetWeek.addEventListener('click', () => {
            pushUndoSnapshot();
            const weekDays = quickDay
                ? Array.from(quickDay.options).map((option) => String(option.value || ''))
                : [];

            let changed = 0;
            weekDays.forEach((day) => {
                const date = new Date(`${day}T00:00:00`);
                const dayOfWeek = date.getDay(); // 0 = Ne, 6 = So
                if (dayOfWeek === 0 || dayOfWeek === 6) {
                    return;
                }
                changed += applyRangeForDay(day, '09:00', '17:00', 'add');
            });

            serializeWindows();
            updateSummary();
            updateQuickStatus(
                changed > 0
                    ? 'Předvolba Po-Pá 9:00-17:00 byla nastavena.'
                    : 'Předvolbu nebylo možné aplikovat (sloty jsou už obsazené/minulé).'
            );
        });
    }

    if (undoButton) {
        undoButton.addEventListener('click', () => {
            const snapshot = undoStack.pop();
            if (!snapshot) {
                updateSummary();
                return;
            }
            restoreSnapshot(snapshot);
            serializeWindows();
            updateSummary();
            updateQuickStatus('Vrácena poslední úprava.');
        });
    }

    resetButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (initialEditableSlots.size === 0 && activeSlots.size === 0) {
                return;
            }
            pushUndoSnapshot();
            restoreSnapshot(Array.from(initialEditableSlots));
            serializeWindows();
            updateSummary();
            updateQuickStatus('Týden byl obnoven do původního stavu.');
        });
    });

}

function setupCategorySorting() {
    const tbody = document.querySelector('[data-category-sortable]');
    const form = document.getElementById('category-order-form');

    if (!tbody || !form) {
        return;
    }

    const hiddenInput = form.querySelector('input[name="category_order_ids"]');

    if (!hiddenInput) {
        return;
    }

    const rows = () => Array.from(tbody.querySelectorAll('tr[data-category-id]'));
    let activeRow = null;
    let moved = false;
    let lastSavedOrder = '';
    let activePointerId = null;

    const syncOrderField = () => {
        hiddenInput.value = rows()
            .map((row) => row.dataset.categoryId || '')
            .filter((id) => id !== '')
            .join(',');
    };

    const clearDragClasses = () => {
        rows().forEach((row) => row.classList.remove('is-dragging', 'is-drag-over'));
        document.body.classList.remove('is-sorting-categories');
    };

    const submitOrderIfChanged = () => {
        syncOrderField();
        const currentOrder = hiddenInput.value;
        if (currentOrder !== '' && currentOrder !== lastSavedOrder) {
            lastSavedOrder = currentOrder;
            form.requestSubmit();
        }
    };

    syncOrderField();
    lastSavedOrder = hiddenInput.value;

    rows().forEach((row) => {
        const handle = row.querySelector('.drag-handle');
        if (!handle) {
            return;
        }

        handle.addEventListener('pointerdown', (event) => {
            event.preventDefault();

            activeRow = row;
            activePointerId = event.pointerId ?? null;
            moved = false;
            activeRow.classList.add('is-dragging');
            document.body.classList.add('is-sorting-categories');

            if (typeof handle.setPointerCapture === 'function' && activePointerId !== null) {
                try {
                    handle.setPointerCapture(activePointerId);
                } catch (captureError) {
                    // Ignore capture issues on unsupported browsers.
                }
            }

            const onPointerMove = (moveEvent) => {
                if (!activeRow) {
                    return;
                }

                const pointTarget = document.elementFromPoint(moveEvent.clientX, moveEvent.clientY);
                const target = pointTarget ? pointTarget.closest('tr[data-category-id]') : null;
                if (!target || target === activeRow || !tbody.contains(target)) {
                    return;
                }

                rows().forEach((entry) => {
                    if (entry !== activeRow) {
                        entry.classList.remove('is-drag-over');
                    }
                });

                const rect = target.getBoundingClientRect();
                const insertAfter = moveEvent.clientY > rect.top + rect.height / 2;
                target.classList.add('is-drag-over');

                if (insertAfter) {
                    target.after(activeRow);
                } else {
                    target.before(activeRow);
                }

                moved = true;
            };

            const onPointerUp = () => {
                document.removeEventListener('pointermove', onPointerMove);
                document.removeEventListener('pointerup', onPointerUp);
                document.removeEventListener('pointercancel', onPointerUp);

                if (activeRow) {
                    activeRow.classList.remove('is-dragging');
                }
                clearDragClasses();
                activeRow = null;
                activePointerId = null;

                if (moved) {
                    submitOrderIfChanged();
                }
            };

            document.addEventListener('pointermove', onPointerMove);
            document.addEventListener('pointerup', onPointerUp);
            document.addEventListener('pointercancel', onPointerUp);
        });
    });
}
