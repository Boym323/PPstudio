<?php
declare(strict_types=1);

namespace PPStudio\Infrastructure\Storage;

final class UploadStorage
{
    public function ensureDirectory(string $targetDir, string $createErrorMessage): ?string
    {
        $targetDir = rtrim($targetDir, DIRECTORY_SEPARATOR);

        if (! is_dir($targetDir)) {
            if (! @mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
                return null;
            }
        }

        $resolvedTargetDir = realpath($targetDir);

        return is_string($resolvedTargetDir) && $resolvedTargetDir !== ''
            ? $resolvedTargetDir
            : $targetDir;
    }

    public function ensureWritableDirectory(
        string $targetDir,
        string $createErrorMessage,
        string $permissionErrorMessage
    ): ?string {
        $resolvedTargetDir = $this->ensureDirectory($targetDir, $createErrorMessage);
        if ($resolvedTargetDir === null) {
            return null;
        }

        if (! is_writable($resolvedTargetDir)) {
            @chmod($resolvedTargetDir, 0775);
        }

        return is_writable($resolvedTargetDir) ? $resolvedTargetDir : null;
    }

    public function prepareDirectoryForMove(string $targetDir, string $createErrorMessage): ?string
    {
        $resolvedTargetDir = $this->ensureDirectory($targetDir, $createErrorMessage);
        if ($resolvedTargetDir === null) {
            return null;
        }

        if (! is_writable($resolvedTargetDir)) {
            @chmod($resolvedTargetDir, 0775);
        }

        return $resolvedTargetDir;
    }

    public function moveUploadedFile(string $tmpName, string $destination): bool
    {
        return move_uploaded_file($tmpName, $destination);
    }

    public function isUploadedFile(string $path): bool
    {
        return is_uploaded_file($path);
    }

    public function readFile(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $raw = @file_get_contents($path);

        return is_string($raw) ? $raw : null;
    }

    public function writeFile(string $path, string $contents): bool
    {
        return @file_put_contents($path, $contents, LOCK_EX) !== false;
    }

    /**
     * @return list<string>
     */
    public function listDirectory(string $directoryPath): array
    {
        $entries = scandir($directoryPath);

        return is_array($entries) ? array_values($entries) : [];
    }

    public function deleteFile(string $path): bool
    {
        return is_file($path) ? @unlink($path) : false;
    }

    public function setPermissions(string $path, int $permissions): void
    {
        @chmod($path, $permissions);
    }

    public function publicPath(string $fileName): string
    {
        return 'uploads/' . ltrim($fileName, '/');
    }
}
