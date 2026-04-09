<?php

$serviceCategoryQuery = $connection->query(
    "SELECT c.id, c.nazev, c.poradi, c.aktivni, COUNT(s.id) AS services_count, SUM(CASE WHEN s.aktivni = 1 THEN 1 ELSE 0 END) AS active_services_count
     FROM kategorie c
     LEFT JOIN sluzby s ON s.kategorie_id = c.id
     GROUP BY c.id, c.nazev, c.poradi, c.aktivni
     ORDER BY COALESCE(c.poradi, 9999) ASC, c.nazev ASC"
);
if ($serviceCategoryQuery instanceof mysqli_result) {
    while ($row = $serviceCategoryQuery->fetch_assoc()) {
        $serviceCategoryRows[] = $row;
    }
    $serviceCategoryQuery->free();
}

$serviceCategoryFilterOptions = ['all' => 'Všechny kategorie'];
foreach ($serviceCategoryRows as $categoryRow) {
    $categoryId = (string) ($categoryRow['id'] ?? '');
    if ($categoryId === '') {
        continue;
    }
    $categoryLabel = (string) ($categoryRow['nazev'] ?? '');
    if ((int) ($categoryRow['aktivni'] ?? 1) !== 1) {
        $categoryLabel .= ' (neaktivní)';
    }
    $serviceCategoryFilterOptions[$categoryId] = $categoryLabel;
}

if (! in_array($serviceFilters['status'] ?? 'all', array_keys($serviceStatusFilterOptions), true)) {
    $serviceFilters['status'] = 'all';
}

if (! in_array($serviceFilters['category'] ?? 'all', array_keys($serviceCategoryFilterOptions), true)) {
    $serviceFilters['category'] = 'all';
}

$serviceWhere = ['1=1'];
if (($serviceFilters['status'] ?? 'all') === 'active') {
    $serviceWhere[] = 's.aktivni = 1';
} elseif (($serviceFilters['status'] ?? 'all') === 'inactive') {
    $serviceWhere[] = 's.aktivni = 0';
}

if (($serviceFilters['category'] ?? 'all') !== 'all') {
    $serviceWhere[] = 's.kategorie_id = ' . (int) $serviceFilters['category'];
}

if (($serviceFilters['q'] ?? '') !== '') {
    $serviceNeedle = $connection->real_escape_string($serviceFilters['q']);
    $serviceWhere[] = "(s.nazev LIKE '%{$serviceNeedle}%'
        OR s.popis LIKE '%{$serviceNeedle}%'
        OR c.nazev LIKE '%{$serviceNeedle}%')";
}

$serviceQuery = $connection->query(
    "SELECT s.id, s.nazev, s.kategorie_id, s.aktivni AS service_active, c.nazev AS kategorie, c.poradi AS kategorie_poradi, c.aktivni AS category_active, s.popis, s.cena, s.doba_trvani
     FROM sluzby s
     LEFT JOIN kategorie c ON c.id = s.kategorie_id
     WHERE " . implode(' AND ', $serviceWhere) . "
     ORDER BY COALESCE(c.poradi, 9999) ASC,
              COALESCE(NULLIF(c.nazev, ''), 'Ostatní služby') ASC,
              s.nazev ASC"
);
if ($serviceQuery instanceof mysqli_result) {
    while ($row = $serviceQuery->fetch_assoc()) {
        $serviceRows[] = $row;
    }
    $serviceQuery->free();
}

$servicePriceHistoryQuery = $connection->query(
    "SELECT h.id, h.sluzba_id, h.cena, h.platna_od, h.platna_do, s.nazev AS sluzba_nazev
     FROM historie_cen_sluzeb h
     INNER JOIN sluzby s ON s.id = h.sluzba_id
     ORDER BY h.platna_od DESC, h.id DESC
     LIMIT 200"
);
if ($servicePriceHistoryQuery instanceof mysqli_result) {
    while ($row = $servicePriceHistoryQuery->fetch_assoc()) {
        $servicePriceHistoryRows[] = $row;
    }
    $servicePriceHistoryQuery->free();
}

