<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Infrastructure\Storage\UploadStorage;

final class CertificateMetadataService
{
    public function __construct(
        private UploadStorage $storage
    ) {
    }

    public function metadataPath(string $directoryPath): string
    {
        return rtrim($directoryPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'certificate-meta.json';
    }

    public function load(string $directoryPath): array
    {
        $raw = $this->storage->readFile($this->metadataPath($directoryPath));
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $result = [];

        foreach ($decoded as $fileName => $title) {
            $fileName = trim((string) $fileName);
            $title = trim((string) $title);

            if ($fileName === '' || $title === '') {
                continue;
            }

            if (! preg_match('/^cert_[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif|pdf)$/i', $fileName)) {
                continue;
            }

            $result[$fileName] = $title;
        }

        return $result;
    }

    public function save(string $directoryPath, array $metadata): bool
    {
        $payload = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (! is_string($payload)) {
            return false;
        }

        return $this->storage->writeFile($this->metadataPath($directoryPath), $payload);
    }

    public function setTitle(string $directoryPath, string $fileName, string $title): bool
    {
        $fileName = trim($fileName);
        if (! preg_match('/^cert_[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif|pdf)$/i', $fileName)) {
            return false;
        }

        $title = trim($title);
        if ($title === '') {
            return false;
        }

        $metadata = $this->load($directoryPath);
        $metadata[$fileName] = $title;

        return $this->save($directoryPath, $metadata);
    }

    public function remove(string $directoryPath, string $fileName): void
    {
        $fileName = trim($fileName);
        if ($fileName === '') {
            return;
        }

        $metadata = $this->load($directoryPath);
        if (! isset($metadata[$fileName])) {
            return;
        }

        unset($metadata[$fileName]);
        $this->save($directoryPath, $metadata);
    }
}
