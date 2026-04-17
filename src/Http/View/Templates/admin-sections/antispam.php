                <section class="admin-single" id="antispam-log">
                    <div class="section-heading">
                        <p class="eyebrow">Antispam</p>
                        <h2>Přehled bezpečnostních událostí</h2>
                        <p>Události z rezervačního formuláře a přihlášení do administrace (antispam, rate-limit, neúspěšné přihlášení).</p>
                    </div>

                    <article class="admin-card">
                        <form method="get" action="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>" class="admin-form admin-form-grid reservations-filter-form">
                            <input type="hidden" name="tab" value="antispam-log">
                            <label>
                                Vyhledat
                                <input type="text" name="antispam_q" value="<?= \PPStudio\Support\ViewHelper::escape($antispamFilters['q']) ?>" placeholder="IP, user-agent, context...">
                            </label>
                            <label>
                                Důvod
                                <select name="antispam_reason">
                                    <?php foreach ($antispamReasonOptions as $reasonValue => $reasonLabel): ?>
                                        <option value="<?= \PPStudio\Support\ViewHelper::escape($reasonValue) ?>" <?= $reasonValue === $antispamFilters['reason'] ? 'selected' : '' ?>><?= \PPStudio\Support\ViewHelper::escape($reasonLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                Počet řádků
                                <select name="antispam_limit">
                                    <?php foreach ($antispamLimitOptions as $limitValue): ?>
                                        <option value="<?= \PPStudio\Support\ViewHelper::escape((string) $limitValue) ?>" <?= $limitValue === $antispamFilters['limit'] ? 'selected' : '' ?>><?= \PPStudio\Support\ViewHelper::escape((string) $limitValue) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="table-actions">
                                <button class="button button-primary button-small" type="submit">Použít filtr</button>
                                <a class="button button-secondary button-small" href="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>?tab=antispam-log#antispam-log">Reset</a>
                            </div>
                        </form>
                        <p class="form-hint">
                            Zobrazeno <strong><?= \PPStudio\Support\ViewHelper::escape((string) ((int) ($antispamLogStats['shown'] ?? 0))) ?></strong> z
                            <strong><?= \PPStudio\Support\ViewHelper::escape((string) ((int) ($antispamLogStats['total'] ?? 0))) ?></strong> událostí.
                            Zdroj: <strong><?= \PPStudio\Support\ViewHelper::escape((string) ($antispamLogStats['source'] ?? 'db')) ?></strong>.
                            <?php if ((string) ($antispamLogStats['coverage'] ?? 'all') === 'reservation_form_only'): ?>
                                Fallback režim nyní zobrazuje pouze události z rezervačního formuláře, nikoliv pokusy o přihlášení do administrace.
                            <?php endif; ?>
                        </p>
                    </article>

                    <article class="admin-table-wrap">
                        <table class="admin-table antispam-admin-table">
                            <thead>
                                <tr>
                                    <th>Čas</th>
                                    <th>Důvod</th>
                                    <th>Sekce</th>
                                    <th>IP</th>
                                    <th>Akce</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (($antispamRowsPrepared ?? []) === []): ?>
                                    <tr>
                                        <td colspan="5">Zatím nejsou k dispozici žádné antispam události.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach (($antispamRowsPrepared ?? []) as $row): ?>
                                        <tr class="antispam-log-row">
                                            <td data-label="Čas" class="antispam-time"><?= \PPStudio\Support\ViewHelper::escape((string) ($row['time_label'] ?? '')) ?></td>
                                            <td data-label="Důvod"><span class="antispam-reason-badge"><?= \PPStudio\Support\ViewHelper::escape((string) ($row['reason'] ?? '')) ?></span></td>
                                            <td data-label="Sekce"><?= \PPStudio\Support\ViewHelper::escape((string) ($row['source_label'] ?? '')) ?></td>
                                            <td data-label="IP" class="antispam-ip"><?= \PPStudio\Support\ViewHelper::escape((string) ($row['ip'] ?? '')) ?></td>
                                            <td data-label="Akce" class="antispam-summary-actions">
                                                <button class="button button-secondary button-small" type="button" data-antispam-detail-toggle data-open-label="Detail" data-close-label="Skrýt detail" aria-expanded="false">Detail</button>
                                            </td>
                                        </tr>
                                        <tr class="antispam-detail-row" data-antispam-detail-row hidden>
                                            <td colspan="5" class="antispam-detail-cell">
                                                <div class="antispam-detail-grid">
                                                    <div class="antispam-detail-block">
                                                        <h3>Souhrn události</h3>
                                                        <div class="antispam-detail-list">
                                                            <div><strong>Čas</strong><span><?= \PPStudio\Support\ViewHelper::escape((string) ($row['time_label'] ?? '')) ?></span></div>
                                                            <div><strong>Důvod</strong><span><?= \PPStudio\Support\ViewHelper::escape((string) ($row['reason'] ?? '')) ?></span></div>
                                                            <div><strong>Sekce</strong><span><?= \PPStudio\Support\ViewHelper::escape((string) ($row['source_label'] ?? '')) ?></span></div>
                                                            <div><strong>IP adresa</strong><span><?= \PPStudio\Support\ViewHelper::escape((string) ($row['ip'] ?? '')) ?></span></div>
                                                        </div>
                                                    </div>
                                                    <div class="antispam-detail-block">
                                                        <h3>Kontext</h3>
                                                        <div class="antispam-detail-notes">
                                                            <div><strong>Krátký přehled</strong><span><?= \PPStudio\Support\ViewHelper::escape((string) ($row['context_preview'] ?? 'Bez doplňujícího kontextu')) ?></span></div>
                                                            <div><strong>User-Agent</strong><span class="antispam-ua"><?= \PPStudio\Support\ViewHelper::escape((string) ($row['ua_text'] ?? 'Neuvedeno')) ?></span></div>
                                                            <div><strong>Plný kontext</strong><span class="antispam-context"><?= \PPStudio\Support\ViewHelper::escape((string) ($row['context_text'] ?? 'Neuvedeno')) ?></span></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </article>
                    <?php if (((int) ($antispamPaginationView['total_pages'] ?? 1)) > 1): ?>
                        <div class="table-actions reservations-pagination">
                            <a class="button button-secondary button-small<?= ((int) ($antispamPaginationView['current_page'] ?? 1)) <= 1 ? ' is-disabled' : '' ?>" href="<?= \PPStudio\Support\ViewHelper::escape((string) ($antispamPaginationView['prev_url'] ?? '')) ?>">Předchozí</a>
                            <?php foreach (($antispamPaginationView['pages'] ?? []) as $pageData): ?>
                                <?php if (($pageData['type'] ?? '') === 'separator'): ?>
                                    <span class="pagination-separator">…</span>
                                    <?php continue; ?>
                                <?php endif; ?>
                                <?php if (($pageData['type'] ?? '') === 'page'): ?>
                                    <a class="button button-small <?= ($pageData['active'] ?? false) ? 'button-primary' : 'button-secondary' ?>" href="<?= \PPStudio\Support\ViewHelper::escape((string) ($pageData['url'] ?? '')) ?>"><?= \PPStudio\Support\ViewHelper::escape((string) ($pageData['number'] ?? '')) ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <a class="button button-secondary button-small<?= ((int) ($antispamPaginationView['current_page'] ?? 1)) >= ((int) ($antispamPaginationView['total_pages'] ?? 1)) ? ' is-disabled' : '' ?>" href="<?= \PPStudio\Support\ViewHelper::escape((string) ($antispamPaginationView['next_url'] ?? '')) ?>">Další</a>
                        </div>
                    <?php endif; ?>
                </section>
