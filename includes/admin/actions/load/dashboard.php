<?php

$todayBounds = sqlDayBounds(date('Y-m-d'));
$tomorrowBounds = sqlDayBounds((new DateTimeImmutable('tomorrow'))->format('Y-m-d'));
$todayRangeSql = $todayBounds !== null
    ? "datum_cas >= '{$todayBounds['start']}' AND datum_cas < '{$todayBounds['end']}'"
    : '1=0';

$statsQuery = $connection->query(
    'SELECT
        (SELECT COUNT(*) FROM rezervace WHERE stav = "nova") AS new_reservations,
        (SELECT COUNT(*) FROM rezervace WHERE datum_cas >= NOW() AND stav IN ("nova", "potvrzena")) AS upcoming_reservations,
        (SELECT COUNT(*) FROM dostupnost WHERE end_at >= NOW()) AS availability_windows,
        (SELECT COUNT(*) FROM sluzby WHERE aktivni = 1) AS services_total,
        (SELECT COUNT(*) FROM rezervace WHERE ' . $todayRangeSql . ' AND stav IN ("nova", "potvrzena", "dokoncena")) AS today_reservations,
        (SELECT COUNT(*) FROM rezervace WHERE datum_cas >= NOW() AND stav = "nova") AS pending_reservations,
        (
            SELECT COALESCE(ROUND(AVG(COALESCE(r.cena_v_dobe_rezervace, s.cena))), 0)
            FROM rezervace r
            INNER JOIN sluzby s ON s.id = r.sluzba
            WHERE r.datum_cas >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND r.stav IN ("nova", "potvrzena", "dokoncena")
        ) AS avg_ticket_30d,
        (
            SELECT COUNT(*)
            FROM rezervace
            WHERE datum_cas >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND stav IN ("nova", "potvrzena", "dokoncena")
        ) AS active_reservations_30d,
        (
            SELECT COUNT(*)
            FROM rezervace
            WHERE datum_cas >= DATE_SUB(NOW(), INTERVAL 60 DAY)
              AND datum_cas < DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND stav IN ("nova", "potvrzena", "dokoncena")
        ) AS active_reservations_prev_30d'
);
if ($statsQuery instanceof mysqli_result) {
    $dashboardStats = $statsQuery->fetch_assoc() ?: $dashboardStats;
    $statsQuery->free();
}

$current30d = (int) ($dashboardStats['active_reservations_30d'] ?? 0);
$previous30d = (int) ($dashboardStats['active_reservations_prev_30d'] ?? 0);
if ($previous30d > 0) {
    $dashboardStats['active_reservations_trend_pct'] = (int) round((($current30d - $previous30d) / $previous30d) * 100);
} elseif ($current30d > 0) {
    $dashboardStats['active_reservations_trend_pct'] = 100;
} else {
    $dashboardStats['active_reservations_trend_pct'] = 0;
}

$upcomingQuery = $connection->query(
    'SELECT r.datum_cas, r.jmeno, r.stav, s.nazev
     FROM rezervace r
     INNER JOIN sluzby s ON s.id = r.sluzba
     WHERE r.datum_cas >= NOW()
       AND r.stav IN ("nova", "potvrzena")
     ORDER BY r.datum_cas ASC
     LIMIT 6'
);
if ($upcomingQuery instanceof mysqli_result) {
    while ($row = $upcomingQuery->fetch_assoc()) {
        $dashboardUpcomingReservations[] = $row;
    }
    $upcomingQuery->free();
}

$dashboardTodayQuery = $connection->query(
    'SELECT r.id, r.datum_cas, r.jmeno, r.stav, s.nazev
     FROM rezervace r
     INNER JOIN sluzby s ON s.id = r.sluzba
     WHERE ' . ($todayBounds !== null
        ? "r.datum_cas >= '{$todayBounds['start']}' AND r.datum_cas < '{$todayBounds['end']}'"
        : '1=0') . '
       AND r.stav IN ("nova", "potvrzena", "dokoncena")
     ORDER BY r.datum_cas ASC
     LIMIT 6'
);
if ($dashboardTodayQuery instanceof mysqli_result) {
    while ($row = $dashboardTodayQuery->fetch_assoc()) {
        $dashboardTodayReservations[] = $row;
    }
    $dashboardTodayQuery->free();
}

