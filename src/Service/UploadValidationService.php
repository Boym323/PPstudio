<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Infrastructure\Storage\UploadStorage;

final class UploadValidationService
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
        private UploadStorage $storage
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

    public function validateImage(array $file, ?string &$errorMessage = null): ?UploadValidationResult
    {
        $result = $this->validateUploadedFile($file, self::IMAGE_ALLOWED_MIME_BY_EXTENSION, $errorMessage);
        if (! $result instanceof UploadValidationResult) {
            return null;
        }

        if (@getimagesize($result->tmpName) === false) {
            $errorMessage = 'Nahraný soubor není platný obrázek ve zvoleném formátu.';
            return null;
        }

        return $result;
    }

    public function validateCertificateFile(array $file, ?string &$errorMessage = null): ?UploadValidationResult
    {
        $result = $this->validateUploadedFile($file, self::CERTIFICATE_ALLOWED_MIME_BY_EXTENSION, $errorMessage);
        if (! $result instanceof UploadValidationResult) {
            return null;
        }

        if ($result->extension === 'pdf') {
            return $result;
        }

        if (@getimagesize($result->tmpName) === false) {
            $errorMessage = 'Nahraný soubor není platný obrázek ve zvoleném formátu.';
            return null;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, list<string>> $allowedMimeByExtension
     */
    private function validateUploadedFile(
        array $file,
        array $allowedMimeByExtension,
        ?string &$errorMessage = null
    ): ?UploadValidationResult {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            $errorMessage = $this->describeUploadError($errorCode);
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? 'soubor');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($tmpName === '' || ! $this->storage->isUploadedFile($tmpName)) {
            $errorMessage = 'Server neobdržel platný nahraný soubor.';
            return null;
        }

        if (! isset($allowedMimeByExtension[$extension])) {
            $errorMessage = array_key_exists('pdf', $allowedMimeByExtension)
                ? 'Podporované jsou formáty JPG, JPEG, PNG, WEBP, GIF a PDF.'
                : 'Podporované jsou jen formáty JPG, PNG, WEBP a GIF.';
            return null;
        }

        $detectedMime = strtolower($this->detectMime($tmpName));
        $allowedMimes = array_map('strtolower', $allowedMimeByExtension[$extension]);
        $isValidMime = in_array($detectedMime, $allowedMimes, true);

        if ($extension === 'pdf' && ! $isValidMime) {
            $isValidMime = in_array($detectedMime, ['application/octet-stream', 'binary/octet-stream'], true);
        }

        if (! $isValidMime) {
            $errorMessage = $extension === 'pdf'
                ? 'Nahraný soubor není platné PDF.'
                : 'Nahraný soubor není platný obrázek ve zvoleném formátu.';
            return null;
        }

        return new UploadValidationResult($tmpName, $originalName, $extension, $detectedMime);
    }

    private function detectMime(string $tmpName): string
    {
        if (function_exists('mime_content_type')) {
            $detectedMime = mime_content_type($tmpName);
            if (is_string($detectedMime) && $detectedMime !== '') {
                return $detectedMime;
            }
        }

        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo->file($tmpName);
            if (is_string($detectedMime) && $detectedMime !== '') {
                return $detectedMime;
            }
        }

        return '';
    }
}
