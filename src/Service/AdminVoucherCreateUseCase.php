<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Repository\VoucherRepository;

final class AdminVoucherCreateUseCase
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
        $voucherForm = [
            'code' => trim((string) ($post['voucher_code'] ?? '')),
            'value' => trim((string) ($post['voucher_value'] ?? '')),
            'expires_at' => trim((string) ($post['voucher_expires_at'] ?? '')),
            'recipient_name' => trim((string) ($post['voucher_recipient_name'] ?? '')),
            'recipient_email' => \normalizeVoucherRecipientEmail((string) ($post['voucher_recipient_email'] ?? '')),
            'note' => trim((string) ($post['voucher_note'] ?? '')),
        ];

        if (! $this->voucherRepository->isVoucherModuleReady()) {
            return $this->result('', 'Modul poukazů není v databázi dostupný.', $voucherForm, $voucherBatchForm);
        }

        $value = (float) str_replace(',', '.', (string) $voucherForm['value']);
        $code = $voucherForm['code'] !== '' ? strtoupper((string) $voucherForm['code']) : \generateVoucherCode('PP' . date('y'));
        $code = preg_replace('/[^A-Z0-9\-]/', '', $code) ?? '';
        $expiresAt = (string) $voucherForm['expires_at'];
        $recipientEmail = (string) $voucherForm['recipient_email'];

        if ($code === '') {
            return $this->result('', 'Kód poukazu je neplatný.', $voucherForm, $voucherBatchForm);
        }
        if ($value <= 0) {
            return $this->result('', 'Hodnota poukazu musí být vyšší než 0 Kč.', $voucherForm, $voucherBatchForm);
        }
        if ($expiresAt !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) {
            return $this->result('', 'Platnost poukazu má neplatný formát data.', $voucherForm, $voucherBatchForm);
        }
        if ($recipientEmail !== '' && ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->result('', 'E-mail příjemce poukazu není ve správném formátu.', $voucherForm, $voucherBatchForm);
        }

        $expiresAtNullable = $expiresAt !== '' ? $expiresAt : null;
        $recipientName = (string) $voucherForm['recipient_name'];
        $note = (string) $voucherForm['note'];

        if ($this->voucherRepository->create($code, $value, $expiresAtNullable, $recipientName, $recipientEmail, $note)) {
            return $this->result('Poukaz byl uložen.', '', [
                'code' => '',
                'value' => '',
                'expires_at' => date('Y-m-d', strtotime('+1 year')),
                'recipient_name' => '',
                'recipient_email' => '',
                'note' => '',
            ], $voucherBatchForm);
        }

        $error = $this->voucherRepository->lastErrorCode() === 1062
            ? 'Kód poukazu už existuje. Zadejte jiný.'
            : 'Poukaz se nepodařilo uložit.';

        return $this->result('', $error, $voucherForm, $voucherBatchForm);
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
