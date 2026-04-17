<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use mysqli;
use PPStudio\Service\CertificateService;
use PPStudio\Service\MediaService;

final class AdminMediaDataLoader
{
    public function __construct(
        private MediaService $mediaService,
        private CertificateService $certificateService,
        private string $uploadsDirectory
    ) {
    }

    /**
     * @return array{
     *   profile_media: array<int, array<string, mixed>>,
     *   gallery_media: array<int, array<string, mixed>>,
     *   certificate_files: array<int, array<string, mixed>>
     * }
     */
    public function load(mysqli $connection): array
    {
        return [
            'profile_media' => $this->mediaService->loadByCategory($connection, 'profile', 1),
            'gallery_media' => $this->mediaService->loadByCategory($connection, 'gallery', 30),
            'certificate_files' => $this->certificateService->loadUploads($this->uploadsDirectory, '/uploads', 'cert_'),
        ];
    }
}
