<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_media'])) {
    $category = trim((string) ($_POST['category'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
    $externalUrl = trim((string) ($_POST['external_url'] ?? ''));
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);

    if (! in_array($category, ['profile', 'gallery'], true)) {
        $error = 'Neplatná kategorie obrázku.';
    } else {
        $uploadError = null;
        $path = storeUploadedImage($_FILES['image'] ?? [], dirname(__DIR__, 4) . '/uploads', $uploadError);

        if ($path === null) {
            $error = $uploadError !== null && $uploadError !== ''
                ? 'Obrázek se nepodařilo nahrát. ' . $uploadError
                : 'Obrázek se nepodařilo nahrát.';
            $mediaFeedback = $error;
            $mediaFeedbackType = 'error';
        } else {
            if ($category === 'profile') {
                $connection->query("DELETE FROM media WHERE category = 'profile'");
            }

            $statement = $connection->prepare(
                'INSERT INTO media (category, image_path, title, subtitle, external_url, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            if ($statement) {
                $statement->bind_param('sssssi', $category, $path, $title, $subtitle, $externalUrl, $sortOrder);
                if ($statement->execute()) {
                    $message = 'Obrázek byl uložen.';
                    $mediaFeedback = $message;
                    $mediaFeedbackType = 'success';
                } else {
                    $error = 'Obrázek se nepodařilo uložit.';
                    $mediaFeedback = $error;
                    $mediaFeedbackType = 'error';
                }
                $statement->close();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_media'])) {
    $mediaId = (int) ($_POST['media_id'] ?? 0);
    if ($mediaId > 0) {
        $statement = $connection->prepare('SELECT image_path FROM media WHERE id = ? LIMIT 1');
        if ($statement) {
            $statement->bind_param('i', $mediaId);
            $statement->execute();
            $statement->bind_result($imagePath);
            $existingPath = null;
            if ($statement->fetch()) {
                $existingPath = (string) $imagePath;
            }
            $statement->close();

            $deleteStatement = $connection->prepare('DELETE FROM media WHERE id = ? LIMIT 1');
            if ($deleteStatement) {
                $deleteStatement->bind_param('i', $mediaId);
                if ($deleteStatement->execute()) {
                    if ($existingPath !== null) {
                        $fullPath = dirname(__DIR__, 4) . '/' . ltrim($existingPath, '/');
                        if (is_file($fullPath)) {
                            @unlink($fullPath);
                        }
                    }
                    $message = 'Obrázek byl odstraněn.';
                } else {
                    $error = 'Obrázek se nepodařilo odstranit.';
                }
                $deleteStatement->close();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_certificate_file'])) {
    $projectRoot = dirname(__DIR__, 4);
    $certificateTitle = trim((string) ($_POST['certificate_title'] ?? ''));
    $uploadError = null;
    $path = storeUploadedCertificateFile(
        $_FILES['certificate_file'] ?? [],
        $projectRoot . '/uploads',
        $uploadError
    );

    if ($path === null) {
        $error = $uploadError !== null && $uploadError !== ''
            ? 'Certifikát se nepodařilo nahrát. ' . $uploadError
            : 'Certifikát se nepodařilo nahrát.';
        $mediaFeedback = $error;
        $mediaFeedbackType = 'error';
    } else {
        $uploadedName = basename((string) $path);
        if ($certificateTitle !== '' && preg_match('/^cert_[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif|pdf)$/i', $uploadedName)) {
            setCertificateMetadataTitle($projectRoot . '/uploads', $uploadedName, $certificateTitle);
        }
        $message = 'Certifikát byl nahrán.';
        $mediaFeedback = $message;
        $mediaFeedbackType = 'success';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_certificate_title'])) {
    $projectRoot = dirname(__DIR__, 4);
    $fileName = basename((string) ($_POST['certificate_name'] ?? ''));
    $title = trim((string) ($_POST['certificate_title'] ?? ''));

    if ($fileName === '' || ! preg_match('/^cert_[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif|pdf)$/i', $fileName)) {
        $mediaFeedback = 'Neplatný certifikát.';
        $mediaFeedbackType = 'error';
    } elseif ($title === '') {
        $mediaFeedback = 'Vyplňte název certifikátu.';
        $mediaFeedbackType = 'error';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($title) : strlen($title)) > 120) {
        $mediaFeedback = 'Název certifikátu je příliš dlouhý (max. 120 znaků).';
        $mediaFeedbackType = 'error';
    } elseif (! setCertificateMetadataTitle($projectRoot . '/uploads', $fileName, $title)) {
        $mediaFeedback = 'Název certifikátu se nepodařilo uložit.';
        $mediaFeedbackType = 'error';
    } else {
        $mediaFeedback = 'Název certifikátu byl uložen.';
        $mediaFeedbackType = 'success';
        $message = $mediaFeedback;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_certificate_file'])) {
    $projectRoot = dirname(__DIR__, 4);
    $fileName = basename((string) ($_POST['certificate_name'] ?? ''));
    if ($fileName === '' || ! preg_match('/^cert_[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif|pdf)$/i', $fileName)) {
        $mediaFeedback = 'Neplatný certifikát pro smazání.';
        $mediaFeedbackType = 'error';
    } else {
        $fullPath = $projectRoot . '/uploads/' . $fileName;
        if (is_file($fullPath) && @unlink($fullPath)) {
            removeCertificateMetadata($projectRoot . '/uploads', $fileName);
            $previewFileName = function_exists('certificatePreviewFilenameFromOriginal')
                ? certificatePreviewFilenameFromOriginal($fileName)
                : null;
            if (is_string($previewFileName) && $previewFileName !== '') {
                $previewPath = $projectRoot . '/uploads/' . $previewFileName;
                if (is_file($previewPath)) {
                    @unlink($previewPath);
                }
            }
            $mediaFeedback = 'Certifikát byl odstraněn.';
            $mediaFeedbackType = 'success';
            $message = $mediaFeedback;
        } else {
            $mediaFeedback = 'Certifikát se nepodařilo odstranit.';
            $mediaFeedbackType = 'error';
        }
    }
}
