<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Service\AdminVoucherBatchGenerateUseCase;
use PPStudio\Service\AdminVoucherCreateUseCase;
use PPStudio\Service\AdminVoucherEmailSendUseCase;
use PPStudio\Service\AdminVoucherRedeemUseCase;

final class AdminVoucherPostActionHandler
{
    public function __construct(
        private AdminVoucherBatchGenerateUseCase $batchGenerateUseCase,
        private AdminVoucherCreateUseCase $createUseCase,
        private AdminVoucherEmailSendUseCase $emailSendUseCase,
        private AdminVoucherRedeemUseCase $redeemUseCase
    ) {
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     * @param array<string, mixed> $voucherForm
     * @param array<string, mixed> $voucherBatchForm
     * @return array{
     *   message: string,
     *   error: string,
     *   voucher_form: array<string, mixed>,
     *   voucher_batch_form: array<string, mixed>
     * }
     */
    public function handle(array $server, array $post, array $voucherForm, array $voucherBatchForm): array
    {
        if (($server['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return $this->result('', '', $voucherForm, $voucherBatchForm);
        }

        if (isset($post['generate_voucher_batch'])) {
            return $this->batchGenerateUseCase->handle($post, $voucherForm, $voucherBatchForm);
        }

        if (isset($post['create_voucher'])) {
            return $this->createUseCase->handle($post, $voucherForm, $voucherBatchForm);
        }

        if (isset($post['send_voucher_email'])) {
            return $this->emailSendUseCase->handle($post, $voucherForm, $voucherBatchForm);
        }

        if (isset($post['redeem_voucher'])) {
            return $this->redeemUseCase->handle($post, $voucherForm, $voucherBatchForm);
        }

        return $this->result('', '', $voucherForm, $voucherBatchForm);
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
