<?php

if (! in_array($reminderLogFilters['severity'] ?? 'all', array_keys($reminderLogSeverityOptions), true)) {
    $reminderLogFilters['severity'] = 'all';
}

if (! in_array($reminderLogFilters['limit'] ?? 100, $reminderLogLimitOptions, true)) {
    $reminderLogFilters['limit'] = 100;
}

if (($reminderLogFilters['page'] ?? 0) < 1) {
    $reminderLogFilters['page'] = 1;
}

$reminderLogStats = [
    'total' => 0,
    'shown' => 0,
    'source' => 'db',
];
$reminderLogRows = [];
$reminderLogTableExists = false;
$reminderLogTableQuery = $connection->query("SHOW TABLES LIKE 'reservation_reminder_logs'");
if ($reminderLogTableQuery instanceof mysqli_result) {
    $reminderLogTableExists = (bool) $reminderLogTableQuery->fetch_row();
    $reminderLogTableQuery->free();
}

if (! $reminderLogTableExists) {
    $reminderLogStats['source'] = 'table_missing';
} else {
    $reminderEventTypeQuery = $connection->query(
        'SELECT DISTINCT event_type
         FROM reservation_reminder_logs
         ORDER BY event_type ASC
         LIMIT 200'
    );
    if ($reminderEventTypeQuery instanceof mysqli_result) {
        while ($row = $reminderEventTypeQuery->fetch_assoc()) {
            $eventType = trim((string) ($row['event_type'] ?? ''));
            if ($eventType !== '' && ! array_key_exists($eventType, $reminderLogEventOptions)) {
                $reminderLogEventOptions[$eventType] = $eventType;
            }
        }
        $reminderEventTypeQuery->free();
    }

    if (! in_array($reminderLogFilters['event'] ?? 'all', array_keys($reminderLogEventOptions), true)) {
        $reminderLogFilters['event'] = 'all';
    }

    $reminderConditions = ['1=1'];
    if ($reminderLogFilters['severity'] !== 'all') {
        $severityEscaped = $connection->real_escape_string($reminderLogFilters['severity']);
        $reminderConditions[] = "l.severity = '{$severityEscaped}'";
    }

    if ($reminderLogFilters['event'] !== 'all') {
        $eventEscaped = $connection->real_escape_string($reminderLogFilters['event']);
        $reminderConditions[] = "l.event_type = '{$eventEscaped}'";
    }

    if ($reminderLogFilters['q'] !== '') {
        $needleEscaped = $connection->real_escape_string($reminderLogFilters['q']);
        $reminderConditions[] = "(l.run_token LIKE '%{$needleEscaped}%'
            OR l.event_type LIKE '%{$needleEscaped}%'
            OR l.context_json LIKE '%{$needleEscaped}%')";
    }

    $reminderWhereSql = implode(' AND ', $reminderConditions);
    $reminderOffset = max(0, ($reminderLogFilters['page'] - 1) * $reminderLogFilters['limit']);

    $reminderCountQuery = $connection->query(
        "SELECT COUNT(*) AS total
         FROM reservation_reminder_logs l
         WHERE {$reminderWhereSql}"
    );
    if ($reminderCountQuery instanceof mysqli_result) {
        $countRow = $reminderCountQuery->fetch_assoc();
        $reminderLogStats['total'] = (int) ($countRow['total'] ?? 0);
        $reminderCountQuery->free();
    }

    $reminderRowsQuery = $connection->query(
        "SELECT l.created_at, l.run_token, l.event_type, l.severity, l.reservation_id, l.context_json,
                r.jmeno AS reservation_name, r.datum_cas AS reservation_datetime
         FROM reservation_reminder_logs l
         LEFT JOIN rezervace r ON r.id = l.reservation_id
         WHERE {$reminderWhereSql}
         ORDER BY l.created_at DESC
         LIMIT " . (int) $reminderLogFilters['limit'] . '
         OFFSET ' . $reminderOffset
    );
    if ($reminderRowsQuery instanceof mysqli_result) {
        while ($row = $reminderRowsQuery->fetch_assoc()) {
            $contextRaw = trim((string) ($row['context_json'] ?? ''));
            $contextString = $contextRaw;
            if ($contextRaw !== '') {
                $decodedContext = json_decode($contextRaw, true);
                if (is_array($decodedContext)) {
                    $contextString = (string) (json_encode($decodedContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                }
            }

            $reminderLogRows[] = [
                'time' => (string) ($row['created_at'] ?? ''),
                'run_token' => (string) ($row['run_token'] ?? ''),
                'event_type' => (string) ($row['event_type'] ?? ''),
                'severity' => (string) ($row['severity'] ?? ''),
                'reservation_id' => isset($row['reservation_id']) ? (int) $row['reservation_id'] : null,
                'reservation_name' => trim((string) ($row['reservation_name'] ?? '')),
                'reservation_datetime' => (string) ($row['reservation_datetime'] ?? ''),
                'context' => $contextString,
            ];
        }
        $reminderRowsQuery->free();
    }

    $reminderLogStats['shown'] = count($reminderLogRows);
    $reminderLogPagination['total_pages'] = max(1, (int) ceil(((int) $reminderLogStats['total']) / max(1, (int) $reminderLogFilters['limit'])));

    if ($reminderLogFilters['page'] > $reminderLogPagination['total_pages']) {
        $reminderLogFilters['page'] = $reminderLogPagination['total_pages'];
        $reminderOffset = max(0, ($reminderLogFilters['page'] - 1) * $reminderLogFilters['limit']);
        $reminderLogRows = [];

        $reminderRowsQuery = $connection->query(
            "SELECT l.created_at, l.run_token, l.event_type, l.severity, l.reservation_id, l.context_json,
                    r.jmeno AS reservation_name, r.datum_cas AS reservation_datetime
             FROM reservation_reminder_logs l
             LEFT JOIN rezervace r ON r.id = l.reservation_id
             WHERE {$reminderWhereSql}
             ORDER BY l.created_at DESC
             LIMIT " . (int) $reminderLogFilters['limit'] . '
             OFFSET ' . $reminderOffset
        );
        if ($reminderRowsQuery instanceof mysqli_result) {
            while ($row = $reminderRowsQuery->fetch_assoc()) {
                $contextRaw = trim((string) ($row['context_json'] ?? ''));
                $contextString = $contextRaw;
                if ($contextRaw !== '') {
                    $decodedContext = json_decode($contextRaw, true);
                    if (is_array($decodedContext)) {
                        $contextString = (string) (json_encode($decodedContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                    }
                }

                $reminderLogRows[] = [
                    'time' => (string) ($row['created_at'] ?? ''),
                    'run_token' => (string) ($row['run_token'] ?? ''),
                    'event_type' => (string) ($row['event_type'] ?? ''),
                    'severity' => (string) ($row['severity'] ?? ''),
                    'reservation_id' => isset($row['reservation_id']) ? (int) $row['reservation_id'] : null,
                    'reservation_name' => trim((string) ($row['reservation_name'] ?? '')),
                    'reservation_datetime' => (string) ($row['reservation_datetime'] ?? ''),
                    'context' => $contextString,
                ];
            }
            $reminderRowsQuery->free();
        }
        $reminderLogStats['shown'] = count($reminderLogRows);
    }
}

