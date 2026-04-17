<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Infrastructure\Storage\UploadStorage;
use PPStudio\Repository\MediaRepository;

final class AdminMediaPostActionUseCase
{
    private const CERTIFICATE_FILE_PATTERN = '/^cert_[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif|pdf)$/i';

    public function __construct(
        private MediaRepository $mediaRepository,
        private ImageUploadService $imageUploadService,
        private CertificateMetadataService $certificateMetadataService,
        private CertificatePreviewService $certificatePreviewService,
        private UploadStorage $storage,
        private string $projectRoot
    ) {
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array{message:string,error:string,media_feedback:string,media_feedback_type:string} $state
     * @return array{message:string,error:string,media_feedback:string,media_feedback_type:string}
     */
    public function handle(array $post, array $files, array $state): array
    {
        if (isset($post['save_media'])) {
            $state = $this->saveMedia($post, $files, $state);
        }

        if (isset($post['delete_media'])) {
            $state = $this->deleteMedia($post, $state);
        }

        if (isset($post['save_certificate_file'])) {
            $state = $this->saveCertificateFile($post, $files, $state);
        }

        if (isset($post['save_certificate_title'])) {
            $state = $this->saveCertificateTitle($post, $state);
        }

        if (isset($post['delete_certificate_file'])) {
            $state = $this->deleteCertificateFile($post, $state);
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array{message:string,error:string,media_feedback:string,media_feedback_type:string} $state
     * @return array{message:string,error:string,media_feedback:string,media_feedback_type:string}
     */
    private function saveMedia(array $post, array $files, array $state): array
    {
        $category = trim((string) ($post['category'] ?? ''));
        $title = trim((string) ($post['title'] ?? ''));
        $subtitle = trim((string) ($post['subtitle'] ?? ''));
        $externalUrl = trim((string) ($post['external_url'] ?? ''));
        $sortOrder = (int) ($post['sort_order'] ?? 0);

        if (! in_array($category, ['profile', 'gallery'], true)) {
            $state['error'] = 'Neplatná kategorie obrázku.';
            return $state;
        }

        $uploadError = null;
        $path = $this->imageUploadService->storeImage(
            is_array($files['image'] ?? null) ? $files['image'] : [],
            $this->projectRoot . '/uploads',
            $uploadError
        );

        if ($path === null) {
            $state['error'] = $uploadError !== null && $uploadError !== ''
                ? 'Obrázek se nepodařilo nahrát. ' . $uploadError
                : 'Obrázek se nepodařilo nahrát.';
            $state['media_feedback'] = $state['error'];
            $state['media_feedback_type'] = 'error';
            return $state;
        }

        if ($category === 'profile') {
            $this->mediaRepository->deleteByCategory('profile');
        }

        $created = $this->mediaRepository->create($category, $path, $title, $subtitle, $externalUrl, $sortOrder);
        if ($created === false) {
            $state['error'] = 'Obrázek se nepodařilo uložit.';
            $state['media_feedback'] = $state['error'];
            $state['media_feedback_type'] = 'error';
            return $state;
        }
        if ($created === null) {
            return $state;
        }

        $state['message'] = 'Obrázek byl uložen.';
        $state['media_feedback'] = $state['message'];
        $state['media_feedback_type'] = 'success';

        return $state;
    }

    /**
     * @param array<string, mixed> $post
     * @param array{message:string,error:string,media_feedback:string,media_feedback_type:string} $state
     * @return array{message:string,error:string,media_feedback:string,media_feedback_type:string}
     */
    private function deleteMedia(array $post, array $state): array
    {
        $mediaId = (int) ($post['media_id'] ?? 0);
        if ($mediaId <= 0) {
            return $state;
        }

        $existingPath = $this->mediaRepository->findImagePathById($mediaId);
        $deleted = $this->mediaRepository->deleteById($mediaId);
        if ($deleted === false) {
            $state['error'] = 'Obrázek se nepodařilo odstranit.';
            return $state;
        }
        if ($deleted === null) {
            return $state;
        }

        if ($existingPath !== null) {
            $this->storage->deleteFile($this->projectRoot . '/' . ltrim($existingPath, '/'));
        }

        $state['message'] = 'Obrázek byl odstraněn.';

        return $state;
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array{message:string,error:string,media_feedback:string,media_feedback_type:string} $state
     * @return array{message:string,error:string,media_feedback:string,media_feedback_type:string}
     */
    private function saveCertificateFile(array $post, array $files, array $state): array
    {
        $certificateTitle = trim((string) ($post['certificate_title'] ?? ''));
        $uploadError = null;
        $path = $this->imageUploadService->storeCertificateFile(
            is_array($files['certificate_file'] ?? null) ? $files['certificate_file'] : [],
            $this->projectRoot . '/uploads',
            $uploadError
        );

        if ($path === null) {
            $state['error'] = $uploadError !== null && $uploadError !== ''
                ? 'Certifikát se nepodařilo nahrát. ' . $uploadError
                : 'Certifikát se nepodařilo nahrát.';
            $state['media_feedback'] = $state['error'];
            $state['media_feedback_type'] = 'error';
            return $state;
        }

        $uploadedName = basename((string) $path);
        if ($certificateTitle !== '' && $this->isValidCertificateFileName($uploadedName)) {
            $this->certificateMetadataService->setTitle($this->projectRoot . '/uploads', $uploadedName, $certificateTitle);
        }

        $state['message'] = 'Certifikát byl nahrán.';
        $state['media_feedback'] = $state['message'];
        $state['media_feedback_type'] = 'success';

        return $state;
    }

    /**
     * @param array<string, mixed> $post
     * @param array{message:string,error:string,media_feedback:string,media_feedback_type:string} $state
     * @return array{message:string,error:string,media_feedback:string,media_feedback_type:string}
     */
    private function saveCertificateTitle(array $post, array $state): array
    {
        $fileName = basename((string) ($post['certificate_name'] ?? ''));
        $title = trim((string) ($post['certificate_title'] ?? ''));
        $titleValidationError = $this->certificateTitleValidationError($title);

        if (! $this->isValidCertificateFileName($fileName)) {
            $state['media_feedback'] = 'Neplatný certifikát.';
            $state['media_feedback_type'] = 'error';
            return $state;
        }

        if ($titleValidationError !== null) {
            $state['media_feedback'] = $titleValidationError;
            $state['media_feedback_type'] = 'error';
            return $state;
        }

        if (! $this->certificateMetadataService->setTitle($this->projectRoot . '/uploads', $fileName, $title)) {
            $state['media_feedback'] = 'Název certifikátu se nepodařilo uložit.';
            $state['media_feedback_type'] = 'error';
            return $state;
        }

        $state['media_feedback'] = 'Název certifikátu byl uložen.';
        $state['media_feedback_type'] = 'success';
        $state['message'] = $state['media_feedback'];

        return $state;
    }

    /**
     * @param array<string, mixed> $post
     * @param array{message:string,error:string,media_feedback:string,media_feedback_type:string} $state
     * @return array{message:string,error:string,media_feedback:string,media_feedback_type:string}
     */
    private function deleteCertificateFile(array $post, array $state): array
    {
        $fileName = basename((string) ($post['certificate_name'] ?? ''));
        if (! $this->isValidCertificateFileName($fileName)) {
            $state['media_feedback'] = 'Neplatný certifikát pro smazání.';
            $state['media_feedback_type'] = 'error';
            return $state;
        }

        $fullPath = $this->projectRoot . '/uploads/' . $fileName;
        if (! $this->storage->deleteFile($fullPath)) {
            $state['media_feedback'] = 'Certifikát se nepodařilo odstranit.';
            $state['media_feedback_type'] = 'error';
            return $state;
        }

        $this->certificateMetadataService->remove($this->projectRoot . '/uploads', $fileName);
        $previewFileName = $this->certificatePreviewService->previewFilenameFromOriginal($fileName);
        if (is_string($previewFileName) && $previewFileName !== '') {
            $this->storage->deleteFile($this->projectRoot . '/uploads/' . $previewFileName);
        }

        $state['media_feedback'] = 'Certifikát byl odstraněn.';
        $state['media_feedback_type'] = 'success';
        $state['message'] = $state['media_feedback'];

        return $state;
    }

    private function isValidCertificateFileName(string $fileName): bool
    {
        return $fileName !== '' && preg_match(self::CERTIFICATE_FILE_PATTERN, $fileName) === 1;
    }

    private function certificateTitleValidationError(string $title): ?string
    {
        if ($title === '') {
            return 'Vyplňte název certifikátu.';
        }

        if ((function_exists('mb_strlen') ? mb_strlen($title) : strlen($title)) > 120) {
            return 'Název certifikátu je příliš dlouhý (max. 120 znaků).';
        }

        return null;
    }
}
