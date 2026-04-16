<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use PPStudio\Repository\AvailabilityRepository;
use PPStudio\Repository\ReservationRepository;
use PPStudio\Repository\ServiceRepository;

final class AvailabilityModule
{
    private ?ServiceRepository $serviceRepository = null;

    private ?AvailabilityRepository $availabilityRepository = null;

    private ?ReservationRepository $reservationRepository = null;

    private ?AvailabilityService $availabilityService = null;

    private ?ReservationService $reservationService = null;

    public function __construct(
        private mysqli $connection
    ) {
    }

    public function availabilityService(): AvailabilityService
    {
        if ($this->availabilityService instanceof AvailabilityService) {
            return $this->availabilityService;
        }

        $this->availabilityService = new AvailabilityService(
            $this->serviceRepository(),
            $this->availabilityRepository(),
            $this->reservationRepository()
        );

        return $this->availabilityService;
    }

    public function reservationService(): ReservationService
    {
        if ($this->reservationService instanceof ReservationService) {
            return $this->reservationService;
        }

        $this->reservationService = new ReservationService(
            $this->connection,
            $this->serviceRepository(),
            $this->availabilityRepository(),
            $this->reservationRepository(),
            $this->availabilityService()
        );

        return $this->reservationService;
    }

    private function serviceRepository(): ServiceRepository
    {
        if (! $this->serviceRepository instanceof ServiceRepository) {
            $this->serviceRepository = new ServiceRepository($this->connection);
        }

        return $this->serviceRepository;
    }

    private function availabilityRepository(): AvailabilityRepository
    {
        if (! $this->availabilityRepository instanceof AvailabilityRepository) {
            $this->availabilityRepository = new AvailabilityRepository($this->connection);
        }

        return $this->availabilityRepository;
    }

    private function reservationRepository(): ReservationRepository
    {
        if (! $this->reservationRepository instanceof ReservationRepository) {
            $this->reservationRepository = new ReservationRepository($this->connection);
        }

        return $this->reservationRepository;
    }
}
