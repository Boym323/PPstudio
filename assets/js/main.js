document.addEventListener('DOMContentLoaded', () => {
    setupRevealAnimations();
    setupAvailabilityPlanner();
    setupAntispamLogDetails();
    setupCategorySorting();
    setupReservationActions();
    setupVoucherRedeemAssist();
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
    const dailyEntry = form.querySelector('[data-availability-daily-entry]');
    const dailyDay = dailyEntry ? dailyEntry.querySelector('[data-daily-day]') : null;
    const dailyStart = dailyEntry ? dailyEntry.querySelector('[data-daily-start]') : null;
    const dailyEnd = dailyEntry ? dailyEntry.querySelector('[data-daily-end]') : null;
    const dailyAdd = dailyEntry ? dailyEntry.querySelector('[data-daily-add]') : null;
    const dailyRemove = dailyEntry ? dailyEntry.querySelector('[data-daily-remove]') : null;
    const dailyClearDay = dailyEntry ? dailyEntry.querySelector('[data-daily-clear-day]') : null;
    const dailyPresetDay = dailyEntry ? dailyEntry.querySelector('[data-daily-preset-day]') : null;
    const dailyStatus = dailyEntry ? dailyEntry.querySelector('[data-daily-status]') : null;
    const dailySlots = dailyEntry ? dailyEntry.querySelector('[data-daily-slots]') : null;
    const dailySummaryDay = dailyEntry ? dailyEntry.querySelector('[data-daily-summary-day]') : null;
    const dailySummaryTotal = dailyEntry ? dailyEntry.querySelector('[data-daily-summary-total]') : null;
    const dailyDayChipButtons = dailyEntry ? Array.from(dailyEntry.querySelectorAll('[data-daily-day-chip]')) : [];
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
    const weeklyEditor = form.querySelector('[data-availability-weekly-editor]');
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
        if (dailyStatus) {
            dailyStatus.textContent = message;
        }
    };

    const syncSelectedDay = (selectedDay) => {
        if (quickDay && selectedDay !== '' && quickDay.value !== selectedDay) {
            quickDay.value = selectedDay;
        }
        if (dailyDay && selectedDay !== '' && dailyDay.value !== selectedDay) {
            dailyDay.value = selectedDay;
        }
    };

    const syncQuickDayChips = () => {
        const selectedDay = String((dailyDay && dailyDay.value) || (quickDay && quickDay.value) || '');
        quickDayChipButtons.forEach((button) => {
            const chipDay = String(button.dataset.quickDayChip || '');
            const isActive = chipDay === selectedDay;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        dailyDayChipButtons.forEach((button) => {
            const chipDay = String(button.dataset.dailyDayChip || '');
            const isActive = chipDay === selectedDay;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    };

    const setPlannerMode = (mode) => {
        const normalized = mode === 'weekly' ? 'weekly' : 'daily';
        form.dataset.plannerMode = normalized;
        modeTriggers.forEach((trigger) => {
            const triggerMode = String(trigger.dataset.plannerModeTrigger || 'daily');
            trigger.classList.toggle('is-active', triggerMode === normalized);
            trigger.setAttribute('aria-pressed', triggerMode === normalized ? 'true' : 'false');
        });
        if (dailyEntry) {
            dailyEntry.hidden = normalized !== 'daily';
        }
        if (weeklyEditor) {
            weeklyEditor.hidden = normalized !== 'weekly';
        }
        if (detailWrap) {
            detailWrap.open = normalized === 'weekly';
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

    const renderDailySlots = () => {
        if (!dailySlots) {
            return;
        }

        const selectedDay = String((dailyDay && dailyDay.value) || (quickDay && quickDay.value) || '');
        if (!selectedDay) {
            dailySlots.innerHTML = '<div class="availability-daily-empty">Vyberte den.</div>';
            if (dailySummaryDay) dailySummaryDay.textContent = 'Nevybráno';
            if (dailySummaryTotal) dailySummaryTotal.textContent = '0';
            return;
        }

        if (dailySummaryDay) {
            const activeOption = (dailyDay || quickDay)?.selectedOptions?.[0];
            dailySummaryDay.textContent = activeOption ? activeOption.textContent.trim() : selectedDay;
        }

        const dayCells = cells
            .filter((cell) => String(cell.dataset.date || '') === selectedDay)
            .sort((a, b) => String(a.dataset.time || '').localeCompare(String(b.dataset.time || '')));

        let activeCount = 0;
        dailySlots.innerHTML = dayCells.map((cell) => {
            const time = String(cell.dataset.time || '');
            const isActive = cell.classList.contains('is-active');
            const isBooked = cell.classList.contains('is-booked');
            const isPast = cell.classList.contains('is-past');
            const isEditable = isEditableCell(cell);
            if (isActive) {
                activeCount += 1;
            }

            let status = 'Vypnuto';
            if (isBooked) {
                status = 'Obsazeno';
            } else if (isPast) {
                status = 'Minulé';
            } else if (isActive) {
                status = 'Volné';
            }

            const classes = ['availability-slot-chip'];
            if (isActive) classes.push('is-active');
            if (isBooked) classes.push('is-booked');
            if (isPast) classes.push('is-past');
            if (!isEditable) classes.push('is-disabled');

            return `<button type="button" class="${classes.join(' ')}" data-daily-slot="${time}" ${isEditable ? '' : 'disabled'} aria-pressed="${isActive ? 'true' : 'false'}"><span class="availability-slot-time">${time}</span><span class="availability-slot-state">${status}</span></button>`;
        }).join('');

        if (dailySummaryTotal) {
            dailySummaryTotal.textContent = String(activeCount);
        }

        dailySlots.querySelectorAll('[data-daily-slot]').forEach((button) => {
            button.addEventListener('click', () => {
                const time = String(button.getAttribute('data-daily-slot') || '');
                if (!selectedDay || !time) {
                    return;
                }
                const cell = cellsByKey.get(slotKey(selectedDay, time));
                if (!cell || !isEditableCell(cell)) {
                    return;
                }
                pushUndoSnapshot();
                setCellState(cell, !cell.classList.contains('is-active'));
                serializeWindows();
                updateSummary();
                renderDailySlots();
                updateQuickStatus(`Slot ${time} pro ${selectedDay} byl ${cell.classList.contains('is-active') ? 'přidán' : 'odebrán'}.`);
            });
        });
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
        renderDailySlots();
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
        renderDailySlots();
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
    syncSelectedDay(String((dailyDay && dailyDay.value) || (quickDay && quickDay.value) || ''));
    renderDailySlots();

    if (quickAdd) {
        quickAdd.addEventListener('click', () => {
            applyRangeByQuickForm('add');
            renderDailySlots();
        });
    }
    if (quickRemove) {
        quickRemove.addEventListener('click', () => {
            applyRangeByQuickForm('remove');
            renderDailySlots();
        });
    }
    if (quickClearDay) {
        quickClearDay.addEventListener('click', clearQuickDay);
    }

    if (quickDay) {
        quickDay.addEventListener('change', () => {
            syncSelectedDay(String(quickDay.value || ''));
            syncQuickDayChips();
            renderDailySlots();
        });
    }

    if (dailyDay) {
        dailyDay.addEventListener('change', () => {
            syncSelectedDay(String(dailyDay.value || ''));
            syncQuickDayChips();
            renderDailySlots();
            updateQuickStatus(`Vybrán den ${String(dailyDay.value || '')}.`);
        });
    }

    if (quickDayChipButtons.length > 0 && quickDay) {
        quickDayChipButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const day = String(button.dataset.quickDayChip || '');
                if (!day) {
                    return;
                }
                syncSelectedDay(day);
                syncQuickDayChips();
                renderDailySlots();
                updateQuickStatus(`Vybrán den ${day}.`);
            });
        });
    }

    if (dailyDayChipButtons.length > 0) {
        dailyDayChipButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const day = String(button.dataset.dailyDayChip || '');
                if (!day) {
                    return;
                }
                syncSelectedDay(day);
                syncQuickDayChips();
                renderDailySlots();
                updateQuickStatus(`Vybrán den ${day}.`);
            });
        });
    }

    if (modeTriggers.length > 0) {
        modeTriggers.forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const mode = String(trigger.dataset.plannerModeTrigger || 'quick');
                if (mode === 'weekly' && quickDay && dailyDay) {
                    syncSelectedDay(String((dailyDay.value || quickDay.value || '')));
                }
                setPlannerMode(mode);
            });
        });
    }

    const isMobileViewport = window.matchMedia('(max-width: 720px)').matches;
    setPlannerMode(isMobileViewport ? 'daily' : 'weekly');
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
            renderDailySlots();
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
            renderDailySlots();
        });
    }

    if (dailyAdd) {
        dailyAdd.addEventListener('click', () => {
            if (dailyDay && quickDay) quickDay.value = dailyDay.value;
            if (dailyStart && quickStart) quickStart.value = dailyStart.value;
            if (dailyEnd && quickEnd) quickEnd.value = dailyEnd.value;
            applyRangeByQuickForm('add');
            renderDailySlots();
        });
    }

    if (dailyRemove) {
        dailyRemove.addEventListener('click', () => {
            if (dailyDay && quickDay) quickDay.value = dailyDay.value;
            if (dailyStart && quickStart) quickStart.value = dailyStart.value;
            if (dailyEnd && quickEnd) quickEnd.value = dailyEnd.value;
            applyRangeByQuickForm('remove');
            renderDailySlots();
        });
    }

    if (dailyClearDay) {
        dailyClearDay.addEventListener('click', () => {
            if (dailyDay && quickDay) quickDay.value = dailyDay.value;
            clearQuickDay();
            renderDailySlots();
        });
    }

    if (dailyPresetDay) {
        dailyPresetDay.addEventListener('click', () => {
            if (!dailyDay) {
                return;
            }
            if (quickDay) quickDay.value = dailyDay.value;
            pushUndoSnapshot();
            const selectedDay = String(dailyDay.value || '');
            const changed = applyRangeForDay(selectedDay, '09:00', '17:00', 'add');
            serializeWindows();
            updateSummary();
            renderDailySlots();
            updateQuickStatus(
                changed > 0
                    ? `Předvolba 9:00-17:00 byla nastavena pro ${selectedDay}.`
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
            renderDailySlots();
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
            renderDailySlots();
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

function setupReservationActions() {
    const root = document.querySelector('[data-reservations-root]');
    if (!root) {
        return;
    }

    const forms = Array.from(root.querySelectorAll('form[data-reservation-form]'));
    if (forms.length === 0) {
        return;
    }

    const totalNode = root.querySelector('[data-reservation-total]');
    const tbody = root.querySelector('[data-reservation-tbody]');
    let feedbackTimer = null;
    const fetchJson = async (url) => {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'fetch',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            throw new Error('Dostupné termíny se nepodařilo načíst.');
        }
        return response.json();
    };

    const setFeedback = (form, message, type = 'info') => {
        const feedback = form.querySelector('[data-reservation-feedback]');
        if (!feedback) {
            return;
        }
        feedback.textContent = message || '';
        feedback.classList.remove('is-success', 'is-error', 'is-info');
        feedback.classList.add(type === 'error' ? 'is-error' : type === 'success' ? 'is-success' : 'is-info');
    };

    const setFormBusy = (form, busy) => {
        const controls = Array.from(form.querySelectorAll('input, select, button, textarea'));
        controls.forEach((control) => {
            if (control instanceof HTMLInputElement && control.type === 'hidden' && control.name === '_csrf') {
                return;
            }
            control.disabled = busy;
        });
        form.classList.toggle('is-busy', busy);
    };

    const refreshEmptyState = () => {
        if (!tbody) {
            return;
        }
        const rows = Array.from(tbody.querySelectorAll('tr[data-reservation-row]'));
        const currentEmpty = tbody.querySelector('tr[data-reservation-empty-row]');

        if (rows.length > 0) {
            if (currentEmpty) {
                currentEmpty.remove();
            }
            return;
        }

        if (!currentEmpty) {
            const emptyRow = document.createElement('tr');
            emptyRow.setAttribute('data-reservation-empty-row', '');
            const cell = document.createElement('td');
            cell.colSpan = 7;
            cell.textContent = 'Zatím zde nejsou žádné rezervace.';
            emptyRow.appendChild(cell);
            tbody.appendChild(emptyRow);
        }
    };

    const updateTotalAfterDelete = () => {
        if (!totalNode) {
            return;
        }
        const current = Number.parseInt(totalNode.textContent || '0', 10);
        if (Number.isFinite(current) && current > 0) {
            totalNode.textContent = String(current - 1);
        }
    };

    forms.forEach((form) => {
        const detailRow = form.closest('[data-reservation-detail-row]');
        const reservationId = String((form.querySelector('input[name="reservation_id"]')?.value) || '');
        const row = reservationId !== ''
            ? root.querySelector(`[data-reservation-row][data-reservation-id="${reservationId}"]`)
            : null;
        const toggleButton = form.querySelector('[data-reschedule-toggle]');
        const rescheduleBox = form.querySelector('[data-reschedule-box]');
        const daySelect = form.querySelector('[data-reschedule-day]');
        const timeSelect = form.querySelector('[data-reschedule-time]');
        const pickedNode = form.querySelector('[data-reschedule-picked]');
        const hiddenDateTimeInput = form.querySelector('[data-reschedule-datetime]');
        const statusSelect = form.querySelector('[data-reservation-status-select]');
        const cancelReasonWrap = form.querySelector('[data-cancel-reason-wrap]');
        const serviceId = row ? String(row.dataset.reservationServiceId || '').trim() : '';
        let originalDateTimeValue = hiddenDateTimeInput ? String(hiddenDateTimeInput.value || '').trim() : '';
        let daysLoaded = false;

        const syncCancelReasonVisibility = () => {
            if (!statusSelect || !cancelReasonWrap) {
                return;
            }
            const shouldShow = String(statusSelect.value || '') === 'zrusena';
            cancelReasonWrap.classList.toggle('is-hidden', !shouldShow);
        };

        syncCancelReasonVisibility();
        if (statusSelect) {
            statusSelect.addEventListener('change', syncCancelReasonVisibility);
        }

        const setHiddenDateTime = (day, time) => {
            if (!hiddenDateTimeInput) return;
            if (!day || !time) return;
            hiddenDateTimeInput.value = `${day}T${time}`;
        };

        const updatePickedLabel = () => {
            if (!pickedNode || !daySelect || !timeSelect || !hiddenDateTimeInput) return;
            const dayOption = daySelect.options[daySelect.selectedIndex];
            const timeOption = timeSelect.options[timeSelect.selectedIndex];
            if (!daySelect.value || !timeSelect.value) {
                pickedNode.textContent = 'Nový termín zatím není vybraný.';
                return;
            }
            const selectedValue = `${daySelect.value}T${timeSelect.value}`;
            pickedNode.textContent = selectedValue === originalDateTimeValue
                ? 'Vybraný termín je stejný jako aktuální.'
                : `Nový termín: ${String(dayOption?.textContent || daySelect.value)} v ${String(timeOption?.textContent || timeSelect.value)}`;
        };

        const resetTimes = () => {
            if (!timeSelect) return;
            timeSelect.innerHTML = '<option value="">Nejprve vyberte den</option>';
            timeSelect.disabled = true;
            updatePickedLabel();
        };

        const loadTimes = async (dayValue) => {
            if (!timeSelect || !serviceId || !dayValue) {
                resetTimes();
                return;
            }

            timeSelect.disabled = true;
            timeSelect.innerHTML = '<option value="">Načítám časy…</option>';
            try {
                const payload = await fetchJson(`/api/availability.php?service_id=${encodeURIComponent(serviceId)}&date=${encodeURIComponent(dayValue)}`);
                const times = Array.isArray(payload.times) ? payload.times : [];
                if (times.length === 0) {
                    timeSelect.innerHTML = '<option value="">Bez volného času</option>';
                    timeSelect.disabled = true;
                    updatePickedLabel();
                    return;
                }

                timeSelect.innerHTML = '<option value="">Vyberte čas</option>';
                times.forEach((slot) => {
                    const value = String(slot?.value || '');
                    if (!value) return;
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = String(slot?.label || value);
                    timeSelect.appendChild(option);
                });
                timeSelect.disabled = false;
            } catch (_) {
                timeSelect.innerHTML = '<option value="">Časy nelze načíst</option>';
                timeSelect.disabled = true;
            }
            updatePickedLabel();
        };

        const loadDays = async () => {
            if (!daySelect || !serviceId) return;
            daySelect.disabled = true;
            daySelect.innerHTML = '<option value="">Načítám dny…</option>';
            resetTimes();
            try {
                const payload = await fetchJson(`/api/availability.php?service_id=${encodeURIComponent(serviceId)}`);
                const days = Array.isArray(payload.days) ? payload.days : [];
                daySelect.innerHTML = '';
                const first = document.createElement('option');
                first.value = '';
                first.textContent = days.length > 0 ? 'Vyberte den' : 'Bez volných dnů';
                daySelect.appendChild(first);
                days.forEach((day) => {
                    const value = String(day?.value || '');
                    if (!value) return;
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = String(day?.label || value);
                    daySelect.appendChild(option);
                });
                daySelect.disabled = days.length === 0;
                daysLoaded = true;
            } catch (_) {
                daySelect.innerHTML = '<option value="">Dny nelze načíst</option>';
                daySelect.disabled = true;
            }
            updatePickedLabel();
        };

        if (toggleButton && rescheduleBox) {
            toggleButton.addEventListener('click', async () => {
                const shouldOpen = rescheduleBox.hidden;
                rescheduleBox.hidden = !shouldOpen;
                toggleButton.textContent = shouldOpen ? 'Skrýt přeplánování' : 'Přeplánovat';
                if (shouldOpen && !daysLoaded) {
                    await loadDays();
                }
            });
        }

        if (daySelect) {
            daySelect.addEventListener('change', () => {
                const value = String(daySelect.value || '');
                if (!value) {
                    resetTimes();
                    return;
                }
                loadTimes(value);
            });
        }

        if (timeSelect) {
            timeSelect.addEventListener('change', () => {
                const dayValue = daySelect ? String(daySelect.value || '') : '';
                const timeValue = String(timeSelect.value || '');
                if (dayValue && timeValue) {
                    setHiddenDateTime(dayValue, timeValue);
                }
                updatePickedLabel();
            });
        }

        const syncStatusBadges = (statusKey, statusLabel) => {
            const safeKey = String(statusKey || 'nova');
            const safeLabel = String(statusLabel || '').trim();
            [row, form].forEach((scope) => {
                if (!scope) {
                    return;
                }
                scope.querySelectorAll('[data-reservation-status-badge]').forEach((badge) => {
                    badge.textContent = safeLabel || badge.textContent;
                    badge.className = `status-badge status-${safeKey}`;
                });
            });
        };

        form.addEventListener('submit', async (event) => {
            const submitter = event.submitter;
            if (!submitter) {
                return;
            }

            const isDelete = submitter.name === 'delete_reservation';
            const isUpdate = submitter.name === 'update_reservation';
            if (!isDelete && !isUpdate) {
                return;
            }

            event.preventDefault();

            if (isDelete) {
                const clientName = row ? String(row.dataset.reservationClient || '').trim() : '';
                const dateTime = row ? String(row.dataset.reservationDatetime || '').trim() : '';
                const reservationLabel = [clientName, dateTime].filter(Boolean).join(' • ');
                const question = reservationLabel !== ''
                    ? `Opravdu chcete trvale smazat rezervaci: ${reservationLabel}?`
                    : 'Opravdu chcete tuto rezervaci trvale smazat?';
                const confirmed = window.confirm(question);
                if (!confirmed) {
                    return;
                }
            }

            const defaultLabel = submitter.textContent || '';
            submitter.textContent = isDelete ? 'Mazání…' : 'Ukládám…';
            setFeedback(form, isDelete ? 'Mažu rezervaci…' : 'Ukládám změny…', 'info');

            try {
                const formData = new FormData(form);
                if (isDelete) {
                    formData.set('delete_reservation', '1');
                    formData.delete('update_reservation');
                } else {
                    formData.set('update_reservation', '1');
                    formData.delete('delete_reservation');
                }
                setFormBusy(form, true);

                const response = await fetch('/api/admin/reservation-action.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !payload.success) {
                    throw new Error(payload.error || 'Akci se nepodařilo dokončit.');
                }

                if (isDelete) {
                    if (row) {
                        row.classList.add('is-removing');
                    }
                    if (detailRow) {
                        detailRow.classList.add('is-removing');
                    }
                    await new Promise((resolve) => setTimeout(resolve, 120));
                    if (row) {
                        row.remove();
                    }
                    if (detailRow) {
                        detailRow.remove();
                    }
                    updateTotalAfterDelete();
                    refreshEmptyState();
                    return;
                }

                if (payload.data) {
                    syncStatusBadges(payload.data.status_key, payload.data.status_label);
                }
                if (row && payload.data) {
                    const datetimeLabel = String(payload.data.datetime_label || '').trim();
                    if (datetimeLabel !== '') {
                        row.dataset.reservationDatetime = datetimeLabel;
                        const termCell = row.querySelector('td[data-label="Termín"]') || row.querySelector('td');
                        if (termCell) {
                            termCell.textContent = datetimeLabel;
                        }
                        const detailDatetimeNode = form.querySelector('[data-reservation-datetime-text]');
                        if (detailDatetimeNode) {
                            detailDatetimeNode.textContent = datetimeLabel;
                        }
                    }
                    const hiddenDateTimeInput = form.querySelector('[data-reschedule-datetime]');
                    if (hiddenDateTimeInput) {
                        const localDateTime = String(payload.data.datetime_local || '').trim();
                        if (localDateTime !== '') {
                            hiddenDateTimeInput.value = localDateTime;
                            row.dataset.reservationDatetimeLocal = localDateTime;
                            originalDateTimeValue = localDateTime;
                        }
                    }
                }

                syncCancelReasonVisibility();

                setFeedback(form, payload.message || 'Rezervace byla upravena.', 'success');
                if (row) {
                    row.classList.add('is-updated');
                    window.setTimeout(() => row.classList.remove('is-updated'), 1200);
                }
                if (detailRow) {
                    detailRow.classList.add('is-updated');
                    window.setTimeout(() => detailRow.classList.remove('is-updated'), 1200);
                }
                if (feedbackTimer) {
                    window.clearTimeout(feedbackTimer);
                }
                feedbackTimer = window.setTimeout(() => {
                    setFeedback(form, '', 'info');
                }, 2200);
            } catch (error) {
                const message = error instanceof Error ? error.message : 'Akci se nepodařilo dokončit.';
                setFeedback(form, message, 'error');
            } finally {
                setFormBusy(form, false);
                submitter.textContent = defaultLabel;
            }
        });
    });

    const detailButtons = Array.from(root.querySelectorAll('[data-reservation-detail-toggle]'));
    detailButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const summaryRow = button.closest('[data-reservation-row]');
            const reservationId = summaryRow ? String(summaryRow.dataset.reservationId || '') : '';
            if (!reservationId) {
                return;
            }
            const detailRow = root.querySelector(`[data-reservation-detail-row][data-reservation-id="${reservationId}"]`);
            if (!detailRow) {
                return;
            }
            const shouldOpen = detailRow.hidden;
            detailRow.hidden = !shouldOpen;
            detailRow.classList.toggle('is-open', shouldOpen);
            if (summaryRow) {
                summaryRow.classList.toggle('is-open', shouldOpen);
            }
            button.textContent = shouldOpen
                ? String(button.dataset.closeLabel || 'Skrýt detail')
                : String(button.dataset.openLabel || 'Detail');
            button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        });
    });
}

