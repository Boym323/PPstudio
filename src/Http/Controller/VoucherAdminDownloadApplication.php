<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use PPStudio\Http\Controller\Admin\AdminSessionState;
use PPStudio\Http\Controller\Admin\AdminSessionBootstrap;
use PPStudio\Http\View\VoucherAdminDownloadPageRenderer;
use PPStudio\Service\VoucherPdfGenerator;
use PPStudio\Service\VoucherAdminDownloadService;

final class VoucherAdminDownloadApplication
{
    public function __construct(
        private VoucherAdminDownloadService $voucherAdminDownloadService,
        private VoucherAdminDownloadPageRenderer $renderer,
        private VoucherPdfGenerator $voucherPdfGenerator
    ) {
    }

    public static function create(): self
    {
        return new self(
            new VoucherAdminDownloadService((new \PPStudio\Security\SecurityFacade())->requestSecurityService()),
            new VoucherAdminDownloadPageRenderer(),
            new VoucherPdfGenerator()
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    public function handle(array $query): never
    {
        AdminSessionBootstrap::start();

        if (! AdminSessionState::isAuthenticated($_SESSION)) {
            $this->renderer->render([
                'ok' => false,
                'mode' => 'plain_text',
                'http_code' => 401,
                'message' => 'Nejste přihlášeni.',
            ]);
        }

        $voucherId = (int) ($query['id'] ?? 0);
        if ($voucherId <= 0) {
            $this->renderer->render([
                'ok' => false,
                'mode' => 'plain_text',
                'http_code' => 422,
                'message' => 'Neplatné ID poukazu.',
            ]);
        }

        $state = $this->voucherAdminDownloadService->loadDownloadState($voucherId);

        if (strtolower(trim((string) ($query['download'] ?? ''))) === 'pdf') {
            $siteSettings = is_array($state['site_settings'] ?? null) ? $state['site_settings'] : [];
            $voucher = is_array($state['voucher'] ?? null) ? $state['voucher'] : [];
            $attachment = $this->voucherPdfGenerator->buildEmailAttachment($siteSettings, $voucher);

            if (is_array($attachment) && ($attachment['content'] ?? '') !== '') {
                $this->renderer->render([
                    'ok' => true,
                    'mode' => 'pdf_download',
                    'http_code' => 200,
                    'pdf_content' => (string) ($attachment['content'] ?? ''),
                    'pdf_filename' => (string) ($attachment['filename'] ?? ('voucher-' . $voucherId . '.pdf')),
                ]);
            }

            $this->renderer->render([
                'ok' => false,
                'mode' => 'message_page',
                'http_code' => 500,
                'page_title' => (string) ($state['page_title'] ?? (\defaultSiteName() . ' | DL poukaz')),
                'site_name' => (string) ($state['site_name'] ?? \defaultSiteName()),
                'message_heading' => 'Dárkový poukaz',
                'message' => 'PDF se nepodařilo vygenerovat.',
                'message_size' => 'normal',
            ]);
        }

        $this->renderer->render($state);
    }
}
