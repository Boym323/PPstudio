<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Infrastructure\Storage\UploadStorage;

final class ImageUploadService
{
    public function __construct(
        private UploadStorage $storage,
        private UploadValidationService $uploadValidationService,
        private CertificatePreviewService $certificatePreviewService
    ) {
    }

    public function describeUploadError(int $errorCode): string
    {
        return $this->uploadValidationService->describeUploadError($errorCode);
    }

    public function storeImage(array $file, string $targetDir, ?string &$errorMessage = null): ?string
    {
        $validatedUpload = $this->uploadValidationService->validateImage($file, $errorMessage);
        if (! $validatedUpload instanceof UploadValidationResult) {
            return null;
        }

        $resolvedTargetDir = $this->storage->ensureWritableDirectory(
            $targetDir,
            'Nepodařilo se vytvořit složku pro nahrané soubory.',
            'Složka pro nahrávání není zapisovatelná. Zkontrolujte práva adresáře uploads.'
        );

        if ($resolvedTargetDir === null) {
            $errorMessage = ! is_dir(rtrim($targetDir, DIRECTORY_SEPARATOR))
                ? 'Nepodařilo se vytvořit složku pro nahrané soubory.'
                : 'Složka pro nahrávání není zapisovatelná. Zkontrolujte práva adresáře uploads.';
            return null;
        }

        $filename = 'img_' . bin2hex(random_bytes(16)) . '.' . $validatedUpload->extension;
        $destination = $resolvedTargetDir . DIRECTORY_SEPARATOR . $filename;

        if (! $this->storage->moveUploadedFile($validatedUpload->tmpName, $destination)) {
            $errorMessage = 'Soubor se nepodařilo přesunout do složky uploads.';
            return null;
        }

        $this->storage->setPermissions($destination, 0664);

        return $this->storage->publicPath($filename);
    }

    public function storeCertificateFile(array $file, string $targetDir, ?string &$errorMessage = null): ?string
    {
        $validatedUpload = $this->uploadValidationService->validateCertificateFile($file, $errorMessage);
        if (! $validatedUpload instanceof UploadValidationResult) {
            return null;
        }

        $resolvedTargetDir = $this->storage->ensureWritableDirectory(
            $targetDir,
            'Nepodařilo se vytvořit složku pro certifikáty.',
            'Složka pro certifikáty není zapisovatelná. Zkontrolujte práva adresáře uploads.'
        );

        if ($resolvedTargetDir === null) {
            $errorMessage = ! is_dir(rtrim($targetDir, DIRECTORY_SEPARATOR))
                ? 'Nepodařilo se vytvořit složku pro certifikáty.'
                : 'Složka pro certifikáty není zapisovatelná. Zkontrolujte práva adresáře uploads.';
            return null;
        }

        $filename = 'cert_' . bin2hex(random_bytes(16)) . '.' . $validatedUpload->extension;
        $destination = $resolvedTargetDir . DIRECTORY_SEPARATOR . $filename;

        if (! $this->storage->moveUploadedFile($validatedUpload->tmpName, $destination)) {
            $lastError = error_get_last();
            $lastErrorMessage = is_array($lastError) ? trim((string) ($lastError['message'] ?? '')) : '';
            $errorMessage = 'Soubor se nepodařilo uložit do uploads.'
                . ($lastErrorMessage !== '' ? ' ' . $lastErrorMessage : '');

            if (is_dir($resolvedTargetDir)) {
                $errorMessage .= ' Cílová složka: ' . $resolvedTargetDir . '.';
            }

            return null;
        }

        $this->storage->setPermissions($destination, 0664);

        if ($validatedUpload->extension !== 'pdf') {
            $this->certificatePreviewService->createPreview($destination, $resolvedTargetDir, $filename);
        }

        return $this->storage->publicPath($filename);
    }
}
