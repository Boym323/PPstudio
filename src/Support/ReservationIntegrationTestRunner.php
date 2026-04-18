<?php
declare(strict_types=1);

namespace PPStudio\Support;

use DateTimeImmutable;
use mysqli;
use mysqli_result;
use PPStudio\Database\DatabaseFactory;
use PPStudio\Service\AvailabilityFacade;
use Throwable;

final class ReservationIntegrationTestRunner
{
    public function __construct(
        private readonly string $scriptPrefix = '[reservation-integration]'
    ) {
    }

    /**
     * @param array<int, string> $argv
     */
    public function run(array $argv): int
    {
        $options = $this->parseOptions($argv);
        if ($options['is_worker']) {
            return $this->runWorkerMode($options);
        }

        $connection = $this->connectDb();
        $token = 'it_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
        $fixture = null;

        try {
            $fixture = $this->prepareFixture($connection, $token);

            $basic = $this->runBasicFlow($connection, $fixture['service_id'], $fixture['date'], $token);
            $parallel = $this->runConcurrencyFlow($connection, $fixture['service_id'], $fixture['date'], $token);

            echo $this->scriptPrefix . ' [OK] Integration scenario passed.' . PHP_EOL;
            echo $this->scriptPrefix . ' [OK] Token: ' . $token . PHP_EOL;
            echo $this->scriptPrefix . ' [OK] Date: ' . $fixture['date'] . PHP_EOL;
            echo $this->scriptPrefix . ' [OK] Basic slot: ' . $basic['slot'] . ' (slots count: ' . $basic['times_count'] . ')' . PHP_EOL;
            echo $this->scriptPrefix . ' [OK] Parallel slot: ' . $parallel['slot'] . PHP_EOL;
            echo $this->scriptPrefix . ' [OK] Parallel statuses: ' . implode(', ', $parallel['worker_statuses']) . PHP_EOL;

            return 0;
        } catch (Throwable $exception) {
            CliTestSupport::fail($this->scriptPrefix, 'Exception: ' . $exception->getMessage());
        } finally {
            if ($fixture !== null) {
                $this->cleanupFixture(
                    $connection,
                    $token,
                    $fixture['service_id'],
                    $fixture['category_id'],
                    $fixture['availability_ids']
                );
            }

            $connection->close();
        }
    }

    /**
     * @param array<int, string> $argv
     * @return array{is_worker: bool, token: string, service_id: int, date_time: string, status: string, start_at: float}
     */
    private function parseOptions(array $argv): array
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

    private function assertOrFail(bool $condition, string $message): void
    {
        CliTestSupport::assertTrue($this->scriptPrefix, $condition, $message);
    }

    private function connectDb(): mysqli
    {
        return DatabaseFactory::connect();
    }

    private function findIsolatedFixtureDate(mysqli $connection): string
    {
        $statement = $connection->prepare(
            'SELECT COUNT(*) AS total
             FROM dostupnost
             WHERE start_at >= ? AND start_at < ?'
        );

        $startDate = new DateTimeImmutable('+365 days');

        for ($offset = 0; $offset < 180; $offset++) {
            $currentDate = $startDate->modify('+' . $offset . ' days');
            $date = $currentDate->format('Y-m-d');
            $dayStart = $date . ' 00:00:00';
            $dayEnd = $currentDate->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

            $statement->bind_param('ss', $dayStart, $dayEnd);
            $statement->execute();
            $result = $statement->get_result();
            $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
            $total = (int) ($row['total'] ?? 0);
            if ($result instanceof mysqli_result) {
                $result->free();
            }

            if ($total === 0) {
                $statement->close();

                return $date;
            }
        }

        $statement->close();

        CliTestSupport::fail($this->scriptPrefix, 'Nepodarilo se najit prazdny den pro integracni fixture.');
    }

    /**
     * @return array{category_id:int,service_id:int,availability_ids:int[],date:string}
     */
    private function prepareFixture(mysqli $connection, string $token): array
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

        $date = $this->findIsolatedFixtureDate($connection);
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

