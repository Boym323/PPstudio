<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use PPStudio\Repository\VoucherRepository;
use RuntimeException;
use Throwable;

final class AdminVoucherRedeemUseCase
{
    public function __construct(
        private mysqli $connection,
        private VoucherRepository $voucherRepository,
        private ?AdminVoucherHelper $voucherHelper = null
    ) {
        $this->voucherHelper = $this->voucherHelper ?? new AdminVoucherHelper();
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $voucherForm
     * @param array<string, mixed> $voucherBatchForm
     * @return array{message:string,error:string,voucher_form:array<string,mixed>,voucher_batch_form:array<string,mixed>}
     */
    public function handle(array $post, array $voucherForm, array $voucherBatchForm): array
    {
        $voucherId = (int) ($post['voucher_id'] ?? 0);
        $redeemAmount = (float) str_replace(',', '.', trim((string) ($post['redeem_amount'] ?? '')));
        $reservationIdRaw = trim((string) ($post['redeem_reservation_id'] ?? ''));
        $reservationId = $reservationIdRaw !== '' ? (int) $reservationIdRaw : null;
        $redeemNote = trim((string) ($post['redeem_note'] ?? ''));

        if (! $this->voucherRepository->isVoucherModuleReady()) {
            return $this->result('', 'Modul poukazů není v databázi dostupný.', $voucherForm, $voucherBatchForm);
        }
        if ($voucherId <= 0 || $redeemAmount <= 0) {
            return $this->result('', 'Vyberte poukaz a zadejte částku čerpání.', $voucherForm, $voucherBatchForm);
        }

        $this->connection->begin_transaction();
        try {
            $voucher = $this->voucherRepository->findByIdForUpdate($voucherId);

            if (! is_array($voucher)) {
                throw new RuntimeException('Poukaz nebyl nalezen.');
            }

            $effectiveStatus = $this->voucherHelper->effectiveStatus($voucher);
            if ($effectiveStatus === 'storno') {
                throw new RuntimeException('Poukaz je stornovaný.');
            }
            if ($effectiveStatus === 'expirovan') {
                throw new RuntimeException('Poukaz je expirovaný.');
            }

            $remaining = (float) ($voucher['zustatek'] ?? 0);
            if ($redeemAmount > $remaining + 0.0001) {
                throw new RuntimeException('Čerpání je vyšší než aktuální zůstatek poukazu.');
            }

            $newRemaining = round(max(0, $remaining - $redeemAmount), 2);
            $newStatus = $newRemaining <= 0.0001 ? 'vycerpan' : 'aktivni';

            if (! $this->voucherRepository->updateBalanceAndStatus($voucherId, $newRemaining, $newStatus)) {
                throw new RuntimeException('Poukaz se nepodařilo aktualizovat.');
            }

            if (! $this->voucherRepository->insertRedeemTransaction($voucherId, $redeemAmount, $reservationId, $redeemNote)) {
                throw new RuntimeException('Čerpání se nepodařilo uložit.');
            }

            $this->connection->commit();

            return $this->result(
                'Čerpání poukazu bylo uloženo. Zůstatek: ' . number_format($newRemaining, 0, ',', ' ') . ' Kč.',
                '',
                $voucherForm,
                $voucherBatchForm
            );
        } catch (Throwable $exception) {
            $this->connection->rollback();

            return $this->result('', $exception->getMessage(), $voucherForm, $voucherBatchForm);
        }
    }

    /**
     * @param array<string, mixed> $voucherForm
     * @param array<string, mixed> $voucherBatchForm
     * @return array{message:string,error:string,voucher_form:array<string,mixed>,voucher_batch_form:array<string,mixed>}
     */
    private function result(string $message, string $error, array $voucherForm, array $voucherBatchForm): array
    {
        return [
            'message' => $message,
            'error' => $error,
            'voucher_form' => $voucherForm,
            'voucher_batch_form' => $voucherBatchForm,
        ];
    }
}
