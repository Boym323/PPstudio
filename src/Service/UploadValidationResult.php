<?php
declare(strict_types=1);

namespace PPStudio\Service;

final class UploadValidationResult
{
    public function __construct(
        public readonly string $tmpName,
        public readonly string $originalName,
        public readonly string $extension,
        public readonly string $detectedMime
    ) {
    }
}
