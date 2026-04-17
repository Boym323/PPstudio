<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use mysqli;
use mysqli_result;

final class AdminSecurityLogDataLoader
{
    public function __construct(
        private mysqli $connection
    ) {
    }

    /**
     * @param array{reason?:string,q?:string,limit?:int,page?:int} $filters
     * @param array<string, string> $reasonOptions
     * @param int[] $limitOptions
     * @return array{
     *     antispam_rows: array<int, array<string, mixed>>,
     *     antispam_log_stats: array{total:int, shown:int, source:string, coverage:string},
     *     antispam_reason_options: array<string, string>,
     *     antispam_limit_options: int[],
     *     antispam_filters: array{reason:string,q:string,limit:int,page:int},
     *     antispam_pagination: array{total_pages:int}
     * }
     */
    public function loadAntispam(array $filters, array $reasonOptions, array $limitOptions): array
    {
        $filters = [
            'reason' => (string) ($filters['reason'] ?? 'all'),
            'q' => trim((string) ($filters['q'] ?? '')),
            'limit' => (int) ($filters['limit'] ?? 100),
            'page' => max(1, (int) ($filters['page'] ?? 1)),
        ];

        if (! in_array($filters['reason'], array_keys($reasonOptions), true)) {
            $filters['reason'] = 'all';
        }

        if (! in_array($filters['limit'], $limitOptions, true)) {
            $filters['limit'] = 100;
        }

        $stats = [
            'total' => 0,
            'shown' => 0,
            'source' => 'db',
            'coverage' => 'all',
        ];
        $rows = [];
        $filteredRows = [];

        if (! $this->tableExists('security_events')) {
            $stats['source'] = 'file_fallback';
            $stats['coverage'] = 'reservation_form_only';

            $logPath = (new \PPStudio\Security\SecurityFacade())->reservationAntispamService()->logPath();
            if (is_file($logPath) && is_readable($logPath)) {
                $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach (array_reverse($lines) as $line) {
                        $decoded = json_decode((string) $line, true);
                        if (! is_array($decoded)) {
                            continue;
                        }

                        $reason = trim((string) ($decoded['reason'] ?? 'neznamy'));
                        if ($reason !== '' && ! array_key_exists($reason, $reasonOptions)) {
                            $reasonOptions[$reason] = $reason;
                        }

                        if ($filters['reason'] !== 'all' && $reason !== $filters['reason']) {
                            continue;
                        }

                        $contextString = $this->normalizeJsonValue($decoded['context'] ?? []);
                        if ($filters['q'] !== '') {
                            $needle = strtolower($filters['q']);
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

                        $filteredRows[] = [
                            'time' => (string) ($decoded['time'] ?? ''),
                            'reason' => $reason !== '' ? $reason : 'neznamy',
                            'source' => 'reservation_form',
                            'ip' => trim((string) ($decoded['ip'] ?? '')),
                            'ua' => trim((string) ($decoded['ua'] ?? '')),
                            'context' => $contextString,
                        ];
                    }
                }
            }

            $stats['total'] = count($filteredRows);
            $rows = array_slice($filteredRows, max(0, ($filters['page'] - 1) * $filters['limit']), $filters['limit']);
            $stats['shown'] = count($rows);

            $totalPages = max(1, (int) ceil($stats['total'] / max(1, $filters['limit'])));
            if ($filters['page'] > $totalPages) {
                $filters['page'] = $totalPages;
                $rows = array_slice($filteredRows, max(0, ($filters['page'] - 1) * $filters['limit']), $filters['limit']);
                $stats['shown'] = count($rows);
            }
        } else {
            $this->extendAntispamReasonOptions($reasonOptions);
            [$total, $rows] = $this->loadAntispamRowsFromDatabase($filters, $reasonOptions);
            $stats['total'] = $total;
            $stats['shown'] = count($rows);

            $totalPages = max(1, (int) ceil($stats['total'] / max(1, $filters['limit'])));
            if ($filters['page'] > $totalPages) {
                $filters['page'] = $totalPages;
                [$total, $rows] = $this->loadAntispamRowsFromDatabase($filters, $reasonOptions);
                $stats['total'] = $total;
                $stats['shown'] = count($rows);
            }
        }

        $pagination = [
            'total_pages' => max(1, (int) ceil($stats['total'] / max(1, $filters['limit']))),
        ];

        return [
            'antispam_rows' => $rows,
            'antispam_log_stats' => $stats,
            'antispam_reason_options' => $reasonOptions,
            'antispam_limit_options' => $limitOptions,
            'antispam_filters' => $filters,
            'antispam_pagination' => $pagination,
        ];
    }

