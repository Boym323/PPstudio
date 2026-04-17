<?php
declare(strict_types=1);

use PPStudio\Infrastructure\Storage\UploadStorage;
use PPStudio\Service\CertificateMetadataService;
use PPStudio\Service\CertificatePreviewService;
use PPStudio\Service\CertificateService;
use PPStudio\Service\ImageUploadService;
use PPStudio\Service\MediaService;
use PPStudio\Service\UploadValidationService;

function describeUploadError(int $errorCode): string
{
    $storage = new UploadStorage();
    $uploadService = new ImageUploadService(
        $storage,
        new UploadValidationService($storage),
        new CertificatePreviewService($storage)
    );

    return $uploadService->describeUploadError($errorCode);
}

function loadMediaByCategory(mysqli $connection, string $category, int $limit = 50): array
{
    return (new MediaService())->loadByCategory($connection, $category, $limit);
}

function storeUploadedImage(array $file, string $targetDir, ?string &$errorMessage = null): ?string
{
    $storage = new UploadStorage();
    $uploadService = new ImageUploadService(
        $storage,
        new UploadValidationService($storage),
        new CertificatePreviewService($storage)
    );

    return $uploadService->storeImage($file, $targetDir, $errorMessage);
}

function certificatePreviewFilenameFromOriginal(string $certificateFileName): ?string
{
    $storage = new UploadStorage();
    $previewService = new CertificatePreviewService($storage);
    $certificateService = new CertificateService($storage, new CertificateMetadataService($storage), $previewService);

    return $certificateService->previewFilenameFromOriginal($certificateFileName);
}

function certificateMetadataPath(string $directoryPath): string
{
    return (new CertificateMetadataService(new UploadStorage()))->metadataPath($directoryPath);
}

function loadCertificateMetadata(string $directoryPath): array
{
    return (new CertificateMetadataService(new UploadStorage()))->load($directoryPath);
}

function saveCertificateMetadata(string $directoryPath, array $metadata): bool
{
    return (new CertificateMetadataService(new UploadStorage()))->save($directoryPath, $metadata);
}

function setCertificateMetadataTitle(string $directoryPath, string $fileName, string $title): bool
{
    return (new CertificateMetadataService(new UploadStorage()))->setTitle($directoryPath, $fileName, $title);
}

function removeCertificateMetadata(string $directoryPath, string $fileName): void
{
    (new CertificateMetadataService(new UploadStorage()))->remove($directoryPath, $fileName);
}

function createCertificatePreview(string $sourcePath, string $targetDir, string $certificateFileName): ?string
{
    return (new CertificatePreviewService(new UploadStorage()))->createPreview($sourcePath, $targetDir, $certificateFileName);
}

function storeUploadedCertificateFile(array $file, string $targetDir, ?string &$errorMessage = null): ?string
{
    $storage = new UploadStorage();
    $uploadService = new ImageUploadService(
        $storage,
        new UploadValidationService($storage),
        new CertificatePreviewService($storage)
    );

    return $uploadService->storeCertificateFile($file, $targetDir, $errorMessage);
}

function loadCertificateUploads(string $directoryPath, string $publicBasePath = '/uploads', string $filePrefix = 'cert_'): array
{
    $storage = new UploadStorage();
    $previewService = new CertificatePreviewService($storage);

    return (new CertificateService(
        $storage,
        new CertificateMetadataService($storage),
        $previewService
    ))->loadUploads($directoryPath, $publicBasePath, $filePrefix);
}
