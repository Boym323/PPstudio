<?php


if ($reservationFilters['status'] !== 'all') {
    $statusEscaped = $connection->real_escape_string($reservationFilters['status']);
    $where[] = "r.stav = '{$statusEscaped}'";
}

if ($reservationFilters['period'] === 'today') {
    $todayBounds = sqlDayBounds(date('Y-m-d'));
    if ($todayBounds !== null) {
        $where[] = "r.datum_cas >= '{$todayBounds['start']}' AND r.datum_cas < '{$todayBounds['end']}'";
    }
} elseif ($reservationFilters['period'] === 'week') {
    $weekStart = (new DateTimeImmutable('monday this week'))->setTime(0, 0, 0);
    $weekEnd = $weekStart->modify('+1 week');
    $where[] = "r.datum_cas >= '" . $weekStart->format('Y-m-d H:i:s') . "' AND r.datum_cas < '" . $weekEnd->format('Y-m-d H:i:s') . "'";
} elseif ($reservationFilters['period'] === 'month') {
    $monthStart = (new DateTimeImmutable('first day of this month'))->setTime(0, 0, 0);
    $monthEnd = $monthStart->modify('+1 month');
    $where[] = "r.datum_cas >= '" . $monthStart->format('Y-m-d H:i:s') . "' AND r.datum_cas < '" . $monthEnd->format('Y-m-d H:i:s') . "'";
}

if ($reservationFilters['q'] !== '') {
    $queryEscaped = $connection->real_escape_string($reservationFilters['q']);
    $where[] = "(r.jmeno LIKE '%{$queryEscaped}%' OR r.email LIKE '%{$queryEscaped}%' OR r.telefon LIKE '%{$queryEscaped}%')";
}

$whereSql = implode(' AND ', $where);

$countQuery = $connection->query(
    "SELECT COUNT(*) AS total
     FROM rezervace r
     INNER JOIN sluzby s ON s.id = r.sluzba
     WHERE {$whereSql}"
);
if ($countQuery instanceof mysqli_result) {
    $countRow = $countQuery->fetch_assoc();
    $reservationPagination['total'] = (int) ($countRow['total'] ?? 0);
    $countQuery->free();
}

$reservationPagination['total_pages'] = max(
    1,
    (int) ceil($reservationPagination['total'] / max(1, $reservationFilters['per_page']))
);
if ($reservationFilters['page'] > $reservationPagination['total_pages']) {
    $reservationFilters['page'] = $reservationPagination['total_pages'];
}

$offset = ($reservationFilters['page'] - 1) * $reservationFilters['per_page'];

$reservationQuery = $connection->query(
    "SELECT r.id, r.sluzba AS service_id, r.jmeno, r.email, r.telefon, r.zdroj, r.poznamka_klienta, r.poznamka_admina, r.datum_cas, r.stav, r.cena_v_dobe_rezervace,
            r.duvod_zruseni, r.zruseno_kym, r.zruseno_uzivatel, r.zruseno_at, r.reminder_sent_at, s.nazev
     FROM rezervace r
     INNER JOIN sluzby s ON s.id = r.sluzba
     WHERE {$whereSql}
     ORDER BY r.datum_cas ASC
     LIMIT " . (int) $reservationFilters['per_page'] . '
     OFFSET ' . (int) $offset
);
if ($reservationQuery instanceof mysqli_result) {
    while ($row = $reservationQuery->fetch_assoc()) {
        $reservationRows[] = $row;
    }
    $reservationQuery->free();
}
