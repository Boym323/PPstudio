<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Infrastructure\Storage\UploadStorage;

final class ImageUploadService
{
    /**
     * @var array<string, list<string>>
     */
    private const IMAGE_ALLOWED_MIME_BY_EXTENSION = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const CERTIFICATE_ALLOWED_MIME_BY_EXTENSION = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'pdf' => ['application/pdf', 'application/x-pdf', 'application/acrobat'],
    ];

    public function __construct(
        private UploadStorage $storage,
        private CertificatePreviewService $certificatePreviewService
    ) {
    }

    public function describeUploadError(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_OK => '',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Soubor je příliš velký.',
            UPLOAD_ERR_PARTIAL => 'Soubor se nahrál jen částečně. Zkuste to prosím znovu.',
            UPLOAD_ERR_NO_FILE => 'Nebyl vybrán žádný soubor.',
            UPLOAD_ERR_NO_TMP_DIR => 'Na serveru chybí dočasná složka pro upload.',
            UPLOAD_ERR_CANT_WRITE => 'Server nemůže zapsat nahraný soubor na disk.',
            UPLOAD_ERR_EXTENSION => 'Upload byl zastaven rozšířením PHP na serveru.',
            default => 'Nahrávání souboru se nezdařilo.',
        };
    }

    public function storeImage(array $file, string $targetDir, ?string &$errorMessage = null): ?string
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            $errorMessage = $this->describeUploadError($errorCode);
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? 'image');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($tmpName === '' || ! $this->storage->isUploadedFile($tmpName)) {
            $errorMessage = 'Server neobdržel platný nahraný soubor.';
            return null;
        }

        if (! isset(self::IMAGE_ALLOWED_MIME_BY_EXTENSION[$extension])) {
            $errorMessage = 'Podporované jsou jen formáty JPG, PNG, WEBP a GIF.';
            return null;
        }

        $detectedMime = (string) (mime_content_type($tmpName) ?: '');
        $isImage = @getimagesize($tmpName) !== false;
        if (! $isImage || ! in_array($detectedMime, self::IMAGE_ALLOWED_MIME_BY_EXTENSION[$extension], true)) {
            $errorMessage = 'Nahraný soubor není platný obrázek ve zvoleném formátu.';
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

        $filename = 'img_' . bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $resolvedTargetDir . DIRECTORY_SEPARATOR . $filename;

        if (! $this->storage->moveUploadedFile($tmpName, $destination)) {
            $errorMessage = 'Soubor se nepodařilo přesunout do složky uploads.';
            return null;
        }

        $this->storage->setPermissions($destination, 0664);

        return $this->storage->publicPath($filename);
    }

    public function storeCertificateFile(array $file, string $targetDir, ?string &$errorMessage = null): ?string
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            $errorMessage = $this->describeUploadError($errorCode);
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? 'certifikat');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($tmpName === '' || ! $this->storage->isUploadedFile($tmpName)) {
            $errorMessage = 'Server neobdržel platný nahraný soubor.';
            return null;
        }

        if (! isset(self::CERTIFICATE_ALLOWED_MIME_BY_EXTENSION[$extension])) {
            $errorMessage = 'Podporované jsou formáty JPG, JPEG, PNG, WEBP, GIF a PDF.';
            return null;
        }

        $detectedMime = strtolower((string) (mime_content_type($tmpName) ?: ''));
        $allowedMimes = array_map('strtolower', self::CERTIFICATE_ALLOWED_MIME_BY_EXTENSION[$extension]);
        $isValidMime = in_array($detectedMime, $allowedMimes, true);

        if ($extension === 'pdf' && ! $isValidMime) {
            $isValidMime = in_array($detectedMime, ['application/octet-stream', 'binary/octet-stream'], true);
        }

        if ($extension !== 'pdf') {
            $isImage = @getimagesize($tmpName) !== false;
            if (! $isImage || ! $isValidMime) {
                $errorMessage = 'Nahraný soubor není platný obrázek ve zvoleném formátu.';
                return null;
            }
        } elseif (! $isValidMime) {
            $errorMessage = 'Nahraný soubor není platné PDF.';
            return null;
        }

        $resolvedTargetDir = $this->storage->prepareDirectoryForMove(
            $targetDir,
            'Nepodařilo se vytvořit složku pro certifikáty.'
        );

        if ($resolvedTargetDir === null) {
            $errorMessage = 'Nepodařilo se vytvořit složku pro certifikáty.';
            return null;
        }

        $filename = 'cert_' . bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $resolvedTargetDir . DIRECTORY_SEPARATOR . $filename;

        if (! $this->storage->moveUploadedFile($tmpName, $destination)) {
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

        if ($extension !== 'pdf') {
            $this->certificatePreviewService->createPreview($destination, $resolvedTargetDir, $filename);
        }

        return $this->storage->publicPath($filename);
    }
}