$dashboardTomorrowQuery = $connection->query(
    'SELECT r.id, r.datum_cas, r.jmeno, r.stav, s.nazev
     FROM rezervace r
     INNER JOIN sluzby s ON s.id = r.sluzba
     WHERE ' . ($tomorrowBounds !== null
        ? "r.datum_cas >= '{$tomorrowBounds['start']}' AND r.datum_cas < '{$tomorrowBounds['end']}'"
        : '1=0') . '
       AND r.stav IN ("nova", "potvrzena", "dokoncena")
     ORDER BY r.datum_cas ASC
     LIMIT 6'
);
if ($dashboardTomorrowQuery instanceof mysqli_result) {
    while ($row = $dashboardTomorrowQuery->fetch_assoc()) {
        $dashboardTomorrowReservations[] = $row;
    }
    $dashboardTomorrowQuery->free();
}

$dashboardPendingQuery = $connection->query(
    'SELECT r.id, r.datum_cas, r.jmeno, r.email, r.telefon, s.nazev
     FROM rezervace r
     INNER JOIN sluzby s ON s.id = r.sluzba
     WHERE r.datum_cas >= NOW()
       AND r.stav = "nova"
     ORDER BY r.datum_cas ASC
     LIMIT 6'
);
if ($dashboardPendingQuery instanceof mysqli_result) {
    while ($row = $dashboardPendingQuery->fetch_assoc()) {
        $dashboardPendingReservationRows[] = $row;
    }
    $dashboardPendingQuery->free();
}

if ($securityEventsTableExists) {
    $recentReservationChangesQuery = $connection->query(
        "SELECT created_at, event_type, context_json
         FROM security_events
         WHERE event_type IN (
            'reservation_admin_rescheduled',
            'reservation_admin_cancelled',
            'reservation_customer_rescheduled',
            'reservation_customer_cancelled'
         )
         ORDER BY created_at DESC
         LIMIT 6"
    );
    if ($recentReservationChangesQuery instanceof mysqli_result) {
        while ($row = $recentReservationChangesQuery->fetch_assoc()) {
            $context = json_decode((string) ($row['context_json'] ?? ''), true);
            if (! is_array($context)) {
                $context = [];
            }

            $eventType = (string) ($row['event_type'] ?? '');
            $changeTypeLabel = match ($eventType) {
                'reservation_admin_rescheduled' => 'Přesunula jste rezervaci',
                'reservation_customer_rescheduled' => 'Klientka přesunula rezervaci',
                'reservation_admin_cancelled' => 'Zrušila jste rezervaci',
                'reservation_customer_cancelled' => 'Klientka zrušila rezervaci',
                default => 'Změna rezervace',
            };

            $dashboardRecentReservationChanges[] = [
                'time' => (string) ($row['created_at'] ?? ''),
                'label' => $changeTypeLabel,
                'reservation_id' => (int) ($context['reservation_id'] ?? 0),
                'old_datetime' => (string) ($context['old_datetime'] ?? ''),
                'new_datetime' => (string) ($context['new_datetime'] ?? ''),
                'cancel_reason' => trim((string) ($context['cancel_reason'] ?? '')),
                'cancelled_by' => trim((string) ($context['cancelled_by'] ?? '')),
                'event_type' => $eventType,
            ];
        }
        $recentReservationChangesQuery->free();
    }
}

