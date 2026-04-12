                <section class="admin-single" id="reminder-log">
                    <div class="section-heading">
                        <p class="eyebrow">Reminder</p>
                        <h2>Log odesílání reminderů</h2>
                        <p>Audit běhů skriptu <code>reservation-reminders.php</code> včetně výsledků jednotlivých rezervací.</p>
                    </div>

                    <article class="admin-card">
                        <form method="get" action="admin.php" class="admin-form admin-form-grid reservations-filter-form">
                            <input type="hidden" name="tab" value="reminder-log">
                            <label>
                                Vyhledat
                                <input type="text" name="reminder_q" value="<?= escape($reminderLogFilters['q']) ?>" placeholder="run token, event, context...">
                            </label>
                            <label>
                                Událost
                                <select name="reminder_event">
                                    <?php foreach ($reminderLogEventOptions as $eventValue => $eventLabel): ?>
                                        <option value="<?= escape($eventValue) ?>" <?= $eventValue === $reminderLogFilters['event'] ? 'selected' : '' ?>><?= escape($eventLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                Úroveň
                                <select name="reminder_severity">
                                    <?php foreach ($reminderLogSeverityOptions as $severityValue => $severityLabel): ?>
                                        <option value="<?= escape($severityValue) ?>" <?= $severityValue === $reminderLogFilters['severity'] ? 'selected' : '' ?>><?= escape($severityLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                Počet řádků
                                <select name="reminder_limit">
                                    <?php foreach ($reminderLogLimitOptions as $limitValue): ?>
                                        <option value="<?= escape((string) $limitValue) ?>" <?= $limitValue === $reminderLogFilters['limit'] ? 'selected' : '' ?>><?= escape((string) $limitValue) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="table-actions">
                                <button class="button button-primary button-small" type="submit">Použít filtr</button>
                                <a class="button button-secondary button-small" href="admin.php?tab=reminder-log#reminder-log">Reset</a>
                            </div>
                        </form>
                        <p class="form-hint">
                            Zobrazeno <strong><?= escape((string) ((int) ($reminderLogStats['shown'] ?? 0))) ?></strong> z
                            <strong><?= escape((string) ((int) ($reminderLogStats['total'] ?? 0))) ?></strong> záznamů.
                            Zdroj: <strong><?= escape((string) ($reminderLogStats['source'] ?? 'db')) ?></strong>.
                            <?php if (($reminderLogStats['source'] ?? '') === 'table_missing'): ?>
                                DB tabulka <code>reservation_reminder_logs</code> zatím není k dispozici.
                            <?php endif; ?>
                        </p>
                    </article>

                    <article class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Čas</th>
                                    <th>Run</th>
                                    <th>Událost</th>
                                    <th>Úroveň</th>
                                    <th>Rezervace</th>
                                    <th>Kontext</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($reminderLogRows === []): ?>
                                    <tr>
                                        <td colspan="6">Zatím nejsou k dispozici žádné reminder logy.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reminderLogRows as $row): ?>
                                        <?php
                                        $timeLabel = (string) ($row['time'] ?? '');
                                        $timeTimestamp = strtotime($timeLabel);
                                        if ($timeTimestamp !== false) {
                                            $timeLabel = date('d.m.Y H:i:s', $timeTimestamp);
                                        }

                                        $contextText = trim((string) ($row['context'] ?? ''));
                                        $contextPreview = 'Neuvedeno';
                                        if ($contextText !== '') {
                                            if (function_exists('mb_strimwidth')) {
                                                $contextPreview = mb_strimwidth($contextText, 0, 180, '…', 'UTF-8');
                                            } else {
                                                $contextPreview = strlen($contextText) > 180 ? substr($contextText, 0, 177) . '...' : $contextText;
                                            }
                                        }

                                        $reservationLabel = '—';
                                        $reservationId = $row['reservation_id'] ?? null;
                                        if (is_int($reservationId) && $reservationId > 0) {
                                            $reservationLabel = '#' . $reservationId;
                                            $reservationName = trim((string) ($row['reservation_name'] ?? ''));
                                            $reservationDatetime = trim((string) ($row['reservation_datetime'] ?? ''));
                                            if ($reservationName !== '') {
                                                $reservationLabel .= ' · ' . $reservationName;
                                            }
                                            if ($reservationDatetime !== '') {
                                                $reservationLabel .= ' · ' . formatCzechDateTime($reservationDatetime);
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td data-label="Čas"><?= escape($timeLabel) ?></td>
                                            <td data-label="Run"><code><?= escape((string) ($row['run_token'] ?? '')) ?></code></td>
                                            <td data-label="Událost"><span class="antispam-reason-badge"><?= escape((string) ($row['event_type'] ?? '')) ?></span></td>
                                            <td data-label="Úroveň"><?= escape(strtoupper((string) ($row['severity'] ?? ''))) ?></td>
                                            <td data-label="Rezervace"><?= escape($reservationLabel) ?></td>
                                            <td data-label="Kontext" class="antispam-context"><?= escape($contextPreview) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </article>
                    <?php if (($reminderLogPagination['total_pages'] ?? 1) > 1): ?>
                        <div class="table-actions reservations-pagination">
                            <?php
                            $reminderBaseParams = [
                                'tab' => 'reminder-log',
                                'reminder_q' => $reminderLogFilters['q'],
                                'reminder_event' => $reminderLogFilters['event'],
                                'reminder_severity' => $reminderLogFilters['severity'],
                                'reminder_limit' => (string) $reminderLogFilters['limit'],
                            ];
                            $reminderPrevPage = max(1, $reminderLogFilters['page'] - 1);
                            $reminderNextPage = min($reminderLogPagination['total_pages'], $reminderLogFilters['page'] + 1);
                            ?>
                            <a class="button button-secondary button-small<?= $reminderLogFilters['page'] <= 1 ? ' is-disabled' : '' ?>" href="admin.php?<?= escape(http_build_query($reminderBaseParams + ['reminder_page' => (string) $reminderPrevPage])) ?>#reminder-log">Předchozí</a>
                            <?php for ($pageNumber = 1; $pageNumber <= $reminderLogPagination['total_pages']; $pageNumber++): ?>
                                <?php if ($pageNumber === 1 || $pageNumber === $reminderLogPagination['total_pages'] || abs($pageNumber - $reminderLogFilters['page']) <= 1): ?>
                                    <a class="button button-small <?= $pageNumber === $reminderLogFilters['page'] ? 'button-primary' : 'button-secondary' ?>" href="admin.php?<?= escape(http_build_query($reminderBaseParams + ['reminder_page' => (string) $pageNumber])) ?>#reminder-log"><?= escape((string) $pageNumber) ?></a>
                                <?php elseif ($pageNumber === 2 || $pageNumber === $reminderLogPagination['total_pages'] - 1): ?>
                                    <span class="pagination-separator">…</span>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <a class="button button-secondary button-small<?= $reminderLogFilters['page'] >= $reminderLogPagination['total_pages'] ? ' is-disabled' : '' ?>" href="admin.php?<?= escape(http_build_query($reminderBaseParams + ['reminder_page' => (string) $reminderNextPage])) ?>#reminder-log">Další</a>
                        </div>
                    <?php endif; ?>
                </section>
