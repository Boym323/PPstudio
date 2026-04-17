                <section class="admin-single" id="poukazy">
                    <div class="admin-note">
                        <p class="eyebrow">Dárkové poukazy</p>
                        <h2>Poukazy a částečné čerpání</h2>
                        <?php if (! $voucherModuleReady): ?>
                            <div class="alert alert-error">Modul poukazů zatím není v databázi dostupný. Spusťte prosím aktualizační SQL skript.</div>
                        <?php else: ?>
                            <div class="voucher-summary-grid">
                                <article class="voucher-summary-card">
                                    <span class="voucher-summary-label">Celkem poukazů</span>
                                    <strong class="voucher-summary-value"><?= \PPStudio\Support\ViewHelper::escape((string) ($voucherSummary['total_count'] ?? count($voucherRows))) ?></strong>
                                </article>
                                <article class="voucher-summary-card">
                                    <span class="voucher-summary-label">Aktivní</span>
                                    <strong class="voucher-summary-value"><?= \PPStudio\Support\ViewHelper::escape((string) ($voucherSummary['active_count'] ?? 0)) ?></strong>
                                </article>
                                <article class="voucher-summary-card">
                                    <span class="voucher-summary-label">Zůstatek celkem</span>
                                    <strong class="voucher-summary-value"><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatPrice($voucherSummary['total_remaining'] ?? 0)) ?></strong>
                                </article>
                                <article class="voucher-summary-card">
                                    <span class="voucher-summary-label">Vyčerpáno / expirováno</span>
                                    <strong class="voucher-summary-value">
                                        <?= \PPStudio\Support\ViewHelper::escape((string) (($voucherSummary['spent_out_count'] ?? 0) + ($voucherSummary['expired_count'] ?? 0))) ?>
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
                                            <input type="text" name="voucher_batch_prefix" maxlength="12" value="<?= \PPStudio\Support\ViewHelper::escape($voucherBatchForm['prefix']) ?>" placeholder="PP26">
                                        </label>
                                        <label>
                                            <span>Počet</span>
                                            <input type="number" name="voucher_batch_count" min="1" max="200" value="<?= \PPStudio\Support\ViewHelper::escape($voucherBatchForm['count']) ?>">
                                        </label>
                                        <label>
                                            <span>Hodnota poukazu (Kč)</span>
                                            <input type="number" name="voucher_batch_value" min="1" step="1" value="<?= \PPStudio\Support\ViewHelper::escape($voucherBatchForm['value']) ?>">
                                        </label>
                                        <label>
                                            <span>Platnost do</span>
                                            <input type="date" name="voucher_batch_expires_at" value="<?= \PPStudio\Support\ViewHelper::escape($voucherBatchForm['expires_at']) ?>">
                                        </label>
                                        <label>
                                            <span>Příjemce (volitelné)</span>
                                            <input type="text" name="voucher_batch_recipient_name" value="<?= \PPStudio\Support\ViewHelper::escape($voucherBatchForm['recipient_name']) ?>" placeholder="Jméno obdarované">
                                        </label>
                                        <label class="full-span">
                                            <span>Poznámka série</span>
                                            <textarea name="voucher_batch_note" rows="3" placeholder="Např. jarní tisk 2026"><?= \PPStudio\Support\ViewHelper::escape($voucherBatchForm['note']) ?></textarea>
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
                                            <input type="text" name="voucher_code" maxlength="40" value="<?= \PPStudio\Support\ViewHelper::escape($voucherForm['code']) ?>" placeholder="PP26-ABC123">
                                        </label>
                                        <label>
                                            <span>Hodnota poukazu (Kč)</span>
                                            <input type="number" name="voucher_value" min="1" step="1" value="<?= \PPStudio\Support\ViewHelper::escape($voucherForm['value']) ?>" required>
                                        </label>
                                        <label>
                                            <span>Platnost do</span>
                                            <input type="date" name="voucher_expires_at" value="<?= \PPStudio\Support\ViewHelper::escape($voucherForm['expires_at']) ?>">
                                        </label>
                                        <label>
                                            <span>Příjemce (volitelné)</span>
                                            <input type="text" name="voucher_recipient_name" value="<?= \PPStudio\Support\ViewHelper::escape($voucherForm['recipient_name']) ?>" placeholder="Jméno obdarované">
                                        </label>
                                        <label>
                                            <span>E-mail příjemce (volitelné)</span>
                                            <input type="email" name="voucher_recipient_email" value="<?= \PPStudio\Support\ViewHelper::escape($voucherForm['recipient_email']) ?>" placeholder="např. jana@example.cz">
                                        </label>
                                        <label class="full-span">
                                            <span>Poznámka</span>
                                            <textarea name="voucher_note" rows="3" placeholder="Např. prodáno na místě"><?= \PPStudio\Support\ViewHelper::escape($voucherForm['note']) ?></textarea>
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
                                            <?php foreach (($voucherRowsPrepared ?? $voucherRows) as $voucher): ?>
                                                <?php
                                                $voucherId = (int) ($voucher['voucher_id'] ?? $voucher['id'] ?? 0);
                                                $effectiveStatus = (string) ($voucher['effective_status'] ?? 'aktivni');
                                                $statusLabel = (string) ($voucher['status_label'] ?? ucfirst($effectiveStatus));
                                                $remainingAmount = (float) ($voucher['remaining_amount'] ?? $voucher['zustatek'] ?? 0);
                                                $spentAmount = (float) ($voucher['spent_amount'] ?? 0);
                                                $spentPercent = (float) ($voucher['spent_percent'] ?? 0);
                                                $voucherTransactions = is_array($voucher['transactions'] ?? null)
                                                    ? $voucher['transactions']
                                                    : ($voucherTransactionsByVoucher[$voucherId] ?? []);
                                                $canSendEmail = (bool) ($voucher['can_send_email'] ?? ($effectiveStatus === 'aktivni'));
                                                $canRedeem = (bool) ($voucher['can_redeem'] ?? ($effectiveStatus === 'aktivni'));
                                                $voucherTransactionCount = (int) ($voucher['transaction_count'] ?? count($voucherTransactions));
                                                ?>
                                                <tr>
                                                    <td data-label="Kód"><strong><?= \PPStudio\Support\ViewHelper::escape((string) ($voucher['kod'] ?? '')) ?></strong></td>
                                                    <td data-label="Vydání"><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatCzechDateTime((string) ($voucher['issued_at'] ?? ''))) ?></td>
                                                    <td data-label="Platnost"><?= \PPStudio\Support\ViewHelper::escape((string) (($voucher['expires_at'] ?? '') !== '' ? \PPStudio\Support\FormatHelper::formatCzechDate((string) $voucher['expires_at']) : 'Bez omezení')) ?></td>
                                                    <td data-label="Hodnota"><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatPrice($voucher['puvodni_hodnota'] ?? null)) ?></td>
                                                    <td data-label="Zůstatek">
                                                        <strong><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatPrice($voucher['zustatek'] ?? null)) ?></strong>
                                                        <div class="voucher-balance-meta">Vyčerpáno: <?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatPrice($spentAmount)) ?></div>
                                                        <div class="voucher-progress" aria-hidden="true">
                                                            <span style="width: <?= \PPStudio\Support\ViewHelper::escape(number_format($spentPercent, 2, '.', '')) ?>%;"></span>
                                                        </div>
                                                    </td>
                                                    <td data-label="Stav">
                                                        <span class="status-badge voucher-status-<?= \PPStudio\Support\ViewHelper::escape($effectiveStatus) ?>"><?= \PPStudio\Support\ViewHelper::escape($statusLabel) ?></span>
                                                    </td>
                                                    <td data-label="Příjemce">
                                                        <?= \PPStudio\Support\ViewHelper::escape((string) ($voucher['recipient_name'] ?? '')) ?>
                                                        <?php if (trim((string) ($voucher['recipient_email'] ?? '')) !== ''): ?>
                                                            <div class="voucher-note"><?= \PPStudio\Support\ViewHelper::escape((string) $voucher['recipient_email']) ?></div>
                                                        <?php endif; ?>
                                                        <?php if (trim((string) ($voucher['emailed_at'] ?? '')) !== ''): ?>
                                                            <div class="voucher-note">Naposledy odesláno: <?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatCzechDateTime((string) $voucher['emailed_at'])) ?></div>
                                                        <?php endif; ?>
                                                        <?php if (trim((string) ($voucher['note'] ?? '')) !== ''): ?>
                                                            <div class="voucher-note"><?= \PPStudio\Support\ViewHelper::escape((string) $voucher['note']) ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td data-label="Akce a historie">
                                                        <details class="voucher-manage-wrap">
                                                            <summary>
                                                                Správa poukazu
                                                                <?php if ($voucherTransactions !== []): ?>
                                                                    <span class="voucher-manage-count"><?= \PPStudio\Support\ViewHelper::escape((string) $voucherTransactionCount) ?>x čerpání</span>
                                                                <?php else: ?>
                                                                    <span class="voucher-manage-count">Bez čerpání</span>
                                                                <?php endif; ?>
                                                            </summary>
                                                            <div class="table-actions" style="margin-bottom: .55rem;">
                                                                <a class="button button-secondary button-small" href="/admin-voucher-dl.php?id=<?= \PPStudio\Support\ViewHelper::escape((string) $voucherId) ?>" target="_blank" rel="noopener noreferrer">DL tisk / PDF</a>
                                                            </div>
                                                            <form method="post" class="admin-form compact-form compact-form-voucher voucher-send-mail-form">
                                                                <?= csrfInputField() ?>
                                                                <input type="hidden" name="voucher_id" value="<?= \PPStudio\Support\ViewHelper::escape((string) $voucherId) ?>">
                                                                <div class="voucher-email-row">
                                                                    <input
                                                                        type="email"
                                                                        name="voucher_recipient_email"
                                                                        value="<?= \PPStudio\Support\ViewHelper::escape((string) ($voucher['recipient_email'] ?? '')) ?>"
                                                                        placeholder="E-mail pro zaslání poukazu"
                                                                        <?= $canSendEmail ? '' : 'disabled' ?>
                                                                        required
                                                                    >
                                                                    <button
                                                                        class="button button-primary button-small"
                                                                        type="submit"
                                                                        name="send_voucher_email"
                                                                        value="1"
                                                                        <?= $canSendEmail ? '' : 'disabled' ?>
                                                                    >
                                                                        Odeslat e-mailem
                                                                    </button>
                                                                </div>
                                                                <div class="form-hint">
                                                                    <?= $canSendEmail
                                                                        ? 'Po odeslání se e-mail uloží i k poukazu pro další použití.'
                                                                        : 'E-mailem lze odesílat jen aktivní poukazy.' ?>
                                                                </div>
                                                            </form>
                                                            <?php if ($canRedeem): ?>
                                                                <form method="post" class="admin-form compact-form compact-form-voucher" data-voucher-redeem-form data-voucher-remaining="<?= \PPStudio\Support\ViewHelper::escape(number_format($remainingAmount, 2, '.', '')) ?>">
                                                                    <?= csrfInputField() ?>
                                                                    <input type="hidden" name="voucher_id" value="<?= \PPStudio\Support\ViewHelper::escape((string) $voucherId) ?>">
                                                                    <div class="voucher-actions-grid">
                                                                        <div class="voucher-amount-row">
                                                                            <input type="number" name="redeem_amount" min="1" step="1" placeholder="Částka (Kč)" required>
                                                                            <button class="button button-primary button-small" type="submit" name="redeem_voucher" value="1">Uložit čerpání</button>
                                                                        </div>
                                                                        <input type="text" name="redeem_note" placeholder="Poznámka čerpání (volitelné)">
                                                                        <details class="voucher-link-reservation">
                                                                            <summary>Připojit k rezervaci (volitelné)</summary>
                                                                            <input type="search" data-voucher-reservation-search placeholder="Hledat: jméno, telefon, služba, datum">
                                                                            <div class="voucher-reservation-results" data-voucher-search-results></div>
                                                                            <details class="voucher-native-select-wrap">
                                                                                <summary>Ruční výběr ze selectu</summary>
                                                                                <select name="redeem_reservation_id">
                                                                                    <option value="">Bez vazby na rezervaci</option>
                                                                                    <?php foreach (($voucherReservationOptionsPrepared ?? $voucherReservationOptions) as $reservationOption): ?>
                                                                                        <option value="<?= \PPStudio\Support\ViewHelper::escape((string) $reservationOption['id']) ?>" data-reservation-price="<?= \PPStudio\Support\ViewHelper::escape((string) ($reservationOption['reservation_price_value'] ?? number_format((float) ($reservationOption['reservation_price'] ?? 0), 2, '.', ''))) ?>" data-search="<?= \PPStudio\Support\ViewHelper::escape((string) ($reservationOption['reservation_search'] ?? '')) ?>">
                                                                                            <?= \PPStudio\Support\ViewHelper::escape((string) ($reservationOption['reservation_label'] ?? '')) ?>
                                                                                        </option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            </details>
                                                                            <div class="form-hint voucher-redeem-hint" data-voucher-redeem-hint></div>
                                                                            <div class="form-hint" data-voucher-search-hint>Zobrazeny jsou budoucí rezervace a posledních 90 dní.</div>
                                                                        </details>
                                                                    </div>
                                                                </form>
                                                            <?php else: ?>
                                                                <p class="form-hint">Čerpání není dostupné: poukaz má stav <strong><?= \PPStudio\Support\ViewHelper::escape(mb_strtolower($statusLabel)) ?></strong>.</p>
                                                            <?php endif; ?>
                                                            <?php if ($voucherTransactions !== []): ?>
                                                                <details class="voucher-transactions-wrap">
                                                                    <summary>Historie čerpání (<?= \PPStudio\Support\ViewHelper::escape((string) count($voucherTransactions)) ?>)</summary>
                                                                    <ul class="voucher-transactions-list">
                                                                        <?php foreach ($voucherTransactions as $transaction): ?>
                                                                            <li>
                                                                                <div class="voucher-transaction-main">
                                                                                    <strong><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatPrice($transaction['castka'] ?? null)) ?></strong>
                                                                                    <span><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\FormatHelper::formatCzechDateTime((string) ($transaction['created_at'] ?? ''))) ?></span>
                                                                                </div>
                                                                                <?php if ((int) ($transaction['rezervace_id'] ?? 0) > 0): ?>
                                                                                    <span class="voucher-transaction-reservation">
                                                                                        Rezervace:
                                                                                        <?= \PPStudio\Support\ViewHelper::escape((string) ($transaction['reservation_label'] ?? ('#' . (string) ((int) ($transaction['rezervace_id'] ?? 0))))) ?>
                                                                                    </span>
                                                                                <?php endif; ?>
                                                                                <?php if (trim((string) ($transaction['poznamka'] ?? '')) !== ''): ?>
                                                                                    <span class="voucher-transaction-note"><?= \PPStudio\Support\ViewHelper::escape((string) $transaction['poznamka']) ?></span>
                                                                                <?php endif; ?>
                                                                            </li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                </details>
                                                            <?php endif; ?>
                                                        </details>
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
