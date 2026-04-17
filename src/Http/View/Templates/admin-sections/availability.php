                <section class="admin-single" id="dostupnost">
                    <div class="admin-card">
                        <p class="eyebrow">Dostupnost</p>
                        <h2>Plánování volných termínů</h2>
                        <p class="form-hint availability-intro">Pro běžnou práci používejte denní režim. Týdenní režim je vhodný pro hromadné úpravy a kontrolu celého týdne.</p>
                        <div class="availability-toolbar">
                            <div class="availability-week-summary">
                                <span class="availability-summary-label">Zobrazený týden</span>
                                <strong><?= \PPStudio\Support\ViewHelper::escape($plannerWeekLabel) ?></strong>
                            </div>
                            <div class="table-actions availability-week-actions">
                                <a class="button button-secondary button-small" href="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>?tab=dostupnost&amp;planner_week=<?= \PPStudio\Support\ViewHelper::escape((string) ($plannerWeekOffset - 1)) ?>#dostupnost">Předchozí</a>
                                <a class="button button-secondary button-small" href="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>?tab=dostupnost&amp;planner_week=0#dostupnost">Tento týden</a>
                                <a class="button button-secondary button-small" href="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>?tab=dostupnost&amp;planner_week=<?= \PPStudio\Support\ViewHelper::escape((string) ($plannerWeekOffset + 1)) ?>#dostupnost">Další</a>
                            </div>
                        </div>
                        <form method="post" class="admin-form" data-availability-planner-form data-save-endpoint="/api/admin/availability-planner.php">
                            <?= csrfInputField() ?>
                            <input type="hidden" name="planner_start" value="<?= \PPStudio\Support\ViewHelper::escape($plannerDays[0] ?? '') ?>">
                            <input type="hidden" name="planner_end" value="<?= \PPStudio\Support\ViewHelper::escape($plannerDays[count($plannerDays) - 1] ?? '') ?>">
                            <input type="hidden" name="planner_windows" value="[]">

                            <div class="availability-mode-card">
                                <div>
                                    <p class="eyebrow">Režim práce</p>
                                    <p class="form-hint">Denní režim je nejrychlejší pro běžnou obsluhu. Týdenní režim používejte při větších změnách.</p>
                                </div>
                                <div class="availability-mode-switch" role="group" aria-label="Režim plánování">
                                    <button class="button button-secondary button-small is-active" type="button" data-planner-mode-trigger="daily">Denní režim</button>
                                    <button class="button button-secondary button-small" type="button" data-planner-mode-trigger="weekly">Týdenní režim</button>
                                </div>
                            </div>

                            <div class="availability-daily-entry" data-availability-daily-entry>
                                <p class="eyebrow">Denní režim</p>
                                <div class="availability-daily-headline">
                                    <strong>Rychlá úprava jednoho dne</strong>
                                    <span>Vyberte den, klepnutím zapínejte nebo vypínejte sloty a případně použijte hromadnou úpravu rozsahu.</span>
                                </div>
                                <div class="admin-form admin-form-grid availability-daily-grid">
                                    <label class="availability-day-select">
                                        <span>Vybraný den</span>
                                        <select data-daily-day>
                                            <?php if ($plannerEditableDays === []): ?>
                                                <option value="">Žádné budoucí dny</option>
                                            <?php else: ?>
                                                <?php foreach ($plannerEditableDays as $day): ?>
                                                    <option value="<?= \PPStudio\Support\ViewHelper::escape($day) ?>"><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatCzechDate($day)) ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </label>
                                    <div class="availability-daily-summary" data-daily-summary>
                                        <div class="summary-item">
                                            <span>Den</span>
                                            <strong data-daily-summary-day><?= \PPStudio\Support\ViewHelper::escape($plannerEditableDays === [] ? 'Žádné budoucí dny' : \PPStudio\Support\FormatHelper::formatCzechDate($plannerEditableDays[0] ?? '')) ?></strong>
                                        </div>
                                        <div class="summary-item">
                                            <span>Volné sloty</span>
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
                                    <?php if ($plannerEditableDays !== []): ?>
                                        <?php foreach ($plannerEditableDays as $day): ?>
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
                                                data-daily-day-chip="<?= \PPStudio\Support\ViewHelper::escape($day) ?>"
                                                aria-label="<?= \PPStudio\Support\ViewHelper::escape($shortDayName . ' ' . \PPStudio\Support\FormatHelper::formatCzechDate($day)) ?>"
                                            >
                                                <span class="day-chip-week"><?= \PPStudio\Support\ViewHelper::escape($shortDayName) ?></span>
                                                <span class="day-chip-date"><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatCzechDate($day)) ?></span>
                                            </button>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="form-hint">V tomto týdnu už nejsou budoucí dny k úpravě.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="availability-daily-slots-wrap">
                                    <div class="availability-daily-slots" data-daily-slots>
                                        <div class="availability-daily-empty">Načítám denní přehled…</div>
                                    </div>
                                </div>
                                <div class="availability-daily-actions-panel">
                                    <div class="availability-block-heading">
                                        <strong>Hromadná úprava dne</strong>
                                        <span>Použijte pro rychlé přidání nebo odebrání celého rozsahu.</span>
                                    </div>
                                    <div class="admin-form admin-form-grid availability-quick-grid">
                                        <label>
                                            <span>Od</span>
                                            <select data-daily-start>
                                                <?php foreach ($plannerSlots as $index => $slot): ?>
                                                    <option value="<?= \PPStudio\Support\ViewHelper::escape($slot) ?>" <?= $index === 0 ? 'selected' : '' ?>><?= \PPStudio\Support\ViewHelper::escape($slot) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>
                                            <span>Do</span>
                                            <select data-daily-end>
                                                <?php foreach ($plannerSlots as $index => $slot): ?>
                                                    <option value="<?= \PPStudio\Support\ViewHelper::escape($slot) ?>" <?= $index === count($plannerSlots) - 1 ? 'selected' : '' ?>><?= \PPStudio\Support\ViewHelper::escape($slot) ?></option>
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
                                <div class="availability-weekly-layout">
                                <div class="availability-quick-entry" data-availability-quick-entry>
                                    <p class="eyebrow">Týdenní rychlé zadání</p>
                                    <div class="availability-block-heading">
                                        <strong>Hromadná změna pro vybraný den nebo pracovní týden</strong>
                                        <span>Nejprve vyberte den a rozsah, pak použijte akci níže. Týdenní šablona se hodí pro rychlé doplnění Po–Pá.</span>
                                    </div>
                                    <div class="admin-form admin-form-grid availability-quick-grid">
                                    <label>
                                        <span>Den</span>
                                        <select data-quick-day>
                                            <?php if ($plannerEditableDays === []): ?>
                                                <option value="">Žádné budoucí dny</option>
                                            <?php else: ?>
                                                <?php foreach ($plannerEditableDays as $day): ?>
                                                    <option value="<?= \PPStudio\Support\ViewHelper::escape($day) ?>"><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatCzechDate($day)) ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </label>
                                        <label>
                                            <span>Od</span>
                                            <select data-quick-start>
                                                <?php foreach ($plannerSlots as $index => $slot): ?>
                                                    <option value="<?= \PPStudio\Support\ViewHelper::escape($slot) ?>" <?= $index === 0 ? 'selected' : '' ?>><?= \PPStudio\Support\ViewHelper::escape($slot) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>
                                            <span>Do</span>
                                            <select data-quick-end>
                                                <?php foreach ($plannerSlots as $index => $slot): ?>
                                                    <option value="<?= \PPStudio\Support\ViewHelper::escape($slot) ?>" <?= $index === count($plannerSlots) - 1 ? 'selected' : '' ?>><?= \PPStudio\Support\ViewHelper::escape($slot) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    </div>
                                    <div class="availability-day-chips" data-quick-day-chips>
                                        <?php if ($plannerEditableDays !== []): ?>
                                            <?php foreach ($plannerEditableDays as $day): ?>
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
                                                    data-quick-day-chip="<?= \PPStudio\Support\ViewHelper::escape($day) ?>"
                                                    aria-label="<?= \PPStudio\Support\ViewHelper::escape($shortDayName . ' ' . \PPStudio\Support\FormatHelper::formatCzechDate($day)) ?>"
                                                >
                                                    <span class="day-chip-week"><?= \PPStudio\Support\ViewHelper::escape($shortDayName) ?></span>
                                                    <span class="day-chip-date"><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatCzechDate($day)) ?></span>
                                                </button>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="form-hint">V tomto týdnu už nejsou budoucí dny k úpravě.</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="table-actions availability-quick-actions">
                                        <button class="button button-primary button-small" type="button" data-quick-add>Přidat interval</button>
                                        <button class="button button-secondary button-small" type="button" data-quick-remove>Odebrat interval</button>
                                    </div>
                                    <div class="table-actions availability-preset-actions">
                                        <button class="button button-secondary button-small" type="button" data-quick-preset-day>Vybraný den 9:00-17:00</button>
                                        <button class="button button-secondary button-small" type="button" data-quick-preset-week>Po–Pá 9:00-17:00</button>
                                        <button class="button button-danger button-small" type="button" data-quick-clear-day>Vymazat vybraný den</button>
                                    </div>
                                    <p class="form-hint" data-quick-status>Vyberte den a interval, pak jedním klikem přidejte nebo odeberte dostupnost.</p>
                                </div>

                                <details class="availability-detail-wrap" open>
                                    <summary>Týdenní mřížka</summary>
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
                                    data-initial-windows="<?= \PPStudio\Support\ViewHelper::escape(json_encode($plannerInitialWindows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]') ?>"
                                    data-booked-windows="<?= \PPStudio\Support\ViewHelper::escape(json_encode($plannerBookedWindows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]') ?>"
                                >
                                    <div class="planner-scroll">
                                        <div class="planner-grid" style="--planner-day-columns: <?= \PPStudio\Support\ViewHelper::escape((string) count($plannerDays)) ?>;">
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
                                                <div class="<?= \PPStudio\Support\ViewHelper::escape(implode(' ', $dayClasses)) ?>" title="<?= \PPStudio\Support\ViewHelper::escape((string) ($dayMeta['holiday_name'] ?? '')) ?>">
                                                    <strong><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatCzechDate($day)) ?></strong>
                                                    <span class="planner-day-name"><?= \PPStudio\Support\ViewHelper::escape($dayName) ?></span>
                                                    <?php if ($dayMeta['is_holiday']): ?>
                                                        <span class="planner-day-badge">Svátek</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>

                                            <?php foreach ($plannerSlots as $slot): ?>
                                                <div class="planner-time-label"><?= \PPStudio\Support\ViewHelper::escape($slot) ?></div>
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
                                                        class="<?= \PPStudio\Support\ViewHelper::escape(implode(' ', $cellClasses)) ?>"
                                                        data-date="<?= \PPStudio\Support\ViewHelper::escape($day) ?>"
                                                        data-time="<?= \PPStudio\Support\ViewHelper::escape($slot) ?>"
                                                        aria-label="<?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatCzechDate($day) . ' ' . $slot . ($dayMeta['is_holiday'] ? ' ' . (string) $dayMeta['holiday_name'] : '')) ?>"
                                                        title="<?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatCzechDate($day) . ' ' . $slot . ($dayMeta['is_holiday'] ? ' • ' . (string) $dayMeta['holiday_name'] : '')) ?>"
                                                        aria-pressed="false"
                                                    ></button>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                </details>
                                </div>
                            </div>

                            <div class="availability-change-summary" data-planner-change-summary aria-live="polite">
                                <div class="summary-item">
                                    <span>Aktivní sloty</span>
                                    <strong data-summary-total>0</strong>
                                </div>
                                <div class="summary-item">
                                    <span>Přidáno</span>
                                    <strong data-summary-added>0</strong>
                                </div>
                                <div class="summary-item">
                                    <span>Odebráno</span>
                                    <strong data-summary-removed>0</strong>
                                </div>
                                <div class="summary-item">
                                    <span>Blokováno</span>
                                    <strong data-summary-blocked>0</strong>
                                </div>
                                <p class="form-hint availability-dirty-state" data-planner-dirty-state>Bez neuložených změn.</p>
                                <p class="form-hint" data-planner-save-feedback></p>
                                <div class="table-actions">
                                    <button class="button button-secondary button-small" type="button" data-undo-change disabled>Zpět</button>
                                    <button class="button button-secondary button-small" type="button" data-reset-changes disabled>Obnovit týden</button>
                                </div>
                            </div>

                            <div class="table-actions availability-save-actions availability-save-actions-sticky">
                                <button class="button button-primary button-small availability-save-button" type="submit" name="save_availability_grid" value="1">Uložit změny</button>
                            </div>
                        </form>
                        <p class="form-hint">Uložením přepíšete dostupnost jen pro právě zobrazený týden. Rezervované termíny zůstávají zachované a v mřížce je nepřepíšete.</p>
                        <details class="availability-list-wrap" data-availability-list-wrap data-delete-endpoint="/api/admin/availability-window.php">
                            <summary data-availability-list-summary>Uložená okna dostupnosti (<?= \PPStudio\Support\ViewHelper::escape((string) count($availabilityRows)) ?>)</summary>
                            <p class="form-hint availability-list-intro">Přehled uložených intervalů pro rychlou kontrolu a ruční smazání jednotlivých oken.</p>
                            <div class="admin-table-wrap planner-table-wrap">
                                <table class="admin-table availability-admin-table">
                                    <thead><tr><th>Datum</th><th>Časové okno</th><th>Poznámka</th><th>Akce</th></tr></thead>
                                    <tbody data-availability-list-body>
                                        <?php if ($availabilityRows === []): ?>
                                            <tr><td colspan="4">Zatím nejsou zadána žádná volná okna.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($availabilityRows as $row): ?>
                                                <tr>
                                                    <td data-label="Datum"><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatCzechDate(substr((string) $row['start_at'], 0, 10))) ?></td>
                                                    <td data-label="Časové okno"><?= \PPStudio\Support\ViewHelper::escape(substr((string) $row['start_at'], 11, 5)) ?> - <?= \PPStudio\Support\ViewHelper::escape(substr((string) $row['end_at'], 11, 5)) ?></td>
                                                    <td data-label="Poznámka"><?= \PPStudio\Support\ViewHelper::escape((string) ($row['poznamka'] ?? '')) ?></td>
                                                    <td data-label="Akce">
                                                        <form method="post">
                                                            <?= csrfInputField() ?>
                                                            <input type="hidden" name="window_id" value="<?= \PPStudio\Support\ViewHelper::escape((string) $row['id']) ?>">
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
                    <div class="admin-card availability-story-card">
                        <p class="eyebrow">Instagram story</p>
                        <h2>Vygenerovat obrázek s volnými termíny</h2>
                        <p class="form-hint">Můžete si zvolit styl, použít vlastní background a nejdřív zkontrolovat náhled. Export pak stáhne přesně to, co vidíte.</p>

                        <div class="availability-story-template-wrap">
                            <div class="availability-story-template-card">
                                <p class="eyebrow">Šablona pozadí</p>
                                <h3>Vlastní background</h3>
                                <?php if ($storyBackgroundUrl !== ''): ?>
                                    <div class="availability-story-template-preview">
                                        <img src="<?= \PPStudio\Support\ViewHelper::escape($storyBackgroundUrl) ?>" alt="Aktuální background pro Instagram story" loading="lazy" decoding="async">
                                    </div>
                                    <p class="form-hint">Aktuálně se používá vaše vlastní šablona pozadí.</p>
                                <?php else: ?>
                                    <p class="form-hint">Zatím není nahrané vlastní pozadí. Použije se vestavěný styl generátoru.</p>
                                <?php endif; ?>
                            </div>
                            <div class="availability-story-template-card">
                                <form method="post" class="admin-form" enctype="multipart/form-data">
                                    <?= csrfInputField() ?>
                                    <label>
                                        <span>Nahrát nové pozadí</span>
                                        <input type="file" name="story_background" accept=".jpg,.jpeg,.png,.webp,.gif">
                                    </label>
                                    <div class="table-actions availability-story-template-actions">
                                        <button class="button button-secondary" type="submit" name="save_availability_story_background" value="1">Uložit pozadí</button>
                                        <?php if ($storyBackgroundUrl !== ''): ?>
                                            <button class="button button-danger" type="submit" name="delete_availability_story_background" value="1">Odebrat pozadí</button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <form method="post" action="/admin-availability-story.php" class="admin-form" data-availability-story-form data-preview-endpoint="/admin-availability-story.php">
                            <?= csrfInputField() ?>
                            <input type="hidden" name="story_background_path" value="<?= \PPStudio\Support\ViewHelper::escape($storyBackground) ?>">
                            <div class="admin-form admin-form-grid availability-story-grid">
                                <label>
                                    <span>Styl výstupu</span>
                                    <select name="story_style">
                                        <option value="story" selected>Story</option>
                                        <option value="minimal">Minimal</option>
                                        <option value="feed">Feed příspěvek</option>
                                    </select>
                                </label>
                                <label>
                                    <span>Nadpis</span>
                                    <input type="text" name="story_title" value="Zbývají volné termíny">
                                </label>
                                <label>
                                    <span>Nadpis měsíce</span>
                                    <input type="text" name="story_month_label" value="<?= \PPStudio\Support\ViewHelper::escape($storyDefaultMonth) ?>">
                                </label>
                                <label>
                                    <span>Od data</span>
                                    <input type="date" name="story_from" value="<?= \PPStudio\Support\ViewHelper::escape($storyDefaultFrom) ?>">
                                </label>
                                <label>
                                    <span>Do data</span>
                                    <input type="date" name="story_to" value="<?= \PPStudio\Support\ViewHelper::escape($storyDefaultTo) ?>">
                                </label>
                                <label>
                                    <span>Max. počet dnů</span>
                                    <input type="number" name="story_max_days" min="1" max="8" step="1" value="5">
                                </label>
                                <label>
                                    <span>Max. časů za den</span>
                                    <input type="number" name="story_max_times_per_day" min="1" max="8" step="1" value="5">
                                </label>
                                <label class="availability-story-services">
                                    <span>Řádky pod termíny</span>
                                    <textarea name="story_services" rows="5" placeholder="Každý řádek bude na obrázku jako samostatný štítek"><?= \PPStudio\Support\ViewHelper::escape(implode("\n", $storyDefaultServices)) ?></textarea>
                                </label>
                            </div>
                            <div class="availability-story-preview-card">
                                <div class="availability-block-heading">
                                    <strong>Náhled před stažením</strong>
                                    <span>Po změně parametrů se náhled průběžně obnovuje.</span>
                                </div>
                                <div class="availability-story-preview-frame">
                                    <img
                                        src="/admin-availability-story.php?preview=1&amp;story_style=story&amp;story_title=<?= \PPStudio\Support\ViewHelper::escape(urlencode('Zbývají volné termíny')) ?>&amp;story_month_label=<?= \PPStudio\Support\ViewHelper::escape(urlencode($storyDefaultMonth)) ?>&amp;story_from=<?= \PPStudio\Support\ViewHelper::escape($storyDefaultFrom) ?>&amp;story_to=<?= \PPStudio\Support\ViewHelper::escape($storyDefaultTo) ?>&amp;story_max_days=5&amp;story_max_times_per_day=5&amp;story_services=<?= \PPStudio\Support\ViewHelper::escape(urlencode(implode("\n", $storyDefaultServices))) ?>&amp;story_background_path=<?= \PPStudio\Support\ViewHelper::escape(urlencode($storyBackground)) ?>"
                                        alt="Náhled Instagram story s volnými termíny"
                                        data-availability-story-preview
                                    >
                                </div>
                            </div>
                            <div class="table-actions availability-story-actions">
                                <button class="button button-secondary" type="button" data-availability-story-refresh>Obnovit náhled</button>
                                <button class="button button-primary" type="submit">Stáhnout PNG pro Instagram</button>
                            </div>
                        </form>
                    </div>
                </section>
