<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Tento skript lze spouštět jen z CLI.\n";
    exit(1);
}

require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/security_events.php';
require __DIR__ . '/includes/mailer.php';

function reminderRunnerConnect(array $dbConfig): mysqli|PDO
{
    $host = (string) ($dbConfig['host'] ?? '127.0.0.1');
    $database = (string) ($dbConfig['database'] ?? '');
    $username = (string) ($dbConfig['username'] ?? '');
    $password = (string) ($dbConfig['password'] ?? '');
    $charset = (string) ($dbConfig['charset'] ?? 'utf8mb4');

    if (class_exists('mysqli')) {
        $connection = @new mysqli($host, $username, $password, $database, 3306);
        if ($connection->connect_error) {
            throw new RuntimeException('DB connection failed: ' . $connection->connect_error);
        }

        if ($charset !== '') {
            $connection->set_charset($charset);
        }

        return $connection;
    }

    if (class_exists('PDO') && extension_loaded('pdo_mysql')) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $database, $charset !== '' ? $charset : 'utf8mb4');
        return new PDO(
            $dsn,
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    throw new RuntimeException('CLI PHP nemá MySQL driver (`mysqli` ani `pdo_mysql`). Spusťte skript přes stejnou PHP binárku, kterou používá Web Station.');
}

function reminderRunnerLoadSiteSettings(mysqli|PDO $connection): array
{
    $settings = defaultSiteSettings();

    if ($connection instanceof mysqli) {
        $query = $connection->query('SELECT setting_key, setting_value FROM nastaveni');
        if ($query instanceof mysqli_result) {
            while ($row = $query->fetch_assoc()) {
                $key = (string) ($row['setting_key'] ?? '');
                if ($key !== '') {
                    $settings[$key] = (string) ($row['setting_value'] ?? '');
                }
            }
            $query->free();
        }

        return $settings;
    }

    $statement = $connection->query('SELECT setting_key, setting_value FROM nastaveni');
    foreach ($statement->fetchAll() as $row) {
        $key = (string) ($row['setting_key'] ?? '');
        if ($key !== '') {
            $settings[$key] = (string) ($row['setting_value'] ?? '');
        }
    }

    return $settings;
}

function reminderRunnerFetchReservations(mysqli|PDO $connection, string $windowStart, string $windowEnd): array
{
    $sql = 'SELECT r.id, r.sluzba, r.jmeno, r.email, r.telefon, r.poznamka_klienta, r.poznamka_admina, r.datum_cas, r.stav,
                   r.cena_v_dobe_rezervace, r.reminder_sent_at, s.nazev, s.doba_trvani
            FROM rezervace r
            INNER JOIN sluzby s ON s.id = r.sluzba
            WHERE r.stav = "potvrzena"
              AND r.email <> ""
              AND r.reminder_sent_at IS NULL
              AND r.datum_cas >= ?
              AND r.datum_cas < ?
            ORDER BY r.datum_cas ASC';

    if ($connection instanceof mysqli) {
        $statement = $connection->prepare($sql);
        if (! $statement) {
            throw new RuntimeException('Prepare failed: ' . $connection->error);
        }

        $statement->bind_param('ss', $windowStart, $windowEnd);
        $statement->execute();
        $result = $statement->get_result();
        $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        $statement->close();

        return is_array($rows) ? $rows : [];
    }

    $statement = $connection->prepare($sql);
    $statement->execute([$windowStart, $windowEnd]);
    $rows = $statement->fetchAll();

    return is_array($rows) ? $rows : [];
}

function reminderRunnerMarkSent(mysqli|PDO $connection, int $reservationId): void
{
    $sql = 'UPDATE rezervace SET reminder_sent_at = NOW() WHERE id = ? AND reminder_sent_at IS NULL LIMIT 1';

    if ($connection instanceof mysqli) {
        $statement = $connection->prepare($sql);
        if (! $statement) {
            return;
        }
        $statement->bind_param('i', $reservationId);
        $statement->execute();
        $statement->close();
        return;
    }

    $statement = $connection->prepare($sql);
    $statement->execute([$reservationId]);
}

function reminderRunnerRunToken(): string
{
    try {
        return date('YmdHis') . '-' . bin2hex(random_bytes(6));
    } catch (Throwable) {
        return date('YmdHis') . '-' . uniqid('', true);
    }
}