$availabilityQuery = $connection->query('SELECT id, start_at, end_at, poznamka FROM dostupnost WHERE end_at >= NOW() ORDER BY start_at ASC LIMIT 400');
if ($availabilityQuery instanceof mysqli_result) {
    while ($row = $availabilityQuery->fetch_assoc()) {
        $availabilityRows[] = $row;
    }
    $availabilityQuery->free();
}

if (! in_array($reservationFilters['status'], array_keys($reservationStatusFilterOptions), true)) {
    $reservationFilters['status'] = 'all';
}

if (! in_array($reservationFilters['period'], array_keys($reservationPeriodFilterOptions), true)) {
    $reservationFilters['period'] = 'all';
}

if (! in_array($reservationFilters['per_page'], $reservationPerPageOptions, true)) {
    $reservationFilters['per_page'] = 25;
}

if (! in_array($antispamFilters['reason'], array_keys($antispamReasonOptions), true)) {
    $antispamFilters['reason'] = 'all';
}

if (! in_array($antispamFilters['limit'], $antispamLimitOptions, true)) {
    $antispamFilters['limit'] = 100;
}

if (($antispamFilters['page'] ?? 0) < 1) {
    $antispamFilters['page'] = 1;
}

$antispamLogStats = [
    'total' => 0,
    'shown' => 0,
    'source' => 'db',
    'coverage' => 'all',
];
$antispamFilteredRows = [];

$securityEventsTableExists = false;
$securityEventsTableQuery = $connection->query("SHOW TABLES LIKE 'security_events'");
if ($securityEventsTableQuery instanceof mysqli_result) {
    $securityEventsTableExists = (bool) $securityEventsTableQuery->fetch_row();
    $securityEventsTableQuery->free();
}

