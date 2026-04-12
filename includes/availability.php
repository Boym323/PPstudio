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

function reservationFitsAvailabilityWindows(DateTimeImmutable $start, DateTimeImmutable $end, array $windows): bool
{
    foreach ($windows as $window) {
        if ($start >= $window['start'] && $end <= $window['end']) {
            return true;
        }
    }

    return false;
}

function getBookedIntervals(mysqli $connection, string $date): array
{
    $bounds = sqlDayBounds($date);
    if ($bounds === null) {
        return [];
    }

    $statement = $connection->prepare(
        'SELECT r.datum_cas, s.doba_trvani
         FROM rezervace r
         INNER JOIN sluzby s ON s.id = r.sluzba
         WHERE r.datum_cas >= ?
           AND r.datum_cas < ?
           AND r.stav IN ("nova", "potvrzena", "dokoncena")
         ORDER BY r.datum_cas ASC'
    );

    if (! $statement) {
        return [];
    }

    $statement->bind_param('ss', $bounds['start'], $bounds['end']);
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
    $bounds = sqlDayBounds($date);
    if ($bounds === null) {
        return [];
    }

    $statement = $connection->prepare(
        'SELECT id, start_at, end_at
         FROM dostupnost
         WHERE start_at < ?
           AND end_at > ?
           AND end_at > start_at
         ORDER BY start_at ASC'
    );

    if (! $statement) {
        return [];
    }

    $statement->bind_param('ss', $bounds['end'], $bounds['start']);
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
    $normalizedDateTime = normalizeSqlDateTime($dateTime);
    if ($normalizedDateTime === null) {
        return ['status' => 'invalid_datetime'];
    }

    $service = getServiceById($connection, $serviceId);
    if (! is_array($service)) {
        return ['status' => 'service_unavailable'];
    }

    $reservationStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalizedDateTime);
    if (! $reservationStart instanceof DateTimeImmutable) {
        return ['status' => 'invalid_datetime'];
    }

    $durationMinutes = max(15, (int) ($service['doba_trvani'] ?? 0));
    $reservationEnd = $reservationStart->modify('+' . $durationMinutes . ' minutes');
    $bounds = sqlDayBounds($reservationStart->format('Y-m-d'));
    if ($bounds === null) {
        return ['status' => 'invalid_datetime'];
    }

    $servicePrice = isset($service['cena']) ? (float) $service['cena'] : null;
    $connection->begin_transaction();

    try {
        $availabilityStatement = $connection->prepare(
            'SELECT start_at, end_at
             FROM dostupnost
             WHERE start_at < ?
               AND end_at > ?
               AND end_at > start_at
             ORDER BY start_at ASC
             FOR UPDATE'
        );
        if (! $availabilityStatement) {
            throw new RuntimeException('availability_prepare_failed');
        }

        $availabilityStatement->bind_param('ss', $bounds['end'], $bounds['start']);
        $availabilityStatement->execute();
        $availabilityStatement->bind_result($windowStartAt, $windowEndAt);
        $windows = [];
        while ($availabilityStatement->fetch()) {
            $windowStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $windowStartAt);
            $windowEnd = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $windowEndAt);
            if ($windowStart instanceof DateTimeImmutable && $windowEnd instanceof DateTimeImmutable && $windowEnd > $windowStart) {
                $windows[] = [
                    'start' => $windowStart,
                    'end' => $windowEnd,
                ];
            }
        }
        $availabilityStatement->close();

        if (! reservationFitsAvailabilityWindows($reservationStart, $reservationEnd, $windows)) {
            $connection->rollback();
            return ['status' => 'slot_unavailable'];
        }

        $reservationsStatement = $connection->prepare(
            'SELECT r.datum_cas, s.doba_trvani
             FROM rezervace r
             INNER JOIN sluzby s ON s.id = r.sluzba
             WHERE r.datum_cas >= ?
               AND r.datum_cas < ?
               AND r.stav IN ("nova", "potvrzena", "dokoncena")
             ORDER BY r.datum_cas ASC
             FOR UPDATE'
        );
        if (! $reservationsStatement) {
            throw new RuntimeException('reservation_lock_prepare_failed');
        }

        $reservationsStatement->bind_param('ss', $bounds['start'], $bounds['end']);
        $reservationsStatement->execute();
        $reservationsStatement->bind_result($bookedStartAt, $bookedDurationMinutes);
        $bookedIntervals = [];
        while ($reservationsStatement->fetch()) {
            $bookedStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $bookedStartAt);
            if (! $bookedStart instanceof DateTimeImmutable) {
                continue;
            }

            $bookedDuration = max(15, (int) $bookedDurationMinutes);
            $bookedIntervals[] = [
                'start' => $bookedStart,
                'end' => $bookedStart->modify('+' . $bookedDuration . ' minutes'),
            ];
        }
        $reservationsStatement->close();

        if (intervalOverlaps($reservationStart, $reservationEnd, $bookedIntervals)) {
            $connection->rollback();
            return ['status' => 'slot_unavailable'];
        }

        $insertStatement = $connection->prepare(
            'INSERT INTO rezervace (jmeno, email, telefon, zdroj, poznamka_klienta, sluzba, cena_v_dobe_rezervace, datum_cas, stav)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (! $insertStatement) {
            throw new RuntimeException('reservation_insert_prepare_failed');
        }

        $insertStatement->bind_param(
            'sssssidss',
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

        if (! $insertStatement->execute()) {
            $insertStatement->close();
            throw new RuntimeException('reservation_insert_failed');
        }

        $reservationId = (int) $connection->insert_id;
        $insertStatement->close();
        $connection->commit();

        return [
            'status' => 'ok',
            'reservation_id' => $reservationId,
            'date_time' => $normalizedDateTime,
            'service' => $service,
            'service_price' => $servicePrice,
        ];
    } catch (Throwable $exception) {
        $connection->rollback();
        return ['status' => 'error'];
    }
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
