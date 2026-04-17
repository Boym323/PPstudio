                <section class="admin-layout" id="sluzby-admin" data-services-root>
                    <div class="admin-card full-span services-section-switcher" data-services-section-switcher data-initial-section="<?= \PPStudio\Support\ViewHelper::escape($activeServicesSection) ?>">
                        <p class="eyebrow">Navigace sekce</p>
                        <h2>Služby a ceník</h2>
                        <div class="services-section-tabs" role="tablist" aria-label="Podsekce služeb">
                            <button class="button button-secondary button-small<?= $activeServicesSection === 'procedures' ? ' is-active' : '' ?>" type="button" data-services-section-trigger="procedures" aria-pressed="<?= $activeServicesSection === 'procedures' ? 'true' : 'false' ?>">Procedury</button>
                            <button class="button button-secondary button-small<?= $activeServicesSection === 'categories' ? ' is-active' : '' ?>" type="button" data-services-section-trigger="categories" aria-pressed="<?= $activeServicesSection === 'categories' ? 'true' : 'false' ?>">Kategorie</button>
                            <button class="button button-secondary button-small<?= $activeServicesSection === 'price-history' ? ' is-active' : '' ?>" type="button" data-services-section-trigger="price-history" aria-pressed="<?= $activeServicesSection === 'price-history' ? 'true' : 'false' ?>">Poslední změny cen</button>
                        </div>
                        <p class="form-hint">Zobrazte jen tu část, kterou právě potřebujete upravit.</p>
                    </div>
                    <div class="services-section-panel services-section-panel-procedures" data-services-section-panel="procedures"<?= $activeServicesSection === 'procedures' ? '' : ' hidden' ?>>
                    <div class="admin-card service-editor-card">
                        <p class="eyebrow">Procedury</p>
                        <h2><?= $serviceForm['id'] > 0 ? 'Úprava služby' : 'Nová procedura' ?></h2>
                        <form method="post" class="admin-form admin-form-grid service-editor-form">
                            <?= csrfInputField() ?>
                            <input type="hidden" name="service_id" value="<?= \PPStudio\Support\ViewHelper::escape((string) $serviceForm['id']) ?>">
                            <div class="service-form-panel full-span">
                                <h3>Základ</h3>
                                <div class="service-form-fields">
                                    <label><span>Název procedury</span><input type="text" name="nazev" value="<?= \PPStudio\Support\ViewHelper::escape($serviceForm['nazev']) ?>" required></label>
                                    <label>
                                        <span>Existující kategorie</span>
                                        <select name="kategorie_id">
                                            <option value="">Vyberte kategorii</option>
                                            <?php foreach ($serviceCategoryRows as $categoryRow): ?>
                                                <option value="<?= \PPStudio\Support\ViewHelper::escape((string) ($categoryRow['id'] ?? '')) ?>" <?= (string) ($categoryRow['id'] ?? '') === (string) ($serviceForm['kategorie_id'] ?? '') ? 'selected' : '' ?>>
                                                    <?= \PPStudio\Support\ViewHelper::escape((string) ($categoryRow['nazev'] ?? '')) ?><?= (int) ($categoryRow['aktivni'] ?? 1) === 1 ? '' : ' (neaktivní)' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                            </div>
                            <div class="service-form-panel">
                                <h3>Parametry</h3>
                                <div class="service-form-fields">
                                    <label><span>Cena v Kč</span><input type="number" name="cena" min="0" step="1" value="<?= \PPStudio\Support\ViewHelper::escape($serviceForm['cena']) ?>"></label>
                                    <label><span>Délka v minutách</span><input type="number" name="doba_trvani" min="15" step="15" value="<?= \PPStudio\Support\ViewHelper::escape($serviceForm['doba_trvani']) ?>" required></label>
                                    <label><span>Štítek v ceníku</span><input type="text" name="stitek" maxlength="80" value="<?= \PPStudio\Support\ViewHelper::escape($serviceForm['stitek'] ?? '') ?>" placeholder="např. Doporučeno"></label>
                                </div>
                            </div>
                            <div class="service-form-panel">
                                <h3>Popis</h3>
                                <div class="service-form-fields">
                                    <label><span>Popis procedury</span><textarea name="popis" rows="4"><?= \PPStudio\Support\ViewHelper::escape($serviceForm['popis']) ?></textarea></label>
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
                        <form method="get" action="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>" class="admin-form admin-form-grid reservations-filter-form">
                            <input type="hidden" name="tab" value="sluzby-admin">
                            <label>
                                <span>Vyhledat</span>
                                <input type="text" name="service_q" value="<?= \PPStudio\Support\ViewHelper::escape($serviceFilters['q'] ?? '') ?>" placeholder="Název, popis nebo kategorie">
                            </label>
                            <label>
                                <span>Kategorie</span>
                                <select name="service_category">
                                    <?php foreach ($serviceCategoryFilterOptions as $categoryValue => $categoryLabel): ?>
                                        <option value="<?= \PPStudio\Support\ViewHelper::escape((string) $categoryValue) ?>" <?= (string) $categoryValue === (string) ($serviceFilters['category'] ?? 'all') ? 'selected' : '' ?>><?= \PPStudio\Support\ViewHelper::escape((string) $categoryLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Stav</span>
                                <select name="service_status">
                                    <?php foreach ($serviceStatusFilterOptions as $statusValue => $statusLabel): ?>
                                        <option value="<?= \PPStudio\Support\ViewHelper::escape((string) $statusValue) ?>" <?= (string) $statusValue === (string) ($serviceFilters['status'] ?? 'all') ? 'selected' : '' ?>><?= \PPStudio\Support\ViewHelper::escape((string) $statusLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="table-actions full-span">
                                <button class="button button-primary button-small" type="submit">Použít filtr</button>
                                <a class="button button-secondary button-small" href="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>?tab=sluzby-admin#sluzby-admin">Reset</a>
                            </div>
                        </form>
                        <p class="form-hint">Zobrazeno procedur: <strong><?= \PPStudio\Support\ViewHelper::escape((string) count($serviceRows)) ?></strong>.</p>
                    </div>
                    <div class="admin-card full-span service-table-card">
                        <div class="admin-table-wrap">
                        <table class="admin-table service-admin-table procedure-admin-table">
                            <thead><tr><th>Procedura</th><th>Kategorie</th><th>Cena</th><th>Délka</th><th>Stav</th><th>Akce</th></tr></thead>
                            <tbody>
                                <?php if ($serviceRowsPrepared === []): ?>
                                    <tr><td colspan="6">Zatím zde nejsou žádné procedury.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($serviceRowsPrepared as $row): ?>
                                        <tr class="service-list-row">
                                            <td data-label="Procedura">
                                                <div class="reservation-service-main"><?= \PPStudio\Support\ViewHelper::escape((string) $row['nazev']) ?></div>
                                                <?php if ((string) ($row['badge_text'] ?? '') !== ''): ?>
                                                    <div class="reservation-service-meta"><?= \PPStudio\Support\ViewHelper::escape((string) $row['badge_text']) ?></div>
                                                <?php endif; ?>
                                                <div class="reservation-service-meta"><?= \PPStudio\Support\ViewHelper::escape((string) ($row['description_preview'] ?? 'Bez popisu')) ?></div>
                                            </td>
                                            <td data-label="Kategorie"><?= \PPStudio\Support\ViewHelper::escape((string) ($row['category_label'] ?? 'Ostatní služby')) ?></td>
                                            <td data-label="Cena"><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatPrice($row['cena'] ?? null)) ?></td>
                                            <td data-label="Délka"><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatDuration($row['doba_trvani'] ?? null)) ?></td>
                                            <td data-label="Stav">
                                                <span class="status-pill <?= !empty($row['is_active']) ? 'status-pill-active' : 'status-pill-inactive' ?>">
                                                    <?= !empty($row['is_active']) ? 'Aktivní' : 'Neaktivní' ?>
                                                </span>
                                            </td>
                                            <td data-label="Akce" class="service-actions-cell">
                                                <div class="table-actions">
                                                    <button class="button button-secondary button-small" type="button" data-service-detail-toggle data-open-label="Detail" data-close-label="Skrýt detail" aria-expanded="false">Detail</button>
                                                    <a class="button button-secondary button-small" href="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>?<?= \PPStudio\Support\ViewHelper::escape(http_build_query($serviceBaseParams + ['edit_service' => (string) $row['id']])) ?>#sluzby-admin">Upravit</a>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="service-detail-row" data-service-detail-row hidden>
                                            <td colspan="6" class="service-detail-cell">
                                                <div class="service-detail-grid">
                                                    <div class="service-detail-block">
                                                        <h3>Souhrn procedury</h3>
                                                        <div class="service-detail-list">
                                                            <div><strong>Název</strong><span><?= \PPStudio\Support\ViewHelper::escape((string) $row['nazev']) ?></span></div>
                                                            <div><strong>Kategorie</strong><span><?= \PPStudio\Support\ViewHelper::escape((string) ($row['category_label'] ?? 'Ostatní služby')) ?></span></div>
                                                            <div><strong>Štítek</strong><span><?= \PPStudio\Support\ViewHelper::escape((string) (($row['badge_text'] ?? '') !== '' ? $row['badge_text'] : 'Bez štítku')) ?></span></div>
                                                            <div><strong>Cena</strong><span><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatPrice($row['cena'] ?? null)) ?></span></div>
                                                            <div><strong>Délka</strong><span><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatDuration($row['doba_trvani'] ?? null)) ?></span></div>
                                                        </div>
                                                    </div>
	                                                    <div class="service-detail-block">
	                                                        <h3>Popis a správa</h3>
	                                                        <div class="service-detail-notes">
	                                                            <div><strong>Popis</strong><span><?= \PPStudio\Support\ViewHelper::escape((string) ($row['description_text'] ?? 'Bez popisu')) ?></span></div>
	                                                        </div>
                                                        <div class="table-actions service-detail-actions">
                                                            <a class="button button-secondary button-small" href="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>?<?= \PPStudio\Support\ViewHelper::escape(http_build_query($serviceBaseParams + ['edit_service' => (string) $row['id']])) ?>#sluzby-admin">Upravit proceduru</a>
                                                            <form method="post">
                                                                <?= csrfInputField() ?>
                                                                <input type="hidden" name="service_id" value="<?= \PPStudio\Support\ViewHelper::escape((string) $row['id']) ?>">
                                                                <input type="hidden" name="target_active" value="<?= !empty($row['is_active']) ? '0' : '1' ?>">
                                                                <button
                                                                    class="button <?= !empty($row['is_active']) ? 'button-danger' : 'button-primary' ?> button-small"
                                                                    type="submit"
                                                                    name="toggle_service_active"
                                                                    value="1"
                                                                    onclick="return confirm('<?= !empty($row['is_active']) ? 'Opravdu chcete proceduru deaktivovat?' : 'Opravdu chcete proceduru aktivovat?' ?>');"
                                                                ><?= !empty($row['is_active']) ? 'Deaktivovat' : 'Aktivovat' ?></button>
	                                                            </form>
	                                                        </div>
	                                                    </div>
                                                    <div class="service-detail-block service-price-history-block">
                                                        <h3>Cenová historie</h3>
                                                        <?php if (($row['history_preview'] ?? []) === []): ?>
                                                            <p class="form-hint">Zatím tu není uložená žádná historie ceny.</p>
                                                        <?php else: ?>
                                                            <div class="service-price-history-list">
                                                                <?php foreach (($row['history_preview'] ?? []) as $historyIndex => $historyItem): ?>
                                                                    <?php $olderHistoryItem = $row['history_preview'][$historyIndex + 1] ?? (($row['history_items'] ?? [])[$historyIndex + 1] ?? null); ?>
                                                                    <div class="service-price-history-item">
                                                                        <div class="service-price-history-main">
                                                                            <?php if ($olderHistoryItem !== null): ?>
                                                                                <strong><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatPrice($olderHistoryItem['cena'] ?? null)) ?> -> <?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatPrice($historyItem['cena'] ?? null)) ?></strong>
                                                                            <?php else: ?>
                                                                                <strong>První cena: <?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatPrice($historyItem['cena'] ?? null)) ?></strong>
                                                                            <?php endif; ?>
                                                                            <span><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatCzechDateTime((string) ($historyItem['platna_od'] ?? ''))) ?></span>
                                                                        </div>
                                                                        <div class="service-price-history-meta">
                                                                            <span><?= (string) ($historyItem['platna_do'] ?? '') !== '' ? 'Platilo do ' . \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatCzechDateTime((string) $historyItem['platna_do'])) : 'Aktuální cena' ?></span>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
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
	                        <p class="eyebrow">Poslední změny cen</p>
	                        <h2>Poslední změny cen procedur</h2>
	                        <p class="form-hint">Přehled ukazuje skutečné změny ceny ve formátu původní cena -> nová cena.</p>
	                        <div class="admin-table-wrap compact-history-table-wrap">
	                            <table class="admin-table service-admin-table price-history-admin-table">
	                                <thead>
	                                    <tr>
	                                        <th>Procedura</th>
	                                        <th>Změna ceny</th>
	                                        <th>Změněno</th>
	                                        <th>Typ</th>
	                                    </tr>
	                                </thead>
	                                <tbody>
	                                    <?php if ($servicePriceChangesPreview === []): ?>
	                                        <tr><td colspan="4">Zatím zde nejsou žádné záznamy historie cen.</td></tr>
	                                    <?php else: ?>
	                                        <?php foreach ($servicePriceChangesPreview as $historyRow): ?>
	                                            <tr>
	                                                <td data-label="Procedura"><?= \PPStudio\Support\ViewHelper::escape((string) ($historyRow['sluzba_nazev'] ?? '')) ?></td>
	                                                <td data-label="Změna ceny">
                                                        <?php if (!empty($historyRow['is_initial'])): ?>
                                                            <strong>První cena: <?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatPrice($historyRow['new_price'] ?? null)) ?></strong>
                                                        <?php else: ?>
                                                            <strong><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatPrice($historyRow['old_price'] ?? null)) ?> -> <?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatPrice($historyRow['new_price'] ?? null)) ?></strong>
                                                        <?php endif; ?>
                                                    </td>
	                                                <td data-label="Změněno"><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatCzechDateTime((string) ($historyRow['changed_at'] ?? ''))) ?></td>
	                                                <td data-label="Typ"><?= !empty($historyRow['is_initial']) ? 'První nastavení' : 'Úprava ceny' ?></td>
	                                            </tr>
	                                        <?php endforeach; ?>
	                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($servicePriceChangesTotal > 50): ?>
                            <p class="form-hint">Zobrazeno posledních 50 změn z celkových <?= \PPStudio\Support\ViewHelper::escape((string) $servicePriceChangesTotal) ?>.</p>
                        <?php endif; ?>
                    </div>
                    </div>
                    <div class="services-section-panel services-section-panel-categories" data-services-section-panel="categories"<?= $activeServicesSection === 'categories' ? '' : ' hidden' ?>>
                    <div class="admin-card service-editor-card" id="kategorie-admin">
                        <p class="eyebrow">Kategorie</p>
                        <h2><?= (int) ($categoryForm['id'] ?? 0) > 0 ? 'Úprava kategorie' : 'Nová kategorie' ?></h2>
                        <form method="post" class="admin-form admin-form-grid service-editor-form">
                            <?= csrfInputField() ?>
                            <input type="hidden" name="category_id" value="<?= \PPStudio\Support\ViewHelper::escape((string) ($categoryForm['id'] ?? 0)) ?>">
                            <div class="service-form-panel full-span">
                                <h3>Základ</h3>
                                <div class="service-form-fields">
                                    <label>
                                        <span>Název kategorie</span>
                                        <input type="text" name="category_name" value="<?= \PPStudio\Support\ViewHelper::escape((string) ($categoryForm['nazev'] ?? '')) ?>" required>
                                    </label>
                                    <label>
                                        <span>Pořadí kategorie</span>
                                        <input type="number" name="category_order" min="0" step="1" value="<?= \PPStudio\Support\ViewHelper::escape((string) ($categoryForm['poradi'] ?? '')) ?>" placeholder="např. 1">
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
                                        <tr data-category-id="<?= \PPStudio\Support\ViewHelper::escape((string) ($categoryRow['id'] ?? '')) ?>">
                                            <td data-label="" class="drag-cell">
                                                <button type="button" class="drag-handle" aria-label="Přetáhnout kategorii" title="Přetáhnout kategorii">↕</button>
                                            </td>
                                            <td data-label="Pořadí"><?= \PPStudio\Support\ViewHelper::escape((string) ($categoryRow['poradi'] ?? '')) ?></td>
                                            <td data-label="Kategorie"><?= \PPStudio\Support\ViewHelper::escape((string) ($categoryRow['nazev'] ?? '')) ?></td>
                                            <td data-label="Počet procedur"><?= \PPStudio\Support\ViewHelper::escape((string) ((int) ($categoryRow['services_count'] ?? 0))) ?></td>
                                            <td data-label="Stav">
                                                <?php $categoryIsActive = (int) ($categoryRow['aktivni'] ?? 1) === 1; ?>
                                                <span class="status-pill <?= $categoryIsActive ? 'status-pill-active' : 'status-pill-inactive' ?>">
                                                    <?= $categoryIsActive ? 'Aktivní' : 'Neaktivní' ?>
                                                </span>
                                            </td>
                                            <td data-label="Akce" class="service-actions-cell">
                                                <div class="table-actions">
                                                    <a class="button button-secondary button-small" href="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>?tab=sluzby-admin&amp;edit_category=<?= \PPStudio\Support\ViewHelper::escape((string) ($categoryRow['id'] ?? '')) ?>#kategorie-admin">Upravit</a>
                                                    <form method="post">
                                                        <?= csrfInputField() ?>
                                                        <input type="hidden" name="category_id" value="<?= \PPStudio\Support\ViewHelper::escape((string) ($categoryRow['id'] ?? '')) ?>">
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
