<?php
declare(strict_types=1);

use PPStudio\Service\ReservationReminderService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Tento skript lze spouštět jen z CLI.\n";
    exit(1);
}

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/security_events.php';

function reminderRunnerConnect(): mysqli|PDO
{
    $dbConfig = \PPStudio\Database\DatabaseFactory::loadProjectConfig(['port' => 3306]);
    $host = (string) ($dbConfig['host'] ?? '127.0.0.1');
    $database = (string) ($dbConfig['database'] ?? '');
    $username = (string) ($dbConfig['username'] ?? '');
    $password = (string) ($dbConfig['password'] ?? '');
    $charset = (string) ($dbConfig['charset'] ?? 'utf8mb4');

    if (class_exists('mysqli')) {
        return \PPStudio\Database\DatabaseFactory::connect(['port' => 3306]);
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

$dryRun = in_array('--dry-run', $argv ?? [], true);

$emailConfig = require __DIR__ . '/config/email.php';

try {
    $connection = reminderRunnerConnect();
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

$siteSettings = reminderRunnerLoadSiteSettings($connection);
$reminderService = new ReservationReminderService($connection, $emailConfig);

try {
    $result = $reminderService->run($siteSettings, $dryRun);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

if ($connection instanceof mysqli) {
    $connection->close();
} else {
    $connection = null;
}

echo sprintf(
    "Reservation reminders%s: run=%s sent=%d failed=%d skipped=%d window=%s..%s\n",
    $dryRun ? ' (dry-run)' : '',
    $result['run_token'],
    $result['sent'],
    $result['failed'],
    $result['skipped'],
    $result['window_start'],
    $result['window_end']
);
