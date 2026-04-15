<?php
declare(strict_types=1);

namespace PPStudio\Service;

use DateTimeImmutable;
use PPStudio\Domain\AvailabilityWindow;
use PPStudio\Domain\ReservationSlot;
use PPStudio\Domain\ServiceItem;
use PPStudio\Repository\AvailabilityRepository;
use PPStudio\Repository\ReservationRepository;
use PPStudio\Repository\ServiceRepository;

final class AvailabilityService
{
    public function __construct(
        private ServiceRepository $serviceRepository,
        private AvailabilityRepository $availabilityRepository,
        private ReservationRepository $reservationRepository
    ) {
    }

    public function getServiceById(int $serviceId): ?array
    {
        $service = $this->getServiceItemById($serviceId);

        return $service instanceof ServiceItem ? $service->toLegacyArray() : null;
    }

    public function getServiceItemById(int $serviceId): ?ServiceItem
    {
        return $this->serviceRepository->findActiveItemById($serviceId);
    }

    public function getAvailabilityWindows(string $date): array
    {
        return array_map(
            static fn (AvailabilityWindow $window): array => $window->toArray(true),
            $this->getAvailabilityWindowObjects($date)
        );
    }

    /**
     * @return AvailabilityWindow[]
     */
    public function getAvailabilityWindowObjects(string $date): array
    {
        $bounds = self::sqlDayBounds($date);
        if ($bounds === null) {
            return [];
        }

        return $this->normalizeAvailabilityWindows(
            $this->availabilityRepository->findWindowsBetween($bounds['start'], $bounds['end']),
            true
        );
    }

    public function getBookedIntervals(string $date): array
    {
        return array_map(
            static fn (ReservationSlot $slot): array => $slot->toArray(),
            $this->getBookedSlots($date)
        );
    }

    /**
     * @return ReservationSlot[]
     */
    public function getBookedSlots(string $date): array
    {
        $bounds = self::sqlDayBounds($date);
        if ($bounds === null) {
            return [];
        }

        return $this->normalizeBookedIntervals(
            $this->reservationRepository->findBookedBetween($bounds['start'], $bounds['end'])
        );
    }

    public function getAvailableTimesForDate(int $serviceId, string $date): array
    {
        $service = $this->serviceRepository->findActiveItemById($serviceId);

        if (! $service) {
            return [];
        }

        $duration = $service->normalizedDurationMinutes();
        $windows = $this->getAvailabilityWindowObjects($date);
        $bookedSlots = $this->getBookedSlots($date);
        $slots = [];

        foreach ($windows as $window) {
            $cursor = $window->start;
            $latestStart = $window->end->modify('-' . $duration . ' minutes');

            while ($cursor <= $latestStart) {
                $slot = ReservationSlot::fromStartAndDuration($cursor, $duration);

                if (! self::slotOverlaps($slot, $bookedSlots)) {
                    $value = $cursor->format('H:i');
                    $slots[$value] = [
                        'value' => $value,
                        'label' => $cursor->format('H:i'),
                    ];
                }

                $cursor = $cursor->modify('+30 minutes');
            }
        }

        return array_values($slots);
    }

    public function getAvailableDays(int $serviceId, int $daysAhead = 60): array
    {
        $days = [];
        $today = new DateTimeImmutable('today');
        $lastDay = $today->modify('+' . max(0, $daysAhead) . ' days');
        $boundsStart = $today->format('Y-m-d 00:00:00');
        $boundsEnd = $lastDay->modify('+1 day')->format('Y-m-d 00:00:00');
        $candidateDates = [];

        $windows = $this->normalizeAvailabilityWindows(
            $this->availabilityRepository->findWindowsBetween($boundsStart, $boundsEnd),
            false
        );

        foreach ($windows as $window) {
            $windowStart = $window->start;
            $windowEnd = $window->end;
            $cursor = $windowStart < $today ? $today : $windowStart->setTime(0, 0);
            $windowLastDay = $windowEnd->modify('-1 second')->setTime(0, 0);

            if ($windowLastDay > $lastDay) {
                $windowLastDay = $lastDay;
            }

            while ($cursor <= $windowLastDay) {
                $candidateDates[$cursor->format('Y-m-d')] = true;
                $cursor = $cursor->modify('+1 day');
            }
        }

        $dates = array_keys($candidateDates);
        sort($dates);

        if ($dates === []) {
            return [];
        }

        foreach ($dates as $date) {
            $times = $this->getAvailableTimesForDate($serviceId, $date);

            if ($times !== []) {
                $days[] = [
                    'value' => $date,
                    'label' => self::formatCzechDate($date),
                ];
            }
        }

        return $days;
    }

