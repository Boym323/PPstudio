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
$leadSeconds = max(3600, (int) ($emailConfig['reservation_reminder_lead_seconds'] ?? 93600));
$windowSeconds = max(900, (int) ($emailConfig['reservation_reminder_window_seconds'] ?? 3600));
$windowStart = (new DateTimeImmutable('now'))->modify('+' . $leadSeconds . ' seconds')->format('Y-m-d H:i:s');
$windowEnd = (new DateTimeImmutable('now'))->modify('+' . ($leadSeconds + $windowSeconds) . ' seconds')->format('Y-m-d H:i:s');

try {
    $rows = reminderRunnerFetchReservations($connection, $windowStart, $windowEnd);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

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
        continue;
    }

    if ($dryRun) {
        $sent++;
        continue;
    }

    if (! sendReservationReminderEmail($emailConfig, $siteSettings, $reservation)) {
        $failed++;
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

if ($connection instanceof mysqli) {
    $connection->close();
} else {
    $connection = null;
}

echo sprintf(
    "Reservation reminders%s: sent=%d failed=%d skipped=%d window=%s..%s\n",
    $dryRun ? ' (dry-run)' : '',
    $sent,
    $failed,
    $skipped,
    $windowStart,
    $windowEnd
);
