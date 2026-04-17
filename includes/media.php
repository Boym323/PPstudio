<?php
declare(strict_types=1);

use PPStudio\Service\MediaFacade;

function ppstudioMediaFacade(): MediaFacade
{
    static $facade = null;

    if (! $facade instanceof MediaFacade) {
        $facade = new MediaFacade();
    }

    return $facade;
}

function describeUploadError(int $errorCode): string
{
    return ppstudioMediaFacade()->describeUploadError($errorCode);
}

function loadMediaByCategory(mysqli $connection, string $category, int $limit = 50): array
{
    return ppstudioMediaFacade()->loadMediaByCategory($connection, $category, $limit);
}

function storeUploadedImage(array $file, string $targetDir, ?string &$errorMessage = null): ?string
{
    return ppstudioMediaFacade()->storeUploadedImage($file, $targetDir, $errorMessage);
}

function certificatePreviewFilenameFromOriginal(string $certificateFileName): ?string
{
    return ppstudioMediaFacade()->certificatePreviewFilenameFromOriginal($certificateFileName);
}

function certificateMetadataPath(string $directoryPath): string
{
    return ppstudioMediaFacade()->certificateMetadataPath($directoryPath);
}

function loadCertificateMetadata(string $directoryPath): array
{
    return ppstudioMediaFacade()->loadCertificateMetadata($directoryPath);
}

function saveCertificateMetadata(string $directoryPath, array $metadata): bool
{
    return ppstudioMediaFacade()->saveCertificateMetadata($directoryPath, $metadata);
}

function setCertificateMetadataTitle(string $directoryPath, string $fileName, string $title): bool
{
    return ppstudioMediaFacade()->setCertificateMetadataTitle($directoryPath, $fileName, $title);
}

function removeCertificateMetadata(string $directoryPath, string $fileName): void
{
    ppstudioMediaFacade()->removeCertificateMetadata($directoryPath, $fileName);
}

function createCertificatePreview(string $sourcePath, string $targetDir, string $certificateFileName): ?string
{
    return ppstudioMediaFacade()->createCertificatePreview($sourcePath, $targetDir, $certificateFileName);
}

function storeUploadedCertificateFile(array $file, string $targetDir, ?string &$errorMessage = null): ?string
{
    return ppstudioMediaFacade()->storeUploadedCertificateFile($file, $targetDir, $errorMessage);
}

function loadCertificateUploads(string $directoryPath, string $publicBasePath = '/uploads', string $filePrefix = 'cert_'): array
{
    return ppstudioMediaFacade()->loadCertificateUploads($directoryPath, $publicBasePath, $filePrefix);
}