    /**
     * @param array<int, int> $availabilityIds
     */
    private function cleanupFixture(mysqli $connection, string $token, ?int $serviceId, ?int $categoryId, array $availabilityIds): void
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
    private function runBasicFlow(mysqli $connection, int $serviceId, string $date, string $token): array
    {
        $availabilityFacade = new AvailabilityFacade();
        $times = $availabilityFacade->getAvailableTimesForDate($connection, $serviceId, $date);
        $this->assertOrFail(count($times) > 0, 'Ocekavan alespon jeden volny slot v prvnim okne.');

        $slot = (string) ($times[0]['value'] ?? '');
        $this->assertOrFail($slot !== '', 'Prvni volny slot nema hodnotu value.');
        $slotDateTime = $date . ' ' . $slot . ':00';

        $this->assertOrFail($availabilityFacade->isValidReservationSlot($connection, $serviceId, $slotDateTime), 'Slot ma byt validni pred rezervaci.');

        $firstReservation = $availabilityFacade->createReservationWithLock(
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
        $this->assertOrFail(($firstReservation['status'] ?? '') === 'ok', 'Prvni rezervace v basic flow ma projit.');

        $this->assertOrFail(! $availabilityFacade->isValidReservationSlot($connection, $serviceId, $slotDateTime), 'Slot ma byt po rezervaci nevalidni.');

        $collisionReservation = $availabilityFacade->createReservationWithLock(
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
        $this->assertOrFail(($collisionReservation['status'] ?? '') === 'slot_unavailable', 'Kolizni rezervace ma byt odmitnuta.');

        $outsideReservation = $availabilityFacade->createReservationWithLock(
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
        $this->assertOrFail(($outsideReservation['status'] ?? '') === 'slot_unavailable', 'Rezervace mimo dostupnost ma byt odmitnuta.');

        return [
            'slot' => $slot,
            'date_time' => $slotDateTime,
            'times_count' => count($times),
        ];
    }

    /**
     * @return array{status:string,pid:int}
     */
    private function launchWorker(string $token, int $serviceId, string $dateTime, string $status, float $startAt): array
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/scripts/run-reservation-integration.php',
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

        $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__, 2));
        if (! is_resource($process)) {
            CliTestSupport::fail($this->scriptPrefix, 'Nepodarilo se spustit worker proces.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            CliTestSupport::fail($this->scriptPrefix, 'Worker selhal: ' . trim($stderr ?: $stdout));
        }

        $decoded = json_decode($stdout, true);
        if (! is_array($decoded)) {
            CliTestSupport::fail($this->scriptPrefix, 'Worker vratil nevalidni vystup: ' . trim($stdout));
        }

        return [
            'status' => (string) ($decoded['status'] ?? ''),
            'pid' => (int) ($decoded['pid'] ?? 0),
        ];
    }

    /**
     * @return array{slot:string,date_time:string,worker_statuses:array<int, string>}
     */
    private function runConcurrencyFlow(mysqli $connection, int $serviceId, string $date, string $token): array
    {
        $availabilityFacade = new AvailabilityFacade();
        $times = $availabilityFacade->getAvailableTimesForDate($connection, $serviceId, $date);
        $slot = '';

        foreach ($times as $candidate) {
            $value = (string) ($candidate['value'] ?? '');
            if ($value >= '14:00' && $value <= '15:00') {
                $slot = $value;
                break;
            }
        }

        $this->assertOrFail($slot !== '', 'Nenasel se volny slot ve druhem okne (14:00-16:00).');
        $slotDateTime = $date . ' ' . $slot . ':00';
        $this->assertOrFail($availabilityFacade->isValidReservationSlot($connection, $serviceId, $slotDateTime), 'Paralelni slot ma byt pred testem validni.');

        $startAt = microtime(true) + 2.0;

        $workerA = $this->launchWorker($token, $serviceId, $slotDateTime, 'nova', $startAt);
        $workerB = $this->launchWorker($token, $serviceId, $slotDateTime, 'nova', $startAt);

        $statuses = [$workerA['status'], $workerB['status']];
        sort($statuses);

        $this->assertOrFail($statuses === ['ok', 'slot_unavailable'], 'Paralelni kolizni test musi vratit kombinaci ok + slot_unavailable.');

        return [
            'slot' => $slot,
            'date_time' => $slotDateTime,
            'worker_statuses' => $statuses,
        ];
    }

    /**
     * @param array{is_worker: bool, token: string, service_id: int, date_time: string, status: string, start_at: float} $options
     */
    private function runWorkerMode(array $options): int
    {
        $token = $options['token'];
        $serviceId = $options['service_id'];
        $dateTime = $options['date_time'];
        $status = $options['status'];
        $startAt = $options['start_at'];

        if ($token === '' || $serviceId <= 0 || $dateTime === '' || $startAt <= 0.0) {
            CliTestSupport::fail($this->scriptPrefix, 'Worker mode vyzaduje --token, --service-id, --date-time a --start-at.');
        }

        $connection = $this->connectDb();

        try {
            $availabilityFacade = new AvailabilityFacade();
            while (microtime(true) < $startAt) {
                usleep(1000);
            }

            $pid = getmypid() ?: 0;
            $result = $availabilityFacade->createReservationWithLock(
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

            return 0;
        } finally {
            $connection->close();
        }
    }
}
