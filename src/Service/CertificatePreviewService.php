<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Infrastructure\Storage\UploadStorage;

final class CertificatePreviewService
{
    public function __construct(
        private UploadStorage $storage
    ) {
    }

    public function previewFilenameFromOriginal(string $certificateFileName): ?string
    {
        if (! preg_match('/^cert_([a-f0-9]{32})\.(jpg|jpeg|png|webp|gif)$/i', $certificateFileName, $matches)) {
            return null;
        }

        return 'cert_preview_' . strtolower((string) ($matches[1] ?? '')) . '.webp';
    }

    public function createPreview(string $sourcePath, string $targetDir, string $certificateFileName): ?string
    {
        if (! function_exists('getimagesize') || ! function_exists('imagecreatetruecolor') || ! function_exists('imagecopyresampled')) {
            return null;
        }

        if (! function_exists('imagewebp')) {
            return null;
        }

        $previewFileName = $this->previewFilenameFromOriginal($certificateFileName);
        if ($previewFileName === null) {
            return null;
        }

        $imageInfo = @getimagesize($sourcePath);
        if (! is_array($imageInfo) || count($imageInfo) < 3) {
            return null;
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        $imageType = (int) ($imageInfo[2] ?? 0);
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $sourceImage = match ($imageType) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            IMAGETYPE_GIF => function_exists('imagecreatefromgif') ? @imagecreatefromgif($sourcePath) : false,
            default => false,
        };

        if (! is_resource($sourceImage) && ! is_object($sourceImage)) {
            return null;
        }

        $ratio = min(960 / $width, 960 / $height, 1);
        $previewWidth = max(1, (int) round($width * $ratio));
        $previewHeight = max(1, (int) round($height * $ratio));

        $previewImage = imagecreatetruecolor($previewWidth, $previewHeight);
        if (! is_resource($previewImage) && ! is_object($previewImage)) {
            imagedestroy($sourceImage);
            return null;
        }

        imagealphablending($previewImage, false);
        imagesavealpha($previewImage, true);
        $transparent = imagecolorallocatealpha($previewImage, 255, 255, 255, 127);
        imagefilledrectangle($previewImage, 0, 0, $previewWidth, $previewHeight, $transparent);

        imagecopyresampled($previewImage, $sourceImage, 0, 0, 0, 0, $previewWidth, $previewHeight, $width, $height);
        imagedestroy($sourceImage);

        $resolvedTargetDir = $this->storage->ensureDirectory($targetDir, 'Nepodařilo se vytvořit složku pro certifikáty.');
        if ($resolvedTargetDir === null) {
            imagedestroy($previewImage);
            return null;
        }

        $targetPath = $resolvedTargetDir . DIRECTORY_SEPARATOR . $previewFileName;
        $saved = @imagewebp($previewImage, $targetPath, 82);
        imagedestroy($previewImage);

        if (! $saved) {
            return null;
        }

        $this->storage->setPermissions($targetPath, 0664);

        return $this->storage->publicPath($previewFileName);
    }
}
