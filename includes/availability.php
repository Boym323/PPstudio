<?php
declare(strict_types=1);

use PPStudio\Service\AvailabilityModule;
use PPStudio\Service\AvailabilityService;
use PPStudio\Service\ReservationService;

function ppstudioAvailabilityModule(mysqli $connection): AvailabilityModule
{
    static $modules = [];

    $connectionId = spl_object_id($connection);

    if (! isset($modules[$connectionId])) {
        $modules[$connectionId] = new AvailabilityModule($connection);
    }

    return $modules[$connectionId];
}

function ppstudioAvailabilityService(mysqli $connection): AvailabilityService
{
    return ppstudioAvailabilityModule($connection)->availabilityService();
}

function ppstudioReservationService(mysqli $connection): ReservationService
{
    return ppstudioAvailabilityModule($connection)->reservationService();
}

function getServiceById(mysqli $connection, int $serviceId): ?array
{
    return ppstudioAvailabilityService($connection)->getServiceById($serviceId);
}

function reservationFitsAvailabilityWindows(DateTimeImmutable $start, DateTimeImmutable $end, array $windows): bool
{
    return AvailabilityService::reservationFitsAvailabilityWindows($start, $end, $windows);
}

function getBookedIntervals(mysqli $connection, string $date): array
{
    return ppstudioAvailabilityService($connection)->getBookedIntervals($date);
}

function getAvailabilityWindows(mysqli $connection, string $date): array
{
    return ppstudioAvailabilityService($connection)->getAvailabilityWindows($date);
}

function intervalOverlaps(DateTimeImmutable $start, DateTimeImmutable $end, array $intervals): bool
{
    return AvailabilityService::intervalOverlaps($start, $end, $intervals);
}

function getAvailableTimesForDate(mysqli $connection, int $serviceId, string $date): array
{
    return ppstudioAvailabilityService($connection)->getAvailableTimesForDate($serviceId, $date);
}

function createReservationWithLock(
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
    return ppstudioReservationService($connection)->createReservationWithLock(
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

function rescheduleReservationWithLock(mysqli $connection, int $reservationId, string $dateTime): array
{
    return ppstudioReservationService($connection)->rescheduleReservationWithLock($reservationId, $dateTime);
}

function getAvailableDays(mysqli $connection, int $serviceId, int $daysAhead = 60): array
{
    return ppstudioAvailabilityService($connection)->getAvailableDays($serviceId, $daysAhead);
}

function isValidReservationSlot(mysqli $connection, int $serviceId, string $dateTime): bool
{
    return ppstudioAvailabilityService($connection)->isValidReservationSlot($serviceId, $dateTime);
}
