<?php
declare(strict_types=1);

function getServiceById(mysqli $connection, int $serviceId): ?array
{
    $statement = $connection->prepare(
        'SELECT s.id, s.nazev, s.popis, s.cena, s.doba_trvani, s.created_at
         FROM sluzby s
         INNER JOIN kategorie k ON k.id = s.kategorie_id
         WHERE s.id = ?
           AND s.aktivni = 1
           AND k.aktivni = 1
         LIMIT 1'
    );

    if (! $statement) {
        return null;
    }

    $statement->bind_param('i', $serviceId);
    $statement->execute();
    $statement->bind_result($id, $nazev, $popis, $cena, $dobaTrvani, $createdAt);
    $service = null;

    if ($statement->fetch()) {
        $service = [
            'id' => $id,
            'nazev' => $nazev,
            'popis' => $popis,
            'cena' => $cena,
            'doba_trvani' => $dobaTrvani,
            'created_at' => $createdAt,
        ];
    }

    $statement->close();

    return $service ?: null;
}

function getBookedIntervals(mysqli $connection, string $date): array
{
    $statement = $connection->prepare(
        'SELECT r.datum_cas, s.doba_trvani
         FROM rezervace r
         INNER JOIN sluzby s ON s.id = r.sluzba
         WHERE DATE(r.datum_cas) = ?
           AND r.stav IN ("nova", "potvrzena", "dokoncena")
         ORDER BY r.datum_cas ASC'
    );

    if (! $statement) {
        return [];
    }

    $statement->bind_param('s', $date);
    $statement->execute();
    $intervals = [];

    $statement->bind_result($startAt, $durationMinutes);

    while ($statement->fetch()) {
        $start = new DateTimeImmutable((string) $startAt);
        $duration = max(15, (int) $durationMinutes);
        $intervals[] = [
            'start' => $start,
            'end' => $start->modify('+' . $duration . ' minutes'),
        ];
    }

    $statement->close();

    return $intervals;
}

function getAvailabilityWindows(mysqli $connection, string $date): array
{
    $statement = $connection->prepare(
        'SELECT id, start_at, end_at
         FROM dostupnost
         WHERE DATE(start_at) = ?
           AND end_at > start_at
         ORDER BY start_at ASC'
    );

    if (! $statement) {
        return [];
    }

    $statement->bind_param('s', $date);
    $statement->execute();
    $windows = [];

    $statement->bind_result($id, $startAt, $endAt);

    while ($statement->fetch()) {
        $windows[] = [
            'id' => (int) $id,
            'start' => new DateTimeImmutable((string) $startAt),
            'end' => new DateTimeImmutable((string) $endAt),
        ];
    }

    $statement->close();

    return $windows;
}

function intervalOverlaps(DateTimeImmutable $start, DateTimeImmutable $end, array $intervals): bool
{
    foreach ($intervals as $interval) {
        if ($start < $interval['end'] && $end > $interval['start']) {
            return true;
        }
    }

    return false;
}

function getAvailableTimesForDate(mysqli $connection, int $serviceId, string $date): array
{
    $service = getServiceById($connection, $serviceId);

    if (! $service) {
        return [];
    }

    $duration = max(15, (int) ($service['doba_trvani'] ?? 0));
    $windows = getAvailabilityWindows($connection, $date);
    $bookedIntervals = getBookedIntervals($connection, $date);
    $slots = [];

    foreach ($windows as $window) {
        $cursor = $window['start'];
        $latestStart = $window['end']->modify('-' . $duration . ' minutes');

        while ($cursor <= $latestStart) {
            $slotEnd = $cursor->modify('+' . $duration . ' minutes');

            if (! intervalOverlaps($cursor, $slotEnd, $bookedIntervals)) {
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

function getAvailableDays(mysqli $connection, int $serviceId, int $daysAhead = 60): array
{
    $days = [];
    $today = new DateTimeImmutable('today');

    for ($offset = 0; $offset <= $daysAhead; $offset++) {
        $date = $today->modify('+' . $offset . ' days')->format('Y-m-d');
        $times = getAvailableTimesForDate($connection, $serviceId, $date);

        if ($times !== []) {
            $days[] = [
                'value' => $date,
                'label' => formatCzechDate($date),
            ];
        }
    }

    return $days;
}

function isValidReservationSlot(mysqli $connection, int $serviceId, string $dateTime): bool
{
    $date = substr($dateTime, 0, 10);
    $time = substr($dateTime, 11, 5);

    if (strlen($date) !== 10 || strlen($time) !== 5) {
        return false;
    }

    $availableTimes = getAvailableTimesForDate($connection, $serviceId, $date);

    foreach ($availableTimes as $slot) {
        if ($slot['value'] === $time) {
            return true;
        }
    }

    return false;
}
