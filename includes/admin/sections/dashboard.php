                <section class="dashboard-kpi-grid" id="dashboard">
                    <article class="stat-card">
                        <p class="panel-label">Dnes rezervace</p>
                        <strong class="stat-value"><?= escape((string) ((int) ($dashboardStats['today_reservations'] ?? 0))) ?></strong>
                        <p class="stat-note">Včetně nových, potvrzených a dokončených.</p>
                    </article>
                    <article class="stat-card">
                        <p class="panel-label">Čeká na potvrzení</p>
                        <strong class="stat-value"><?= escape((string) ((int) ($dashboardStats['pending_reservations'] ?? 0))) ?></strong>
                        <p class="stat-note">Budoucí rezervace ve stavu „nová“.</p>
                    </article>
                    <article class="stat-card">
                        <p class="panel-label">Volné sloty dnes</p>
                        <strong class="stat-value"><?= escape((string) ((int) ($dashboardStats['free_slots_today'] ?? 0))) ?></strong>
                        <p class="stat-note">Po 30 min, pouze zbývající čas dne.</p>
                    </article>
                    <article class="stat-card">
                        <p class="panel-label">Průměrná cena (30 dní)</p>
                        <strong class="stat-value"><?= escape(number_format((float) ($dashboardStats['avg_ticket_30d'] ?? 0), 0, ',', ' ')) ?> Kč</strong>
                        <p class="stat-note">Průměr ceny aktivních rezervací.</p>
                    </article>
                    <article class="stat-card">
                        <p class="panel-label">Aktivní rezervace (30 dní)</p>
                        <strong class="stat-value"><?= escape((string) ((int) ($dashboardStats['active_reservations_30d'] ?? 0))) ?></strong>
                        <p class="stat-note">Trend: <?= escape((string) ((int) ($dashboardStats['active_reservations_trend_pct'] ?? 0))) ?> % vs. předchozích 30 dní.</p>
                    </article>
                    <article class="stat-card">
                        <p class="panel-label">Aktivní služby</p>
                        <strong class="stat-value"><?= escape((string) ((int) ($dashboardStats['services_total'] ?? 0))) ?></strong>
                        <p class="stat-note">Služby dostupné pro rezervaci na webu.</p>
                    </article>
                </section>

                <section class="dashboard-detail-grid">
                    <article class="admin-card dashboard-panel">
                        <h2>Nejbližší rezervace</h2>
                        <?php if ($dashboardUpcomingReservations === []): ?>
                            <p class="dashboard-empty">V nejbližším období zatím nejsou žádné potvrditelné rezervace.</p>
                        <?php else: ?>
                            <div class="dashboard-rows">
                                <?php foreach ($dashboardUpcomingReservations as $row): ?>
                                    <div class="dashboard-row">
                                        <div>
                                            <p class="dashboard-row-title"><?= escape((string) ($row['nazev'] ?? 'Procedura')) ?></p>
                                            <p class="dashboard-row-subtitle"><?= escape((string) ($row['jmeno'] ?? 'Klientka')) ?></p>
                                        </div>
                                        <div class="dashboard-row-meta">
                                            <p><?= escape(formatCzechDateTime((string) ($row['datum_cas'] ?? ''))) ?></p>
                                            <span class="status-badge status-<?= escape((string) ($row['stav'] ?? 'nova')) ?>">
                                                <?= escape(reservationStatusLabel((string) ($row['stav'] ?? 'nova'))) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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

                    <article class="admin-card dashboard-panel">
                        <h2>Stavy rezervací (30 dní)</h2>
                        <?php
                        $statusRows = [
                            'nova' => 'Nové',
                            'potvrzena' => 'Potvrzené',
                            'dokoncena' => 'Dokončené',
                            'zrusena' => 'Zrušené',
                        ];
                        $statusTotal = max(1, array_sum(array_map(static fn($value): int => (int) $value, $dashboardStatusBreakdown)));
                        ?>
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
                </section>
