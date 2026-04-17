<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Database\DatabaseFactory;
use PPStudio\Repository\SiteSettingsRepository;
use PPStudio\Repository\VoucherRepository;
use PPStudio\Security\RequestSecurityService;

final class VoucherPublicService
{
    public function __construct(private RequestSecurityService $requestSecurityService)
    {
    }

    public function loadVoucherViewState(int $voucherId, string $signature): array
    {
        $context = $this->voucherContext($voucherId);
        if (! ($context['ok'] ?? false)) {
            return $context;
        }

        $voucher = is_array($context['voucher'] ?? null) ? $context['voucher'] : null;
        $siteSettings = is_array($context['site_settings'] ?? null) ? $context['site_settings'] : [];
        $siteName = (string) ($context['site_name'] ?? \defaultSiteName());

        if (! $this->isValidVoucherSignature($voucher, $signature)) {
            \ppstudioSecurityFacade()->securityEventLogger()->log('voucher_view_invalid', 'voucher_view', 'warning', [
                'voucher_id' => $voucherId,
            ]);

            return [
                'ok' => false,
                'mode' => 'message_page',
                'http_code' => 403,
                'page_title' => $siteName . ' | Dárkový poukaz',
                'site_name' => $siteName,
                'message_heading' => 'Dárkový poukaz',
                'message' => 'Odkaz je neplatný nebo expirovaný.',
                'message_size' => 'normal',
            ];
        }

        $status = $this->effectiveStatus($voucher);
        $statusLabel = $this->statusLabel($status);
        $expiresAtRaw = trim((string) ($voucher['expires_at'] ?? ''));
        $expiresLabel = $expiresAtRaw !== '' ? \formatCzechDate($expiresAtRaw) : 'Bez omezení';
        $valueLabel = \formatPrice($voucher['puvodni_hodnota'] ?? null);
        $security = \ppstudioSecurityFacade();
        $voucherUrl = $security->buildVoucherViewUrl($siteSettings, (int) ($voucher['id'] ?? 0), (string) ($voucher['kod'] ?? ''), $this->requestSecurityService->voucherVerifySecret());
        $verifyUrl = $security->buildVoucherVerifyUrl($siteSettings, (int) ($voucher['id'] ?? 0), (string) ($voucher['kod'] ?? ''), $this->requestSecurityService->voucherVerifySecret());
        $qrPayload = $voucherUrl !== '' ? $voucherUrl : implode("\n", [
            'PP Studio - darkovy poukaz',
            'Kod: ' . (string) ($voucher['kod'] ?? ''),
            'Hodnota: ' . $valueLabel,
            'Platnost: ' . $expiresLabel,
        ]);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($qrPayload);

        \ppstudioSecurityFacade()->securityEventLogger()->log('voucher_view_ok', 'voucher_view', 'info', [
            'voucher_id' => (int) ($voucher['id'] ?? 0),
            'status' => $status,
        ]);

        return [
            'ok' => true,
            'mode' => 'voucher_view',
            'page_title' => $siteName . ' | Dárkový poukaz',
            'site_name' => $siteName,
            'voucher' => $voucher,
            'status_label' => $statusLabel,
            'value_label' => $valueLabel,
            'expires_label' => $expiresLabel,
            'verify_url' => $verifyUrl,
            'qr_url' => $qrUrl,
        ];
    }

