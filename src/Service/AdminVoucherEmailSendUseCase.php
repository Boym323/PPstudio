<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Repository\VoucherRepository;

final class AdminVoucherEmailSendUseCase
{
    public function __construct(
        private VoucherRepository $voucherRepository,
        private MailerIntegrationService $mailerIntegrationService,
        private array $siteSettings,
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
        $recipientEmail = $this->voucherHelper->normalizeRecipientEmail((string) ($post['voucher_recipient_email'] ?? ''));

        if (! $this->voucherRepository->isVoucherModuleReady()) {
            return $this->result('', 'Modul poukazů není v databázi dostupný.', $voucherForm, $voucherBatchForm);
        }
        if ($voucherId <= 0) {
            return $this->result('', 'Vyberte prosím platný poukaz.', $voucherForm, $voucherBatchForm);
        }
        if ($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->result('', 'Zadejte platný e-mail, na který se má poukaz odeslat.', $voucherForm, $voucherBatchForm);
        }

        $voucher = $this->voucherRepository->findById($voucherId);

        if (! is_array($voucher)) {
            return $this->result('', 'Poukaz nebyl nalezen.', $voucherForm, $voucherBatchForm);
        }

        $voucher['recipient_email'] = $recipientEmail;
        $effectiveStatus = $this->voucherHelper->effectiveStatus($voucher);

        if ($effectiveStatus !== 'aktivni') {
            return $this->result('', 'E-mailem lze odeslat jen aktivní poukaz.', $voucherForm, $voucherBatchForm);
        }
        if (! $this->mailerIntegrationService->isEnabled()) {
            return $this->result('', 'E-mailové odesílání není v nastavení aktivní.', $voucherForm, $voucherBatchForm);
        }
        if (! $this->mailerIntegrationService->sendVoucherEmail($this->siteSettings, $voucher, $recipientEmail)) {
            return $this->result('', 'Poukaz se nepodařilo odeslat e-mailem.', $voucherForm, $voucherBatchForm);
        }

        $this->voucherRepository->markEmailed($voucherId, $recipientEmail);

        return $this->result('Poukaz byl odeslán na e-mail ' . $recipientEmail . '.', '', $voucherForm, $voucherBatchForm);
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
