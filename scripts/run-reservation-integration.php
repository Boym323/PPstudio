#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Tento skript lze spouštět jen z CLI.\n";
    exit(1);
}

require_once __DIR__ . '/_test_helpers.php';
ppstudioCliTestBootstrapBase();
require dirname(__DIR__) . '/includes/availability.php';

const SCRIPT_PREFIX = '[reservation-integration]';

/**
 * @return array{is_worker: bool, token: string, service_id: int, date_time: string, status: string, start_at: float}
 */
function parseOptions(array $argv): array
{
    $options = getopt('', ['worker', 'token:', 'service-id:', 'date-time:', 'status:', 'start-at:']);

    return [
        'is_worker' => isset($options['worker']),
        'token' => (string) ($options['token'] ?? ''),
        'service_id' => (int) ($options['service-id'] ?? 0),
        'date_time' => (string) ($options['date-time'] ?? ''),
        'status' => (string) ($options['status'] ?? 'nova'),
        'start_at' => isset($options['start-at']) ? (float) $options['start-at'] : 0.0,
    ];
}

function assertOrFail(bool $condition, string $message): void
{
    ppstudioCliTestAssertTrue(SCRIPT_PREFIX, $condition, $message);
}

function connectDb(): mysqli
{
    return \PPStudio\Database\DatabaseFactory::connect();
}

/**
 * @return array{category_id:int,service_id:int,availability_ids:int[],date:string}
 */
function prepareFixture(mysqli $connection, string $token): array
{
    $categoryName = 'IT Category ' . $token;
    $serviceName = 'IT Service ' . $token;

    $statement = $connection->prepare('INSERT INTO kategorie (nazev, poradi, aktivni) VALUES (?, 9999, 1)');
    $statement->bind_param('s', $categoryName);
    $statement->execute();
    $categoryId = (int) $connection->insert_id;
    $statement->close();

    $duration = 60;
    $price = 1234.0;
    $description = 'Reservation integration test fixture';
    $statement = $connection->prepare('INSERT INTO sluzby (nazev, kategorie_id, popis, cena, doba_trvani, aktivni) VALUES (?, ?, ?, ?, ?, 1)');
    $statement->bind_param('sisdi', $serviceName, $categoryId, $description, $price, $duration);
    $statement->execute();
    $serviceId = (int) $connection->insert_id;
    $statement->close();

    $date = (new DateTimeImmutable('+365 days'))->format('Y-m-d');
    $availabilityIds = [];

    $windows = [
        [$date . ' 10:00:00', $date . ' 12:00:00', 'integration-basic:' . $token],
        [$date . ' 14:00:00', $date . ' 16:00:00', 'integration-concurrency:' . $token],
    ];

    $statement = $connection->prepare('INSERT INTO dostupnost (start_at, end_at, poznamka) VALUES (?, ?, ?)');
    foreach ($windows as [$startAt, $endAt, $note]) {
        $statement->bind_param('sss', $startAt, $endAt, $note);
        $statement->execute();
        $availabilityIds[] = (int) $connection->insert_id;
    }
    $statement->close();

    return [
        'category_id' => $categoryId,
        'service_id' => $serviceId,
        'availability_ids' => $availabilityIds,
        'date' => $date,
    ];
}

function cleanupFixture(mysqli $connection, string $token, ?int $serviceId, ?int $categoryId, array $availabilityIds): void
{
    try {
        $statement = $connection->prepare('DELETE FROM rezervace WHERE zdroj = ?');
        $source = 'integration:' . $token;
        $statement->bind_param('s', $source);
        $statement->execute();
        $statement->close();
    } catch (Throwable) {
    }

    if ($availabilityIds !== []) {
        $statement = $connection->prepare('DELETE FROM dostupnost WHERE id = ?');
        foreach ($availabilityIds as $availabilityId) {
            try {
                $statement->bind_param('i', $availabilityId);
                $statement->execute();
            } catch (Throwable) {
            }
        }
        $statement->close();
    }

    if ($serviceId !== null) {
        try {
            $statement = $connection->prepare('DELETE FROM sluzby WHERE id = ?');
            $statement->bind_param('i', $serviceId);
            $statement->execute();
            $statement->close();
        } catch (Throwable) {
        }
    }

    if ($categoryId !== null) {
        try {
            $statement = $connection->prepare('DELETE FROM kategorie WHERE id = ?');
            $statement->bind_param('i', $categoryId);
            $statement->execute();
            $statement->close();
        } catch (Throwable) {
        }
    }
}

