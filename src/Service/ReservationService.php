<?php
declare(strict_types=1);

namespace PPStudio\Service;

use DateTimeImmutable;
use mysqli;
use PPStudio\Domain\ReservationData;
use PPStudio\Domain\ReservationSlot;
use PPStudio\Domain\ServiceItem;
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

        $service = $this->serviceRepository->findActiveItemById($serviceId);
        if (! $service instanceof ServiceItem) {
            return ['status' => 'service_unavailable'];
        }

        $reservationStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalizedDateTime);
        if (! $reservationStart instanceof DateTimeImmutable) {
            return ['status' => 'invalid_datetime'];
        }

        $reservationSlot = ReservationSlot::fromStartAndDuration($reservationStart, $service->normalizedDurationMinutes());
        $bounds = AvailabilityService::sqlDayBounds($reservationStart->format('Y-m-d'));
        if ($bounds === null) {
            return ['status' => 'invalid_datetime'];
        }

        $this->connection->begin_transaction();

        try {
            $windows = $this->availabilityService->normalizeAvailabilityWindows(
                $this->availabilityRepository->lockWindowsBetween($bounds['start'], $bounds['end']),
                false
            );

            if (! AvailabilityService::reservationFitsAvailabilityWindows($reservationSlot->start, $reservationSlot->end, $windows)) {
                $this->connection->rollback();

                return ['status' => 'slot_unavailable'];
            }

            $bookedIntervals = $this->availabilityService->normalizeBookedIntervals(
                $this->reservationRepository->lockBookedBetween($bounds['start'], $bounds['end'])
            );

            if (AvailabilityService::slotOverlaps($reservationSlot, $bookedIntervals)) {
                $this->connection->rollback();

                return ['status' => 'slot_unavailable'];
            }

            $reservationData = new ReservationData(
                $name,
                $email,
                $phone,
                $source,
                $clientNote,
                $serviceId,
                $service->price,
                $service->normalizedDurationMinutes(),
                $normalizedDateTime,
                $status
            );
            $reservationId = $this->reservationRepository->insertData($reservationData);

            $this->connection->commit();

            return [
                'status' => 'ok',
                'reservation_id' => $reservationId,
                'date_time' => $normalizedDateTime,
                'service' => $service->toLegacyArray(),
                'service_price' => $service->price,
            ];
        } catch (Throwable) {
            $this->connection->rollback();

            return ['status' => 'error'];
        }
    }

    public function rescheduleReservationWithLock(int $reservationId, string $dateTime): array
    {
        $normalizedDateTime = self::normalizeSqlDateTime($dateTime);
        if ($normalizedDateTime === null) {
            return ['status' => 'invalid_datetime'];
        }

        $reservationStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalizedDateTime);
        if (! $reservationStart instanceof DateTimeImmutable) {
            return ['status' => 'invalid_datetime'];
        }

        $bounds = AvailabilityService::sqlDayBounds($reservationStart->format('Y-m-d'));
        if ($bounds === null) {
            return ['status' => 'invalid_datetime'];
        }

        $this->connection->begin_transaction();

        try {
            $reservation = $this->reservationRepository->findDetailsByIdForUpdate($reservationId);
            if (! is_array($reservation)) {
                $this->connection->rollback();

                return ['status' => 'not_found'];
            }

            $durationMinutes = max(15, (int) ($reservation['reservation_duration'] ?? $reservation['service_duration'] ?? 0));
            $reservationSlot = ReservationSlot::fromStartAndDuration($reservationStart, $durationMinutes);

            $windows = $this->availabilityService->normalizeAvailabilityWindows(
                $this->availabilityRepository->lockWindowsBetween($bounds['start'], $bounds['end']),
                false
            );

            if (! AvailabilityService::reservationFitsAvailabilityWindows($reservationSlot->start, $reservationSlot->end, $windows)) {
                $this->connection->rollback();

                return ['status' => 'slot_unavailable'];
            }

            $bookedIntervals = $this->availabilityService->normalizeBookedIntervals(
                $this->reservationRepository->lockBookedBetweenExcludingId($bounds['start'], $bounds['end'], $reservationId)
            );

            if (AvailabilityService::slotOverlaps($reservationSlot, $bookedIntervals)) {
                $this->connection->rollback();

                return ['status' => 'slot_unavailable'];
            }

            if (! $this->reservationRepository->updateDateTimeAndResetReminder($reservationId, $normalizedDateTime)) {
                $this->connection->rollback();

                return ['status' => 'error'];
            }

            $this->connection->commit();

            return [
                'status' => 'ok',
                'reservation_id' => $reservationId,
                'date_time' => $normalizedDateTime,
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
