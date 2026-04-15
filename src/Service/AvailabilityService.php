<?php
declare(strict_types=1);

namespace PPStudio\Service;

use DateTimeImmutable;
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
        return $this->serviceRepository->findActiveById($serviceId);
    }

    public function getAvailabilityWindows(string $date): array
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
        $service = $this->serviceRepository->findActiveById($serviceId);

        if (! $service) {
            return [];
        }

        $duration = max(15, (int) ($service['doba_trvani'] ?? 0));
        $windows = $this->getAvailabilityWindows($date);
        $bookedIntervals = $this->getBookedIntervals($date);
        $slots = [];

        foreach ($windows as $window) {
            $cursor = $window['start'];
            $latestStart = $window['end']->modify('-' . $duration . ' minutes');

            while ($cursor <= $latestStart) {
                $slotEnd = $cursor->modify('+' . $duration . ' minutes');

                if (! self::intervalOverlaps($cursor, $slotEnd, $bookedIntervals)) {
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
            $windowStart = $window['start'];
            $windowEnd = $window['end'];
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

    public function normalizeAvailabilityWindows(array $rows, bool $includeId): array
    {
        $windows = [];

        foreach ($rows as $row) {
            $windowStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) ($row['start_at'] ?? ''));
            $windowEnd = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) ($row['end_at'] ?? ''));

            if (! $windowStart instanceof DateTimeImmutable || ! $windowEnd instanceof DateTimeImmutable || $windowEnd <= $windowStart) {
                continue;
            }

            $window = [
                'start' => $windowStart,
                'end' => $windowEnd,
            ];

            if ($includeId) {
                $window = ['id' => (int) ($row['id'] ?? 0)] + $window;
            }

            $windows[] = $window;
        }

        return $windows;
    }

    public function normalizeBookedIntervals(array $rows): array
    {
        $intervals = [];

        foreach ($rows as $row) {
            $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) ($row['start_at'] ?? ''));
            if (! $start instanceof DateTimeImmutable) {
                continue;
            }

            $duration = max(15, (int) ($row['duration_minutes'] ?? 0));
            $intervals[] = [
                'start' => $start,
                'end' => $start->modify('+' . $duration . ' minutes'),
            ];
        }

        return $intervals;
    }

    public static function reservationFitsAvailabilityWindows(DateTimeImmutable $start, DateTimeImmutable $end, array $windows): bool
    {
        foreach ($windows as $window) {
            if ($start >= $window['start'] && $end <= $window['end']) {
                return true;
            }
        }

        return false;
    }

    public static function intervalOverlaps(DateTimeImmutable $start, DateTimeImmutable $end, array $intervals): bool
    {
        foreach ($intervals as $interval) {
            if ($start < $interval['end'] && $end > $interval['start']) {
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
}