/**
 * @return array{slot:string,date_time:string,times_count:int}
 */
function runBasicFlow(mysqli $connection, int $serviceId, string $date, string $token): array
{
    $times = getAvailableTimesForDate($connection, $serviceId, $date);
    assertOrFail(count($times) > 0, 'Ocekavan alespon jeden volny slot v prvnim okne.');

    $slot = (string) ($times[0]['value'] ?? '');
    assertOrFail($slot !== '', 'Prvni volny slot nema hodnotu value.');
    $slotDateTime = $date . ' ' . $slot . ':00';

    assertOrFail(isValidReservationSlot($connection, $serviceId, $slotDateTime), 'Slot ma byt validni pred rezervaci.');

    $firstReservation = createReservationWithLock(
        $connection,
        'IT Basic One',
        'it-basic-1@example.test',
        '777111222',
        'integration:' . $token,
        'basic flow first reservation',
        $serviceId,
        $slotDateTime,
        'nova'
    );
    assertOrFail(($firstReservation['status'] ?? '') === 'ok', 'Prvni rezervace v basic flow ma projit.');

    assertOrFail(! isValidReservationSlot($connection, $serviceId, $slotDateTime), 'Slot ma byt po rezervaci nevalidni.');

    $collisionReservation = createReservationWithLock(
        $connection,
        'IT Basic Two',
        'it-basic-2@example.test',
        '777111333',
        'integration:' . $token,
        'basic flow collision',
        $serviceId,
        $slotDateTime,
        'nova'
    );
    assertOrFail(($collisionReservation['status'] ?? '') === 'slot_unavailable', 'Kolizni rezervace ma byt odmitnuta.');

    $outsideReservation = createReservationWithLock(
        $connection,
        'IT Basic Three',
        'it-basic-3@example.test',
        '777111444',
        'integration:' . $token,
        'basic flow outside availability',
        $serviceId,
        $date . ' 13:00:00',
        'nova'
    );
    assertOrFail(($outsideReservation['status'] ?? '') === 'slot_unavailable', 'Rezervace mimo dostupnost ma byt odmitnuta.');

    return [
        'slot' => $slot,
        'date_time' => $slotDateTime,
        'times_count' => count($times),
    ];
}

/**
 * @return array{status:string,pid:int}
 */
function launchWorker(string $token, int $serviceId, string $dateTime, string $status, float $startAt): array
{
    $command = [
        PHP_BINARY,
        __FILE__,
        '--worker',
        '--token=' . $token,
        '--service-id=' . $serviceId,
        '--date-time=' . $dateTime,
        '--status=' . $status,
        '--start-at=' . sprintf('%.6F', $startAt),
    ];

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__));
    if (! is_resource($process)) {
        ppstudioCliTestFail(SCRIPT_PREFIX, 'Nepodarilo se spustit worker proces.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        ppstudioCliTestFail(SCRIPT_PREFIX, 'Worker selhal: ' . trim($stderr ?: $stdout));
    }

    $decoded = json_decode($stdout, true);
    if (! is_array($decoded)) {
        ppstudioCliTestFail(SCRIPT_PREFIX, 'Worker vratil nevalidni vystup: ' . trim($stdout));
    }

    return [
        'status' => (string) ($decoded['status'] ?? ''),
        'pid' => (int) ($decoded['pid'] ?? 0),
    ];
}

