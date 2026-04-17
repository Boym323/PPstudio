                <section class="admin-single" id="reminder-log">
                    <div class="section-heading">
                        <p class="eyebrow">Reminder</p>
                        <h2>Log odesílání reminderů</h2>
                        <p>Audit běhů skriptu <code>reservation-reminders.php</code> se souhrnným jedním záznamem za každý běh.</p>
                    </div>

                    <article class="admin-card">
                        <form method="get" action="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>" class="admin-form admin-form-grid reservations-filter-form">
                            <input type="hidden" name="tab" value="reminder-log">
                            <label>
                                Vyhledat
                                <input type="text" name="reminder_q" value="<?= \PPStudio\Support\ViewHelper::escape($reminderLogFilters['q']) ?>" placeholder="run token, event, context...">
                            </label>
                            <label>
                                Událost
                                <select name="reminder_event">
                                    <?php foreach ($reminderLogEventOptions as $eventValue => $eventLabel): ?>
                                        <option value="<?= \PPStudio\Support\ViewHelper::escape($eventValue) ?>" <?= $eventValue === $reminderLogFilters['event'] ? 'selected' : '' ?>><?= \PPStudio\Support\ViewHelper::escape($eventLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                Úroveň
                                <select name="reminder_severity">
                                    <?php foreach ($reminderLogSeverityOptions as $severityValue => $severityLabel): ?>
                                        <option value="<?= \PPStudio\Support\ViewHelper::escape($severityValue) ?>" <?= $severityValue === $reminderLogFilters['severity'] ? 'selected' : '' ?>><?= \PPStudio\Support\ViewHelper::escape($severityLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                Počet řádků
                                <select name="reminder_limit">
                                    <?php foreach ($reminderLogLimitOptions as $limitValue): ?>
                                        <option value="<?= \PPStudio\Support\ViewHelper::escape((string) $limitValue) ?>" <?= $limitValue === $reminderLogFilters['limit'] ? 'selected' : '' ?>><?= \PPStudio\Support\ViewHelper::escape((string) $limitValue) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="table-actions">
                                <button class="button button-primary button-small" type="submit">Použít filtr</button>
                                <a class="button button-secondary button-small" href="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>?tab=reminder-log#reminder-log">Reset</a>
                            </div>
                        </form>
                        <p class="form-hint">
                            Zobrazeno <strong><?= \PPStudio\Support\ViewHelper::escape((string) ((int) ($reminderLogStats['shown'] ?? 0))) ?></strong> z
                            <strong><?= \PPStudio\Support\ViewHelper::escape((string) ((int) ($reminderLogStats['total'] ?? 0))) ?></strong> záznamů.
                            Zdroj: <strong><?= \PPStudio\Support\ViewHelper::escape((string) ($reminderLogStats['source'] ?? 'db')) ?></strong>.
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
                                <?php if (($reminderLogRowsPrepared ?? []) === []): ?>
                                    <tr>
                                        <td colspan="6">Zatím nejsou k dispozici žádné reminder logy.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach (($reminderLogRowsPrepared ?? []) as $row): ?>
                                        <tr>
                                            <td data-label="Čas"><?= \PPStudio\Support\ViewHelper::escape((string) ($row['time_label'] ?? '')) ?></td>
                                            <td data-label="Run"><code><?= \PPStudio\Support\ViewHelper::escape((string) ($row['run_token'] ?? '')) ?></code></td>
                                            <td data-label="Událost"><span class="antispam-reason-badge"><?= \PPStudio\Support\ViewHelper::escape((string) ($row['event_type'] ?? '')) ?></span></td>
                                            <td data-label="Úroveň"><?= \PPStudio\Support\ViewHelper::escape((string) ($row['severity_label'] ?? '')) ?></td>
                                            <td data-label="Rezervace"><?= \PPStudio\Support\ViewHelper::escape((string) ($row['reservation_label'] ?? '—')) ?></td>
                                            <td data-label="Kontext" class="antispam-context"><?= \PPStudio\Support\ViewHelper::escape((string) ($row['context_preview'] ?? 'Neuvedeno')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </article>
                    <?php if (((int) ($reminderPaginationView['total_pages'] ?? 1)) > 1): ?>
                        <div class="table-actions reservations-pagination">
                            <a class="button button-secondary button-small<?= ((int) ($reminderPaginationView['current_page'] ?? 1)) <= 1 ? ' is-disabled' : '' ?>" href="<?= \PPStudio\Support\ViewHelper::escape((string) ($reminderPaginationView['prev_url'] ?? '')) ?>">Předchozí</a>
                            <?php foreach (($reminderPaginationView['pages'] ?? []) as $pageData): ?>
                                <?php if (($pageData['type'] ?? '') === 'separator'): ?>
                                    <span class="pagination-separator">…</span>
                                    <?php continue; ?>
                                <?php endif; ?>
                                <?php if (($pageData['type'] ?? '') === 'page'): ?>
                                    <a class="button button-small <?= ($pageData['active'] ?? false) ? 'button-primary' : 'button-secondary' ?>" href="<?= \PPStudio\Support\ViewHelper::escape((string) ($pageData['url'] ?? '')) ?>"><?= \PPStudio\Support\ViewHelper::escape((string) ($pageData['number'] ?? '')) ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <a class="button button-secondary button-small<?= ((int) ($reminderPaginationView['current_page'] ?? 1)) >= ((int) ($reminderPaginationView['total_pages'] ?? 1)) ? ' is-disabled' : '' ?>" href="<?= \PPStudio\Support\ViewHelper::escape((string) ($reminderPaginationView['next_url'] ?? '')) ?>">Další</a>
                        </div>
                    <?php endif; ?>
                </section>
