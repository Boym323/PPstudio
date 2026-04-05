<?php
declare(strict_types=1);

function describeUploadError(int $errorCode): string
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

function loadMediaByCategory(mysqli $connection, string $category, int $limit = 50): array
{
    $statement = $connection->prepare(
        'SELECT id, category, image_path, title, subtitle, external_url, sort_order
         FROM media
         WHERE category = ?
         ORDER BY sort_order ASC, id DESC
         LIMIT ?'
    );

    if (! $statement) {
        return [];
    }

    $statement->bind_param('si', $category, $limit);
    $statement->execute();
    $items = [];

    $statement->bind_result($id, $rowCategory, $imagePath, $title, $subtitle, $externalUrl, $sortOrder);

    while ($statement->fetch()) {
        $items[] = [
            'id' => $id,
            'category' => $rowCategory,
            'image_path' => $imagePath,
            'title' => $title,
            'subtitle' => $subtitle,
            'external_url' => $externalUrl,
            'sort_order' => $sortOrder,
        ];
    }

    $statement->close();

    return $items;
}

function storeUploadedImage(array $file, string $targetDir, ?string &$errorMessage = null): ?string
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode !== UPLOAD_ERR_OK) {
        $errorMessage = describeUploadError($errorCode);
        return null;
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $originalName = (string) ($file['name'] ?? 'image');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowedMimeByExt = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
    ];

    if ($tmpName === '' || ! is_uploaded_file($tmpName)) {
        $errorMessage = 'Server neobdržel platný nahraný soubor.';
        return null;
    }

    if (! in_array($extension, $allowed, true)) {
        $errorMessage = 'Podporované jsou jen formáty JPG, PNG, WEBP a GIF.';
        return null;
    }

    $detectedMime = (string) (mime_content_type($tmpName) ?: '');
    $isImage = @getimagesize($tmpName) !== false;

    if (! $isImage || ! in_array($detectedMime, $allowedMimeByExt[$extension] ?? [], true)) {
        $errorMessage = 'Nahraný soubor není platný obrázek ve zvoleném formátu.';
        return null;
    }

    $targetDir = rtrim($targetDir, DIRECTORY_SEPARATOR);

    if (! is_dir($targetDir)) {
        if (! mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
            $errorMessage = 'Nepodařilo se vytvořit složku pro nahrané soubory.';
            return null;
        }
    }

    $resolvedTargetDir = realpath($targetDir);
    if ($resolvedTargetDir === false) {
        $resolvedTargetDir = $targetDir;
    }

    if (! is_writable($resolvedTargetDir)) {
        @chmod($resolvedTargetDir, 0775);
    }

    if (! is_writable($resolvedTargetDir)) {
        $errorMessage = 'Složka pro nahrávání není zapisovatelná. Zkontrolujte práva adresáře uploads.';
        return null;
    }

    $filename = 'img_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = $resolvedTargetDir . DIRECTORY_SEPARATOR . $filename;

    if (! move_uploaded_file($tmpName, $destination)) {
        $errorMessage = 'Soubor se nepodařilo přesunout do složky uploads.';
        return null;
    }

    @chmod($destination, 0664);

    return 'uploads/' . $filename;
}

function certificatePreviewFilenameFromOriginal(string $certificateFileName): ?string
{
    if (! preg_match('/^cert_([a-f0-9]{32})\.(jpg|jpeg|png|webp|gif)$/i', $certificateFileName, $matches)) {
        return null;
    }

    return 'cert_preview_' . strtolower((string) ($matches[1] ?? '')) . '.webp';
}

