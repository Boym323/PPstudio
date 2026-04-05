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
                                    <th>User-Agent</th>
                                    <th>Kontext</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($antispamRows === []): ?>
                                    <tr>
                                        <td colspan="6">Zatím nejsou k dispozici žádné antispam události.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($antispamRows as $row): ?>
                                        <?php
                                        $timeLabel = (string) ($row['time'] ?? '');
                                        $timeTimestamp = strtotime($timeLabel);
                                        if ($timeTimestamp !== false) {
                                            $timeLabel = date('d.m.Y H:i:s', $timeTimestamp);
                                        }
                                        ?>
                                        <tr>
                                            <td data-label="Čas" class="antispam-time"><?= escape($timeLabel) ?></td>
                                            <td data-label="Důvod"><span class="antispam-reason-badge"><?= escape((string) ($row['reason'] ?? '')) ?></span></td>
                                            <td data-label="Sekce"><?= escape((string) ($row['source'] ?? '')) ?></td>
                                            <td data-label="IP" class="antispam-ip"><?= escape((string) ($row['ip'] ?? '')) ?></td>
                                            <td data-label="User-Agent" class="antispam-ua"><?= escape((string) ($row['ua'] ?? '')) ?></td>
                                            <td data-label="Kontext" class="antispam-context"><?= escape((string) ($row['context'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </article>
                </section>
