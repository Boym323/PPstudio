                <section class="admin-single" id="rezervace-list">
                    <div class="admin-note" data-reservations-root>
                        <p class="eyebrow">Rezervace</p>
                        <h2>Objednávky a jejich aktuální stav</h2>
                        <form method="get" action="<?= escape($adminBasePath ?? 'admin.php') ?>" class="admin-form admin-form-grid reservations-filter-form">
                            <input type="hidden" name="tab" value="rezervace-list">
                            <label>
                                <span>Hledat (jméno / e-mail / telefon)</span>
                                <input type="text" name="reservation_q" value="<?= escape($reservationFilters['q']) ?>" placeholder="Např. Nováková nebo +420...">
                            </label>
                            <label>
                                <span>Stav</span>
                                <select name="reservation_status">
                                    <?php foreach ($reservationStatusFilterOptions as $statusValue => $statusLabel): ?>
                                        <option value="<?= escape($statusValue) ?>" <?= $statusValue === $reservationFilters['status'] ? 'selected' : '' ?>><?= escape($statusLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Období</span>
                                <select name="reservation_period">
                                    <?php foreach ($reservationPeriodFilterOptions as $periodValue => $periodLabel): ?>
                                        <option value="<?= escape($periodValue) ?>" <?= $periodValue === $reservationFilters['period'] ? 'selected' : '' ?>><?= escape($periodLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Na stránku</span>
                                <select name="reservation_per_page">
                                    <?php foreach ($reservationPerPageOptions as $perPageValue): ?>
                                        <option value="<?= escape((string) $perPageValue) ?>" <?= $perPageValue === $reservationFilters['per_page'] ? 'selected' : '' ?>><?= escape((string) $perPageValue) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="table-actions full-span">
                                <button class="button button-primary button-small" type="submit">Použít filtr</button>
                                <a class="button button-secondary button-small" href="<?= escape($adminBasePath ?? 'admin.php') ?>?tab=rezervace-list#rezervace-list">Reset</a>
                            </div>
                        </form>
                        <p class="form-hint">Nalezeno rezervací: <strong data-reservation-total><?= escape((string) $reservationPagination['total']) ?></strong>. Stránka <?= escape((string) $reservationFilters['page']) ?> z <?= escape((string) $reservationPagination['total_pages']) ?>.</p>
                        <div class="admin-table-wrap">
                            <table class="admin-table reservations-admin-table">
                                <thead>
                                    <tr>
                                        <th>Termín</th>
                                        <th>Klientka</th>
                                        <th>Procedura</th>
                                        <th>Cena</th>
                                        <th>Stav</th>
                                        <th>Kontakt</th>
                                        <th>Akce</th>
                                    </tr>
                                </thead>
                                <tbody data-reservation-tbody>
                                    <?php if ($reservationRows === []): ?>
                                        <tr data-reservation-empty-row><td colspan="7">Zatím zde nejsou žádné rezervace.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($reservationRows as $row): ?>
                                            <?php
                                            $cancelledByKey = (string) ($row['zruseno_kym'] ?? '');
                                            $cancelledByLabel = match ($cancelledByKey) {
                                                'customer_link' => 'Zákazník přes e-mailový odkaz',
                                                'admin_full' => 'Admin',
                                                'admin_lite' => 'User admin',
                                                default => ($cancelledByKey !== '' ? $cancelledByKey : ''),
                                            };
                                            $dateTimeLocalValue = '';
                                            $dateTimeTimestamp = strtotime((string) ($row['datum_cas'] ?? ''));
                                            if ($dateTimeTimestamp) {
                                                $dateTimeLocalValue = date('Y-m-d\TH:i', $dateTimeTimestamp);
                                            }
                                            $serviceId = (int) ($row['service_id'] ?? 0);
                                            $statusKey = (string) ($row['stav'] ?? 'nova');
                                            $statusLabel = reservationStatusLabel($statusKey);
                                            $sourceLabel = $reservationSourceOptions[(string) ($row['zdroj'] ?? '')] ?? ucfirst((string) ($row['zdroj'] ?? 'web'));
                                            $clientNote = trim((string) ($row['poznamka_klienta'] ?? ''));
                                            $adminNote = trim((string) ($row['poznamka_admina'] ?? ''));
                                            $cancelReason = trim((string) ($row['duvod_zruseni'] ?? ''));
                                            ?>
                                            <tr class="reservation-row" data-reservation-row data-reservation-id="<?= escape((string) $row['id']) ?>" data-reservation-client="<?= escape((string) ($row['jmeno'] ?? '')) ?>" data-reservation-datetime="<?= escape(formatCzechDateTime((string) $row['datum_cas'])) ?>" data-reservation-service-id="<?= escape((string) $serviceId) ?>" data-reservation-datetime-local="<?= escape($dateTimeLocalValue) ?>">
                                                <td data-label="Termín"><?= escape(formatCzechDateTime((string) $row['datum_cas'])) ?></td>
                                                <td data-label="Klientka"><?= escape((string) $row['jmeno']) ?></td>
                                                <td data-label="Procedura">
                                                    <div class="reservation-service-main"><?= escape((string) $row['nazev']) ?></div>
                                                    <div class="reservation-service-meta"><?= escape($sourceLabel) ?></div>
                                                </td>
                                                <td data-label="Cena"><?= escape(formatPrice($row['cena_v_dobe_rezervace'] ?? null)) ?></td>
                                                <td data-label="Stav" class="reservation-status-cell"><span class="status-badge status-<?= escape($statusKey) ?>" data-reservation-status-badge><?= escape($statusLabel) ?></span></td>
                                                <td data-label="Kontakt" class="reservation-contact"><div><?= escape((string) $row['email']) ?></div><div><?= escape((string) ($row['telefon'] ?? '')) ?></div></td>
                                                <td data-label="Akce" class="reservation-summary-actions">
                                                    <button class="button button-secondary button-small" type="button" data-reservation-detail-toggle data-open-label="Detail" data-close-label="Skrýt detail" aria-expanded="false">Detail</button>
                                                </td>
                                            </tr>
                                            <tr class="reservation-detail-row" data-reservation-detail-row data-reservation-id="<?= escape((string) $row['id']) ?>" hidden>
                                                <td colspan="7" class="reservation-detail-cell">
                                                    <form method="post" class="admin-form compact-form compact-form-reservation" data-reservation-form>
                                                        <?= csrfInputField() ?>
                                                        <input type="hidden" name="reservation_id" value="<?= escape((string) $row['id']) ?>">
                                                        <input type="hidden" name="datum_cas" value="<?= escape($dateTimeLocalValue) ?>" data-reschedule-datetime>
                                                        <div class="reservation-detail-grid">
                                                            <div class="reservation-detail-block">
                                                                <h3>Souhrn rezervace</h3>
                                                                <div class="reservation-overview-hero">
                                                                    <div class="reservation-overview-datetime">
                                                                        <span>Termín</span>
                                                                        <strong data-reservation-datetime-text><?= escape(formatCzechDateTime((string) $row['datum_cas'])) ?></strong>
                                                                    </div>
                                                                    <div class="reservation-overview-price">
                                                                        <span>Cena</span>
                                                                        <strong><?= escape(formatPrice($row['cena_v_dobe_rezervace'] ?? null)) ?></strong>
                                                                    </div>
                                                                </div>
                                                                <div class="reservation-detail-list reservation-detail-list-grid">
                                                                    <div><strong>Procedura</strong><span><?= escape((string) $row['nazev']) ?></span></div>
                                                                    <div><strong>Klientka</strong><span><?= escape((string) $row['jmeno']) ?></span></div>
                                                                    <div><strong>Kontakt</strong><span><?= escape((string) $row['email']) ?><?php if ((string) ($row['telefon'] ?? '') !== ''): ?><br><?= escape((string) $row['telefon']) ?><?php endif; ?></span></div>
                                                                    <div><strong>Zdroj</strong><span><?= escape($sourceLabel) ?></span></div>
                                                                </div>
                                                            </div>
                                                            <div class="reservation-detail-block">
                                                                <h3>Poznámky</h3>
                                                                <div class="reservation-detail-notes">
                                                                    <div><strong>Poznámka klientky</strong><span><?= escape($clientNote !== '' ? $clientNote : 'Bez poznámky') ?></span></div>
                                                                    <label>
                                                                        <span>Interní poznámka</span>
                                                                        <input type="text" name="poznamka_admina" value="<?= escape($adminNote) ?>" placeholder="Interní poznámka">
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <div class="reservation-detail-block">
                                                                <h3>Stav rezervace</h3>
                                                                <div class="reservation-status-editor">
                                                                    <div class="reservation-status-current">
                                                                        <span>Aktuální stav</span>
                                                                        <span class="status-badge status-<?= escape($statusKey) ?>" data-reservation-status-badge><?= escape($statusLabel) ?></span>
                                                                    </div>
                                                                    <label>
                                                                        <span>Nový stav</span>
                                                                        <select name="stav" data-reservation-status-select>
                                                                            <?php foreach (reservationStatusOptions() as $statusValue => $statusLabelOption): ?>
                                                                                <option value="<?= escape($statusValue) ?>" <?= $statusValue === $statusKey ? 'selected' : '' ?>><?= escape($statusLabelOption) ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </label>
                                                                    <label class="reservation-cancel-reason-wrap<?= $statusKey === 'zrusena' ? '' : ' is-hidden' ?>" data-cancel-reason-wrap>
                                                                        <span>Důvod zrušení</span>
                                                                        <input type="text" name="duvod_zruseni" value="<?= escape($cancelReason) ?>" placeholder="Povinné při zrušení rezervace">
                                                                    </label>
                                                                </div>
                                                                <?php if ($statusKey === 'zrusena' && ($cancelledByLabel !== '' || (string) ($row['zruseno_at'] ?? '') !== '')): ?>
                                                                    <div class="reservation-cancel-meta">
                                                                        <strong>Zrušeno:</strong>
                                                                        <?= escape($cancelledByLabel !== '' ? $cancelledByLabel : 'neznámý zdroj') ?>
                                                                        <?php if ((string) ($row['zruseno_uzivatel'] ?? '') !== ''): ?>
                                                                            (<?= escape((string) $row['zruseno_uzivatel']) ?>)
                                                                        <?php endif; ?>
                                                                        <?php if ((string) ($row['zruseno_at'] ?? '') !== ''): ?>
                                                                            dne <?= escape(formatCzechDateTime((string) ($row['zruseno_at'] ?? ''))) ?>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="reservation-detail-block">
                                                                <h3>Přeplánování</h3>
                                                                <button class="button button-secondary button-small" type="button" data-reschedule-toggle>Přeplánovat</button>
                                                                <div class="reservation-reschedule-box" data-reschedule-box hidden>
                                                                    <label>
                                                                        <span>Dostupný den</span>
                                                                        <select data-reschedule-day>
                                                                            <option value="">Vyberte den</option>
                                                                        </select>
                                                                    </label>
                                                                    <label>
                                                                        <span>Dostupný čas</span>
                                                                        <select data-reschedule-time disabled>
                                                                            <option value="">Nejprve vyberte den</option>
                                                                        </select>
                                                                    </label>
                                                                    <div class="form-hint" data-reschedule-picked>Nový termín zatím není vybraný.</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="reservation-detail-actions">
                                                            <button class="button button-primary button-small" type="submit" name="update_reservation" value="1">Uložit změny</button>
                                                        </div>
                                                        <details class="reservation-danger-zone">
                                                            <summary>Trvalé smazání rezervace</summary>
                                                            <p class="form-hint">Použijte jen pokud má být rezervace odstraněna úplně ze systému.</p>
                                                            <button class="button button-danger button-small" type="submit" name="delete_reservation" value="1">Smazat rezervaci</button>
                                                        </details>
                                                        <div class="reservation-inline-feedback" data-reservation-feedback role="status" aria-live="polite"></div>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($reservationPagination['total_pages'] > 1): ?>
                            <div class="table-actions reservations-pagination">
                                <?php
                                $baseParams = [
                                    'tab' => 'rezervace-list',
                                    'reservation_q' => $reservationFilters['q'],
                                    'reservation_status' => $reservationFilters['status'],
                                    'reservation_period' => $reservationFilters['period'],
                                    'reservation_per_page' => (string) $reservationFilters['per_page'],
                                ];
                                $prevPage = max(1, $reservationFilters['page'] - 1);
                                $nextPage = min($reservationPagination['total_pages'], $reservationFilters['page'] + 1);
                                ?>
                                <a class="button button-secondary button-small<?= $reservationFilters['page'] <= 1 ? ' is-disabled' : '' ?>" href="<?= escape($adminBasePath ?? 'admin.php') ?>?<?= escape(http_build_query($baseParams + ['reservation_page' => (string) $prevPage])) ?>#rezervace-list">Předchozí</a>
                                <?php for ($pageNumber = 1; $pageNumber <= $reservationPagination['total_pages']; $pageNumber++): ?>
                                    <?php if ($pageNumber === 1 || $pageNumber === $reservationPagination['total_pages'] || abs($pageNumber - $reservationFilters['page']) <= 1): ?>
                                        <a class="button button-small <?= $pageNumber === $reservationFilters['page'] ? 'button-primary' : 'button-secondary' ?>" href="<?= escape($adminBasePath ?? 'admin.php') ?>?<?= escape(http_build_query($baseParams + ['reservation_page' => (string) $pageNumber])) ?>#rezervace-list"><?= escape((string) $pageNumber) ?></a>
                                    <?php elseif ($pageNumber === 2 || $pageNumber === $reservationPagination['total_pages'] - 1): ?>
                                        <span class="pagination-separator">…</span>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <a class="button button-secondary button-small<?= $reservationFilters['page'] >= $reservationPagination['total_pages'] ? ' is-disabled' : '' ?>" href="<?= escape($adminBasePath ?? 'admin.php') ?>?<?= escape(http_build_query($baseParams + ['reservation_page' => (string) $nextPage])) ?>#rezervace-list">Další</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="admin-card">
                        <?php
                        $manualDateTimeRaw = trim((string) ($manualReservationForm['datum_cas'] ?? ''));
                        $manualDateTimeLocal = str_replace(' ', 'T', $manualDateTimeRaw);
                        if (strlen($manualDateTimeLocal) === 19) {
                            $manualDateTimeLocal = substr($manualDateTimeLocal, 0, 16);
                        }
                        $manualSelectedDay = strlen($manualDateTimeLocal) >= 10 ? substr($manualDateTimeLocal, 0, 10) : '';
                        $manualSelectedTime = strlen($manualDateTimeLocal) >= 16 ? substr($manualDateTimeLocal, 11, 5) : '';
                        ?>
                        <p class="eyebrow">Ruční rezervace</p>
                        <h2>Vložení objednávky z telefonu, Instagramu nebo zprávy</h2>
                        <form method="post" class="admin-form admin-form-grid" data-manual-reservation-form data-initial-day="<?= escape($manualSelectedDay) ?>" data-initial-time="<?= escape($manualSelectedTime) ?>">
                            <?= csrfInputField() ?>
                            <label>
                                <span>Jméno klientky</span>
                                <input type="text" name="jmeno" value="<?= escape($manualReservationForm['jmeno']) ?>" required>
                            </label>
                            <label>
                                <span>E-mail</span>
                                <input type="email" name="email" value="<?= escape($manualReservationForm['email']) ?>">
                            </label>
                            <label>
                                <span>Telefon</span>
                                <input type="text" name="telefon" value="<?= escape($manualReservationForm['telefon']) ?>">
                            </label>
                            <label>
                                <span>Zdroj rezervace</span>
                                <select name="zdroj">
                                    <?php foreach ($reservationSourceOptions as $sourceValue => $sourceLabel): ?>
                                        <option value="<?= escape($sourceValue) ?>" <?= $sourceValue === $manualReservationForm['zdroj'] ? 'selected' : '' ?>><?= escape($sourceLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Procedura</span>
                                <select name="sluzba_id" data-manual-service-select required>
                                    <option value="">Vyberte proceduru</option>
                                    <?php foreach ($serviceRows as $service): ?>
                                        <?php
                                        $isServiceActive = (int) ($service['service_active'] ?? 1) === 1;
                                        $isCategoryActive = (int) ($service['category_active'] ?? 1) === 1;
                                        if (! $isServiceActive || ! $isCategoryActive) {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?= escape((string) $service['id']) ?>" <?= (string) $service['id'] === $manualReservationForm['sluzba_id'] ? 'selected' : '' ?>>
                                            <?= escape((string) $service['nazev']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Den</span>
                                <select data-manual-day-select <?= $manualReservationForm['sluzba_id'] === '' ? 'disabled' : '' ?> required>
                                    <option value=""><?= $manualReservationForm['sluzba_id'] === '' ? 'Nejprve vyberte proceduru' : 'Načítám dny…' ?></option>
                                </select>
                            </label>
                            <label>
                                <span>Čas</span>
                                <select data-manual-time-select disabled required>
                                    <option value="">Nejprve vyberte den</option>
                                </select>
                                <input type="hidden" name="datum_cas" value="<?= escape($manualDateTimeLocal) ?>" data-manual-datetime required>
                            </label>
                            <label class="full-span">
                                <span>Poznámka klientky</span>
                                <textarea name="poznamka_klienta" rows="4" placeholder="Např. preferovaný kontakt, citlivost pleti nebo doplnění k návštěvě"><?= escape($manualReservationForm['poznamka_klienta']) ?></textarea>
                            </label>
                            <button class="button button-primary full-span" type="submit" name="save_manual_reservation" value="1">Vložit ruční rezervaci</button>
                        </form>
                        <p class="form-hint">Ruční rezervace se ukládá rovnou jako potvrzená a lze ji vložit jen do skutečně volných termínů. Pokud je vyplněný e-mail klientky, odejde jí i potvrzení s kalendářovou přílohou.</p>
                    </div>
                </section>
