<?php
declare(strict_types=1);

namespace PPStudio\Service;

final class AdminVoucherCatalogService
{
    /**
     * @param array<int, array<string, mixed>> $voucherRows
     * @param array<int, array<int, array<string, mixed>>> $voucherTransactionsByVoucher
     * @param array<int, array<string, mixed>> $voucherReservationOptions
     * @param array<int, array<string, string>> $voucherReservationLookup
     * @return array{
     *   voucher_summary: array<string, int|float>,
     *   voucher_rows_prepared: array<int, array<string, mixed>>,
     *   voucher_reservation_options_prepared: array<int, array<string, mixed>>
     * }
     */
    public function buildSectionViewData(
        array $voucherRows,
        array $voucherTransactionsByVoucher,
        array $voucherReservationOptions,
        array $voucherReservationLookup
    ): array {
        return [
            'voucher_summary' => $this->buildSummary($voucherRows),
            'voucher_rows_prepared' => $this->prepareVoucherRows($voucherRows, $voucherTransactionsByVoucher, $voucherReservationLookup),
            'voucher_reservation_options_prepared' => $this->prepareReservationOptions($voucherReservationOptions),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $voucherRows
     * @return array<string, int|float>
     */
    private function buildSummary(array $voucherRows): array
    {
        $summary = [
            'total_count' => count($voucherRows),
            'active_count' => 0,
            'expired_count' => 0,
            'spent_out_count' => 0,
            'total_original' => 0.0,
            'total_remaining' => 0.0,
        ];

        foreach ($voucherRows as $voucherRow) {
            $summary['total_original'] += (float) ($voucherRow['puvodni_hodnota'] ?? 0);
            $summary['total_remaining'] += (float) ($voucherRow['zustatek'] ?? 0);

            $status = (string) ($voucherRow['effective_status'] ?? '');
            if ($status === 'aktivni') {
                $summary['active_count']++;
            } elseif ($status === 'expirovan') {
                $summary['expired_count']++;
            } elseif ($status === 'vycerpan') {
                $summary['spent_out_count']++;
            }
        }

        return $summary;
    }

    /**
     * @param array<int, array<string, mixed>> $voucherRows
     * @param array<int, array<int, array<string, mixed>>> $voucherTransactionsByVoucher
     * @param array<int, array<string, string>> $voucherReservationLookup
     * @return array<int, array<string, mixed>>
     */
    private function prepareVoucherRows(
        array $voucherRows,
        array $voucherTransactionsByVoucher,
        array $voucherReservationLookup
    ): array {
        $preparedRows = [];

        foreach ($voucherRows as $voucherRow) {
            $voucherId = (int) ($voucherRow['id'] ?? 0);
            $effectiveStatus = (string) ($voucherRow['effective_status'] ?? 'aktivni');
            $originalAmount = (float) ($voucherRow['puvodni_hodnota'] ?? 0);
            $remainingAmount = (float) ($voucherRow['zustatek'] ?? 0);
            $spentAmount = max(0.0, $originalAmount - $remainingAmount);
            $transactions = $voucherTransactionsByVoucher[$voucherId] ?? [];

            $voucherRow['voucher_id'] = $voucherId;
            $voucherRow['status_label'] = $this->statusLabel($effectiveStatus);
            $voucherRow['original_amount'] = $originalAmount;
            $voucherRow['remaining_amount'] = $remainingAmount;
            $voucherRow['spent_amount'] = $spentAmount;
            $voucherRow['spent_percent'] = $originalAmount > 0
                ? min(100.0, max(0.0, ($spentAmount / $originalAmount) * 100.0))
                : 0.0;
            $voucherRow['transactions'] = $this->prepareTransactions($transactions, $voucherReservationLookup);
            $voucherRow['transaction_count'] = count($transactions);
            $voucherRow['can_send_email'] = $effectiveStatus === 'aktivni';
            $voucherRow['can_redeem'] = $effectiveStatus === 'aktivni';

            $preparedRows[] = $voucherRow;
        }

        return $preparedRows;
    }

    /**
     * @param array<int, array<string, mixed>> $voucherReservationOptions
     * @return array<int, array<string, mixed>>
     */
    private function prepareReservationOptions(array $voucherReservationOptions): array
    {
        $preparedOptions = [];

        foreach ($voucherReservationOptions as $reservationOption) {
            $reservationPrice = (float) ($reservationOption['reservation_price'] ?? 0);
            $labelParts = [
                \PPStudio\Support\FormatHelper::formatCzechDateTime((string) ($reservationOption['datum_cas'] ?? '')),
                (string) ($reservationOption['jmeno'] ?? ''),
                (string) ($reservationOption['service_name'] ?? ''),
            ];

            $reservationOption['reservation_price_value'] = number_format($reservationPrice, 2, '.', '');
            $reservationOption['reservation_label'] = implode(
                ' - ',
                array_values(array_filter($labelParts, static fn (string $part): bool => trim($part) !== ''))
            );
            $reservationOption['reservation_search'] = mb_strtolower(
                trim(
                    (string) (
                        ($reservationOption['jmeno'] ?? '')
                        . ' '
                        . ($reservationOption['telefon'] ?? '')
                        . ' '
                        . ($reservationOption['service_name'] ?? '')
                        . ' '
                        . ($reservationOption['datum_cas'] ?? '')
                    )
                )
            );

            $preparedOptions[] = $reservationOption;
        }

        return $preparedOptions;
    }

    /**
     * @param array<int, array<string, mixed>> $transactions
     * @param array<int, array<string, string>> $voucherReservationLookup
     * @return array<int, array<string, mixed>>
     */
    private function prepareTransactions(array $transactions, array $voucherReservationLookup): array
    {
        $preparedTransactions = [];

        foreach ($transactions as $transaction) {
            $reservationId = (int) ($transaction['rezervace_id'] ?? 0);
            $reservationInfo = $voucherReservationLookup[$reservationId] ?? null;
            $reservationLabel = '';

            if (is_array($reservationInfo)) {
                $labelParts = [];
                if ((string) ($reservationInfo['datum_cas'] ?? '') !== '') {
                    $labelParts[] = \PPStudio\Support\FormatHelper::formatCzechDateTime((string) $reservationInfo['datum_cas']);
                }
                if ((string) ($reservationInfo['jmeno'] ?? '') !== '') {
                    $labelParts[] = (string) $reservationInfo['jmeno'];
                }

                $reservationLabel = implode(' • ', $labelParts);
            }

            $transaction['reservation_label'] = $reservationLabel !== '' ? $reservationLabel : ($reservationId > 0 ? '#' . (string) $reservationId : '');
            $preparedTransactions[] = $transaction;
        }

        return $preparedTransactions;
    }

    private function statusLabel(string $effectiveStatus): string
    {
        return match ($effectiveStatus) {
            'aktivni' => 'Aktivní',
            'vycerpan' => 'Vyčerpán',
            'storno' => 'Storno',
            'expirovan' => 'Expirovaný',
            default => ucfirst($effectiveStatus),
        };
    }
}
