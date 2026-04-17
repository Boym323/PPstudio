<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Repository\VoucherRepository;

final class AdminVoucherBatchGenerateUseCase
{
    public function __construct(private VoucherRepository $voucherRepository)
    {
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $voucherForm
     * @param array<string, mixed> $voucherBatchForm
     * @return array{message:string,error:string,voucher_form:array<string,mixed>,voucher_batch_form:array<string,mixed>}
     */
    public function handle(array $post, array $voucherForm, array $voucherBatchForm): array
    {
        $voucherBatchForm = [
            'prefix' => trim((string) ($post['voucher_batch_prefix'] ?? '')),
            'count' => trim((string) ($post['voucher_batch_count'] ?? '20')),
            'value' => trim((string) ($post['voucher_batch_value'] ?? '1000')),
            'expires_at' => trim((string) ($post['voucher_batch_expires_at'] ?? '')),
            'recipient_name' => trim((string) ($post['voucher_batch_recipient_name'] ?? '')),
            'note' => trim((string) ($post['voucher_batch_note'] ?? '')),
        ];

        if (! $this->voucherRepository->isVoucherModuleReady()) {
            return $this->result('', 'Modul poukazů není v databázi dostupný.', $voucherForm, $voucherBatchForm);
        }

        $count = max(1, min(200, (int) $voucherBatchForm['count']));
        $value = (float) str_replace(',', '.', (string) $voucherBatchForm['value']);
        $expiresAt = (string) $voucherBatchForm['expires_at'];

        if ($value <= 0) {
            return $this->result('', 'Hodnota poukazu musí být vyšší než 0 Kč.', $voucherForm, $voucherBatchForm);
        }

        if ($expiresAt !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) {
            return $this->result('', 'Platnost poukazu má neplatný formát data.', $voucherForm, $voucherBatchForm);
        }

        $generated = 0;
        $generatedCodes = [];
        $maxAttempts = $count * 20;
        $attempts = 0;
        $prepareFailed = false;

        while ($generated < $count && $attempts < $maxAttempts) {
            $attempts++;
            $code = \generateVoucherCode((string) $voucherBatchForm['prefix']);
            $expiresAtNullable = $expiresAt !== '' ? $expiresAt : null;
            $recipientName = (string) $voucherBatchForm['recipient_name'];
            $note = (string) $voucherBatchForm['note'];

            $ok = $this->voucherRepository->createBatchVoucher(
                $code,
                $value,
                $expiresAtNullable,
                $recipientName,
                $note
            );

            if ($ok) {
                $generated++;
                $generatedCodes[] = $code;
                continue;
            }

            $errorCode = $this->voucherRepository->lastErrorCode();
            if ($errorCode === 0) {
                $prepareFailed = true;
                break;
            }

            if ($errorCode !== 1062) {
                break;
            }
        }

        if ($generated === $count) {
            return $this->result(
                'Vygenerováno ' . $generated . ' poukazů. První kódy: ' . implode(', ', array_slice($generatedCodes, 0, 5)) . (count($generatedCodes) > 5 ? '…' : ''),
                '',
                $voucherForm,
                $voucherBatchForm
            );
        }

        if ($generated > 0) {
            return $this->result('', 'Vygenerováno jen ' . $generated . ' z ' . $count . ' poukazů. Zkuste akci zopakovat.', $voucherForm, $voucherBatchForm);
        }

        if ($prepareFailed) {
            return $this->result('', 'Sérii poukazů se nepodařilo vygenerovat.', $voucherForm, $voucherBatchForm);
        }

        return $this->result('', 'Sérii poukazů se nepodařilo vygenerovat.', $voucherForm, $voucherBatchForm);
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
