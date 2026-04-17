<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Repository\VoucherRepository;

final class AdminVoucherDataLoader
{
    public function __construct(private VoucherRepository $voucherRepository)
    {
    }

    /**
     * @return array{
     *   voucher_module_ready: bool,
     *   voucher_rows: array<int, array<string, mixed>>,
     *   voucher_transactions_by_voucher: array<int, array<int, array<string, mixed>>>,
     *   voucher_reservation_options: array<int, array<string, mixed>>,
     *   voucher_reservation_lookup: array<int, array<string, string>>
     * }
     */
    public function load(): array
    {
        if (! $this->voucherRepository->isVoucherModuleReady()) {
            return [
                'voucher_module_ready' => false,
                'voucher_rows' => [],
                'voucher_transactions_by_voucher' => [],
                'voucher_reservation_options' => [],
                'voucher_reservation_lookup' => [],
            ];
        }

        $voucherRows = $this->voucherRepository->findAdminRows();
        $voucherTransactionsByVoucher = [];
        $voucherReservationLookup = [];

        if ($voucherRows !== []) {
            $voucherIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $voucherRows);
            $voucherIds = array_values(array_filter($voucherIds, static fn (int $id): bool => $id > 0));

            if ($voucherIds !== []) {
                $voucherTransactionsByVoucher = $this->voucherRepository->findTransactionsGroupedByVoucher($voucherIds);
                $voucherReservationLookup = $this->voucherRepository->findReservationLookupByIds(
                    $this->extractReservationIdsFromTransactions($voucherTransactionsByVoucher)
                );
            }
        }

        return [
            'voucher_module_ready' => true,
            'voucher_rows' => $voucherRows,
            'voucher_transactions_by_voucher' => $voucherTransactionsByVoucher,
            'voucher_reservation_options' => $this->voucherRepository->findRedeemReservationOptions(),
            'voucher_reservation_lookup' => $voucherReservationLookup,
        ];
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $voucherTransactionsByVoucher
     * @return array<int, int>
     */
    private function extractReservationIdsFromTransactions(array $voucherTransactionsByVoucher): array
    {
        $reservationIds = [];

        foreach ($voucherTransactionsByVoucher as $transactions) {
            foreach ($transactions as $transaction) {
                $reservationId = (int) ($transaction['rezervace_id'] ?? 0);
                if ($reservationId > 0) {
                    $reservationIds[$reservationId] = $reservationId;
                }
            }
        }

        return array_values($reservationIds);
    }
}
