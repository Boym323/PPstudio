<?php
declare(strict_types=1);

use PPStudio\Service\AvailabilityModule;

function ppstudioAvailabilityModule(mysqli $connection): AvailabilityModule
{
    static $modules = [];

    $connectionId = spl_object_id($connection);

    if (! isset($modules[$connectionId])) {
        $modules[$connectionId] = new AvailabilityModule($connection);
    }

    return $modules[$connectionId];
}

function getServiceById(mysqli $connection, int $serviceId): ?array
{
    return ppstudioAvailabilityModule($connection)->availabilityService()->getServiceById($serviceId);
}

function reservationFitsAvailabilityWindows(DateTimeImmutable $start, DateTimeImmutable $end, array $windows): bool
{
    return \PPStudio\Service\AvailabilityService::reservationFitsAvailabilityWindows($start, $end, $windows);
}

function getBookedIntervals(mysqli $connection, string $date): array
{
    return ppstudioAvailabilityModule($connection)->availabilityService()->getBookedIntervals($date);
}

function getAvailabilityWindows(mysqli $connection, string $date): array
{
    return ppstudioAvailabilityModule($connection)->availabilityService()->getAvailabilityWindows($date);
}

function intervalOverlaps(DateTimeImmutable $start, DateTimeImmutable $end, array $intervals): bool
{
    return \PPStudio\Service\AvailabilityService::intervalOverlaps($start, $end, $intervals);
}

function getAvailableTimesForDate(mysqli $connection, int $serviceId, string $date): array
{
    return ppstudioAvailabilityModule($connection)->availabilityService()->getAvailableTimesForDate($serviceId, $date);
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
    return ppstudioAvailabilityModule($connection)->reservationService()->createReservationWithLock(
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
    return ppstudioAvailabilityModule($connection)->reservationService()->rescheduleReservationWithLock($reservationId, $dateTime);
}

function getAvailableDays(mysqli $connection, int $serviceId, int $daysAhead = 60): array
{
    return ppstudioAvailabilityModule($connection)->availabilityService()->getAvailableDays($serviceId, $daysAhead);
}

function isValidReservationSlot(mysqli $connection, int $serviceId, string $dateTime): bool
{
    return ppstudioAvailabilityModule($connection)->availabilityService()->isValidReservationSlot($serviceId, $dateTime);
}
