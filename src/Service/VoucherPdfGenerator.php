<?php
declare(strict_types=1);

namespace PPStudio\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use PPStudio\Security\SecurityFacade;
use Throwable;

final class VoucherPdfGenerator
{
    /**
     * @param array<string, mixed> $siteSettings
     * @param array<string, mixed> $voucher
     * @return array<string, string>|null
     */
    public function buildEmailAttachment(array $siteSettings, array $voucher): ?array
    {
        if (! class_exists(Dompdf::class)) {
            return null;
        }

        $voucherId = (int) ($voucher['id'] ?? 0);
        $voucherCode = trim((string) ($voucher['kod'] ?? ''));
        if ($voucherId <= 0 || $voucherCode === '') {
            return null;
        }

        try {
            $siteName = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'site_name', \defaultSiteName());
            $security = new SecurityFacade();
            $secret = isset($siteSettings['voucher_verify_secret']) ? (string) $siteSettings['voucher_verify_secret'] : null;
            $voucherUrl = $security->buildVoucherViewUrl($siteSettings, $voucherId, $voucherCode, $secret);
            $verifyUrl = $security->buildVoucherVerifyUrl($siteSettings, $voucherId, $voucherCode, $secret);
            $expiresAtRaw = trim((string) ($voucher['expires_at'] ?? ''));
            $expiresLabel = $expiresAtRaw !== '' ? \PPStudio\Support\FormatHelper::formatCzechDate($expiresAtRaw) : 'Bez omezení';
            $issuedLabel = \PPStudio\Support\FormatHelper::formatCzechDateTime((string) ($voucher['issued_at'] ?? ''));
            $valueLabel = \PPStudio\Support\FormatHelper::formatPrice($voucher['puvodni_hodnota'] ?? null);
            $recipient = trim((string) ($voucher['recipient_name'] ?? ''));
            $note = trim((string) ($voucher['note'] ?? ''));
            $qrPayload = $verifyUrl !== '' ? $verifyUrl : implode("\n", [
                'PP Studio - darkovy poukaz',
                'Kod: ' . $voucherCode,
                'Hodnota: ' . $valueLabel,
                'Platnost: ' . $expiresLabel,
            ]);
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($qrPayload);

            $html = $this->renderPdfHtml([
                'site_name' => $siteName,
                'voucher_code' => $voucherCode,
                'voucher_value_label' => $valueLabel,
                'expires_label' => $expiresLabel,
                'issued_label' => $issuedLabel,
                'recipient' => $recipient,
                'note' => $note,
                'verify_url' => $verifyUrl,
                'voucher_url' => $voucherUrl,
                'qr_url' => $qrUrl,
            ]);

            $options = new Options();
            $options->setIsRemoteEnabled(true);
            $options->setDefaultFont('DejaVu Sans');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            // Fixed DL canvas (210 x 99 mm) without orientation swap keeps output on one page.
            $dompdf->setPaper([0, 0, 595.28, 280.63]);
            $dompdf->render();
            $pdfContent = $dompdf->output();

            if ($pdfContent === '') {
                return null;
            }

            $safeCode = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtoupper($voucherCode));
            $safeCode = trim((string) $safeCode, '-');
            if ($safeCode === '') {
                $safeCode = (string) $voucherId;
            }

            return [
                'content' => $pdfContent,
                'filename' => 'voucher-' . $safeCode . '.pdf',
                'content_type' => 'application/pdf',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, string> $view
     */
    private function renderPdfHtml(array $view): string
    {
        ob_start();
        $__view = $view;
        require __DIR__ . '/../Http/View/Templates/voucher-email-pdf.php';
        return (string) ob_get_clean();
    }
}