function certificateMetadataPath(string $directoryPath): string
{
    return rtrim($directoryPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'certificate-meta.json';
}

function loadCertificateMetadata(string $directoryPath): array
{
    $path = certificateMetadataPath($directoryPath);
    if (! is_file($path) || ! is_readable($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    if (! is_string($raw) || trim($raw) === '') {
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

function saveCertificateMetadata(string $directoryPath, array $metadata): bool
{
    $path = certificateMetadataPath($directoryPath);
    $payload = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (! is_string($payload)) {
        return false;
    }

    return @file_put_contents($path, $payload, LOCK_EX) !== false;
}

function setCertificateMetadataTitle(string $directoryPath, string $fileName, string $title): bool
{
    $fileName = trim($fileName);
    if (! preg_match('/^cert_[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif|pdf)$/i', $fileName)) {
        return false;
    }

    $title = trim($title);
    if ($title === '') {
        return false;
    }

    $metadata = loadCertificateMetadata($directoryPath);
    $metadata[$fileName] = $title;

    return saveCertificateMetadata($directoryPath, $metadata);
}

function removeCertificateMetadata(string $directoryPath, string $fileName): void
{
    $fileName = trim($fileName);
    if ($fileName === '') {
        return;
    }

    $metadata = loadCertificateMetadata($directoryPath);
    if (! isset($metadata[$fileName])) {
        return;
    }

    unset($metadata[$fileName]);
    saveCertificateMetadata($directoryPath, $metadata);
}

function createCertificatePreview(string $sourcePath, string $targetDir, string $certificateFileName): ?string
{
    if (! function_exists('getimagesize') || ! function_exists('imagecreatetruecolor') || ! function_exists('imagecopyresampled')) {
        return null;
    }

    if (! function_exists('imagewebp')) {
        return null;
    }

    $previewFileName = certificatePreviewFilenameFromOriginal($certificateFileName);
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

    $maxWidth = 960;
    $maxHeight = 960;
    $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
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

    $targetDir = rtrim($targetDir, DIRECTORY_SEPARATOR);
    if (! is_dir($targetDir)) {
        @mkdir($targetDir, 0775, true);
    }

    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $previewFileName;
    $saved = @imagewebp($previewImage, $targetPath, 82);
    imagedestroy($previewImage);

    if (! $saved) {
        return null;
    }

    @chmod($targetPath, 0664);

    return 'uploads/' . $previewFileName;
}

function storeUploadedCertificateFile(array $file, string $targetDir, ?string &$errorMessage = null): ?string
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode !== UPLOAD_ERR_OK) {
        $errorMessage = describeUploadError($errorCode);
        return null;
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $originalName = (string) ($file['name'] ?? 'certifikat');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $allowedMimeByExt = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'pdf' => ['application/pdf', 'application/x-pdf', 'application/acrobat'],
    ];

    if ($tmpName === '' || ! is_uploaded_file($tmpName)) {
        $errorMessage = 'Server neobdržel platný nahraný soubor.';
        return null;
    }

    if (! isset($allowedMimeByExt[$extension])) {
        $errorMessage = 'Podporované jsou formáty JPG, JPEG, PNG, WEBP, GIF a PDF.';
        return null;
    }

    $detectedMime = strtolower((string) (mime_content_type($tmpName) ?: ''));
    $allowedMimes = array_map('strtolower', $allowedMimeByExt[$extension]);
    $isValidMime = in_array($detectedMime, $allowedMimes, true);

    if ($extension === 'pdf' && ! $isValidMime) {
        // Některé servery vrací pro PDF generic MIME.
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

    $targetDir = rtrim($targetDir, DIRECTORY_SEPARATOR);

    if (! is_dir($targetDir)) {
        if (! mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
            $errorMessage = 'Nepodařilo se vytvořit složku pro certifikáty.';
            return null;
        }
    }

    $resolvedTargetDir = realpath($targetDir);
    if ($resolvedTargetDir === false) {
        $resolvedTargetDir = $targetDir;
    }

    // Some environments report false negatives for is_writable() due to ACL.
    // Try chmod best-effort, then attempt move directly and handle the real result.
    if (! is_writable($resolvedTargetDir)) {
        @chmod($resolvedTargetDir, 0775);
    }

    $filename = 'cert_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = $resolvedTargetDir . DIRECTORY_SEPARATOR . $filename;

    if (! move_uploaded_file($tmpName, $destination)) {
        $lastError = error_get_last();
        $lastErrorMessage = is_array($lastError) ? trim((string) ($lastError['message'] ?? '')) : '';
        $errorMessage = 'Soubor se nepodařilo uložit do uploads.'
            . ($lastErrorMessage !== '' ? ' ' . $lastErrorMessage : '');
        if (is_dir($resolvedTargetDir)) {
            $errorMessage .= ' Cílová složka: ' . $resolvedTargetDir . '.';
        }
        return null;
    }

    @chmod($destination, 0664);

    if ($extension !== 'pdf') {
        createCertificatePreview($destination, $resolvedTargetDir, $filename);
    }

    return 'uploads/' . $filename;
}

function loadCertificateUploads(string $directoryPath, string $publicBasePath = '/uploads', string $filePrefix = 'cert_'): array
{
    $allowedImageExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowedDocExt = ['pdf'];
    $items = [];

    if (! is_dir($directoryPath)) {
        return $items;
    }

    $metadata = loadCertificateMetadata($directoryPath);

    $entries = scandir($directoryPath);
    if (! is_array($entries)) {
        return $items;
    }

    foreach ($entries as $entry) {
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
        $isImage = in_array($ext, $allowedImageExt, true);
        $isDoc = in_array($ext, $allowedDocExt, true);
        if (! $isImage && ! $isDoc) {
            continue;
        }

        $previewUrl = '';
        if ($isImage) {
            $previewFileName = certificatePreviewFilenameFromOriginal($entry);
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
