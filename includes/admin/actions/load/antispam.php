<?php

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
        : dirname(__DIR__, 4) . '/var/security/reservation-antispam.log';

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