function setupAntispamLogDetails() {
    const buttons = Array.from(document.querySelectorAll('[data-antispam-detail-toggle]'));
    if (buttons.length === 0) {
        return;
    }

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const summaryRow = button.closest('tr');
            if (!summaryRow) {
                return;
            }

            const detailRow = summaryRow.nextElementSibling;
            if (!detailRow || !detailRow.hasAttribute('data-antispam-detail-row')) {
                return;
            }

            const shouldOpen = detailRow.hidden;
            detailRow.hidden = !shouldOpen;
            button.textContent = shouldOpen
                ? String(button.dataset.closeLabel || 'Skrýt detail')
                : String(button.dataset.openLabel || 'Detail');
            button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        });
    });
}

function setupVoucherRedeemAssist() {
    const forms = Array.from(document.querySelectorAll('[data-voucher-redeem-form]'));
    if (forms.length === 0) {
        return;
    }

    const formatPrice = (value) => {
        const number = Number(value);
        if (!Number.isFinite(number)) {
            return '0 Kč';
        }
        return new Intl.NumberFormat('cs-CZ', {
            style: 'currency',
            currency: 'CZK',
            maximumFractionDigits: 0,
        }).format(number);
    };

    forms.forEach((form) => {
        const amountInput = form.querySelector('input[name="redeem_amount"]');
        const reservationSelect = form.querySelector('select[name="redeem_reservation_id"]');
        const reservationSearch = form.querySelector('[data-voucher-reservation-search]');
        const resultList = form.querySelector('[data-voucher-search-results]');
        const hint = form.querySelector('[data-voucher-redeem-hint]');
        const searchHint = form.querySelector('[data-voucher-search-hint]');
        const remainingRaw = Number.parseFloat(String(form.getAttribute('data-voucher-remaining') || '0'));
        const remaining = Number.isFinite(remainingRaw) ? Math.max(0, remainingRaw) : 0;
        const baseOptions = Array.from(reservationSelect ? reservationSelect.options : []).slice(1).map((option) => ({
            value: String(option.value || ''),
            text: String(option.textContent || ''),
            price: String(option.getAttribute('data-reservation-price') || '0'),
            search: String(option.getAttribute('data-search') || '').toLowerCase(),
        }));

        if (!amountInput || !reservationSelect) {
            return;
        }

        const renderResultItems = (items) => {
            if (!resultList) {
                return;
            }
            resultList.innerHTML = '';

            if (items.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'form-hint';
                empty.textContent = 'Žádné odpovídající rezervace.';
                resultList.appendChild(empty);
                return;
            }

            const activeValue = String(reservationSelect.value || '');
            items.forEach((item) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = `voucher-reservation-result${item.value === activeValue ? ' is-selected' : ''}`;
                button.setAttribute('data-reservation-id', item.value);
                button.innerHTML = `<span class="voucher-reservation-result-main">${item.text}</span><span class="voucher-reservation-result-meta">Cena rezervace: ${formatPrice(item.price)}</span>`;
                button.addEventListener('click', () => {
                    reservationSelect.value = item.value;
                    updateByReservation();
                    renderResultItems(items);
                });
                resultList.appendChild(button);
            });
        };

        const refreshReservationOptions = (queryRaw) => {
            const query = String(queryRaw || '').trim().toLowerCase();
            const prevValue = String(reservationSelect.value || '');
            reservationSelect.innerHTML = '<option value="">Bez vazby na rezervaci</option>';
            let matched = 0;
            const matchedItems = [];

            baseOptions.forEach((item) => {
                if (query !== '' && !item.search.includes(query)) {
                    return;
                }
                const option = document.createElement('option');
                option.value = item.value;
                option.textContent = item.text;
                option.setAttribute('data-reservation-price', item.price);
                option.setAttribute('data-search', item.search);
                reservationSelect.appendChild(option);
                matched += 1;
                matchedItems.push(item);
            });

            if (prevValue !== '') {
                const hasPrevious = Array.from(reservationSelect.options).some((opt) => String(opt.value || '') === prevValue);
                reservationSelect.value = hasPrevious ? prevValue : '';
            } else {
                reservationSelect.value = '';
            }

            if (searchHint) {
                searchHint.textContent = query === ''
                    ? 'Zobrazeny jsou budoucí rezervace a posledních 90 dní.'
                    : `Nalezeno rezervací: ${matched}.`;
            }

            const itemsToRender = query === '' ? matchedItems.slice(0, 12) : matchedItems.slice(0, 30);
            renderResultItems(itemsToRender);
        };

        const updateByReservation = () => {
            const selected = reservationSelect.options[reservationSelect.selectedIndex];
            const selectedId = String(reservationSelect.value || '').trim();
            if (!selectedId || !selected) {
                if (hint) {
                    hint.textContent = '';
                }
                return;
            }

            const reservationPriceRaw = Number.parseFloat(String(selected.getAttribute('data-reservation-price') || '0'));
            const reservationPrice = Number.isFinite(reservationPriceRaw) ? Math.max(0, reservationPriceRaw) : 0;
            if (reservationPrice <= 0) {
                if (hint) {
                    hint.textContent = 'Cena rezervace není k dispozici, částku zadejte ručně.';
                }
                return;
            }

            const allowedAmount = Math.min(reservationPrice, remaining);
            amountInput.value = String(Math.max(1, Math.round(allowedAmount)));

            if (hint) {
                if (reservationPrice > remaining) {
                    hint.textContent = `Cena rezervace ${formatPrice(reservationPrice)} je vyšší než zůstatek poukazu ${formatPrice(remaining)}. Předvyplněn byl maximální možný zůstatek.`;
                } else {
                    hint.textContent = `Předvyplněno podle ceny rezervace: ${formatPrice(reservationPrice)}.`;
                }
            }
        };

        reservationSelect.addEventListener('change', () => {
            updateByReservation();
            if (reservationSearch) {
                refreshReservationOptions(reservationSearch.value);
            }
        });
        if (reservationSearch) {
            reservationSearch.addEventListener('input', () => {
                refreshReservationOptions(reservationSearch.value);
                updateByReservation();
            });
        }
        refreshReservationOptions('');
    });
}
