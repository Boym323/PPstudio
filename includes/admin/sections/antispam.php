                <section class="admin-single" id="antispam-log">
                    <div class="section-heading">
                        <p class="eyebrow">Antispam</p>
                        <h2>Přehled bezpečnostních událostí</h2>
                        <p>Události z rezervačního formuláře a přihlášení do administrace (antispam, rate-limit, neúspěšné přihlášení).</p>
                    </div>

                    <article class="admin-card">
                        <form method="get" action="admin.php" class="admin-form admin-form-grid reservations-filter-form">
                            <input type="hidden" name="tab" value="antispam-log">
                            <label>
                                Vyhledat
                                <input type="text" name="antispam_q" value="<?= escape($antispamFilters['q']) ?>" placeholder="IP, user-agent, context...">
                            </label>
                            <label>
                                Důvod
                                <select name="antispam_reason">
                                    <?php foreach ($antispamReasonOptions as $reasonValue => $reasonLabel): ?>
                                        <option value="<?= escape($reasonValue) ?>" <?= $reasonValue === $antispamFilters['reason'] ? 'selected' : '' ?>><?= escape($reasonLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                Počet řádků
                                <select name="antispam_limit">
                                    <?php foreach ($antispamLimitOptions as $limitValue): ?>
                                        <option value="<?= escape((string) $limitValue) ?>" <?= $limitValue === $antispamFilters['limit'] ? 'selected' : '' ?>><?= escape((string) $limitValue) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="table-actions">
                                <button class="button button-primary button-small" type="submit">Použít filtr</button>
                                <a class="button button-secondary button-small" href="admin.php?tab=antispam-log#antispam-log">Reset</a>
                            </div>
                        </form>
                        <p class="form-hint">
                            Zobrazeno <strong><?= escape((string) ((int) ($antispamLogStats['shown'] ?? 0))) ?></strong> z
                            <strong><?= escape((string) ((int) ($antispamLogStats['total'] ?? 0))) ?></strong> událostí.
                            Zdroj: <strong><?= escape((string) ($antispamLogStats['source'] ?? 'db')) ?></strong>.
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
                                <?php if ($antispamRows === []): ?>
                                    <tr>
                                        <td colspan="5">Zatím nejsou k dispozici žádné antispam události.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($antispamRows as $row): ?>
                                        <?php
                                        $timeLabel = (string) ($row['time'] ?? '');
                                        $timeTimestamp = strtotime($timeLabel);
                                        if ($timeTimestamp !== false) {
                                            $timeLabel = date('d.m.Y H:i:s', $timeTimestamp);
                                        }
                                        $sourceKey = (string) ($row['source'] ?? '');
                                        $sourceLabel = match ($sourceKey) {
                                            'reservation_form' => 'Rezervační formulář',
                                            'admin_login' => 'Admin přihlášení',
                                            'admin_lite_login' => 'User admin přihlášení',
                                            'reservation_action' => 'Akce rezervace',
                                            default => ($sourceKey !== '' ? $sourceKey : 'Neznámá sekce'),
                                        };
                                        $uaText = trim((string) ($row['ua'] ?? ''));
                                        $contextText = trim((string) ($row['context'] ?? ''));
                                        $contextPreview = 'Bez doplňujícího kontextu';
                                        if ($contextText !== '') {
                                            if (function_exists('mb_strimwidth')) {
                                                $contextPreview = mb_strimwidth($contextText, 0, 180, '…', 'UTF-8');
                                            } else {
                                                $contextPreview = strlen($contextText) > 180 ? substr($contextText, 0, 177) . '...' : $contextText;
                                            }
                                        }
                                        ?>
                                        <tr class="antispam-log-row">
                                            <td data-label="Čas" class="antispam-time"><?= escape($timeLabel) ?></td>
                                            <td data-label="Důvod"><span class="antispam-reason-badge"><?= escape((string) ($row['reason'] ?? '')) ?></span></td>
                                            <td data-label="Sekce"><?= escape($sourceLabel) ?></td>
                                            <td data-label="IP" class="antispam-ip"><?= escape((string) ($row['ip'] ?? '')) ?></td>
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
                                                            <div><strong>Čas</strong><span><?= escape($timeLabel) ?></span></div>
                                                            <div><strong>Důvod</strong><span><?= escape((string) ($row['reason'] ?? '')) ?></span></div>
                                                            <div><strong>Sekce</strong><span><?= escape($sourceLabel) ?></span></div>
                                                            <div><strong>IP adresa</strong><span><?= escape((string) ($row['ip'] ?? '')) ?></span></div>
                                                        </div>
                                                    </div>
                                                    <div class="antispam-detail-block">
                                                        <h3>Kontext</h3>
                                                        <div class="antispam-detail-notes">
                                                            <div><strong>Krátký přehled</strong><span><?= escape($contextPreview) ?></span></div>
                                                            <div><strong>User-Agent</strong><span class="antispam-ua"><?= escape($uaText !== '' ? $uaText : 'Neuvedeno') ?></span></div>
                                                            <div><strong>Plný kontext</strong><span class="antispam-context"><?= escape($contextText !== '' ? $contextText : 'Neuvedeno') ?></span></div>
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
                    <?php if (($antispamPagination['total_pages'] ?? 1) > 1): ?>
                        <div class="table-actions reservations-pagination">
                            <?php
                            $antispamBaseParams = [
                                'tab' => 'antispam-log',
                                'antispam_q' => $antispamFilters['q'],
                                'antispam_reason' => $antispamFilters['reason'],
                                'antispam_limit' => (string) $antispamFilters['limit'],
                            ];
                            $antispamPrevPage = max(1, $antispamFilters['page'] - 1);
                            $antispamNextPage = min($antispamPagination['total_pages'], $antispamFilters['page'] + 1);
                            ?>
                            <a class="button button-secondary button-small<?= $antispamFilters['page'] <= 1 ? ' is-disabled' : '' ?>" href="admin.php?<?= escape(http_build_query($antispamBaseParams + ['antispam_page' => (string) $antispamPrevPage])) ?>#antispam-log">Předchozí</a>
                            <?php for ($pageNumber = 1; $pageNumber <= $antispamPagination['total_pages']; $pageNumber++): ?>
                                <?php if ($pageNumber === 1 || $pageNumber === $antispamPagination['total_pages'] || abs($pageNumber - $antispamFilters['page']) <= 1): ?>
                                    <a class="button button-small <?= $pageNumber === $antispamFilters['page'] ? 'button-primary' : 'button-secondary' ?>" href="admin.php?<?= escape(http_build_query($antispamBaseParams + ['antispam_page' => (string) $pageNumber])) ?>#antispam-log"><?= escape((string) $pageNumber) ?></a>
                                <?php elseif ($pageNumber === 2 || $pageNumber === $antispamPagination['total_pages'] - 1): ?>
                                    <span class="pagination-separator">…</span>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <a class="button button-secondary button-small<?= $antispamFilters['page'] >= $antispamPagination['total_pages'] ? ' is-disabled' : '' ?>" href="admin.php?<?= escape(http_build_query($antispamBaseParams + ['antispam_page' => (string) $antispamNextPage])) ?>#antispam-log">Další</a>
                        </div>
                    <?php endif; ?>
                </section>
