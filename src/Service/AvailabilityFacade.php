<?php
declare(strict_types=1);

namespace PPStudio\Service;

use DateTimeImmutable;
use mysqli;

final class AvailabilityFacade
{
    /** @var array<int, AvailabilityModule> */
    private array $modules = [];

    public function module(mysqli $connection): AvailabilityModule
    {
        $connectionId = spl_object_id($connection);

        if (! isset($this->modules[$connectionId])) {
            $this->modules[$connectionId] = new AvailabilityModule($connection);
        }

        return $this->modules[$connectionId];
    }

    public function getServiceById(mysqli $connection, int $serviceId): ?array
    {
        return $this->module($connection)->availabilityService()->getServiceById($serviceId);
    }

    public function reservationFitsAvailabilityWindows(DateTimeImmutable $start, DateTimeImmutable $end, array $windows): bool
    {
        return AvailabilityService::reservationFitsAvailabilityWindows($start, $end, $windows);
    }

    public function getBookedIntervals(mysqli $connection, string $date): array
    {
        return $this->module($connection)->availabilityService()->getBookedIntervals($date);
    }

    public function getAvailabilityWindows(mysqli $connection, string $date): array
    {
        return $this->module($connection)->availabilityService()->getAvailabilityWindows($date);
    }

    public function intervalOverlaps(DateTimeImmutable $start, DateTimeImmutable $end, array $intervals): bool
    {
        return AvailabilityService::intervalOverlaps($start, $end, $intervals);
    }

    public function getAvailableTimesForDate(mysqli $connection, int $serviceId, string $date): array
    {
        return $this->module($connection)->availabilityService()->getAvailableTimesForDate($serviceId, $date);
    }

    public function createReservationWithLock(
        mysqli $connection,
        string $name,
        string $email,
        string $phone,
        string $source,
        string $clientNote,
        int $serviceId,
        string $dateTime,
        string $status = 'nova'
    ): array {
        return $this->module($connection)->reservationService()->createReservationWithLock(
            $name,
            $email,
            $phone,
            $source,
            $clientNote,
            $serviceId,
            $dateTime,
            $status
        );
    }

    public function rescheduleReservationWithLock(mysqli $connection, int $reservationId, string $dateTime): array
    {
        return $this->module($connection)->reservationService()->rescheduleReservationWithLock($reservationId, $dateTime);
    }

    public function getAvailableDays(mysqli $connection, int $serviceId, int $daysAhead = 60): array
    {
        return $this->module($connection)->availabilityService()->getAvailableDays($serviceId, $daysAhead);
    }

    public function isValidReservationSlot(mysqli $connection, int $serviceId, string $dateTime): bool
    {
        return $this->module($connection)->availabilityService()->isValidReservationSlot($serviceId, $dateTime);
    }
}