function runConcurrencyFlow(mysqli $connection, int $serviceId, string $date, string $token): array
{
    $times = getAvailableTimesForDate($connection, $serviceId, $date);
    $slot = '';

    foreach ($times as $candidate) {
        $value = (string) ($candidate['value'] ?? '');
        if ($value >= '14:00' && $value <= '15:00') {
            $slot = $value;
            break;
        }
    }

    assertOrFail($slot !== '', 'Nenasel se volny slot ve druhem okne (14:00-16:00).');
    $slotDateTime = $date . ' ' . $slot . ':00';
    assertOrFail(isValidReservationSlot($connection, $serviceId, $slotDateTime), 'Paralelni slot ma byt pred testem validni.');

    $startAt = microtime(true) + 2.0;

    $workerA = launchWorker($token, $serviceId, $slotDateTime, 'nova', $startAt);
    $workerB = launchWorker($token, $serviceId, $slotDateTime, 'nova', $startAt);

    $statuses = [$workerA['status'], $workerB['status']];
    sort($statuses);

    assertOrFail($statuses === ['ok', 'slot_unavailable'], 'Paralelni kolizni test musi vratit kombinaci ok + slot_unavailable.');

    return [
        'slot' => $slot,
        'date_time' => $slotDateTime,
        'worker_statuses' => $statuses,
    ];
}

function runWorkerMode(array $options): never
{
    $token = $options['token'];
    $serviceId = $options['service_id'];
    $dateTime = $options['date_time'];
    $status = $options['status'];
    $startAt = $options['start_at'];

    if ($token === '' || $serviceId <= 0 || $dateTime === '' || $startAt <= 0.0) {
        ppstudioCliTestFail(SCRIPT_PREFIX, 'Worker mode vyzaduje --token, --service-id, --date-time a --start-at.');
    }

    $connection = connectDb();

    try {
        while (microtime(true) < $startAt) {
            usleep(1000);
        }

        $pid = getmypid() ?: 0;
        $result = createReservationWithLock(
            $connection,
            'IT Parallel Worker',
            'it-parallel-' . $pid . '@example.test',
            '777222000',
            'integration:' . $token,
            'parallel worker',
            $serviceId,
            $dateTime,
            $status
        );

        echo json_encode([
            'status' => (string) ($result['status'] ?? 'unknown'),
            'pid' => $pid,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo PHP_EOL;
        exit(0);
    } finally {
        $connection->close();
    }
}

$options = parseOptions($argv);
if ($options['is_worker']) {
    runWorkerMode($options);
}

$connection = connectDb();
$token = 'it_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
$fixture = null;

try {
    $fixture = prepareFixture($connection, $token);

    $basic = runBasicFlow($connection, $fixture['service_id'], $fixture['date'], $token);
    $parallel = runConcurrencyFlow($connection, $fixture['service_id'], $fixture['date'], $token);

    echo SCRIPT_PREFIX . ' [OK] Integration scenario passed.' . PHP_EOL;
    echo SCRIPT_PREFIX . ' [OK] Token: ' . $token . PHP_EOL;
    echo SCRIPT_PREFIX . ' [OK] Date: ' . $fixture['date'] . PHP_EOL;
    echo SCRIPT_PREFIX . ' [OK] Basic slot: ' . $basic['slot'] . ' (slots count: ' . $basic['times_count'] . ')' . PHP_EOL;
    echo SCRIPT_PREFIX . ' [OK] Parallel slot: ' . $parallel['slot'] . PHP_EOL;
    echo SCRIPT_PREFIX . ' [OK] Parallel statuses: ' . implode(', ', $parallel['worker_statuses']) . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    ppstudioCliTestFail(SCRIPT_PREFIX, 'Exception: ' . $exception->getMessage());
} finally {
    if ($fixture !== null) {
        cleanupFixture(
            $connection,
            $token,
            $fixture['service_id'],
            $fixture['category_id'],
            $fixture['availability_ids']
        );
    }
    $connection->close();
}