    /**
     * @param array{event?:string,severity?:string,q?:string,limit?:int,page?:int} $filters
     * @param array<string, string> $eventOptions
     * @param array<string, string> $severityOptions
     * @param int[] $limitOptions
     * @return array{
     *     reminder_log_rows: array<int, array<string, mixed>>,
     *     reminder_log_stats: array{total:int, shown:int, source:string},
     *     reminder_log_event_options: array<string, string>,
     *     reminder_log_severity_options: array<string, string>,
     *     reminder_log_limit_options: int[],
     *     reminder_log_filters: array{q:string,event:string,severity:string,limit:int,page:int},
     *     reminder_log_pagination: array{total_pages:int}
     * }
     */
    public function loadReminderLogs(
        array $filters,
        array $eventOptions,
        array $severityOptions,
        array $limitOptions
    ): array {
        $filters = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'event' => (string) ($filters['event'] ?? 'all'),
            'severity' => (string) ($filters['severity'] ?? 'all'),
            'limit' => (int) ($filters['limit'] ?? 100),
            'page' => max(1, (int) ($filters['page'] ?? 1)),
        ];

        if (! in_array($filters['severity'], array_keys($severityOptions), true)) {
            $filters['severity'] = 'all';
        }

        if (! in_array($filters['limit'], $limitOptions, true)) {
            $filters['limit'] = 100;
        }

        $stats = [
            'total' => 0,
            'shown' => 0,
            'source' => 'db',
        ];
        $rows = [];

        if (! $this->tableExists('reservation_reminder_logs')) {
            $stats['source'] = 'table_missing';
            return [
                'reminder_log_rows' => $rows,
                'reminder_log_stats' => $stats,
                'reminder_log_event_options' => $eventOptions,
                'reminder_log_severity_options' => $severityOptions,
                'reminder_log_limit_options' => $limitOptions,
                'reminder_log_filters' => $filters,
                'reminder_log_pagination' => ['total_pages' => 1],
            ];
        }

        $this->extendReminderEventOptions($eventOptions);
        if (! in_array($filters['event'], array_keys($eventOptions), true)) {
            $filters['event'] = 'all';
        }

        [$total, $rows] = $this->loadReminderRowsFromDatabase($filters, $eventOptions);
        $stats['total'] = $total;
        $stats['shown'] = count($rows);

        $pagination = [
            'total_pages' => max(1, (int) ceil($stats['total'] / max(1, $filters['limit']))),
        ];
        if ($filters['page'] > $pagination['total_pages']) {
            $filters['page'] = $pagination['total_pages'];
            [$total, $rows] = $this->loadReminderRowsFromDatabase($filters, $eventOptions);
            $stats['total'] = $total;
            $stats['shown'] = count($rows);
            $pagination['total_pages'] = max(1, (int) ceil($stats['total'] / max(1, $filters['limit'])));
        }

