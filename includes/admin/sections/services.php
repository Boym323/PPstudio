                <section class="admin-layout" id="sluzby-admin" data-services-root>
                    <?php
                        $serviceBaseParams = [
                            'tab' => 'sluzby-admin',
                            'service_q' => $serviceFilters['q'] ?? '',
                            'service_category' => $serviceFilters['category'] ?? 'all',
                            'service_status' => $serviceFilters['status'] ?? 'all',
                        ];
                        $activeServicesSection = 'procedures';
                        if ((int) ($categoryForm['id'] ?? 0) > 0 || isset($_GET['edit_category'])) {
                            $activeServicesSection = 'categories';
                        } elseif (isset($_GET['service_section']) && in_array((string) $_GET['service_section'], ['procedures', 'categories', 'price-history'], true)) {
                            $activeServicesSection = (string) $_GET['service_section'];
                        }
                    ?>
                    <div class="admin-card full-span services-section-switcher" data-services-section-switcher data-initial-section="<?= escape($activeServicesSection) ?>">
                        <p class="eyebrow">Navigace sekce</p>
                        <h2>Služby a ceník</h2>
                        <div class="services-section-tabs" role="tablist" aria-label="Podsekce služeb">
                            <button class="button button-secondary button-small<?= $activeServicesSection === 'procedures' ? ' is-active' : '' ?>" type="button" data-services-section-trigger="procedures" aria-pressed="<?= $activeServicesSection === 'procedures' ? 'true' : 'false' ?>">Procedury</button>
                            <button class="button button-secondary button-small<?= $activeServicesSection === 'categories' ? ' is-active' : '' ?>" type="button" data-services-section-trigger="categories" aria-pressed="<?= $activeServicesSection === 'categories' ? 'true' : 'false' ?>">Kategorie</button>
                            <button class="button button-secondary button-small<?= $activeServicesSection === 'price-history' ? ' is-active' : '' ?>" type="button" data-services-section-trigger="price-history" aria-pressed="<?= $activeServicesSection === 'price-history' ? 'true' : 'false' ?>">Historie cen</button>
                        </div>
                        <p class="form-hint">Zobrazte jen tu část, kterou právě potřebujete upravit.</p>
                    </div>
                    <div class="services-section-panel services-section-panel-procedures" data-services-section-panel="procedures"<?= $activeServicesSection === 'procedures' ? '' : ' hidden' ?>>
                    <div class="admin-card service-editor-card">
                        <p class="eyebrow">Procedury</p>
                        <h2><?= $serviceForm['id'] > 0 ? 'Úprava služby' : 'Nová procedura' ?></h2>
                        <form method="post" class="admin-form admin-form-grid service-editor-form">
                            <?= csrfInputField() ?>
                            <input type="hidden" name="service_id" value="<?= escape((string) $serviceForm['id']) ?>">
                            <div class="service-form-panel full-span">
                                <h3>Základ</h3>
                                <div class="service-form-fields">
                                    <label><span>Název procedury</span><input type="text" name="nazev" value="<?= escape($serviceForm['nazev']) ?>" required></label>
                                    <label>
                                        <span>Existující kategorie</span>
                                        <select name="kategorie_id">
                                            <option value="">Vyberte kategorii</option>
                                            <?php foreach ($serviceCategoryRows as $categoryRow): ?>
                                                <option value="<?= escape((string) ($categoryRow['id'] ?? '')) ?>" <?= (string) ($categoryRow['id'] ?? '') === (string) ($serviceForm['kategorie_id'] ?? '') ? 'selected' : '' ?>>
                                                    <?= escape((string) ($categoryRow['nazev'] ?? '')) ?><?= (int) ($categoryRow['aktivni'] ?? 1) === 1 ? '' : ' (neaktivní)' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                            </div>
                            <div class="service-form-panel">
                                <h3>Parametry</h3>
                                <div class="service-form-fields">
                                    <label><span>Cena v Kč</span><input type="number" name="cena" min="0" step="1" value="<?= escape($serviceForm['cena']) ?>"></label>
                                    <label><span>Délka v minutách</span><input type="number" name="doba_trvani" min="15" step="15" value="<?= escape($serviceForm['doba_trvani']) ?>" required></label>
                                </div>
                            </div>
                            <div class="service-form-panel">
                                <h3>Popis</h3>
                                <div class="service-form-fields">
                                    <label><span>Popis procedury</span><textarea name="popis" rows="4"><?= escape($serviceForm['popis']) ?></textarea></label>
                                </div>
                            </div>
                            <div class="service-form-actions full-span">
                                <button class="button button-primary" type="submit" name="save_service" value="1"><?= $serviceForm['id'] > 0 ? 'Uložit úpravy' : 'Přidat proceduru' ?></button>
                            </div>
                        </form>
                    </div>
                    <div class="admin-card full-span service-filters-card">
                        <p class="eyebrow">Přehled procedur</p>
                        <h2>Filtrovat a spravovat služby</h2>
                        <form method="get" action="<?= escape($adminBasePath ?? 'admin.php') ?>" class="admin-form admin-form-grid reservations-filter-form">
                            <input type="hidden" name="tab" value="sluzby-admin">
                            <label>
                                <span>Vyhledat</span>
                                <input type="text" name="service_q" value="<?= escape($serviceFilters['q'] ?? '') ?>" placeholder="Název, popis nebo kategorie">
                            </label>
                            <label>
                                <span>Kategorie</span>
                                <select name="service_category">
                                    <?php foreach ($serviceCategoryFilterOptions as $categoryValue => $categoryLabel): ?>
                                        <option value="<?= escape((string) $categoryValue) ?>" <?= (string) $categoryValue === (string) ($serviceFilters['category'] ?? 'all') ? 'selected' : '' ?>><?= escape((string) $categoryLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Stav</span>
                                <select name="service_status">
                                    <?php foreach ($serviceStatusFilterOptions as $statusValue => $statusLabel): ?>
                                        <option value="<?= escape((string) $statusValue) ?>" <?= (string) $statusValue === (string) ($serviceFilters['status'] ?? 'all') ? 'selected' : '' ?>><?= escape((string) $statusLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="table-actions full-span">
                                <button class="button button-primary button-small" type="submit">Použít filtr</button>
                                <a class="button button-secondary button-small" href="<?= escape($adminBasePath ?? 'admin.php') ?>?tab=sluzby-admin#sluzby-admin">Reset</a>
                            </div>
                        </form>
                        <p class="form-hint">Zobrazeno procedur: <strong><?= escape((string) count($serviceRows)) ?></strong>.</p>
                    </div>
                    <div class="admin-card full-span service-table-card">
                        <div class="admin-table-wrap">
                        <table class="admin-table service-admin-table procedure-admin-table">
                            <thead><tr><th>Procedura</th><th>Kategorie</th><th>Cena</th><th>Délka</th><th>Stav</th><th>Akce</th></tr></thead>
                            <tbody>
                                <?php if ($serviceRows === []): ?>
                                    <tr><td colspan="6">Zatím zde nejsou žádné procedury.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($serviceRows as $row): ?>
                                        <?php
                                            $serviceDescription = trim((string) ($row['popis'] ?? ''));
                                            $serviceIsActive = (int) ($row['service_active'] ?? 1) === 1;
                                            $serviceCategoryLabel = trim((string) ($row['kategorie'] ?? '')) !== '' ? (string) $row['kategorie'] : 'Ostatní služby';
                                        ?>
                                        <tr class="service-list-row">
                                            <td data-label="Procedura">
                                                <div class="reservation-service-main"><?= escape((string) $row['nazev']) ?></div>
                                                <div class="reservation-service-meta"><?= escape($serviceDescription !== '' ? (function_exists('mb_strimwidth') ? mb_strimwidth($serviceDescription, 0, 90, '…', 'UTF-8') : (strlen($serviceDescription) > 90 ? substr($serviceDescription, 0, 87) . '...' : $serviceDescription)) : 'Bez popisu') ?></div>
                                            </td>
                                            <td data-label="Kategorie"><?= escape($serviceCategoryLabel) ?></td>
                                            <td data-label="Cena"><?= escape(formatPrice($row['cena'] ?? null)) ?></td>
                                            <td data-label="Délka"><?= escape(formatDuration($row['doba_trvani'] ?? null)) ?></td>
                                            <td data-label="Stav">
                                                <span class="status-pill <?= $serviceIsActive ? 'status-pill-active' : 'status-pill-inactive' ?>">
                                                    <?= $serviceIsActive ? 'Aktivní' : 'Neaktivní' ?>
                                                </span>
                                            </td>
                                            <td data-label="Akce" class="service-actions-cell">
                                                <div class="table-actions">
                                                    <button class="button button-secondary button-small" type="button" data-service-detail-toggle data-open-label="Detail" data-close-label="Skrýt detail" aria-expanded="false">Detail</button>
                                                    <a class="button button-secondary button-small" href="<?= escape($adminBasePath ?? 'admin.php') ?>?<?= escape(http_build_query($serviceBaseParams + ['edit_service' => (string) $row['id']])) ?>#sluzby-admin">Upravit</a>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="service-detail-row" data-service-detail-row hidden>
                                            <td colspan="6" class="service-detail-cell">
                                                <div class="service-detail-grid">
                                                    <div class="service-detail-block">
                                                        <h3>Souhrn procedury</h3>
                                                        <div class="service-detail-list">
                                                            <div><strong>Název</strong><span><?= escape((string) $row['nazev']) ?></span></div>
                                                            <div><strong>Kategorie</strong><span><?= escape($serviceCategoryLabel) ?></span></div>
                                                            <div><strong>Cena</strong><span><?= escape(formatPrice($row['cena'] ?? null)) ?></span></div>
                                                            <div><strong>Délka</strong><span><?= escape(formatDuration($row['doba_trvani'] ?? null)) ?></span></div>
                                                        </div>
                                                    </div>
                                                    <div class="service-detail-block">
                                                        <h3>Popis a správa</h3>
                                                        <div class="service-detail-notes">
                                                            <div><strong>Popis</strong><span><?= escape($serviceDescription !== '' ? $serviceDescription : 'Bez popisu') ?></span></div>
                                                        </div>
                                                        <div class="table-actions service-detail-actions">
                                                            <a class="button button-secondary button-small" href="<?= escape($adminBasePath ?? 'admin.php') ?>?<?= escape(http_build_query($serviceBaseParams + ['edit_service' => (string) $row['id']])) ?>#sluzby-admin">Upravit proceduru</a>
                                                            <form method="post">
                                                                <?= csrfInputField() ?>
                                                                <input type="hidden" name="service_id" value="<?= escape((string) $row['id']) ?>">
                                                                <input type="hidden" name="target_active" value="<?= $serviceIsActive ? '0' : '1' ?>">
                                                                <button
                                                                    class="button <?= $serviceIsActive ? 'button-danger' : 'button-primary' ?> button-small"
                                                                    type="submit"
                                                                    name="toggle_service_active"
                                                                    value="1"
                                                                    onclick="return confirm('<?= $serviceIsActive ? 'Opravdu chcete proceduru deaktivovat?' : 'Opravdu chcete proceduru aktivovat?' ?>');"
                                                                ><?= $serviceIsActive ? 'Deaktivovat' : 'Aktivovat' ?></button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    </div>
                    </div>
                    <div class="services-section-panel services-section-panel-history" data-services-section-panel="price-history"<?= $activeServicesSection === 'price-history' ? '' : ' hidden' ?>>
                    <div class="admin-card admin-card-compact-history full-span">
                        <p class="eyebrow">Historie cen</p>
                        <h2>Poslední změny cen procedur</h2>
                        <?php
                            $historyTotal = count($servicePriceHistoryRows);
                            $historyRowsPreview = array_slice($servicePriceHistoryRows, 0, 50);
                        ?>
                        <details class="compact-history-panel" <?= $historyTotal > 0 ? '' : 'open' ?>>
                            <summary>
                                <?= $historyTotal > 0
                                    ? 'Zobrazit historii (' . escape((string) $historyTotal) . ' záznamů)'
                                    : 'Zobrazit historii'
                                ?>
                            </summary>
                            <p class="form-hint">Ceny se ukládají automaticky při každé změně procedury.</p>
                            <div class="admin-table-wrap compact-history-table-wrap">
                                <table class="admin-table service-admin-table price-history-admin-table">
                                    <thead>
                                        <tr>
                                            <th>Procedura</th>
                                            <th>Cena</th>
                                            <th>Platná od</th>
                                            <th>Platná do</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($historyRowsPreview === []): ?>
                                            <tr><td colspan="4">Zatím zde nejsou žádné záznamy historie cen.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($historyRowsPreview as $historyRow): ?>
                                                <tr>
                                                    <td data-label="Procedura"><?= escape((string) ($historyRow['sluzba_nazev'] ?? '')) ?></td>
                                                    <td data-label="Cena"><?= escape(formatPrice($historyRow['cena'] ?? null)) ?></td>
                                                    <td data-label="Platná od"><?= escape(formatCzechDateTime((string) ($historyRow['platna_od'] ?? ''))) ?></td>
                                                    <td data-label="Platná do"><?= escape((string) ($historyRow['platna_do'] ?? '') !== '' ? formatCzechDateTime((string) $historyRow['platna_do']) : 'Aktuální') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($historyTotal > 50): ?>
                                <p class="form-hint">Zobrazeno posledních 50 změn z celkových <?= escape((string) $historyTotal) ?>.</p>
                            <?php endif; ?>
                        </details>
                    </div>
                    </div>
                    <div class="services-section-panel services-section-panel-categories" data-services-section-panel="categories"<?= $activeServicesSection === 'categories' ? '' : ' hidden' ?>>
                    <div class="admin-card service-editor-card" id="kategorie-admin">
                        <p class="eyebrow">Kategorie</p>
                        <h2><?= (int) ($categoryForm['id'] ?? 0) > 0 ? 'Úprava kategorie' : 'Nová kategorie' ?></h2>
                        <form method="post" class="admin-form admin-form-grid service-editor-form">
                            <?= csrfInputField() ?>
                            <input type="hidden" name="category_id" value="<?= escape((string) ($categoryForm['id'] ?? 0)) ?>">
                            <div class="service-form-panel full-span">
                                <h3>Základ</h3>
                                <div class="service-form-fields">
                                    <label>
                                        <span>Název kategorie</span>
                                        <input type="text" name="category_name" value="<?= escape((string) ($categoryForm['nazev'] ?? '')) ?>" required>
                                    </label>
                                    <label>
                                        <span>Pořadí kategorie</span>
                                        <input type="number" name="category_order" min="0" step="1" value="<?= escape((string) ($categoryForm['poradi'] ?? '')) ?>" placeholder="např. 1">
                                    </label>
                                </div>
                            </div>
                            <div class="service-form-actions full-span">
                                <button class="button button-primary" type="submit" name="save_category" value="1">
                                    <?= (int) ($categoryForm['id'] ?? 0) > 0 ? 'Uložit kategorii' : 'Přidat kategorii' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="admin-card full-span">
                        <p class="eyebrow">Přehled kategorií</p>
                        <h2>Řazení a správa kategorií</h2>
                        <p class="form-hint">Pořadí změníte přetažením řádku za ikonu se šipkami.</p>
                        <div class="admin-table-wrap">
                        <form method="post" class="admin-form" id="category-order-form">
                            <?= csrfInputField() ?>
                            <input type="hidden" name="save_category_order" value="1">
                            <input type="hidden" name="category_order_ids" value="">
                        </form>
                        <table class="admin-table service-admin-table category-admin-table">
                            <thead><tr><th></th><th>Pořadí</th><th>Kategorie</th><th>Počet procedur</th><th>Stav</th><th>Akce</th></tr></thead>
                            <tbody data-category-sortable>
                                <?php if ($serviceCategoryRows === []): ?>
                                    <tr><td colspan="6">Zatím zde nejsou žádné kategorie.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($serviceCategoryRows as $categoryRow): ?>
                                        <tr data-category-id="<?= escape((string) ($categoryRow['id'] ?? '')) ?>">
                                            <td data-label="" class="drag-cell">
                                                <button type="button" class="drag-handle" aria-label="Přetáhnout kategorii" title="Přetáhnout kategorii">↕</button>
                                            </td>
                                            <td data-label="Pořadí"><?= escape((string) ($categoryRow['poradi'] ?? '')) ?></td>
                                            <td data-label="Kategorie"><?= escape((string) ($categoryRow['nazev'] ?? '')) ?></td>
                                            <td data-label="Počet procedur"><?= escape((string) ((int) ($categoryRow['services_count'] ?? 0))) ?></td>
                                            <td data-label="Stav">
                                                <?php $categoryIsActive = (int) ($categoryRow['aktivni'] ?? 1) === 1; ?>
                                                <span class="status-pill <?= $categoryIsActive ? 'status-pill-active' : 'status-pill-inactive' ?>">
                                                    <?= $categoryIsActive ? 'Aktivní' : 'Neaktivní' ?>
                                                </span>
                                            </td>
                                            <td data-label="Akce" class="service-actions-cell">
                                                <div class="table-actions">
                                                    <a class="button button-secondary button-small" href="<?= escape($adminBasePath ?? 'admin.php') ?>?tab=sluzby-admin&amp;edit_category=<?= escape((string) ($categoryRow['id'] ?? '')) ?>#kategorie-admin">Upravit</a>
                                                    <form method="post">
                                                        <?= csrfInputField() ?>
                                                        <input type="hidden" name="category_id" value="<?= escape((string) ($categoryRow['id'] ?? '')) ?>">
                                                        <input type="hidden" name="target_active" value="<?= $categoryIsActive ? '0' : '1' ?>">
                                                        <button
                                                            class="button <?= $categoryIsActive ? 'button-danger' : 'button-primary' ?> button-small"
                                                            type="submit"
                                                            name="toggle_category_active"
                                                            value="1"
                                                            onclick="return confirm('<?= $categoryIsActive ? 'Opravdu chcete kategorii deaktivovat? Navázané procedury se také deaktivují.' : 'Opravdu chcete kategorii aktivovat?' ?>');"
                                                        ><?= $categoryIsActive ? 'Deaktivovat' : 'Aktivovat' ?></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    </div>
                    </div>
                </section>
