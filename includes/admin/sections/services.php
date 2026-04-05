                <section class="admin-layout" id="sluzby-admin">
                    <div class="admin-card">
                        <p class="eyebrow">Procedury</p>
                        <h2><?= $serviceForm['id'] > 0 ? 'Úprava služby' : 'Nová procedura' ?></h2>
                        <form method="post" class="admin-form">
                            <?= csrfInputField() ?>
                            <input type="hidden" name="service_id" value="<?= escape((string) $serviceForm['id']) ?>">
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
                            <label><span>Popis</span><textarea name="popis" rows="4"><?= escape($serviceForm['popis']) ?></textarea></label>
                            <label><span>Cena v Kč</span><input type="number" name="cena" min="0" step="1" value="<?= escape($serviceForm['cena']) ?>"></label>
                            <label><span>Délka v minutách</span><input type="number" name="doba_trvani" min="15" step="15" value="<?= escape($serviceForm['doba_trvani']) ?>" required></label>
                            <button class="button button-primary" type="submit" name="save_service" value="1"><?= $serviceForm['id'] > 0 ? 'Uložit úpravy' : 'Přidat proceduru' ?></button>
                        </form>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table service-admin-table procedure-admin-table">
                            <thead><tr><th>Kategorie</th><th>Procedura</th><th>Délka</th><th>Cena</th><th>Popis</th><th>Stav</th><th>Akce</th></tr></thead>
                            <tbody>
                                <?php if ($serviceRows === []): ?>
                                    <tr><td colspan="7">Zatím zde nejsou žádné procedury.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($serviceRows as $row): ?>
                                        <tr>
                                            <td data-label="Kategorie"><?= escape(trim((string) ($row['kategorie'] ?? '')) !== '' ? (string) $row['kategorie'] : 'Ostatní služby') ?></td>
                                            <td data-label="Procedura"><?= escape((string) $row['nazev']) ?></td>
                                            <td data-label="Délka"><?= escape(formatDuration($row['doba_trvani'] ?? null)) ?></td>
                                            <td data-label="Cena"><?= escape(formatPrice($row['cena'] ?? null)) ?></td>
                                            <?php
                                                $serviceDescription = trim((string) ($row['popis'] ?? ''));
                                                $serviceDescriptionPreview = $serviceDescription;
                                                if (function_exists('mb_strimwidth')) {
                                                    $serviceDescriptionPreview = mb_strimwidth($serviceDescription, 0, 120, '…', 'UTF-8');
                                                } elseif (strlen($serviceDescription) > 120) {
                                                    $serviceDescriptionPreview = substr($serviceDescription, 0, 120) . '…';
                                                }
                                            ?>
                                            <td data-label="Popis" class="service-description-cell">
                                                <div
                                                    class="service-description-preview"
                                                    title="<?= escape($serviceDescription) ?>"
                                                ><?= escape($serviceDescriptionPreview !== '' ? $serviceDescriptionPreview : '—') ?></div>
                                            </td>
                                            <td data-label="Stav">
                                                <?php $serviceIsActive = (int) ($row['service_active'] ?? 1) === 1; ?>
                                                <span class="status-pill <?= $serviceIsActive ? 'status-pill-active' : 'status-pill-inactive' ?>">
                                                    <?= $serviceIsActive ? 'Aktivní' : 'Neaktivní' ?>
                                                </span>
                                            </td>
                                            <td data-label="Akce" class="service-actions-cell">
                                                <div class="table-actions">
                                                    <a class="button button-secondary button-small" href="<?= escape($adminBasePath ?? 'admin.php') ?>?tab=sluzby-admin&amp;edit_service=<?= escape((string) $row['id']) ?>#sluzby-admin">Upravit</a>
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
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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
                    <div class="admin-card" id="kategorie-admin">
                        <p class="eyebrow">Kategorie</p>
                        <h2><?= (int) ($categoryForm['id'] ?? 0) > 0 ? 'Úprava kategorie' : 'Nová kategorie' ?></h2>
                        <form method="post" class="admin-form admin-form-grid">
                            <?= csrfInputField() ?>
                            <input type="hidden" name="category_id" value="<?= escape((string) ($categoryForm['id'] ?? 0)) ?>">
                            <label>
                                <span>Název kategorie</span>
                                <input type="text" name="category_name" value="<?= escape((string) ($categoryForm['nazev'] ?? '')) ?>" required>
                            </label>
                            <label>
                                <span>Pořadí kategorie</span>
                                <input type="number" name="category_order" min="0" step="1" value="<?= escape((string) ($categoryForm['poradi'] ?? '')) ?>" placeholder="např. 1">
                            </label>
                            <button class="button button-primary" type="submit" name="save_category" value="1">
                                <?= (int) ($categoryForm['id'] ?? 0) > 0 ? 'Uložit kategorii' : 'Přidat kategorii' ?>
                            </button>
                        </form>
                    </div>
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
                </section>