        return [
            'reminder_log_rows' => $rows,
            'reminder_log_stats' => $stats,
            'reminder_log_event_options' => $eventOptions,
            'reminder_log_severity_options' => $severityOptions,
            'reminder_log_limit_options' => $limitOptions,
            'reminder_log_filters' => $filters,
            'reminder_log_pagination' => $pagination,
        ];
    }

    private function tableExists(string $tableName): bool
    {
        $query = $this->connection->query("SHOW TABLES LIKE '" . $this->connection->real_escape_string($tableName) . "'");
        if (! $query instanceof mysqli_result) {
            return false;
        }

        $exists = (bool) $query->fetch_row();
        $query->free();

        return $exists;
    }

    /**
     * @param array{reason:string,q:string,limit:int,page:int} $filters
     * @param array<string, string> $reasonOptions
     * @return array{0:int,1:array<int, array<string, mixed>>}
     */
    private function loadAntispamRowsFromDatabase(array $filters, array &$reasonOptions): array
    {
        $baseWhere = "event_source IN ('reservation_form', 'admin_login', 'admin_lite_login', 'reservation_action')";
        $conditions = [$baseWhere];

        if ($filters['reason'] !== 'all') {
            $reasonEscaped = $this->connection->real_escape_string($filters['reason']);
            $conditions[] = "event_type = '{$reasonEscaped}'";
        }

        if ($filters['q'] !== '') {
            $needleEscaped = $this->connection->real_escape_string($filters['q']);
            $conditions[] = "(event_type LIKE '%{$needleEscaped}%'
                OR event_source LIKE '%{$needleEscaped}%'
                OR ip_address LIKE '%{$needleEscaped}%'
                OR user_agent LIKE '%{$needleEscaped}%'
                OR context_json LIKE '%{$needleEscaped}%')";
        }

        $whereSql = implode(' AND ', $conditions);
        $offset = max(0, ($filters['page'] - 1) * $filters['limit']);

        $countQuery = $this->connection->query(
            "SELECT COUNT(*) AS total
             FROM security_events
             WHERE {$whereSql}"
        );
        $total = 0;
        if ($countQuery instanceof mysqli_result) {
            $countRow = $countQuery->fetch_assoc();
            $total = (int) ($countRow['total'] ?? 0);
            $countQuery->free();
        }

        $rows = [];
        $rowsQuery = $this->connection->query(
            "SELECT created_at, event_type, event_source, ip_address, user_agent, context_json
             FROM security_events
             WHERE {$whereSql}
             ORDER BY created_at DESC
             LIMIT " . (int) $filters['limit'] . "
             OFFSET " . $offset
        );
        if ($rowsQuery instanceof mysqli_result) {
            while ($row = $rowsQuery->fetch_assoc()) {
                $contextRaw = trim((string) ($row['context_json'] ?? ''));
                $contextString = $this->normalizeContextJson($contextRaw);

                $rows[] = [
                    'time' => (string) ($row['created_at'] ?? ''),
                    'reason' => (string) ($row['event_type'] ?? ''),
                    'source' => (string) ($row['event_source'] ?? ''),
                    'ip' => (string) ($row['ip_address'] ?? ''),
                    'ua' => (string) ($row['user_agent'] ?? ''),
                    'context' => $contextString,
                ];
            }
            $rowsQuery->free();
        }

        return [$total, $rows];
    }

    /**
     * @param array{event:string,severity:string,q:string,limit:int,page:int} $filters
     * @param array<string, string> $eventOptions
     * @return array{0:int,1:array<int, array<string, mixed>>}
     */
    private function loadReminderRowsFromDatabase(array $filters, array $eventOptions): array
    {
        $conditions = ['1=1'];

        if ($filters['severity'] !== 'all') {
            $severityEscaped = $this->connection->real_escape_string($filters['severity']);
            $conditions[] = "l.severity = '{$severityEscaped}'";
        }

        if ($filters['event'] !== 'all') {
            $eventEscaped = $this->connection->real_escape_string($filters['event']);
            $conditions[] = "l.event_type = '{$eventEscaped}'";
        }

        if ($filters['q'] !== '') {
            $needleEscaped = $this->connection->real_escape_string($filters['q']);
            $conditions[] = "(l.run_token LIKE '%{$needleEscaped}%'
                OR l.event_type LIKE '%{$needleEscaped}%'
                OR l.context_json LIKE '%{$needleEscaped}%')";
        }

        $whereSql = implode(' AND ', $conditions);
        $offset = max(0, ($filters['page'] - 1) * $filters['limit']);

        $countQuery = $this->connection->query(
            "SELECT COUNT(*) AS total
             FROM reservation_reminder_logs l
             WHERE {$whereSql}"
        );
        $total = 0;
        if ($countQuery instanceof mysqli_result) {
            $countRow = $countQuery->fetch_assoc();
            $total = (int) ($countRow['total'] ?? 0);
            $countQuery->free();
        }

        $rows = [];
        $rowsQuery = $this->connection->query(
            "SELECT l.created_at, l.run_token, l.event_type, l.severity, l.reservation_id, l.context_json,
                    r.jmeno AS reservation_name, r.datum_cas AS reservation_datetime
             FROM reservation_reminder_logs l
             LEFT JOIN rezervace r ON r.id = l.reservation_id
             WHERE {$whereSql}
             ORDER BY l.created_at DESC
             LIMIT " . (int) $filters['limit'] . '
             OFFSET ' . $offset
        );
        if ($rowsQuery instanceof mysqli_result) {
            while ($row = $rowsQuery->fetch_assoc()) {
                $contextRaw = trim((string) ($row['context_json'] ?? ''));
                $contextString = $this->normalizeContextJson($contextRaw);

                $rows[] = [
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
            $rowsQuery->free();
        }

        return [$total, $rows];
    }

    /**
     * @param array<string, string> $reasonOptions
     */
    private function extendAntispamReasonOptions(array &$reasonOptions): void
    {
        $query = $this->connection->query(
            "SELECT DISTINCT event_type
             FROM security_events
             WHERE event_source IN ('reservation_form', 'admin_login', 'admin_lite_login', 'reservation_action')
             ORDER BY event_type ASC
             LIMIT 200"
        );
        if (! $query instanceof mysqli_result) {
            return;
        }

        while ($row = $query->fetch_assoc()) {
            $eventType = trim((string) ($row['event_type'] ?? ''));
            if ($eventType !== '' && ! array_key_exists($eventType, $reasonOptions)) {
                $reasonOptions[$eventType] = $eventType;
            }
        }

        $query->free();
    }

    /**
     * @param array<string, string> $eventOptions
     */
    private function extendReminderEventOptions(array &$eventOptions): void
    {
        $query = $this->connection->query(
            'SELECT DISTINCT event_type
             FROM reservation_reminder_logs
             ORDER BY event_type ASC
             LIMIT 200'
        );
        if (! $query instanceof mysqli_result) {
            return;
        }

        while ($row = $query->fetch_assoc()) {
            $eventType = trim((string) ($row['event_type'] ?? ''));
            if ($eventType !== '' && ! array_key_exists($eventType, $eventOptions)) {
                $eventOptions[$eventType] = $eventType;
            }
        }

        $query->free();
    }

    private function normalizeJsonValue(mixed $value): string
    {
        if (! is_array($value) || $value === []) {
            return '';
        }

        return (string) (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    private function normalizeContextJson(string $contextRaw): string
    {
        if ($contextRaw === '') {
            return '';
        }

        $decodedContext = json_decode($contextRaw, true);
        if (! is_array($decodedContext)) {
            return $contextRaw;
        }

        return (string) (json_encode($decodedContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }
}
