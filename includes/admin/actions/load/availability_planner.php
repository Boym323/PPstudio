<?php

$plannerStartDate = (new DateTimeImmutable('monday this week'))->modify(
    sprintf('%+d weeks', $plannerWeekOffset)
);
$plannerEndDate = $plannerStartDate->modify('+' . ($plannerDayRange - 1) . ' days');
$plannerWeekLabel = formatCzechDate($plannerStartDate->format('Y-m-d')) . ' - ' . formatCzechDate($plannerEndDate->format('Y-m-d'));

for ($i = 0; $i < $plannerDayRange; $i++) {
    $plannerDays[] = $plannerStartDate->modify('+' . $i . ' days')->format('Y-m-d');
}

foreach ($plannerDays as $plannerDay) {
    $holidayName = getCzechHolidayName($plannerDay);
    $plannerDayMeta[$plannerDay] = [
        'is_weekend' => isWeekendDate($plannerDay),
        'holiday_name' => $holidayName,
        'is_holiday' => $holidayName !== null,
    ];
}

$bookedStartAt = $plannerStartDate->format('Y-m-d 00:00:00');
$bookedEndAt = $plannerEndDate->modify('+1 day')->format('Y-m-d 00:00:00');
$bookedReservationStatement = $connection->prepare(
    'SELECT r.datum_cas, r.jmeno, s.nazev, s.doba_trvani
     FROM rezervace r
     INNER JOIN sluzby s ON s.id = r.sluzba
     WHERE r.datum_cas >= ?
       AND r.datum_cas < ?
       AND r.stav IN ("nova", "potvrzena", "dokoncena")
     ORDER BY r.datum_cas ASC'
);

if ($bookedReservationStatement) {
    $bookedReservationStatement->bind_param('ss', $bookedStartAt, $bookedEndAt);
    $bookedReservationStatement->execute();
    $bookedReservationStatement->bind_result($bookedDateTime, $bookedClientName, $bookedServiceName, $bookedDurationMinutes);

    while ($bookedReservationStatement->fetch()) {
        $startAt = (string) ($bookedDateTime ?? '');
        if ($startAt === '') {
            continue;
        }

        $durationMinutes = max(15, (int) $bookedDurationMinutes);
        $start = new DateTimeImmutable($startAt);
        $end = $start->modify('+' . $durationMinutes . ' minutes');

        $plannerBookedWindows[] = [
            'start_at' => $start->format('Y-m-d H:i:s'),
            'end_at' => $end->format('Y-m-d H:i:s'),
            'service_name' => (string) ($bookedServiceName ?? ''),
            'client_name' => (string) ($bookedClientName ?? ''),
        ];
    }

    $bookedReservationStatement->close();
}

for ($minutes = 8 * 60; $minutes < 20 * 60; $minutes += 30) {
    $plannerSlots[] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

foreach ($availabilityRows as $row) {
    $date = substr((string) $row['start_at'], 0, 10);
    if (in_array($date, $plannerDays, true)) {
        $plannerInitialWindows[] = [
            'start_at' => (string) $row['start_at'],
            'end_at' => (string) $row['end_at'],
        ];
    }
}