function reminderRunnerContextJson(array $context): string
{
    return (string) (json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
}

function reminderRunnerEnsureLogTable(mysqli|PDO $connection): void
{
    $sql = 'CREATE TABLE IF NOT EXISTS reservation_reminder_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                run_token VARCHAR(64) NOT NULL,
                event_type VARCHAR(100) NOT NULL,
                severity ENUM(\'info\', \'warning\', \'error\') NOT NULL DEFAULT \'info\',
                reservation_id INT UNSIGNED NULL,
                context_json TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_reminder_logs_run_token_created (run_token, created_at),
                KEY idx_reminder_logs_event_created (event_type, created_at),
                KEY idx_reminder_logs_reservation_created (reservation_id, created_at),
                CONSTRAINT fk_reminder_logs_reservation
                    FOREIGN KEY (reservation_id) REFERENCES rezervace(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL
            )';

    try {
        if ($connection instanceof mysqli) {
            $connection->query($sql);
            return;
        }

        $connection->exec($sql);
    } catch (Throwable) {
        // Pokud DB uzivatel nema prava na DDL, runner pokracuje bez DB audit logu.
    }
}

function reminderRunnerLogEvent(
    mysqli|PDO $connection,
    string $runToken,
    string $eventType,
    string $severity = 'info',
    ?int $reservationId = null,
    ?array $context = null
): void {
    $contextJson = reminderRunnerContextJson(is_array($context) ? $context : []);

    try {
        if ($connection instanceof mysqli) {
            if ($reservationId === null) {
                $statement = $connection->prepare(
                    'INSERT INTO reservation_reminder_logs (run_token, event_type, severity, reservation_id, context_json)
                     VALUES (?, ?, ?, NULL, ?)'
                );
                if (! $statement) {
                    return;
                }
                $statement->bind_param('ssss', $runToken, $eventType, $severity, $contextJson);
                $statement->execute();
                $statement->close();
                return;
            }

            $statement = $connection->prepare(
                'INSERT INTO reservation_reminder_logs (run_token, event_type, severity, reservation_id, context_json)
                 VALUES (?, ?, ?, ?, ?)'
            );
            if (! $statement) {
                return;
            }
            $statement->bind_param('sssis', $runToken, $eventType, $severity, $reservationId, $contextJson);
            $statement->execute();
            $statement->close();
            return;
        }

        if ($reservationId === null) {
            $statement = $connection->prepare(
                'INSERT INTO reservation_reminder_logs (run_token, event_type, severity, reservation_id, context_json)
                 VALUES (?, ?, ?, NULL, ?)'
            );
            $statement->execute([$runToken, $eventType, $severity, $contextJson]);
            return;
        }

        $statement = $connection->prepare(
            'INSERT INTO reservation_reminder_logs (run_token, event_type, severity, reservation_id, context_json)
             VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([$runToken, $eventType, $severity, $reservationId, $contextJson]);
    } catch (Throwable) {
        // Logging nesmí nikdy zastavit odesílání reminderů.
    }
}

function reminderRunnerCleanupLogs(mysqli|PDO $connection): void
{
    $sql = 'DELETE FROM reservation_reminder_logs
            WHERE created_at < (NOW() - INTERVAL 90 DAY)
            ORDER BY created_at ASC
            LIMIT 500';

    try {
        if ($connection instanceof mysqli) {
            $statement = $connection->prepare($sql);
            if (! $statement) {
                return;
            }
            $statement->execute();
            $statement->close();
            return;
        }

        $statement = $connection->prepare($sql);
        $statement->execute();
    } catch (Throwable) {
        // Cleanup je best-effort, nesmí shodit běh.
    }
}

$dryRun = in_array('--dry-run', $argv ?? [], true);

$dbConfig = require __DIR__ . '/config/database.php';
$emailConfig = require __DIR__ . '/config/email.php';

try {
    $connection = reminderRunnerConnect($dbConfig);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

$siteSettings = reminderRunnerLoadSiteSettings($connection);
reminderRunnerEnsureLogTable($connection);
$leadSeconds = max(3600, (int) ($emailConfig['reservation_reminder_lead_seconds'] ?? 93600));
$windowSeconds = max(900, (int) ($emailConfig['reservation_reminder_window_seconds'] ?? 3600));
$runToken = reminderRunnerRunToken();
$windowStart = (new DateTimeImmutable('now'))->modify('+' . $leadSeconds . ' seconds')->format('Y-m-d H:i:s');
$windowEnd = (new DateTimeImmutable('now'))->modify('+' . ($leadSeconds + $windowSeconds) . ' seconds')->format('Y-m-d H:i:s');

reminderRunnerLogEvent(
    $connection,
    $runToken,
    'run_started',
    'info',
    null,
    [
        'dry_run' => $dryRun,
        'lead_seconds' => $leadSeconds,
        'window_seconds' => $windowSeconds,
        'window_start' => $windowStart,
        'window_end' => $windowEnd,
    ]
);

try {
    $rows = reminderRunnerFetchReservations($connection, $windowStart, $windowEnd);
} catch (Throwable $exception) {
    reminderRunnerLogEvent(
        $connection,
        $runToken,
        'run_query_failed',
        'error',
        null,
        [
            'error' => $exception->getMessage(),
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
        ]
    );
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

reminderRunnerLogEvent(
    $connection,
    $runToken,
    'run_candidates_loaded',
    'info',
    null,
    [
        'candidates' => count($rows),
    ]
);

$sent = 0;
$failed = 0;
$skipped = 0;

foreach ($rows as $row) {
    $reservation = [
        'id' => (int) ($row['id'] ?? 0),
        'service_id' => (int) ($row['sluzba'] ?? 0),
        'jmeno' => (string) ($row['jmeno'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'telefon' => (string) ($row['telefon'] ?? ''),
        'poznamka_klienta' => $row['poznamka_klienta'] ?? null,
        'poznamka_admina' => $row['poznamka_admina'] ?? null,
        'datum_cas' => (string) ($row['datum_cas'] ?? ''),
        'stav' => (string) ($row['stav'] ?? ''),
        'service_price' => isset($row['cena_v_dobe_rezervace']) ? (float) $row['cena_v_dobe_rezervace'] : null,
        'reminder_sent_at' => $row['reminder_sent_at'] ?? null,
        'service_name' => (string) ($row['nazev'] ?? ''),
        'service_duration' => isset($row['doba_trvani']) ? (int) $row['doba_trvani'] : null,
    ];

    if ((string) $reservation['email'] === '') {
        $skipped++;
        reminderRunnerLogEvent(
            $connection,
            $runToken,
            'reservation_skipped_missing_email',
            'warning',
            $reservation['id'],
            [
                'reservation_datetime' => $reservation['datum_cas'],
            ]
        );
        continue;
    }

    if ($dryRun) {
        $sent++;
        reminderRunnerLogEvent(
            $connection,
            $runToken,
            'reservation_dry_run_candidate',
            'info',
            $reservation['id'],
            [
                'email' => $reservation['email'],
                'reservation_datetime' => $reservation['datum_cas'],
            ]
        );
        continue;
    }

    if (! sendReservationReminderEmail($emailConfig, $siteSettings, $reservation)) {
        $failed++;
        reminderRunnerLogEvent(
            $connection,
            $runToken,
            'reservation_send_failed',
            'warning',
            $reservation['id'],
            [
                'reservation_datetime' => $reservation['datum_cas'],
                'email' => $reservation['email'],
            ]
        );
        securityEventLog(
            'reservation_reminder_failed',
            'reservation_reminder',
            'warning',
            [
                'reservation_id' => $reservation['id'],
                'reservation_datetime' => $reservation['datum_cas'],
                'email' => $reservation['email'],
            ],
            'system',
            'cli'
        );
        continue;
    }

    reminderRunnerMarkSent($connection, $reservation['id']);
    $sent++;
    reminderRunnerLogEvent(
        $connection,
        $runToken,
        'reservation_sent',
        'info',
        $reservation['id'],
        [
            'reservation_datetime' => $reservation['datum_cas'],
            'email' => $reservation['email'],
        ]
    );

    securityEventLog(
        'reservation_reminder_sent',
        'reservation_reminder',
        'info',
        [
            'reservation_id' => $reservation['id'],
            'reservation_datetime' => $reservation['datum_cas'],
            'email' => $reservation['email'],
        ],
        'system',
        'cli'
    );
}

reminderRunnerLogEvent(
    $connection,
    $runToken,
    'run_finished',
    'info',
    null,
    [
        'dry_run' => $dryRun,
        'sent' => $sent,
        'failed' => $failed,
        'skipped' => $skipped,
        'candidates' => count($rows),
        'window_start' => $windowStart,
        'window_end' => $windowEnd,
    ]
);
reminderRunnerCleanupLogs($connection);

if ($connection instanceof mysqli) {
    $connection->close();
} else {
    $connection = null;
}

echo sprintf(
    "Reservation reminders%s: run=%s sent=%d failed=%d skipped=%d window=%s..%s\n",
    $dryRun ? ' (dry-run)' : '',
    $runToken,
    $sent,
    $failed,
    $skipped,
    $windowStart,
    $windowEnd
);