$topServicesQuery = $connection->query(
    'SELECT s.nazev, COUNT(*) AS reservations_count
     FROM rezervace r
     INNER JOIN sluzby s ON s.id = r.sluzba
     WHERE r.datum_cas >= DATE_SUB(NOW(), INTERVAL 30 DAY)
       AND r.stav IN ("nova", "potvrzena", "dokoncena")
     GROUP BY s.id, s.nazev
     ORDER BY reservations_count DESC, s.nazev ASC
     LIMIT 5'
);
if ($topServicesQuery instanceof mysqli_result) {
    while ($row = $topServicesQuery->fetch_assoc()) {
        $dashboardTopServices[] = $row;
    }
    $topServicesQuery->free();
}

$topCategoriesQuery = $connection->query(
    'SELECT COALESCE(NULLIF(c.nazev, ""), "Ostatní služby") AS category_name, COUNT(*) AS reservations_count
     FROM rezervace r
     INNER JOIN sluzby s ON s.id = r.sluzba
     LEFT JOIN kategorie c ON c.id = s.kategorie_id
     WHERE r.datum_cas >= DATE_SUB(NOW(), INTERVAL 30 DAY)
       AND r.stav IN ("nova", "potvrzena", "dokoncena")
     GROUP BY c.id, category_name
     ORDER BY reservations_count DESC, category_name ASC
     LIMIT 5'
);
if ($topCategoriesQuery instanceof mysqli_result) {
    while ($row = $topCategoriesQuery->fetch_assoc()) {
        $dashboardTopCategories[] = $row;
    }
    $topCategoriesQuery->free();
}

$statusBreakdownQuery = $connection->query(
    'SELECT stav, COUNT(*) AS total
     FROM rezervace
     WHERE datum_cas >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY stav'
);
if ($statusBreakdownQuery instanceof mysqli_result) {
    while ($row = $statusBreakdownQuery->fetch_assoc()) {
        $status = (string) ($row['stav'] ?? '');
        if ($status !== '' && array_key_exists($status, $dashboardStatusBreakdown)) {
            $dashboardStatusBreakdown[$status] = (int) ($row['total'] ?? 0);
        }
    }
    $statusBreakdownQuery->free();
}

$slotSizeSeconds = 30 * 60;
$nowTimestamp = time();
$todayStart = new DateTimeImmutable('today');
$tomorrowStart = $todayStart->modify('+1 day');
$todayStartSql = $todayStart->format('Y-m-d H:i:s');
$tomorrowStartSql = $tomorrowStart->format('Y-m-d H:i:s');
$availableSlots = [];
$bookedSlots = [];

$availabilityTodayStatement = $connection->prepare(
    'SELECT start_at, end_at
     FROM dostupnost
     WHERE start_at < ?
       AND end_at > ?'
);
if ($availabilityTodayStatement) {
    $availabilityTodayStatement->bind_param('ss', $tomorrowStartSql, $todayStartSql);
    $availabilityTodayStatement->execute();
    $availabilityTodayStatement->bind_result($windowStartAt, $windowEndAt);

    while ($availabilityTodayStatement->fetch()) {
        if (! is_string($windowStartAt) || ! is_string($windowEndAt) || $windowStartAt === '' || $windowEndAt === '') {
            continue;
        }

        $startTs = strtotime($windowStartAt);
        $endTs = strtotime($windowEndAt);
        if ($startTs === false || $endTs === false || $endTs <= $startTs) {
            continue;
        }

        $startTs = max($startTs, $todayStart->getTimestamp(), $nowTimestamp);
        $endTs = min($endTs, $tomorrowStart->getTimestamp());
        if ($endTs <= $startTs) {
            continue;
        }

        $cursorTs = (int) (ceil($startTs / $slotSizeSeconds) * $slotSizeSeconds);
        while ($cursorTs < $endTs) {
            $availableSlots[(string) $cursorTs] = true;
            $cursorTs += $slotSizeSeconds;
        }
    }

    $availabilityTodayStatement->close();
}

