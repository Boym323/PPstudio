<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use PPStudio\Http\Controller\Admin\AdminMediaDataLoader;
use PPStudio\Http\Controller\Admin\AdminMediaPostActionHandler;
use PPStudio\Infrastructure\Storage\UploadStorage;
use PPStudio\Repository\MediaRepository;

final class AdminMediaModule
{
    private ?UploadStorage $storage = null;

    private ?MediaRepository $mediaRepository = null;

    private ?CertificateMetadataService $certificateMetadataService = null;

    private ?CertificatePreviewService $certificatePreviewService = null;

    private ?ImageUploadService $imageUploadService = null;

    private ?CertificateService $certificateService = null;

    private ?MediaService $mediaService = null;

    private ?AdminMediaDataLoader $dataLoader = null;

    private ?AdminMediaPostActionHandler $postActionHandler = null;

    private ?AdminMediaPostActionUseCase $postActionUseCase = null;

    public function __construct(
        private mysqli $connection,
        private string $projectRoot
    ) {
    }

    public function dataLoader(): AdminMediaDataLoader
    {
        if ($this->dataLoader instanceof AdminMediaDataLoader) {
            return $this->dataLoader;
        }

        $this->dataLoader = new AdminMediaDataLoader(
            $this->mediaService(),
            $this->certificateService(),
            $this->projectRoot . '/uploads'
        );

        return $this->dataLoader;
    }

    public function postActionHandler(): AdminMediaPostActionHandler
    {
        if ($this->postActionHandler instanceof AdminMediaPostActionHandler) {
            return $this->postActionHandler;
        }

        $this->postActionHandler = new AdminMediaPostActionHandler($this->postActionUseCase());

        return $this->postActionHandler;
    }

    private function postActionUseCase(): AdminMediaPostActionUseCase
    {
        if ($this->postActionUseCase instanceof AdminMediaPostActionUseCase) {
            return $this->postActionUseCase;
        }

        $this->postActionUseCase = new AdminMediaPostActionUseCase(
            $this->mediaRepository(),
            $this->imageUploadService(),
            $this->certificateMetadataService(),
            $this->certificatePreviewService(),
            $this->storage(),
            $this->projectRoot
        );

        return $this->postActionUseCase;
    }

    private function mediaService(): MediaService
    {
        if (! $this->mediaService instanceof MediaService) {
            $this->mediaService = new MediaService();
        }

        return $this->mediaService;
    }

    private function certificateService(): CertificateService
    {
        if (! $this->certificateService instanceof CertificateService) {
            $this->certificateService = new CertificateService(
                $this->storage(),
                $this->certificateMetadataService(),
                $this->certificatePreviewService()
            );
        }

        return $this->certificateService;
    }

    private function imageUploadService(): ImageUploadService
    {
        if (! $this->imageUploadService instanceof ImageUploadService) {
            $this->imageUploadService = new ImageUploadService($this->storage(), $this->certificatePreviewService());
        }

        return $this->imageUploadService;
    }

    private function certificateMetadataService(): CertificateMetadataService
    {
        if (! $this->certificateMetadataService instanceof CertificateMetadataService) {
            $this->certificateMetadataService = new CertificateMetadataService($this->storage());
        }

        return $this->certificateMetadataService;
    }

    private function certificatePreviewService(): CertificatePreviewService
    {
        if (! $this->certificatePreviewService instanceof CertificatePreviewService) {
            $this->certificatePreviewService = new CertificatePreviewService($this->storage());
        }

        return $this->certificatePreviewService;
    }

    private function mediaRepository(): MediaRepository
    {
        if (! $this->mediaRepository instanceof MediaRepository) {
            $this->mediaRepository = new MediaRepository($this->connection);
        }

        return $this->mediaRepository;
    }

    private function storage(): UploadStorage
    {
        if (! $this->storage instanceof UploadStorage) {
            $this->storage = new UploadStorage();
        }

        return $this->storage;
    }
}
