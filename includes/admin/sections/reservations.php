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
                                        <th>Procedura</th>
                                        <th>Cena</th>
                                        <th>Klientka</th>
                                        <th>Kontakt</th>
                                        <th>Zdroj</th>
                                        <th>Poznámka</th>
                                        <th>Stav a správa</th>
                                    </tr>
                                </thead>
                                <tbody data-reservation-tbody>
                                    <?php if ($reservationRows === []): ?>
                                        <tr data-reservation-empty-row><td colspan="8">Zatím zde nejsou žádné rezervace.</td></tr>
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
                                            ?>
                                            <tr class="reservation-row" data-reservation-row data-reservation-id="<?= escape((string) $row['id']) ?>" data-reservation-client="<?= escape((string) ($row['jmeno'] ?? '')) ?>" data-reservation-datetime="<?= escape(formatCzechDateTime((string) $row['datum_cas'])) ?>">
                                                <td data-label="Termín"><?= escape(formatCzechDateTime((string) $row['datum_cas'])) ?></td>
                                                <td data-label="Procedura"><?= escape((string) $row['nazev']) ?></td>
                                                <td data-label="Cena"><?= escape(formatPrice($row['cena_v_dobe_rezervace'] ?? null)) ?></td>
                                                <td data-label="Klientka"><?= escape((string) $row['jmeno']) ?></td>
                                                <td data-label="Kontakt" class="reservation-contact"><div><?= escape((string) $row['email']) ?></div><div><?= escape((string) ($row['telefon'] ?? '')) ?></div></td>
                                                <td data-label="Zdroj"><?= escape($reservationSourceOptions[(string) ($row['zdroj'] ?? '')] ?? ucfirst((string) ($row['zdroj'] ?? 'web'))) ?></td>
                                                <td data-label="Poznámka" class="reservation-note-cell">
                                                    <div class="reservation-note-client">
                                                        <strong>Klientka:</strong> <?= escape((string) ($row['poznamka_klienta'] ?? '')) ?>
                                                    </div>
                                                    <?php if ((string) ($row['stav'] ?? '') === 'zrusena'): ?>
                                                        <div class="reservation-note-client">
                                                            <strong>Důvod zrušení:</strong> <?= escape((string) ($row['duvod_zruseni'] ?? 'neuvedeno')) ?>
                                                        </div>
                                                        <?php if ($cancelledByLabel !== '' || (string) ($row['zruseno_at'] ?? '') !== ''): ?>
                                                            <div class="reservation-note-client">
                                                                <strong>Zrušeno:</strong>
                                                                <?= escape($cancelledByLabel !== '' ? $cancelledByLabel : 'neznámý zdroj') ?>
                                                                <?php if ((string) ($row['zruseno_uzivatel'] ?? '') !== ''): ?>
                                                                    (<?= escape((string) $row['zruseno_uzivatel']) ?>)
                                                                <?php endif; ?>
                                                                <?php if ((string) ($row['zruseno_at'] ?? '') !== ''): ?>
                                                                    dne <?= escape(formatCzechDateTime((string) $row['zruseno_at'])) ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Stav a správa">
                                                    <form method="post" class="admin-form compact-form compact-form-reservation" data-reservation-form>
                                                        <?= csrfInputField() ?>
                                                        <input type="hidden" name="reservation_id" value="<?= escape((string) $row['id']) ?>">
                                                        <span class="status-badge status-<?= escape((string) $row['stav']) ?>" data-reservation-status-badge><?= escape(reservationStatusLabel((string) $row['stav'])) ?></span>
                                                        <select name="stav">
                                                            <?php foreach (reservationStatusOptions() as $statusValue => $statusLabel): ?>
                                                                <option value="<?= escape($statusValue) ?>" <?= $statusValue === (string) $row['stav'] ? 'selected' : '' ?>><?= escape($statusLabel) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <input type="text" name="poznamka_admina" value="<?= escape((string) ($row['poznamka_admina'] ?? '')) ?>" placeholder="Interní poznámka">
                                                        <input type="text" name="duvod_zruseni" value="<?= escape((string) ($row['duvod_zruseni'] ?? '')) ?>" placeholder="Důvod zrušení (povinný při stavu Zrušená)">
                                                        <div class="table-actions">
                                                            <button class="button button-primary button-small" type="submit" name="update_reservation" value="1">Uložit</button>
                                                            <button class="button button-danger button-small" type="submit" name="delete_reservation" value="1">Smazat</button>
                                                        </div>
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
                        <p class="eyebrow">Ruční rezervace</p>
                        <h2>Vložení objednávky z telefonu, Instagramu nebo zprávy</h2>
                        <form method="post" class="admin-form admin-form-grid">
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
                                <select name="sluzba_id" required>
                                    <option value="">Vyberte proceduru</option>
                                    <?php foreach ($serviceRows as $service): ?>
                                        <option value="<?= escape((string) $service['id']) ?>" <?= (string) $service['id'] === $manualReservationForm['sluzba_id'] ? 'selected' : '' ?>>
                                            <?= escape((string) $service['nazev']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Termín</span>
                                <input type="datetime-local" name="datum_cas" step="1800" value="<?= escape($manualReservationForm['datum_cas']) ?>" required>
                            </label>
                            <label class="full-span">
                                <span>Poznámka klientky</span>
                                <textarea name="poznamka_klienta" rows="4" placeholder="Např. preferovaný kontakt, citlivost pleti nebo doplnění k návštěvě"><?= escape($manualReservationForm['poznamka_klienta']) ?></textarea>
                            </label>
                            <button class="button button-primary full-span" type="submit" name="save_manual_reservation" value="1">Vložit ruční rezervaci</button>
                        </form>
                        <p class="form-hint">Ruční rezervace se ukládá rovnou jako potvrzená. Pokud je vyplněný e-mail klientky, odejde jí i potvrzení s kalendářovou přílohou.</p>
                    </div>
                </section>
