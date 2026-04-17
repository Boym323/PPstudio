<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;

final class AdminReservationMutationService
{
    public function __construct(
        private AdminReservationUpdateUseCase $updateUseCase,
        private AdminManualReservationCreateUseCase $createUseCase,
        private AdminReservationDeleteUseCase $deleteUseCase
    ) {
    }

    public static function create(mysqli $connection, array $emailConfig, array $siteSettings): self
    {
        return (new AdminReservationModule($connection, $emailConfig, $siteSettings))->mutationService();
    }

    public function updateReservation(array $post, array $session): array
    {
        return $this->updateUseCase->handle($post, $session);
    }

    public function createManualReservation(array $post): array
    {
        return $this->createUseCase->handle($post);
    }

    public function deleteReservation(array $post): array
    {
        return $this->deleteUseCase->handle($post);
    }
}