    public function isValidReservationSlot(int $serviceId, string $dateTime): bool
    {
        $date = substr($dateTime, 0, 10);
        $time = substr($dateTime, 11, 5);

        if (strlen($date) !== 10 || strlen($time) !== 5) {
            return false;
        }

        $availableTimes = $this->getAvailableTimesForDate($serviceId, $date);

        foreach ($availableTimes as $slot) {
            if ($slot['value'] === $time) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return AvailabilityWindow[]
     */
    public function normalizeAvailabilityWindows(array $rows, bool $includeId): array
    {
        $windows = [];

        foreach ($rows as $row) {
            $window = AvailabilityWindow::fromDatabaseRow($row);

            if (! $window instanceof AvailabilityWindow) {
                continue;
            }

            if (! $includeId && $window->id !== null) {
                $window = new AvailabilityWindow($window->start, $window->end);
            }

            $windows[] = $window;
        }

        return $windows;
    }

    /**
     * @return ReservationSlot[]
     */
    public function normalizeBookedIntervals(array $rows): array
    {
        $intervals = [];

        foreach ($rows as $row) {
            $slot = ReservationSlot::fromBookedRow($row);

            if (! $slot instanceof ReservationSlot) {
                continue;
            }

            $intervals[] = $slot;
        }

        return $intervals;
    }

    public static function reservationFitsAvailabilityWindows(DateTimeImmutable $start, DateTimeImmutable $end, array $windows): bool
    {
        $slot = new ReservationSlot($start, $end);

        foreach ($windows as $window) {
            if (self::availabilityWindowFromMixed($window)?->contains($slot)) {
                return true;
            }
        }

        return false;
    }

    public static function intervalOverlaps(DateTimeImmutable $start, DateTimeImmutable $end, array $intervals): bool
    {
        return self::slotOverlaps(new ReservationSlot($start, $end), $intervals);
    }

    public static function slotOverlaps(ReservationSlot $slot, array $intervals): bool
    {
        foreach ($intervals as $interval) {
            $bookedSlot = self::reservationSlotFromMixed($interval);

            if ($bookedSlot instanceof ReservationSlot && $slot->overlaps($bookedSlot)) {
                return true;
            }
        }

        return false;
    }

    public static function sqlDayBounds(string $date): ?array
    {
        $dayStart = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (! $dayStart) {
            return null;
        }

        $dayStart = $dayStart->setTime(0, 0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        return [
            'start' => $dayStart->format('Y-m-d H:i:s'),
            'end' => $dayEnd->format('Y-m-d H:i:s'),
        ];
    }

    private static function formatCzechDate(string $date): string
    {
        $dateObject = DateTimeImmutable::createFromFormat('Y-m-d', $date);

        if (! $dateObject) {
            return $date;
        }

        return $dateObject->format('d.m.Y');
    }

    private static function availabilityWindowFromMixed(mixed $window): ?AvailabilityWindow
    {
        if ($window instanceof AvailabilityWindow) {
            return $window;
        }

        if (! is_array($window) || ! ($window['start'] ?? null) instanceof DateTimeImmutable || ! ($window['end'] ?? null) instanceof DateTimeImmutable) {
            return null;
        }

        return new AvailabilityWindow($window['start'], $window['end'], isset($window['id']) ? (int) $window['id'] : null);
    }

    private static function reservationSlotFromMixed(mixed $slot): ?ReservationSlot
    {
        if ($slot instanceof ReservationSlot) {
            return $slot;
        }

        if (! is_array($slot) || ! ($slot['start'] ?? null) instanceof DateTimeImmutable || ! ($slot['end'] ?? null) instanceof DateTimeImmutable) {
            return null;
        }

        return new ReservationSlot($slot['start'], $slot['end']);
    }
}