    public function loadVoucherVerifyState(int $voucherId, string $signature, bool $isPrivileged): array
    {
        $context = $this->voucherContext($voucherId);
        if (! ($context['ok'] ?? false)) {
            return $context;
        }

        $voucher = is_array($context['voucher'] ?? null) ? $context['voucher'] : null;
        $siteName = (string) ($context['site_name'] ?? \defaultSiteName());

        if (! $this->isValidVoucherSignature($voucher, $signature)) {
            \ppstudioSecurityFacade()->securityEventLogger()->log('voucher_verify_invalid', 'voucher_verify', 'warning', [
                'voucher_id' => $voucherId,
            ]);

            return [
                'ok' => false,
                'mode' => 'message_page',
                'http_code' => 403,
                'page_title' => $siteName . ' | Ověření poukazu',
                'site_name' => $siteName,
                'message_heading' => 'Ověření poukazu',
                'message' => 'Odkaz je neplatný nebo expirovaný.',
                'message_size' => 'narrow',
            ];
        }

        $status = $this->effectiveStatus($voucher);
        $expiresAt = trim((string) ($voucher['expires_at'] ?? ''));

        \ppstudioSecurityFacade()->securityEventLogger()->log('voucher_verify_ok', 'voucher_verify', 'info', [
            'voucher_id' => (int) ($voucher['id'] ?? 0),
            'status' => $status,
            'privileged_view' => $isPrivileged ? 1 : 0,
        ]);

        return [
            'ok' => true,
            'mode' => 'voucher_verify',
            'page_title' => $siteName . ' | Ověření poukazu',
            'site_name' => $siteName,
            'voucher' => $voucher,
            'is_privileged' => $isPrivileged,
            'status_label' => $this->statusLabel($status),
            'expires_label' => $expiresAt !== '' ? \formatCzechDate($expiresAt) : 'Bez omezení',
            'issued_at_label' => \formatCzechDateTime((string) ($voucher['issued_at'] ?? '')),
            'original_value_label' => \formatPrice($voucher['puvodni_hodnota'] ?? null),
            'remaining_value_label' => \formatPrice($voucher['zustatek'] ?? null),
        ];
    }

    private function voucherContext(int $voucherId): array
    {
        $connection = DatabaseFactory::tryConnect();
        if (! $connection instanceof \mysqli) {
            return [
                'ok' => false,
                'mode' => 'plain_text',
                'http_code' => 500,
                'message' => 'Databáze není dostupná.',
            ];
        }

        $siteSettings = (new SiteSettingsService(
            new SiteSettingsRepository($connection),
            \defaultSiteSettings()
        ))->load();
        $siteName = \setting($siteSettings, 'site_name', \defaultSiteName());
        $voucher = (new VoucherRepository($connection))->findPublicById($voucherId);
        $connection->close();

        if (! is_array($voucher)) {
            return [
                'ok' => false,
                'mode' => 'message_page',
                'http_code' => 403,
                'page_title' => $siteName . ' | Dárkový poukaz',
                'site_name' => $siteName,
                'message_heading' => 'Dárkový poukaz',
                'message' => 'Odkaz je neplatný nebo expirovaný.',
                'message_size' => 'normal',
            ];
        }

        return [
            'ok' => true,
            'voucher' => $voucher,
            'site_settings' => $siteSettings,
            'site_name' => $siteName,
        ];
    }

    private function isValidVoucherSignature(?array $voucher, string $signature): bool
    {
        if (! is_array($voucher)) {
            return false;
        }

        return $this->requestSecurityService->isValidVoucherVerifySignature(
            $this->requestSecurityService->voucherVerifySecret(),
            (int) ($voucher['id'] ?? 0),
            (string) ($voucher['kod'] ?? ''),
            $signature
        );
    }

    private function effectiveStatus(array $voucher): string
    {
        $statusRaw = (string) ($voucher['status'] ?? 'aktivni');
        $remaining = (float) ($voucher['zustatek'] ?? 0);
        $expiresAt = trim((string) ($voucher['expires_at'] ?? ''));

        if ($statusRaw !== 'storno' && $remaining <= 0.0001) {
            return 'vycerpan';
        }

        if (
            $statusRaw !== 'storno'
            && $expiresAt !== ''
            && strtotime($expiresAt . ' 23:59:59') !== false
            && strtotime($expiresAt . ' 23:59:59') < time()
        ) {
            return 'expirovan';
        }

        return $statusRaw;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'aktivni' => 'Aktivní',
            'vycerpan' => 'Vyčerpán',
            'expirovan' => 'Expirovaný',
            'storno' => 'Storno',
            default => ucfirst($status),
        };
    }
}
