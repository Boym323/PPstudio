<?php
declare(strict_types=1);

namespace PPStudio\Service;

use DateTimeImmutable;
use mysqli;
use PPStudio\Repository\AvailabilityRepository;
use PPStudio\Repository\ReservationRepository;
use PPStudio\Repository\ServiceRepository;
use Throwable;

final class ReservationService
{
    public function __construct(
        private mysqli $connection,
        private ServiceRepository $serviceRepository,
        private AvailabilityRepository $availabilityRepository,
        private ReservationRepository $reservationRepository,
        private AvailabilityService $availabilityService
    ) {
    }

    public function createReservationWithLock(
        string $name,
        string $email,
        string $phone,
        string $source,
        string $clientNote,
        int $serviceId,
        string $dateTime,
        string $status = 'nova'
    ): array {
        $normalizedDateTime = self::normalizeSqlDateTime($dateTime);
        if ($normalizedDateTime === null) {
            return ['status' => 'invalid_datetime'];
        }

        $service = $this->serviceRepository->findActiveById($serviceId);
        if (! is_array($service)) {
            return ['status' => 'service_unavailable'];
        }

        $reservationStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalizedDateTime);
        if (! $reservationStart instanceof DateTimeImmutable) {
            return ['status' => 'invalid_datetime'];
        }

        $durationMinutes = max(15, (int) ($service['doba_trvani'] ?? 0));
        $reservationEnd = $reservationStart->modify('+' . $durationMinutes . ' minutes');
        $bounds = AvailabilityService::sqlDayBounds($reservationStart->format('Y-m-d'));
        if ($bounds === null) {
            return ['status' => 'invalid_datetime'];
        }

        $servicePrice = isset($service['cena']) ? (float) $service['cena'] : null;
        $this->connection->begin_transaction();

        try {
            $windows = $this->availabilityService->normalizeAvailabilityWindows(
                $this->availabilityRepository->lockWindowsBetween($bounds['start'], $bounds['end']),
                false
            );

            if (! AvailabilityService::reservationFitsAvailabilityWindows($reservationStart, $reservationEnd, $windows)) {
                $this->connection->rollback();

                return ['status' => 'slot_unavailable'];
            }

            $bookedIntervals = $this->availabilityService->normalizeBookedIntervals(
                $this->reservationRepository->lockBookedBetween($bounds['start'], $bounds['end'])
            );

            if (AvailabilityService::intervalOverlaps($reservationStart, $reservationEnd, $bookedIntervals)) {
                $this->connection->rollback();

                return ['status' => 'slot_unavailable'];
            }

            $reservationId = $this->reservationRepository->insert(
                $name,
                $email,
                $phone,
                $source,
                $clientNote,
                $serviceId,
                $servicePrice,
                $normalizedDateTime,
                $status
            );

            $this->connection->commit();

            return [
                'status' => 'ok',
                'reservation_id' => $reservationId,
                'date_time' => $normalizedDateTime,
                'service' => $service,
                'service_price' => $servicePrice,
            ];
        } catch (Throwable) {
            $this->connection->rollback();

            return ['status' => 'error'];
        }
    }

    private static function normalizeSqlDateTime(string $dateTime): ?string
    {
        $value = trim(str_replace('T', ' ', $dateTime));
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed->format('Y-m-d H:i:s');
            }
        }

        return null;
    }
}
