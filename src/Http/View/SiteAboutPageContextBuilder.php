<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

use PPStudio\Infrastructure\Storage\UploadStorage;
use PPStudio\Service\CertificateMetadataService;
use PPStudio\Service\CertificatePreviewService;
use PPStudio\Service\CertificateService;

final class SiteAboutPageContextBuilder
{
    public function build(): array
    {
        $uploadStorage = new UploadStorage();
        $certificateService = new CertificateService(
            $uploadStorage,
            new CertificateMetadataService($uploadStorage),
            new CertificatePreviewService($uploadStorage)
        );

        $certificateItems = $certificateService->loadUploads(
            dirname(__DIR__, 3) . '/uploads',
            '/uploads',
            'cert_'
        );

        return [
            'certificateItems' => array_map([$this, 'normalizeCertificateItem'], $certificateItems),
        ];
    }

    /**
     * @param array<string, mixed> $certificate
     * @return array<string, mixed>
     */
    private function normalizeCertificateItem(array $certificate): array
    {
        $uploadedAt = (int) ($certificate['modified_at'] ?? 0);
        $isImageCertificate = ! empty($certificate['is_image']);
        $certificateTitle = trim((string) ($certificate['title'] ?? ''));
        $certificateLabel = trim((string) ($certificate['label'] ?? ''));
        $certificateUrl = (string) ($certificate['url'] ?? '');
        $certificatePreviewUrl = (string) ($certificate['preview_url'] ?? '');

        return [
            'uploaded_label' => $uploadedAt > 0 ? date('d.m.Y', $uploadedAt) : '',
            'is_image_certificate' => $isImageCertificate,
            'certificate_type' => $isImageCertificate ? 'Obrázkový certifikát' : 'PDF certifikát',
            'certificate_title' => $certificateTitle !== '' ? $certificateTitle : 'Certifikát',
            'certificate_label' => $certificateLabel,
            'certificate_url' => $certificateUrl,
            'certificate_preview_url' => $certificatePreviewUrl,
        ];
    }
}