if (! $securityEventsTableExists) {
    $antispamLogStats['source'] = 'file_fallback';
    $antispamLogStats['coverage'] = 'reservation_form_only';
    $antispamLogPath = function_exists('reservationAntispamLogPath')
        ? reservationAntispamLogPath()
        : dirname(__DIR__, 3) . '/var/security/reservation-antispam.log';

    if (is_file($antispamLogPath) && is_readable($antispamLogPath)) {
        $lines = @file($antispamLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            $reversedLines = array_reverse($lines);
            foreach ($reversedLines as $line) {
                $decoded = json_decode((string) $line, true);
                if (! is_array($decoded)) {
                    continue;
                }

                $reason = trim((string) ($decoded['reason'] ?? 'neznamy'));
                if ($reason !== '' && ! array_key_exists($reason, $antispamReasonOptions)) {
                    $antispamReasonOptions[$reason] = $reason;
                }

                if ($antispamFilters['reason'] !== 'all' && $reason !== $antispamFilters['reason']) {
                    continue;
                }

                $context = $decoded['context'] ?? [];
                $contextString = is_array($context) && $context !== []
                    ? (string) (json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
                    : '';

                if ($antispamFilters['q'] !== '') {
                    $needle = strtolower($antispamFilters['q']);
                    $haystack = strtolower(implode(' ', [
                        (string) ($decoded['time'] ?? ''),
                        $reason,
                        (string) ($decoded['ip'] ?? ''),
                        (string) ($decoded['ua'] ?? ''),
                        $contextString,
                    ]));
                    if (! str_contains($haystack, $needle)) {
                        continue;
                    }
                }

                $antispamFilteredRows[] = [
                    'time' => (string) ($decoded['time'] ?? ''),
                    'reason' => $reason !== '' ? $reason : 'neznamy',
                    'source' => 'reservation_form',
                    'ip' => trim((string) ($decoded['ip'] ?? '')),
                    'ua' => trim((string) ($decoded['ua'] ?? '')),
                    'context' => $contextString,
                ];
            }

            $antispamLogStats['total'] = count($antispamFilteredRows);
            $offset = max(0, ($antispamFilters['page'] - 1) * $antispamFilters['limit']);
            $antispamRows = array_slice($antispamFilteredRows, $offset, $antispamFilters['limit']);
        }
    }

    $antispamLogStats['shown'] = count($antispamRows);
} else {
$antispamTypeQuery = $connection->query(
    "SELECT DISTINCT event_type
     FROM security_events
     WHERE event_source IN ('reservation_form', 'admin_login', 'admin_lite_login', 'reservation_action')
     ORDER BY event_type ASC
     LIMIT 200"
);
if ($antispamTypeQuery instanceof mysqli_result) {
    while ($row = $antispamTypeQuery->fetch_assoc()) {
        $eventType = trim((string) ($row['event_type'] ?? ''));
        if ($eventType !== '' && ! array_key_exists($eventType, $antispamReasonOptions)) {
            $antispamReasonOptions[$eventType] = $eventType;
        }
    }
    $antispamTypeQuery->free();
}

$baseWhere = "event_source IN ('reservation_form', 'admin_login', 'admin_lite_login', 'reservation_action')";
$conditions = [$baseWhere];

if ($antispamFilters['reason'] !== 'all') {
    $reasonEscaped = $connection->real_escape_string($antispamFilters['reason']);
    $conditions[] = "event_type = '{$reasonEscaped}'";
}

if ($antispamFilters['q'] !== '') {
    $needleEscaped = $connection->real_escape_string($antispamFilters['q']);
    $conditions[] = "(event_type LIKE '%{$needleEscaped}%'
        OR event_source LIKE '%{$needleEscaped}%'
        OR ip_address LIKE '%{$needleEscaped}%'
        OR user_agent LIKE '%{$needleEscaped}%'
        OR context_json LIKE '%{$needleEscaped}%')";
}

$whereSqlEvents = implode(' AND ', $conditions);
$antispamOffset = max(0, ($antispamFilters['page'] - 1) * $antispamFilters['limit']);

$antispamCountQuery = $connection->query(
    "SELECT COUNT(*) AS total
     FROM security_events
     WHERE {$whereSqlEvents}"
);
if ($antispamCountQuery instanceof mysqli_result) {
    $countRow = $antispamCountQuery->fetch_assoc();
    $antispamLogStats['total'] = (int) ($countRow['total'] ?? 0);
    $antispamCountQuery->free();
}

$antispamRowsQuery = $connection->query(
    "SELECT created_at, event_type, event_source, ip_address, user_agent, context_json
     FROM security_events
     WHERE {$whereSqlEvents}
     ORDER BY created_at DESC
     LIMIT " . (int) $antispamFilters['limit'] . "
     OFFSET " . $antispamOffset
);
if ($antispamRowsQuery instanceof mysqli_result) {
    while ($row = $antispamRowsQuery->fetch_assoc()) {
        $contextRaw = trim((string) ($row['context_json'] ?? ''));
        $contextString = $contextRaw;
        if ($contextRaw !== '') {
            $decodedContext = json_decode($contextRaw, true);
            if (is_array($decodedContext)) {
                $contextString = (string) (json_encode($decodedContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
            }
        }

        $antispamRows[] = [
            'time' => (string) ($row['created_at'] ?? ''),
            'reason' => (string) ($row['event_type'] ?? ''),
            'source' => (string) ($row['event_source'] ?? ''),
            'ip' => (string) ($row['ip_address'] ?? ''),
            'ua' => (string) ($row['user_agent'] ?? ''),
            'context' => $contextString,
        ];
    }
    $antispamRowsQuery->free();
}

$antispamLogStats['shown'] = count($antispamRows);
}

$antispamPagination['total_pages'] = max(1, (int) ceil(((int) $antispamLogStats['total']) / max(1, (int) $antispamFilters['limit'])));
if ($antispamFilters['page'] > $antispamPagination['total_pages']) {
    $antispamFilters['page'] = $antispamPagination['total_pages'];
    if ($antispamLogStats['source'] === 'file_fallback') {
        $offset = max(0, ($antispamFilters['page'] - 1) * $antispamFilters['limit']);
        $antispamRows = array_slice($antispamFilteredRows, $offset, $antispamFilters['limit']);
        $antispamLogStats['shown'] = count($antispamRows);
    } elseif ($securityEventsTableExists) {
        $antispamOffset = max(0, ($antispamFilters['page'] - 1) * $antispamFilters['limit']);
        $antispamRows = [];
        $antispamRowsQuery = $connection->query(
            "SELECT created_at, event_type, event_source, ip_address, user_agent, context_json
             FROM security_events
             WHERE {$whereSqlEvents}
             ORDER BY created_at DESC
             LIMIT " . (int) $antispamFilters['limit'] . "
             OFFSET " . $antispamOffset
        );
        if ($antispamRowsQuery instanceof mysqli_result) {
            while ($row = $antispamRowsQuery->fetch_assoc()) {
                $contextRaw = trim((string) ($row['context_json'] ?? ''));
                $contextString = $contextRaw;
                if ($contextRaw !== '') {
                    $decodedContext = json_decode($contextRaw, true);
                    if (is_array($decodedContext)) {
                        $contextString = (string) (json_encode($decodedContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                    }
                }

                $antispamRows[] = [
                    'time' => (string) ($row['created_at'] ?? ''),
                    'reason' => (string) ($row['event_type'] ?? ''),
                    'source' => (string) ($row['event_source'] ?? ''),
                    'ip' => (string) ($row['ip_address'] ?? ''),
                    'ua' => (string) ($row['user_agent'] ?? ''),
                    'context' => $contextString,
                ];
            }
            $antispamRowsQuery->free();
        }
        $antispamLogStats['shown'] = count($antispamRows);
    }
}

$where = ['1=1'];

if ($reservationFilters['status'] !== 'all') {
    $statusEscaped = $connection->real_escape_string($reservationFilters['status']);
    $where[] = "r.stav = '{$statusEscaped}'";
}

if ($reservationFilters['period'] === 'today') {
    $where[] = 'DATE(r.datum_cas) = CURDATE()';
} elseif ($reservationFilters['period'] === 'week') {
    $where[] = 'YEARWEEK(r.datum_cas, 1) = YEARWEEK(CURDATE(), 1)';
} elseif ($reservationFilters['period'] === 'month') {
    $where[] = 'YEAR(r.datum_cas) = YEAR(CURDATE()) AND MONTH(r.datum_cas) = MONTH(CURDATE())';
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
            r.duvod_zruseni, r.zruseno_kym, r.zruseno_uzivatel, r.zruseno_at, s.nazev
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

$statsQuery = $connection->query(
    'SELECT
        (SELECT COUNT(*) FROM rezervace WHERE stav = "nova") AS new_reservations,
        (SELECT COUNT(*) FROM rezervace WHERE datum_cas >= NOW() AND stav IN ("nova", "potvrzena")) AS upcoming_reservations,
        (SELECT COUNT(*) FROM dostupnost WHERE end_at >= NOW()) AS availability_windows,
        (SELECT COUNT(*) FROM sluzby WHERE aktivni = 1) AS services_total,
        (SELECT COUNT(*) FROM rezervace WHERE DATE(datum_cas) = CURDATE() AND stav IN ("nova", "potvrzena", "dokoncena")) AS today_reservations,
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
     WHERE DATE(r.datum_cas) = CURDATE()
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
     WHERE DATE(r.datum_cas) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
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

$profileMedia = loadMediaByCategory($connection, 'profile', 1);
$galleryMedia = loadMediaByCategory($connection, 'gallery', 30);
$certificateFiles = loadCertificateUploads(__DIR__ . '/../../../uploads', '/uploads', 'cert_');

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

$voucherTableQuery = $connection->query("SHOW TABLES LIKE 'poukazy'");
if ($voucherTableQuery instanceof mysqli_result) {
    $voucherModuleReady = (bool) $voucherTableQuery->fetch_row();
    $voucherTableQuery->free();
}

if ($voucherModuleReady) {
    $voucherQuery = $connection->query(
        'SELECT p.id, p.kod, p.puvodni_hodnota, p.zustatek, p.status, p.issued_at, p.expires_at, p.recipient_name, p.note, p.updated_at,
                CASE
                    WHEN p.status = "storno" THEN "storno"
                    WHEN p.expires_at IS NOT NULL AND p.expires_at < CURDATE() THEN "expirovan"
                    WHEN p.zustatek <= 0 THEN "vycerpan"
                    ELSE "aktivni"
                END AS effective_status
         FROM poukazy p
         ORDER BY p.created_at DESC, p.id DESC
         LIMIT 300'
    );
    if ($voucherQuery instanceof mysqli_result) {
        while ($row = $voucherQuery->fetch_assoc()) {
            $voucherRows[] = $row;
        }
        $voucherQuery->free();
    }

    if ($voucherRows !== []) {
        $voucherIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $voucherRows);
        $voucherIds = array_values(array_filter($voucherIds, static fn(int $id): bool => $id > 0));
        $reservationIdsFromTransactions = [];

        if ($voucherIds !== []) {
            $idList = implode(',', $voucherIds);
            $voucherTxQuery = $connection->query(
                "SELECT id, poukaz_id, castka, typ, rezervace_id, poznamka, created_at
                 FROM poukaz_cerpani
                 WHERE poukaz_id IN ({$idList})
                 ORDER BY created_at DESC, id DESC
                 LIMIT 1200"
            );
            if ($voucherTxQuery instanceof mysqli_result) {
                while ($row = $voucherTxQuery->fetch_assoc()) {
                    $voucherId = (int) ($row['poukaz_id'] ?? 0);
                    if ($voucherId <= 0) {
                        continue;
                    }
                    if (! isset($voucherTransactionsByVoucher[$voucherId])) {
                        $voucherTransactionsByVoucher[$voucherId] = [];
                    }
                    if (count($voucherTransactionsByVoucher[$voucherId]) >= 12) {
                        continue;
                    }
                    $reservationId = (int) ($row['rezervace_id'] ?? 0);
                    if ($reservationId > 0) {
                        $reservationIdsFromTransactions[$reservationId] = $reservationId;
                    }
                    $voucherTransactionsByVoucher[$voucherId][] = $row;
                }
                $voucherTxQuery->free();
            }

            if ($reservationIdsFromTransactions !== []) {
                $reservationIdList = implode(',', array_values($reservationIdsFromTransactions));
                $voucherReservationLookupQuery = $connection->query(
                    "SELECT r.id, r.jmeno, r.datum_cas, s.nazev AS sluzba_nazev
                     FROM rezervace r
                     LEFT JOIN sluzby s ON s.id = r.sluzba
                     WHERE r.id IN ({$reservationIdList})"
                );
                if ($voucherReservationLookupQuery instanceof mysqli_result) {
                    while ($lookupRow = $voucherReservationLookupQuery->fetch_assoc()) {
                        $lookupId = (int) ($lookupRow['id'] ?? 0);
                        if ($lookupId <= 0) {
                            continue;
                        }
                        $voucherReservationLookup[$lookupId] = [
                            'jmeno' => (string) ($lookupRow['jmeno'] ?? ''),
                            'datum_cas' => (string) ($lookupRow['datum_cas'] ?? ''),
                            'sluzba_nazev' => (string) ($lookupRow['sluzba_nazev'] ?? ''),
                        ];
                    }
                    $voucherReservationLookupQuery->free();
                }
            }
        }
    }

    $voucherReservationsQuery = $connection->query(
        'SELECT r.id, r.jmeno, r.telefon, r.datum_cas, s.nazev AS service_name, COALESCE(r.cena_v_dobe_rezervace, s.cena, 0) AS reservation_price
         FROM rezervace r
         LEFT JOIN sluzby s ON s.id = r.sluzba
         WHERE r.stav IN ("nova", "potvrzena", "dokoncena")
           AND r.datum_cas >= DATE_SUB(NOW(), INTERVAL 90 DAY)
         ORDER BY r.datum_cas DESC
         LIMIT 250'
    );
    if ($voucherReservationsQuery instanceof mysqli_result) {
        while ($row = $voucherReservationsQuery->fetch_assoc()) {
            $voucherReservationOptions[] = $row;
        }
        $voucherReservationsQuery->free();
    }
}
