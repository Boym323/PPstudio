<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Infrastructure\Storage\UploadStorage;

final class CertificateService
{
    public function __construct(
        private UploadStorage $storage,
        private CertificateMetadataService $metadataService,
        private CertificatePreviewService $previewService
    ) {
    }

    public function previewFilenameFromOriginal(string $certificateFileName): ?string
    {
        return $this->previewService->previewFilenameFromOriginal($certificateFileName);
    }

    public function loadUploads(
        string $directoryPath,
        string $publicBasePath = '/uploads',
        string $filePrefix = 'cert_'
    ): array {
        $items = [];

        if (! is_dir($directoryPath)) {
            return $items;
        }

        $metadata = $this->metadataService->load($directoryPath);

        foreach ($this->storage->listDirectory($directoryPath) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if ($filePrefix !== '' && ! str_starts_with($entry, $filePrefix)) {
                continue;
            }

            if (str_starts_with($entry, 'cert_preview_')) {
                continue;
            }

            $fullPath = rtrim($directoryPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $entry;
            if (! is_file($fullPath)) {
                continue;
            }

            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
            $isDoc = $ext === 'pdf';
            if (! $isImage && ! $isDoc) {
                continue;
            }

            $previewUrl = '';
            if ($isImage) {
                $previewFileName = $this->previewFilenameFromOriginal($entry);
                if ($previewFileName !== null) {
                    $previewPath = rtrim($directoryPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $previewFileName;
                    if (is_file($previewPath)) {
                        $previewUrl = rtrim($publicBasePath, '/') . '/' . rawurlencode($previewFileName);
                    }
                }
            }

            $items[] = [
                'name' => $entry,
                'label' => pathinfo($entry, PATHINFO_FILENAME),
                'title' => (string) ($metadata[$entry] ?? ''),
                'url' => rtrim($publicBasePath, '/') . '/' . rawurlencode($entry),
                'preview_url' => $previewUrl,
                'is_image' => $isImage,
                'modified_at' => @filemtime($fullPath) ?: 0,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return ($b['modified_at'] ?? 0) <=> ($a['modified_at'] ?? 0);
        });

        return $items;
    }
}