$bookedTodayStatement = $connection->prepare(
    'SELECT r.datum_cas, s.doba_trvani
     FROM rezervace r
     INNER JOIN sluzby s ON s.id = r.sluzba
     WHERE r.datum_cas >= ?
       AND r.datum_cas < ?
       AND r.stav IN ("nova", "potvrzena", "dokoncena")'
);
if ($bookedTodayStatement) {
    $bookedTodayStatement->bind_param('ss', $todayStartSql, $tomorrowStartSql);
    $bookedTodayStatement->execute();
    $bookedTodayStatement->bind_result($bookedStartAtRow, $bookedDurationRow);

    while ($bookedTodayStatement->fetch()) {
        if (! is_string($bookedStartAtRow) || $bookedStartAtRow === '') {
            continue;
        }

        $bookingStartTs = strtotime($bookedStartAtRow);
        if ($bookingStartTs === false) {
            continue;
        }

        $durationSeconds = max(15, (int) $bookedDurationRow) * 60;
        $bookingEndTs = $bookingStartTs + $durationSeconds;
        $bookingStartTs = max($bookingStartTs, $nowTimestamp, $todayStart->getTimestamp());
        $bookingEndTs = min($bookingEndTs, $tomorrowStart->getTimestamp());

        if ($bookingEndTs <= $bookingStartTs) {
            continue;
        }

        $cursorTs = (int) (floor($bookingStartTs / $slotSizeSeconds) * $slotSizeSeconds);
        while ($cursorTs < $bookingEndTs) {
            $bookedSlots[(string) $cursorTs] = true;
            $cursorTs += $slotSizeSeconds;
        }
    }

    $bookedTodayStatement->close();
}

$dashboardStats['free_slots_today'] = count(array_diff_key($availableSlots, $bookedSlots));

$todayReservationsCount = (int) ($dashboardStats['today_reservations'] ?? 0);
$tomorrowReservationsCount = count($dashboardTomorrowReservations);
$pendingReservationsCount = (int) ($dashboardStats['pending_reservations'] ?? 0);
$freeSlotsTodayCount = (int) ($dashboardStats['free_slots_today'] ?? 0);

if ($pendingReservationsCount > 0) {
    $dashboardAttentionItems[] = [
        'tone' => 'warning',
        'title' => 'Čekají nové rezervace k potvrzení',
        'text' => $pendingReservationsCount === 1
            ? 'Máte 1 novou rezervaci, která čeká na potvrzení.'
            : 'Máte ' . $pendingReservationsCount . ' nových rezervací, které čekají na potvrzení.',
    ];
}

if ($todayReservationsCount === 0) {
    $dashboardAttentionItems[] = [
        'tone' => 'info',
        'title' => 'Dnes zatím není žádná rezervace',
        'text' => 'Zkontrolujte dostupnost a případně uvolněte termíny pro dnešní den.',
    ];
} elseif ($freeSlotsTodayCount > 0) {
    $dashboardAttentionItems[] = [
        'tone' => 'success',
        'title' => 'Dnes ještě zbývají volné sloty',
        'text' => $freeSlotsTodayCount . ' volných 30min slotů je stále k dispozici pro rychlé doobjednání.',
    ];
}

if ($tomorrowReservationsCount === 0) {
    $dashboardAttentionItems[] = [
        'tone' => 'info',
        'title' => 'Zítřek je zatím prázdný',
        'text' => 'Na zítřek zatím nevidím žádnou rezervaci. Může se hodit zkontrolovat dostupnost nebo propagaci volných termínů.',
    ];
}

if ($dashboardRecentReservationChanges !== []) {
    $latestChange = $dashboardRecentReservationChanges[0];
    $dashboardAttentionItems[] = [
        'tone' => 'neutral',
        'title' => 'Poslední změna v rezervacích',
        'text' => trim((string) ($latestChange['label'] ?? 'Změna rezervace')) . ' ' . mb_strtolower(formatCzechDateTime((string) ($latestChange['time'] ?? ''))),
    ];
}
