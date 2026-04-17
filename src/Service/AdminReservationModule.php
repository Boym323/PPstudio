<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use PPStudio\Repository\ReservationRepository;

final class AdminReservationModule
{
    private ?AdminReservationService $adminReservationService = null;

    private ?AdminReservationMutationService $adminReservationMutationService = null;

    private ?ReservationRepository $reservationRepository = null;

    private ?ReservationService $reservationService = null;

    private ?ReservationNotificationService $notificationService = null;

    public function __construct(
        private mysqli $connection,
        private array $emailConfig = [],
        private array $siteSettings = []
    ) {
    }

    public function adminReservationService(): AdminReservationService
    {
        if ($this->adminReservationService instanceof AdminReservationService) {
            return $this->adminReservationService;
        }

        $this->adminReservationService = new AdminReservationService($this->connection);

        return $this->adminReservationService;
    }

    public function mutationService(): AdminReservationMutationService
    {
        if ($this->adminReservationMutationService instanceof AdminReservationMutationService) {
            return $this->adminReservationMutationService;
        }

        $this->adminReservationMutationService = new AdminReservationMutationService(
            new AdminReservationUpdateUseCase(
                $this->connection,
                $this->siteSettings,
                $this->reservationRepository(),
                $this->reservationService(),
                $this->notificationService()
            ),
            new AdminManualReservationCreateUseCase(
                $this->siteSettings,
                $this->reservationService(),
                $this->notificationService()
            ),
            new AdminReservationDeleteUseCase($this->connection)
        );

        return $this->adminReservationMutationService;
    }

    private function reservationRepository(): ReservationRepository
    {
        if (! $this->reservationRepository instanceof ReservationRepository) {
            $this->reservationRepository = new ReservationRepository($this->connection);
        }

        return $this->reservationRepository;
    }

    private function reservationService(): ReservationService
    {
        if ($this->reservationService instanceof ReservationService) {
            return $this->reservationService;
        }

        $this->reservationService = (new AvailabilityModule($this->connection))->reservationService();

        return $this->reservationService;
    }

    private function notificationService(): ReservationNotificationService
    {
        if (! $this->notificationService instanceof ReservationNotificationService) {
            $this->notificationService = new ReservationNotificationService($this->emailConfig);
        }

        return $this->notificationService;
    }
}
