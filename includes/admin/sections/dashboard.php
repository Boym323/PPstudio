                <?php
                $statusRows = [
                    'nova' => 'Nové',
                    'potvrzena' => 'Potvrzené',
                    'dokoncena' => 'Dokončené',
                    'zrusena' => 'Zrušené',
                ];
                $statusTotal = max(1, array_sum(array_map(static fn($value): int => (int) $value, $dashboardStatusBreakdown)));

                $renderReservationRows = static function (array $rows, string $emptyText, bool $showStatus = true): void {
                    if ($rows === []) {
                        ?>
                        <p class="dashboard-empty"><?= escape($emptyText) ?></p>
                        <?php
                        return;
                    }
                    ?>
                    <div class="dashboard-rows">
                        <?php foreach ($rows as $row): ?>
                            <div class="dashboard-row">
                                <div>
                                    <p class="dashboard-row-title"><?= escape((string) ($row['nazev'] ?? 'Procedura')) ?></p>
                                    <p class="dashboard-row-subtitle"><?= escape((string) ($row['jmeno'] ?? 'Klientka')) ?></p>
                                </div>
                                <div class="dashboard-row-meta">
                                    <p><?= escape(formatCzechDateTime((string) ($row['datum_cas'] ?? ''))) ?></p>
                                    <?php if ($showStatus): ?>
                                        <span class="status-badge status-<?= escape((string) ($row['stav'] ?? 'nova')) ?>">
                                            <?= escape(reservationStatusLabel((string) ($row['stav'] ?? 'nova'))) ?>
                                        </span>
                                    <?php elseif (($row['telefon'] ?? '') !== '' || ($row['email'] ?? '') !== ''): ?>
                                        <p class="dashboard-row-submeta">
                                            <?= escape(trim((string) (($row['telefon'] ?? '') !== '' ? $row['telefon'] : ($row['email'] ?? '')))) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php
                };
                ?>

                <section class="dashboard-kpi-grid" id="dashboard">
                    <article class="stat-card">
                        <p class="panel-label">Dnes rezervace</p>
                        <strong class="stat-value"><?= escape((string) ((int) ($dashboardStats['today_reservations'] ?? 0))) ?></strong>
                        <p class="stat-note">Co dnes skutečně odbavíte.</p>
                    </article>
                    <article class="stat-card">
                        <p class="panel-label">Zítra rezervace</p>
                        <strong class="stat-value"><?= escape((string) count($dashboardTomorrowReservations)) ?></strong>
                        <p class="stat-note">Rychlá kontrola zítřejšího diáře.</p>
                    </article>
                    <article class="stat-card">
                        <p class="panel-label">Čeká na potvrzení</p>
                        <strong class="stat-value"><?= escape((string) ((int) ($dashboardStats['pending_reservations'] ?? 0))) ?></strong>
                        <p class="stat-note">Nové rezervace, které čekají na reakci.</p>
                    </article>
                    <article class="stat-card">
                        <p class="panel-label">Volné sloty dnes</p>
                        <strong class="stat-value"><?= escape((string) ((int) ($dashboardStats['free_slots_today'] ?? 0))) ?></strong>
                        <p class="stat-note">Volné půlhodiny v dnešním zbytku dne.</p>
                    </article>
                </section>

                <section class="dashboard-detail-grid dashboard-operational-grid">
                    <article class="admin-card dashboard-panel dashboard-panel-highlight">
                        <h2>Co potřebuje pozornost</h2>
                        <?php if ($dashboardAttentionItems === []): ?>
                            <p class="dashboard-empty">Dnes je vše v klidu. Dashboard zatím nevidí nic urgentního.</p>
                        <?php else: ?>
                            <div class="dashboard-attention-list">
                                <?php foreach ($dashboardAttentionItems as $item): ?>
                                    <div class="dashboard-attention-item dashboard-attention-<?= escape((string) ($item['tone'] ?? 'neutral')) ?>">
                                        <p class="dashboard-row-title"><?= escape((string) ($item['title'] ?? 'Upozornění')) ?></p>
                                        <p class="dashboard-row-subtitle"><?= escape((string) ($item['text'] ?? '')) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>

                    <article class="admin-card dashboard-panel">
                        <h2>Dnešní rezervace</h2>
                        <?php $renderReservationRows($dashboardTodayReservations, 'Na dnešek zatím není žádná rezervace.'); ?>
                    </article>

                    <article class="admin-card dashboard-panel">
                        <h2>Zítřejší rezervace</h2>
                        <?php $renderReservationRows($dashboardTomorrowReservations, 'Na zítřek zatím není žádná rezervace.'); ?>
                    </article>

                    <article class="admin-card dashboard-panel">
                        <h2>Čekající nové rezervace</h2>
                        <?php $renderReservationRows($dashboardPendingReservationRows, 'Všechny nové rezervace už jsou vyřízené.', false); ?>
                    </article>

                    <article class="admin-card dashboard-panel">
                        <h2>Poslední zrušení a přesuny</h2>
                        <?php if ($dashboardRecentReservationChanges === []): ?>
                            <p class="dashboard-empty">Zatím se neobjevilo žádné zrušení ani přeplánování.</p>
                        <?php else: ?>
                            <div class="dashboard-rows">
                                <?php foreach ($dashboardRecentReservationChanges as $change): ?>
                                    <div class="dashboard-row">
                                        <div>
                                            <p class="dashboard-row-title"><?= escape((string) ($change['label'] ?? 'Změna rezervace')) ?></p>
                                            <?php if (($change['new_datetime'] ?? '') !== ''): ?>
                                                <p class="dashboard-row-subtitle">
                                                    Nový termín: <?= escape(formatCzechDateTime((string) ($change['new_datetime'] ?? ''))) ?>
                                                </p>
                                            <?php elseif (($change['old_datetime'] ?? '') !== ''): ?>
                                                <p class="dashboard-row-subtitle">
                                                    Původní termín: <?= escape(formatCzechDateTime((string) ($change['old_datetime'] ?? ''))) ?>
                                                </p>
                                            <?php elseif (($change['cancel_reason'] ?? '') !== ''): ?>
                                                <p class="dashboard-row-subtitle">
                                                    Důvod: <?= escape((string) ($change['cancel_reason'] ?? '')) ?>
                                                </p>
                                            <?php else: ?>
                                                <p class="dashboard-row-subtitle">Rezervace #<?= escape((string) ((int) ($change['reservation_id'] ?? 0))) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="dashboard-row-meta">
                                            <p><?= escape(formatCzechDateTime((string) ($change['time'] ?? ''))) ?></p>
                                            <?php if (($change['event_type'] ?? '') !== ''): ?>
                                                <span class="dashboard-change-type dashboard-change-<?= str_contains((string) ($change['event_type'] ?? ''), 'cancelled') ? 'cancelled' : 'rescheduled' ?>">
                                                    <?= escape(str_contains((string) ($change['event_type'] ?? ''), 'cancelled') ? 'Zrušení' : 'Přesun') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                </section>

                <section class="dashboard-detail-grid dashboard-secondary-grid">
                    <article class="admin-card dashboard-panel">
                        <h2>Nejbližší rezervace</h2>
                        <?php $renderReservationRows($dashboardUpcomingReservations, 'V nejbližším období zatím nejsou žádné potvrditelné rezervace.'); ?>
                    </article>

                    <article class="admin-card dashboard-panel">
                        <h2>Stavy rezervací (30 dní)</h2>
                        <div class="dashboard-status-list">
                            <?php foreach ($statusRows as $statusKey => $statusLabel): ?>
                                <?php
                                $statusCount = (int) ($dashboardStatusBreakdown[$statusKey] ?? 0);
                                $statusPercent = (int) round(($statusCount / $statusTotal) * 100);
                                ?>
                                <div class="dashboard-status-item">
                                    <div class="dashboard-status-header">
                                        <span><?= escape($statusLabel) ?></span>
                                        <strong><?= escape((string) $statusCount) ?> (<?= escape((string) $statusPercent) ?> %)</strong>
                                    </div>
                                    <div class="dashboard-status-bar">
                                        <div class="dashboard-status-fill status-<?= escape($statusKey) ?>" style="width: <?= escape((string) $statusPercent) ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="admin-card dashboard-panel">
                        <h2>Nejžádanější procedury (30 dní)</h2>
                        <?php if ($dashboardTopServices === []): ?>
                            <p class="dashboard-empty">Zatím nejsou data pro vyhodnocení.</p>
                        <?php else: ?>
                            <div class="dashboard-ranked-list">
                                <?php foreach ($dashboardTopServices as $index => $row): ?>
                                    <div class="dashboard-ranked-item">
                                        <strong>#<?= escape((string) ($index + 1)) ?></strong>
                                        <span><?= escape((string) ($row['nazev'] ?? 'Procedura')) ?></span>
                                        <span><?= escape((string) ((int) ($row['reservations_count'] ?? 0))) ?>x</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>

                    <article class="admin-card dashboard-panel">
                        <h2>Top kategorie (30 dní)</h2>
                        <?php if ($dashboardTopCategories === []): ?>
                            <p class="dashboard-empty">Zatím nejsou data pro vyhodnocení.</p>
                        <?php else: ?>
                            <div class="dashboard-ranked-list">
                                <?php foreach ($dashboardTopCategories as $index => $row): ?>
                                    <div class="dashboard-ranked-item">
                                        <strong>#<?= escape((string) ($index + 1)) ?></strong>
                                        <span><?= escape((string) ($row['category_name'] ?? 'Kategorie')) ?></span>
                                        <span><?= escape((string) ((int) ($row['reservations_count'] ?? 0))) ?>x</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                </section>
