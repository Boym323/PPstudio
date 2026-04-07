                <section class="admin-single" id="poukazy">
                    <div class="admin-note">
                        <p class="eyebrow">Dárkové poukazy</p>
                        <h2>Poukazy a částečné čerpání</h2>
                        <?php if (! $voucherModuleReady): ?>
                            <div class="alert alert-error">Modul poukazů zatím není v databázi dostupný. Spusťte prosím aktualizační SQL skript.</div>
                        <?php else: ?>
                            <?php
                            $voucherTotalCount = count($voucherRows);
                            $voucherActiveCount = 0;
                            $voucherExpiredCount = 0;
                            $voucherSpentOutCount = 0;
                            $voucherTotalOriginal = 0.0;
                            $voucherTotalRemaining = 0.0;
                            foreach ($voucherRows as $voucherSummaryRow) {
                                $voucherTotalOriginal += (float) ($voucherSummaryRow['puvodni_hodnota'] ?? 0);
                                $voucherTotalRemaining += (float) ($voucherSummaryRow['zustatek'] ?? 0);
                                $summaryStatus = (string) ($voucherSummaryRow['effective_status'] ?? '');
                                if ($summaryStatus === 'aktivni') {
                                    $voucherActiveCount++;
                                } elseif ($summaryStatus === 'expirovan') {
                                    $voucherExpiredCount++;
                                } elseif ($summaryStatus === 'vycerpan') {
                                    $voucherSpentOutCount++;
                                }
                            }
                            ?>
                            <div class="voucher-summary-grid">
                                <article class="voucher-summary-card">
                                    <span class="voucher-summary-label">Celkem poukazů</span>
                                    <strong class="voucher-summary-value"><?= escape((string) $voucherTotalCount) ?></strong>
                                </article>
                                <article class="voucher-summary-card">
                                    <span class="voucher-summary-label">Aktivní</span>
                                    <strong class="voucher-summary-value"><?= escape((string) $voucherActiveCount) ?></strong>
                                </article>
                                <article class="voucher-summary-card">
                                    <span class="voucher-summary-label">Zůstatek celkem</span>
                                    <strong class="voucher-summary-value"><?= escape(formatPrice($voucherTotalRemaining)) ?></strong>
                                </article>
                                <article class="voucher-summary-card">
                                    <span class="voucher-summary-label">Vyčerpáno / expirováno</span>
                                    <strong class="voucher-summary-value">
                                        <?= escape((string) ($voucherSpentOutCount + $voucherExpiredCount)) ?>
                                    </strong>
                                </article>
                            </div>
                            <div class="admin-layout">
                                <div class="admin-card">
                                    <h3>Vygenerovat sérii poukazů</h3>
                                    <form method="post" class="admin-form admin-form-grid">
                                        <?= csrfInputField() ?>
                                        <label>
                                            <span>Prefix kódu</span>
                                            <input type="text" name="voucher_batch_prefix" maxlength="12" value="<?= escape($voucherBatchForm['prefix']) ?>" placeholder="PP26">
                                        </label>
                                        <label>
                                            <span>Počet</span>
                                            <input type="number" name="voucher_batch_count" min="1" max="200" value="<?= escape($voucherBatchForm['count']) ?>">
                                        </label>
                                        <label>
                                            <span>Hodnota poukazu (Kč)</span>
                                            <input type="number" name="voucher_batch_value" min="1" step="1" value="<?= escape($voucherBatchForm['value']) ?>">
                                        </label>
                                        <label>
                                            <span>Platnost do</span>
                                            <input type="date" name="voucher_batch_expires_at" value="<?= escape($voucherBatchForm['expires_at']) ?>">
                                        </label>
                                        <label>
                                            <span>Příjemce (volitelné)</span>
                                            <input type="text" name="voucher_batch_recipient_name" value="<?= escape($voucherBatchForm['recipient_name']) ?>" placeholder="Jméno obdarované">
                                        </label>
                                        <label class="full-span">
                                            <span>Poznámka série</span>
                                            <textarea name="voucher_batch_note" rows="3" placeholder="Např. jarní tisk 2026"><?= escape($voucherBatchForm['note']) ?></textarea>
                                        </label>
                                        <button class="button button-primary full-span" type="submit" name="generate_voucher_batch" value="1">Vygenerovat kódy</button>
                                    </form>
                                    <p class="form-hint">Vygenerované kódy jsou unikátní. Hodnotu a platnost lze při potřebě později upravit čerpáním/korekcí.</p>
                                </div>
                                <div class="admin-card">
                                    <h3>Vložit jednotlivý poukaz</h3>
                                    <form method="post" class="admin-form admin-form-grid">
                                        <?= csrfInputField() ?>
                                        <label>
                                            <span>Kód (prázdné = auto)</span>
                                            <input type="text" name="voucher_code" maxlength="40" value="<?= escape($voucherForm['code']) ?>" placeholder="PP26-ABC123">
                                        </label>
                                        <label>
                                            <span>Hodnota poukazu (Kč)</span>
                                            <input type="number" name="voucher_value" min="1" step="1" value="<?= escape($voucherForm['value']) ?>" required>
                                        </label>
                                        <label>
                                            <span>Platnost do</span>
                                            <input type="date" name="voucher_expires_at" value="<?= escape($voucherForm['expires_at']) ?>">
                                        </label>
                                        <label>
                                            <span>Příjemce (volitelné)</span>
                                            <input type="text" name="voucher_recipient_name" value="<?= escape($voucherForm['recipient_name']) ?>" placeholder="Jméno obdarované">
                                        </label>
                                        <label class="full-span">
                                            <span>Poznámka</span>
                                            <textarea name="voucher_note" rows="3" placeholder="Např. prodáno na místě"><?= escape($voucherForm['note']) ?></textarea>
                                        </label>
                                        <button class="button button-primary full-span" type="submit" name="create_voucher" value="1">Uložit poukaz</button>
                                    </form>
                                </div>
                            </div>

                            <div class="admin-table-wrap">
                                <table class="admin-table voucher-admin-table">
                                    <thead>
                                        <tr>
                                            <th>Kód</th>
                                            <th>Vydání</th>
                                            <th>Platnost</th>
                                            <th>Hodnota</th>
                                            <th>Zůstatek</th>
                                            <th>Stav</th>
                                            <th>Příjemce</th>
                                            <th>Akce a historie</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($voucherRows === []): ?>
                                            <tr><td colspan="8">Zatím zde nejsou žádné poukazy.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($voucherRows as $voucher): ?>
                                                <?php
                                                $voucherId = (int) ($voucher['id'] ?? 0);
                                                $effectiveStatus = (string) ($voucher['effective_status'] ?? 'aktivni');
                                                $statusLabel = match ($effectiveStatus) {
                                                    'aktivni' => 'Aktivní',
                                                    'vycerpan' => 'Vyčerpán',
                                                    'storno' => 'Storno',
                                                    'expirovan' => 'Expirovaný',
                                                    default => ucfirst($effectiveStatus),
                                                };
                                                $originalAmount = (float) ($voucher['puvodni_hodnota'] ?? 0);
                                                $remainingAmount = (float) ($voucher['zustatek'] ?? 0);
                                                $spentAmount = max(0.0, $originalAmount - $remainingAmount);
                                                $spentPercent = $originalAmount > 0 ? min(100.0, max(0.0, ($spentAmount / $originalAmount) * 100.0)) : 0.0;
                                                $voucherTransactions = $voucherTransactionsByVoucher[$voucherId] ?? [];
                                                ?>
                                                <tr>
                                                    <td data-label="Kód"><strong><?= escape((string) ($voucher['kod'] ?? '')) ?></strong></td>
                                                    <td data-label="Vydání"><?= escape(formatCzechDateTime((string) ($voucher['issued_at'] ?? ''))) ?></td>
                                                    <td data-label="Platnost"><?= escape((string) (($voucher['expires_at'] ?? '') !== '' ? formatCzechDate((string) $voucher['expires_at']) : 'Bez omezení')) ?></td>
                                                    <td data-label="Hodnota"><?= escape(formatPrice($voucher['puvodni_hodnota'] ?? null)) ?></td>
                                                    <td data-label="Zůstatek">
                                                        <strong><?= escape(formatPrice($voucher['zustatek'] ?? null)) ?></strong>
                                                        <div class="voucher-balance-meta">Vyčerpáno: <?= escape(formatPrice($spentAmount)) ?></div>
                                                        <div class="voucher-progress" aria-hidden="true">
                                                            <span style="width: <?= escape(number_format($spentPercent, 2, '.', '')) ?>%;"></span>
                                                        </div>
                                                    </td>
                                                    <td data-label="Stav">
                                                        <span class="status-badge voucher-status-<?= escape($effectiveStatus) ?>"><?= escape($statusLabel) ?></span>
                                                    </td>
                                                    <td data-label="Příjemce">
                                                        <?= escape((string) ($voucher['recipient_name'] ?? '')) ?>
                                                        <?php if (trim((string) ($voucher['note'] ?? '')) !== ''): ?>
                                                            <div class="voucher-note"><?= escape((string) $voucher['note']) ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td data-label="Akce a historie">
                                                        <?php if ($effectiveStatus === 'aktivni'): ?>
                                                            <form method="post" class="admin-form compact-form compact-form-voucher">
                                                                <?= csrfInputField() ?>
                                                                <input type="hidden" name="voucher_id" value="<?= escape((string) $voucherId) ?>">
                                                                <div class="voucher-actions-grid">
                                                                    <div class="voucher-amount-row">
                                                                        <input type="number" name="redeem_amount" min="1" step="1" placeholder="Částka (Kč)" required>
                                                                        <button class="button button-primary button-small" type="submit" name="redeem_voucher" value="1">Uložit čerpání</button>
                                                                    </div>
                                                                    <input type="text" name="redeem_note" placeholder="Poznámka čerpání (volitelné)">
                                                                    <details class="voucher-link-reservation">
                                                                        <summary>Připojit k rezervaci (volitelné)</summary>
                                                                        <select name="redeem_reservation_id">
                                                                            <option value="">Bez vazby na rezervaci</option>
                                                                            <?php foreach ($voucherReservationOptions as $reservationOption): ?>
                                                                                <option value="<?= escape((string) $reservationOption['id']) ?>">
                                                                                    <?= escape(formatCzechDateTime((string) $reservationOption['datum_cas']) . ' - ' . (string) $reservationOption['jmeno']) ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </details>
                                                                </div>
                                                            </form>
                                                        <?php else: ?>
                                                            <p class="form-hint">Čerpání není dostupné: poukaz má stav <strong><?= escape(mb_strtolower($statusLabel)) ?></strong>.</p>
                                                        <?php endif; ?>
                                                        <?php if ($voucherTransactions !== []): ?>
                                                            <details class="voucher-transactions-wrap">
                                                                <summary>Historie čerpání (<?= escape((string) count($voucherTransactions)) ?>)</summary>
                                                                <ul class="voucher-transactions-list">
                                                                    <?php foreach ($voucherTransactions as $transaction): ?>
                                                                        <li>
                                                                            <div class="voucher-transaction-main">
                                                                                <strong><?= escape(formatPrice($transaction['castka'] ?? null)) ?></strong>
                                                                                <span><?= escape(formatCzechDateTime((string) ($transaction['created_at'] ?? ''))) ?></span>
                                                                            </div>
                                                                            <?php if ((int) ($transaction['rezervace_id'] ?? 0) > 0): ?>
                                                                                <?php
                                                                                $txReservationId = (int) $transaction['rezervace_id'];
                                                                                $reservationInfo = $voucherReservationLookup[$txReservationId] ?? null;
                                                                                $reservationLabel = '';
                                                                                if (is_array($reservationInfo)) {
                                                                                    $labelParts = [];
                                                                                    if ((string) ($reservationInfo['datum_cas'] ?? '') !== '') {
                                                                                        $labelParts[] = formatCzechDateTime((string) $reservationInfo['datum_cas']);
                                                                                    }
                                                                                    if ((string) ($reservationInfo['jmeno'] ?? '') !== '') {
                                                                                        $labelParts[] = (string) $reservationInfo['jmeno'];
                                                                                    }
                                                                                    $reservationLabel = implode(' • ', $labelParts);
                                                                                }
                                                                                ?>
                                                                                <span class="voucher-transaction-reservation">
                                                                                    Rezervace:
                                                                                    <?= escape($reservationLabel !== '' ? $reservationLabel : ('#' . (string) $txReservationId)) ?>
                                                                                </span>
                                                                            <?php endif; ?>
                                                                            <?php if (trim((string) ($transaction['poznamka'] ?? '')) !== ''): ?>
                                                                                <span class="voucher-transaction-note"><?= escape((string) $transaction['poznamka']) ?></span>
                                                                            <?php endif; ?>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            </details>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="form-hint">Čerpání je nevratná účetní operace. Pro opravy používejte raději nové kompenzační čerpání s poznámkou.</p>
                        <?php endif; ?>
                    </div>
                </section>
