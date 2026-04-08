                <section class="admin-single" id="dostupnost">
                    <div class="admin-card">
                        <p class="eyebrow">Plánovač</p>
                        <h2>Plánování dostupnosti po týdnech</h2>
                        <div class="availability-topbar">
                            <p class="form-hint">Zobrazený týden: <strong><?= escape($plannerWeekLabel) ?></strong></p>
                            <div class="table-actions availability-week-actions">
                                <a class="button button-secondary button-small" href="<?= escape($adminBasePath ?? 'admin.php') ?>?tab=dostupnost&amp;planner_week=<?= escape((string) ($plannerWeekOffset - 1)) ?>#dostupnost">Předchozí týden</a>
                                <a class="button button-secondary button-small" href="<?= escape($adminBasePath ?? 'admin.php') ?>?tab=dostupnost&amp;planner_week=0#dostupnost">Aktuální týden</a>
                                <a class="button button-secondary button-small" href="<?= escape($adminBasePath ?? 'admin.php') ?>?tab=dostupnost&amp;planner_week=<?= escape((string) ($plannerWeekOffset + 1)) ?>#dostupnost">Další týden</a>
                            </div>
                        </div>
                        <form method="post" class="admin-form" data-availability-planner-form>
                            <?= csrfInputField() ?>
                            <input type="hidden" name="planner_start" value="<?= escape($plannerDays[0] ?? '') ?>">
                            <input type="hidden" name="planner_end" value="<?= escape($plannerDays[count($plannerDays) - 1] ?? '') ?>">
                            <input type="hidden" name="planner_windows" value="[]">

                            <div class="availability-mode-switch" role="group" aria-label="Režim plánování">
                                <button class="button button-secondary button-small is-active" type="button" data-planner-mode-trigger="daily">Denní režim</button>
                                <button class="button button-secondary button-small" type="button" data-planner-mode-trigger="weekly">Týdenní režim</button>
                            </div>

                            <div class="availability-daily-entry" data-availability-daily-entry>
                                <p class="eyebrow">Denní režim (doporučeno na mobilu)</p>
                                <div class="availability-daily-headline">
                                    <strong>Rychlá úprava jednoho dne</strong>
                                    <span>Klepnutím na čas hned zapnete nebo vypnete dostupnost.</span>
                                </div>
                                <div class="admin-form admin-form-grid availability-daily-grid">
                                    <label>
                                        <span>Den</span>
                                        <select data-daily-day>
                                            <?php foreach ($plannerDays as $day): ?>
                                                <option value="<?= escape($day) ?>"><?= escape(formatCzechDate($day)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <div class="availability-daily-summary" data-daily-summary>
                                        <div class="summary-item">
                                            <span>Vybraný den</span>
                                            <strong data-daily-summary-day><?= escape(formatCzechDate($plannerDays[0] ?? '')) ?></strong>
                                        </div>
                                        <div class="summary-item">
                                            <span>Dostupné sloty</span>
                                            <strong data-daily-summary-total>0</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="availability-daily-legend">
                                    <span class="legend-item"><i class="legend-swatch is-active"></i>Dostupné</span>
                                    <span class="legend-item"><i class="legend-swatch is-booked"></i>Rezervované</span>
                                    <span class="legend-item"><i class="legend-swatch is-past"></i>Minulé</span>
                                </div>
                                <div class="availability-day-chips" data-daily-day-chips>
                                    <?php foreach ($plannerDays as $day): ?>
                                        <?php $dayNumber = (int) (new DateTimeImmutable($day))->format('N'); ?>
                                        <?php
                                            $shortDayNameMap = [
                                                1 => 'Po',
                                                2 => 'Út',
                                                3 => 'St',
                                                4 => 'Čt',
                                                5 => 'Pá',
                                                6 => 'So',
                                                7 => 'Ne',
                                            ];
                                            $shortDayName = $shortDayNameMap[$dayNumber] ?? '';
                                        ?>
                                        <button
                                            class="availability-day-chip"
                                            type="button"
                                            data-daily-day-chip="<?= escape($day) ?>"
                                            aria-label="<?= escape($shortDayName . ' ' . formatCzechDate($day)) ?>"
                                        >
                                            <span class="day-chip-week"><?= escape($shortDayName) ?></span>
                                            <span class="day-chip-date"><?= escape(formatCzechDate($day)) ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <div class="availability-daily-slots-wrap">
                                    <div class="availability-daily-slots" data-daily-slots>
                                        <div class="availability-daily-empty">Načítám denní přehled…</div>
                                    </div>
                                </div>
                                <div class="availability-daily-actions-panel">
                                    <div class="admin-form admin-form-grid availability-quick-grid">
                                        <label>
                                            <span>Od</span>
                                            <select data-daily-start>
                                                <?php foreach ($plannerSlots as $index => $slot): ?>
                                                    <option value="<?= escape($slot) ?>" <?= $index === 0 ? 'selected' : '' ?>><?= escape($slot) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>
                                            <span>Do</span>
                                            <select data-daily-end>
                                                <?php foreach ($plannerSlots as $index => $slot): ?>
                                                    <option value="<?= escape($slot) ?>" <?= $index === count($plannerSlots) - 1 ? 'selected' : '' ?>><?= escape($slot) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    </div>
                                    <div class="table-actions availability-quick-actions">
                                        <button class="button button-primary button-small" type="button" data-daily-add>Přidat rozsah</button>
                                        <button class="button button-secondary button-small" type="button" data-daily-remove>Odebrat rozsah</button>
                                    </div>
                                    <div class="table-actions availability-preset-actions">
                                        <button class="button button-secondary button-small" type="button" data-daily-preset-day>9:00-17:00</button>
                                        <button class="button button-danger button-small" type="button" data-daily-clear-day>Vymazat den</button>
                                    </div>
                                </div>
                                <p class="form-hint" data-daily-status>Klepněte na konkrétní čas nebo použijte interval pro rychlejší úpravu dne.</p>
                            </div>

                            <div class="availability-weekly-editor" data-availability-weekly-editor hidden>
                            <div class="availability-quick-entry" data-availability-quick-entry>
                                <p class="eyebrow">Týdenní rychlé zadání</p>
                                <div class="admin-form admin-form-grid availability-quick-grid">
                                    <label>
                                        <span>Den</span>
                                        <select data-quick-day>
                                            <?php foreach ($plannerDays as $day): ?>
                                                <option value="<?= escape($day) ?>"><?= escape(formatCzechDate($day)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Od</span>
                                        <select data-quick-start>
                                            <?php foreach ($plannerSlots as $index => $slot): ?>
                                                <option value="<?= escape($slot) ?>" <?= $index === 0 ? 'selected' : '' ?>><?= escape($slot) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Do</span>
                                        <select data-quick-end>
                                            <?php foreach ($plannerSlots as $index => $slot): ?>
                                                <option value="<?= escape($slot) ?>" <?= $index === count($plannerSlots) - 1 ? 'selected' : '' ?>><?= escape($slot) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                                <div class="availability-day-chips" data-quick-day-chips>
                                    <?php foreach ($plannerDays as $day): ?>
                                        <?php $dayNumber = (int) (new DateTimeImmutable($day))->format('N'); ?>
                                        <?php
                                            $shortDayNameMap = [
                                                1 => 'Po',
                                                2 => 'Út',
                                                3 => 'St',
                                                4 => 'Čt',
                                                5 => 'Pá',
                                                6 => 'So',
                                                7 => 'Ne',
                                            ];
                                            $shortDayName = $shortDayNameMap[$dayNumber] ?? '';
                                        ?>
                                        <button
                                            class="availability-day-chip"
                                            type="button"
                                            data-quick-day-chip="<?= escape($day) ?>"
                                            aria-label="<?= escape($shortDayName . ' ' . formatCzechDate($day)) ?>"
                                        >
                                            <span class="day-chip-week"><?= escape($shortDayName) ?></span>
                                            <span class="day-chip-date"><?= escape(formatCzechDate($day)) ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <div class="table-actions availability-quick-actions">
                                    <button class="button button-primary button-small" type="button" data-quick-add>Přidat interval</button>
                                    <button class="button button-secondary button-small" type="button" data-quick-remove>Odebrat interval</button>
                                </div>
                                <div class="table-actions availability-preset-actions">
                                    <button class="button button-secondary button-small" type="button" data-quick-preset-day>Vybraný den 9:00-17:00</button>
                                    <button class="button button-secondary button-small" type="button" data-quick-preset-week>Po-Pá 9:00-17:00</button>
                                    <button class="button button-danger button-small" type="button" data-quick-clear-day>Vymazat vybraný den</button>
                                </div>
                                <p class="form-hint" data-quick-status>Tip: vyberte den a interval, pak přidejte nebo odeberte dostupnost jedním kliknutím.</p>
                            </div>

                            <details class="availability-detail-wrap">
                                <summary>Detailní mřížka (pokročilé)</summary>
                                <div class="availability-legend">
                                    <span class="legend-item"><i class="legend-swatch is-active"></i>Dostupné</span>
                                    <span class="legend-item"><i class="legend-swatch is-booked"></i>Rezervované</span>
                                    <span class="legend-item"><i class="legend-swatch is-past"></i>Minulé</span>
                                    <span class="legend-item"><i class="legend-swatch is-weekend"></i>Víkend</span>
                                    <span class="legend-item"><i class="legend-swatch is-holiday"></i>Svátek</span>
                                </div>
                                <div
                                    class="availability-planner"
                                    data-availability-planner
                                    data-initial-windows="<?= escape(json_encode($plannerInitialWindows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]') ?>"
                                    data-booked-windows="<?= escape(json_encode($plannerBookedWindows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]') ?>"
                                >
                                    <div class="planner-scroll">
                                        <div class="planner-grid" style="--planner-day-columns: <?= escape((string) count($plannerDays)) ?>;">
                                            <div class="planner-corner">Čas</div>
                                            <?php foreach ($plannerDays as $day): ?>
                                                <?php
                                                    $dayMeta = $plannerDayMeta[$day] ?? ['is_weekend' => false, 'is_holiday' => false, 'holiday_name' => null];
                                                    $dayClasses = ['planner-day-header'];
                                                    $dayNameMap = [
                                                        1 => 'Pondělí',
                                                        2 => 'Úterý',
                                                        3 => 'Středa',
                                                        4 => 'Čtvrtek',
                                                        5 => 'Pátek',
                                                        6 => 'Sobota',
                                                        7 => 'Neděle',
                                                    ];
                                                    $dayNumber = (int) (new DateTimeImmutable($day))->format('N');
                                                    $dayName = $dayNameMap[$dayNumber] ?? '';
                                                    if ($dayMeta['is_weekend']) {
                                                        $dayClasses[] = 'is-weekend';
                                                    }
                                                    if ($dayMeta['is_holiday']) {
                                                        $dayClasses[] = 'is-holiday';
                                                    }
                                                ?>
                                                <div class="<?= escape(implode(' ', $dayClasses)) ?>" title="<?= escape((string) ($dayMeta['holiday_name'] ?? '')) ?>">
                                                    <strong><?= escape(formatCzechDate($day)) ?></strong>
                                                    <span class="planner-day-name"><?= escape($dayName) ?></span>
                                                    <?php if ($dayMeta['is_holiday']): ?>
                                                        <span class="planner-day-badge">Svátek</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>

                                            <?php foreach ($plannerSlots as $slot): ?>
                                                <div class="planner-time-label"><?= escape($slot) ?></div>
                                                <?php foreach ($plannerDays as $day): ?>
                                                    <?php
                                                        $dayMeta = $plannerDayMeta[$day] ?? ['is_weekend' => false, 'is_holiday' => false, 'holiday_name' => null];
                                                        $cellClasses = ['planner-cell'];
                                                        if ($dayMeta['is_weekend']) {
                                                            $cellClasses[] = 'is-weekend';
                                                        }
                                                        if ($dayMeta['is_holiday']) {
                                                            $cellClasses[] = 'is-holiday';
                                                        }
                                                    ?>
                                                    <button
                                                        type="button"
                                                        class="<?= escape(implode(' ', $cellClasses)) ?>"
                                                        data-date="<?= escape($day) ?>"
                                                        data-time="<?= escape($slot) ?>"
                                                        aria-label="<?= escape(formatCzechDate($day) . ' ' . $slot . ($dayMeta['is_holiday'] ? ' ' . (string) $dayMeta['holiday_name'] : '')) ?>"
                                                        title="<?= escape(formatCzechDate($day) . ' ' . $slot . ($dayMeta['is_holiday'] ? ' • ' . (string) $dayMeta['holiday_name'] : '')) ?>"
                                                        aria-pressed="false"
                                                    ></button>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </details>
                            </div>

                            <div class="table-actions availability-save-actions availability-save-actions-sticky">
                                <button class="button button-primary" type="submit" name="save_availability_grid" value="1">Uložit plánovač dostupnosti</button>
                            </div>
                        </form>
                        <p class="form-hint">Jedním tahem označíte dostupné půlhodiny myší i prstem. „Vrátit poslední úpravu“ vrátí poslední krok. „Obnovit původní stav týdne“ zahodí všechny neuložené změny v aktuálním týdnu. Uložením přepíšete dostupnost jen pro právě zobrazený týden. Rezervované termíny zůstávají v systému zachované.</p>
                        <details class="availability-list-wrap">
                            <summary>Seznam uložených oken</summary>
                            <div class="admin-table-wrap planner-table-wrap">
                                <table class="admin-table availability-admin-table">
                                    <thead><tr><th>Datum</th><th>Časové okno</th><th>Poznámka</th><th>Akce</th></tr></thead>
                                    <tbody>
                                        <?php if ($availabilityRows === []): ?>
                                            <tr><td colspan="4">Zatím nemáte zadaná žádná volná okna.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($availabilityRows as $row): ?>
                                                <tr>
                                                    <td data-label="Datum"><?= escape(formatCzechDate(substr((string) $row['start_at'], 0, 10))) ?></td>
                                                    <td data-label="Časové okno"><?= escape(substr((string) $row['start_at'], 11, 5)) ?> - <?= escape(substr((string) $row['end_at'], 11, 5)) ?></td>
                                                    <td data-label="Poznámka"><?= escape((string) ($row['poznamka'] ?? '')) ?></td>
                                                    <td data-label="Akce">
                                                        <form method="post">
                                                            <?= csrfInputField() ?>
                                                            <input type="hidden" name="window_id" value="<?= escape((string) $row['id']) ?>">
                                                            <button class="button button-danger button-small" type="submit" name="delete_window" value="1">Smazat</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </div>
                </section>
