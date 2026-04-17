<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use PPStudio\Infrastructure\Storage\UploadStorage;

final class MediaFacade
{
    private ?UploadStorage $storage = null;

    private ?UploadValidationService $uploadValidationService = null;

    private ?CertificatePreviewService $certificatePreviewService = null;

    private ?CertificateMetadataService $certificateMetadataService = null;

    private ?CertificateService $certificateService = null;

    private ?ImageUploadService $imageUploadService = null;

    private ?MediaService $mediaService = null;

    public function describeUploadError(int $errorCode): string
    {
        return $this->imageUploadService()->describeUploadError($errorCode);
    }

    public function loadMediaByCategory(mysqli $connection, string $category, int $limit = 50): array
    {
        return $this->mediaService()->loadByCategory($connection, $category, $limit);
    }

    public function storeUploadedImage(array $file, string $targetDir, ?string &$errorMessage = null): ?string
    {
        return $this->imageUploadService()->storeImage($file, $targetDir, $errorMessage);
    }

    public function certificatePreviewFilenameFromOriginal(string $certificateFileName): ?string
    {
        return $this->certificateService()->previewFilenameFromOriginal($certificateFileName);
    }

    public function certificateMetadataPath(string $directoryPath): string
    {
        return $this->certificateMetadataService()->metadataPath($directoryPath);
    }

    public function loadCertificateMetadata(string $directoryPath): array
    {
        return $this->certificateMetadataService()->load($directoryPath);
    }

    public function saveCertificateMetadata(string $directoryPath, array $metadata): bool
    {
        return $this->certificateMetadataService()->save($directoryPath, $metadata);
    }

    public function setCertificateMetadataTitle(string $directoryPath, string $fileName, string $title): bool
    {
        return $this->certificateMetadataService()->setTitle($directoryPath, $fileName, $title);
    }

    public function removeCertificateMetadata(string $directoryPath, string $fileName): void
    {
        $this->certificateMetadataService()->remove($directoryPath, $fileName);
    }

    public function createCertificatePreview(string $sourcePath, string $targetDir, string $certificateFileName): ?string
    {
        return $this->certificatePreviewService()->createPreview($sourcePath, $targetDir, $certificateFileName);
    }

    public function storeUploadedCertificateFile(array $file, string $targetDir, ?string &$errorMessage = null): ?string
    {
        return $this->imageUploadService()->storeCertificateFile($file, $targetDir, $errorMessage);
    }

    public function loadCertificateUploads(
        string $directoryPath,
        string $publicBasePath = '/uploads',
        string $filePrefix = 'cert_'
    ): array {
        return $this->certificateService()->loadUploads($directoryPath, $publicBasePath, $filePrefix);
    }

    private function storage(): UploadStorage
    {
        if (! $this->storage instanceof UploadStorage) {
            $this->storage = new UploadStorage();
        }

        return $this->storage;
    }

    private function uploadValidationService(): UploadValidationService
    {
        if (! $this->uploadValidationService instanceof UploadValidationService) {
            $this->uploadValidationService = new UploadValidationService($this->storage());
        }

        return $this->uploadValidationService;
    }

    private function certificatePreviewService(): CertificatePreviewService
    {
        if (! $this->certificatePreviewService instanceof CertificatePreviewService) {
            $this->certificatePreviewService = new CertificatePreviewService($this->storage());
        }

        return $this->certificatePreviewService;
    }

    private function certificateMetadataService(): CertificateMetadataService
    {
        if (! $this->certificateMetadataService instanceof CertificateMetadataService) {
            $this->certificateMetadataService = new CertificateMetadataService($this->storage());
        }

        return $this->certificateMetadataService;
    }

    private function certificateService(): CertificateService
    {
        if (! $this->certificateService instanceof CertificateService) {
            $this->certificateService = new CertificateService(
                $this->storage(),
                $this->certificateMetadataService(),
                $this->certificatePreviewService()
            );
        }

        return $this->certificateService;
    }

    private function imageUploadService(): ImageUploadService
    {
        if (! $this->imageUploadService instanceof ImageUploadService) {
            $this->imageUploadService = new ImageUploadService(
                $this->storage(),
                $this->uploadValidationService(),
                $this->certificatePreviewService()
            );
        }

        return $this->imageUploadService;
    }

    private function mediaService(): MediaService
    {
        if (! $this->mediaService instanceof MediaService) {
            $this->mediaService = new MediaService();
        }

        return $this->mediaService;
    }
}
