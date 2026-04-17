<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Database\DatabaseFactory;
use PPStudio\Repository\SiteSettingsRepository;
use PPStudio\Repository\VoucherRepository;
use PPStudio\Security\RequestSecurityService;

final class VoucherAdminDownloadService
{
    public function __construct(private RequestSecurityService $requestSecurityService)
    {
    }

    public function loadDownloadState(int $voucherId): array
    {
        $connection = DatabaseFactory::tryConnect();
        if (! $connection instanceof \mysqli) {
            return $this->errorState('Databáze není dostupná.', 500);
        }

        $siteSettings = (new SiteSettingsService(
            new SiteSettingsRepository($connection),
            \defaultSiteSettings()
        ))->load();
        $siteName = \setting($siteSettings, 'site_name', \defaultSiteName());

        $voucher = (new VoucherRepository($connection))->findPrintById($voucherId);
        $connection->close();

        if (! is_array($voucher)) {
            return $this->errorState('Poukaz nebyl nalezen.', 404, $siteName);
        }

        $code = (string) ($voucher['kod'] ?? '');
        $recipient = trim((string) ($voucher['recipient_name'] ?? ''));
        $originalValue = (float) ($voucher['puvodni_hodnota'] ?? 0);
        $expiresAt = trim((string) ($voucher['expires_at'] ?? ''));
        $expiresLabel = $expiresAt !== '' ? \formatCzechDate($expiresAt) : 'Bez omezení';
        $issuedLabel = \formatCzechDateTime((string) ($voucher['issued_at'] ?? ''));
        $note = trim((string) ($voucher['note'] ?? ''));
        $verifyUrl = \ppstudioSecurityFacade()->buildVoucherVerifyUrl($siteSettings, $voucherId, $code, $this->requestSecurityService->voucherVerifySecret());
        $qrPayload = $verifyUrl !== '' ? $verifyUrl : implode("\n", [
            'PP Studio - darkovy poukaz',
            'Kod: ' . $code,
            'Hodnota: ' . \formatPrice($originalValue),
            'Platnost: ' . $expiresLabel,
        ]);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($qrPayload);

        return [
            'ok' => true,
            'mode' => 'voucher_dl',
            'page_title' => $siteName . ' | DL poukaz ' . $code,
            'site_name' => $siteName,
            'voucher' => $voucher,
            'code' => $code,
            'recipient' => $recipient,
            'original_value_label' => \formatPrice($originalValue),
            'expires_label' => $expiresLabel,
            'issued_label' => $issuedLabel,
            'note' => $note,
            'verify_url' => $verifyUrl,
            'qr_url' => $qrUrl,
        ];
    }

    private function errorState(string $message, int $httpCode, string $siteName = ''): array
    {
        return [
            'ok' => false,
            'mode' => 'message_page',
            'http_code' => $httpCode,
            'page_title' => ($siteName !== '' ? $siteName : \defaultSiteName()) . ' | DL poukaz',
            'site_name' => $siteName !== '' ? $siteName : \defaultSiteName(),
            'message_heading' => 'Dárkový poukaz',
            'message' => $message,
            'message_size' => 'normal',
        ];
    }
}
