<?php
declare(strict_types=1);

namespace PPStudio\Support;

use DateTimeImmutable;
use mysqli;
use mysqli_result;
use PPStudio\Database\DatabaseFactory;
use PPStudio\Repository\AvailabilityRepository;
use PPStudio\Repository\ReservationRepository;
use PPStudio\Repository\ServiceRepository;
use PPStudio\Service\AdminManualReservationCreateUseCase;
use PPStudio\Service\AdminReservationUpdateUseCase;
use PPStudio\Service\AvailabilityService;
use PPStudio\Service\ReservationNotificationService;
use PPStudio\Service\ReservationService;
use Throwable;

final class AdminReservationUsecaseTestRunner
{
    public function __construct(
        private readonly string $scriptPrefix = '[admin-reservation-usecase-tests]'
    ) {
    }

    public function run(): int
    {
        $storageDir = CliTestSupport::tempSecurityStorageDir($this->scriptPrefix, 'ppstudio-admin-reservation-usecase-');
        $previousEnv = CliTestSupport::setEnv([
            'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
            'PPSTUDIO_EMAIL_ENABLED' => '0',
            'HTTP_HOST' => 'reservation-usecase-tests.local',
            'HTTPS' => 'off',
        ]);

        $connection = $this->connectDb();
        $token = 'ar_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
        $fixture = null;

        try {
            $fixture = $this->prepareFixture($connection, $token);
            $reservationRepository = new ReservationRepository($connection);
            $reservationService = $this->buildReservationService($connection);
            $notificationService = new ReservationNotificationService(['enabled' => false]);
            $updateUseCase = new AdminReservationUpdateUseCase(
                $connection,
                [],
                $reservationRepository,
                $reservationService,
                $notificationService
            );
            $createUseCase = new AdminManualReservationCreateUseCase(
                [],
                $reservationService,
                $notificationService
            );

            $confirmId = $this->insertReservation(
                $connection,
                'aut:' . $token . ':c1',
                $fixture['service_id'],
                $fixture['date'] . ' 10:00:00',
                'nova',
                'Confirm Test',
                'confirm-' . $token . '@example.test'
            );
            $confirmResult = $updateUseCase->handle([
                'reservation_id' => $confirmId,
                'stav' => 'potvrzena',
                'poznamka_admina' => 'Potvrzeno administrací',
                'datum_cas' => $fixture['date'] . 'T10:00',
            ], [
                'ppstudio_admin_authenticated' => true,
                'ppstudio_admin_username' => 'admin-review',
            ]);
            CliTestSupport::assertTrue($this->scriptPrefix, (bool) ($confirmResult['success'] ?? false), 'Potvrzeni rezervace ma probehnout uspesne.');
            $confirmRow = $this->findReservationRow($connection, $confirmId);
            CliTestSupport::assertSame($this->scriptPrefix, 'potvrzena', (string) ($confirmRow['stav'] ?? ''), 'Rezervace ma byt po potvrzeni ve stavu potvrzena.');
            CliTestSupport::assertSame($this->scriptPrefix, 'Potvrzeno administrací', (string) ($confirmRow['poznamka_admina'] ?? ''), 'Admin poznamka ma byt ulozena.');

            $cancelId = $this->insertReservation(
                $connection,
                'aut:' . $token . ':c2',
                $fixture['service_id'],
                $fixture['date'] . ' 11:00:00',
                'potvrzena',
                'Cancel Test',
                'cancel-' . $token . '@example.test'
            );
            $cancelResult = $updateUseCase->handle([
                'reservation_id' => $cancelId,
                'stav' => 'zrusena',
                'poznamka_admina' => 'Klientka volala',
                'duvod_zruseni' => 'Nemůže dorazit',
                'datum_cas' => $fixture['date'] . 'T11:00',
            ], [
                'ppstudio_admin_lite_authenticated' => true,
                'ppstudio_admin_lite_username' => 'lite-review',
            ]);
            CliTestSupport::assertTrue($this->scriptPrefix, (bool) ($cancelResult['success'] ?? false), 'Zruseni rezervace ma probehnout uspesne.');
            $cancelRow = $this->findReservationRow($connection, $cancelId);
            CliTestSupport::assertSame($this->scriptPrefix, 'zrusena', (string) ($cancelRow['stav'] ?? ''), 'Rezervace ma byt po zruseni ve stavu zrusena.');
            CliTestSupport::assertSame($this->scriptPrefix, 'Nemůže dorazit', (string) ($cancelRow['duvod_zruseni'] ?? ''), 'Duvod zruseni ma byt ulozen.');
            CliTestSupport::assertSame($this->scriptPrefix, 'admin_lite', (string) ($cancelRow['zruseno_kym'] ?? ''), 'Lite admin ma byt zapsan do cancel meta.');
            CliTestSupport::assertSame($this->scriptPrefix, 'lite-review', (string) ($cancelRow['zruseno_uzivatel'] ?? ''), 'Lite admin username ma byt zapsan do cancel meta.');
            CliTestSupport::assertTrue($this->scriptPrefix, trim((string) ($cancelRow['zruseno_at'] ?? '')) !== '', 'Cas zruseni ma byt vyplnen.');

            $rescheduleId = $this->insertReservation(
                $connection,
                'aut:' . $token . ':c3',
                $fixture['service_id'],
                $fixture['date'] . ' 12:00:00',
                'nova',
                'Reschedule Test',
                'reschedule-' . $token . '@example.test',
                null,
                null,
                null,
                null,
                $fixture['date'] . ' 09:00:00'
            );
            $rescheduleResult = $updateUseCase->handle([
                'reservation_id' => $rescheduleId,
                'stav' => 'nova',
                'poznamka_admina' => 'Posunuto',
                'datum_cas' => $fixture['date'] . 'T15:00',
            ], [
                'ppstudio_admin_authenticated' => true,
                'ppstudio_admin_username' => 'admin-review',
            ]);
            CliTestSupport::assertTrue($this->scriptPrefix, (bool) ($rescheduleResult['success'] ?? false), 'Preplanovani rezervace ma probehnout uspesne.');
            $rescheduleRow = $this->findReservationRow($connection, $rescheduleId);
            CliTestSupport::assertSame($this->scriptPrefix, $fixture['date'] . ' 15:00:00', (string) ($rescheduleRow['datum_cas'] ?? ''), 'Rezervace ma byt presunuta na novy termin.');
            CliTestSupport::assertSame($this->scriptPrefix, null, $rescheduleRow['reminder_sent_at'] ?? null, 'Preplanovani ma vynulovat reminder_sent_at.');

            $cancelledMoveId = $this->insertReservation(
                $connection,
                'aut:' . $token . ':c4',
                $fixture['service_id'],
                $fixture['date'] . ' 16:00:00',
                'zrusena',
                'Cancelled Move Test',
                'cancelled-move-' . $token . '@example.test',
                'Puvodne zruseno',
                'admin_full',
                'admin-review',
                $fixture['date'] . ' 08:30:00'
            );
            $cancelledMoveResult = $updateUseCase->handle([
                'reservation_id' => $cancelledMoveId,
                'stav' => 'zrusena',
                'duvod_zruseni' => 'Puvodne zruseno',
                'datum_cas' => $fixture['date'] . 'T17:00',
            ], [
                'ppstudio_admin_authenticated' => true,
                'ppstudio_admin_username' => 'admin-review',
            ]);
            CliTestSupport::assertTrue($this->scriptPrefix, ! (bool) ($cancelledMoveResult['success'] ?? true), 'Presun zrusene rezervace ma byt odmitnut.');
            CliTestSupport::assertSame($this->scriptPrefix, 422, (int) ($cancelledMoveResult['http_code'] ?? 0), 'Presun zrusene rezervace ma vratit 422.');

            $completedMoveId = $this->insertReservation(
                $connection,
                'aut:' . $token . ':c5',
                $fixture['service_id'],
                $fixture['date'] . ' 17:00:00',
                'dokoncena',
                'Completed Move Test',
                'completed-move-' . $token . '@example.test'
            );
            $completedMoveResult = $updateUseCase->handle([
                'reservation_id' => $completedMoveId,
                'stav' => 'dokoncena',
                'datum_cas' => $fixture['date'] . 'T18:00',
            ], [
                'ppstudio_admin_authenticated' => true,
                'ppstudio_admin_username' => 'admin-review',
            ]);
            CliTestSupport::assertTrue($this->scriptPrefix, ! (bool) ($completedMoveResult['success'] ?? true), 'Presun dokoncene rezervace ma byt odmitnut.');
            CliTestSupport::assertSame($this->scriptPrefix, 422, (int) ($completedMoveResult['http_code'] ?? 0), 'Presun dokoncene rezervace ma vratit 422.');

            $manualSuccessResult = $createUseCase->handle([
                'jmeno' => 'Manual Success',
                'email' => 'manual-success-' . $token . '@example.test',
                'telefon' => '777999111',
                'zdroj' => 'telefon',
                'sluzba_id' => (string) $fixture['service_id'],
                'datum_cas' => $fixture['date'] . 'T13:00',
                'poznamka_klienta' => 'Preferuje tichy rezim',
            ]);
            CliTestSupport::assertTrue($this->scriptPrefix, (bool) ($manualSuccessResult['success'] ?? false), 'Rucni rezervace ma jit vytvorit pro volny termin.');
            $manualReservationId = (int) (($manualSuccessResult['data']['reservation_id'] ?? 0));
            CliTestSupport::assertTrue($this->scriptPrefix, $manualReservationId > 0, 'Rucni rezervace ma vratit reservation_id.');
            $manualRow = $this->findReservationRow($connection, $manualReservationId);
            CliTestSupport::assertSame($this->scriptPrefix, 'potvrzena', (string) ($manualRow['stav'] ?? ''), 'Rucni rezervace se ma ulozit jako potvrzena.');
            CliTestSupport::assertSame($this->scriptPrefix, 'manual-success-' . $token . '@example.test', (string) ($manualRow['email'] ?? ''), 'Rucni rezervace ma ulozit e-mail.');

            $manualCollisionResult = $createUseCase->handle([
                'jmeno' => 'Manual Collision',
                'email' => 'manual-collision-' . $token . '@example.test',
                'telefon' => '777999222',
                'zdroj' => 'instagram',
                'sluzba_id' => (string) $fixture['service_id'],
                'datum_cas' => $fixture['date'] . 'T13:00',
                'poznamka_klienta' => 'Kolizni pokus',
            ]);
            CliTestSupport::assertTrue($this->scriptPrefix, ! (bool) ($manualCollisionResult['success'] ?? true), 'Kolizni rucni rezervace ma byt odmitnuta.');
            CliTestSupport::assertSame($this->scriptPrefix, 422, (int) ($manualCollisionResult['http_code'] ?? 0), 'Kolizni rucni rezervace ma vratit 422.');

            echo $this->scriptPrefix . ' [OK] Admin reservation use-case scenarios passed.' . PHP_EOL;
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
            CliTestSupport::restoreEnv($previousEnv);

            if (is_dir($storageDir)) {
                $files = glob($storageDir . '/*') ?: [];
                foreach ($files as $file) {
                    @unlink($file);
                }
                @rmdir($storageDir);
            }
        }
    }

    private function connectDb(): mysqli
    {
        return DatabaseFactory::connect();
    }

    /**
     * @return array{category_id:int,service_id:int,availability_ids:int[],date:string}
     */
    private function prepareFixture(mysqli $connection, string $token): array
    {
        $categoryName = 'IT Admin Reservation Category ' . $token;
        $serviceName = 'IT Admin Reservation Service ' . $token;

        $statement = $connection->prepare('INSERT INTO kategorie (nazev, poradi, aktivni) VALUES (?, 9999, 1)');
        $statement->bind_param('s', $categoryName);
        $statement->execute();
        $categoryId = (int) $connection->insert_id;
        $statement->close();

        $duration = 60;
        $price = 1450.0;
        $description = 'Admin reservation usecase smoke test fixture ' . $token;
        $statement = $connection->prepare('INSERT INTO sluzby (nazev, kategorie_id, popis, cena, doba_trvani, aktivni) VALUES (?, ?, ?, ?, ?, 1)');
        $statement->bind_param('sisdi', $serviceName, $categoryId, $description, $price, $duration);
        $statement->execute();
        $serviceId = (int) $connection->insert_id;
        $statement->close();

        $date = $this->findFixtureDateWithoutReservations($connection);
        $availabilityIds = [];
        $windows = [
            [$date . ' 10:00:00', $date . ' 14:00:00', 'admin-reservation-usecase-window-1:' . $token],
            [$date . ' 15:00:00', $date . ' 18:00:00', 'admin-reservation-usecase-window-2:' . $token],
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

    private function findFixtureDateWithoutReservations(mysqli $connection): string
    {
        $cursor = new DateTimeImmutable('+365 days');
        $statement = $connection->prepare(
            'SELECT COUNT(*) AS total
             FROM rezervace
             WHERE datum_cas >= ?
               AND datum_cas < ?'
        );

        for ($i = 0; $i < 120; $i++) {
            $date = $cursor->format('Y-m-d');
            $start = $date . ' 00:00:00';
            $end = $cursor->modify('+1 day')->format('Y-m-d 00:00:00');
            $statement->bind_param('ss', $start, $end);
            $statement->execute();
            $result = $statement->get_result();
            $row = $result instanceof mysqli_result ? $result->fetch_assoc() : [];
            if ($result instanceof mysqli_result) {
                $result->free();
            }

            if ((int) ($row['total'] ?? 0) === 0) {
                $statement->close();

                return $date;
            }

            $cursor = $cursor->modify('+1 day');
        }

        $statement->close();

        return (new DateTimeImmutable('+730 days'))->format('Y-m-d');
    }

    /**
     * @param array<int, int> $availabilityIds
     */
    private function cleanupFixture(mysqli $connection, string $token, ?int $serviceId, ?int $categoryId, array $availabilityIds): void
    {
        try {
            $prefix = 'aut:' . $token . '%';
            $statement = $connection->prepare('DELETE FROM rezervace WHERE zdroj LIKE ?');
            $statement->bind_param('s', $prefix);
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

    private function insertReservation(
        mysqli $connection,
        string $source,
        int $serviceId,
        string $dateTime,
        string $status,
        string $name,
        string $email,
        ?string $cancelReason = null,
        ?string $cancelledBy = null,
        ?string $cancelledByUser = null,
        ?string $cancelledAt = null,
        ?string $reminderSentAt = null
    ): int {
        $price = 1450.0;
        $duration = 60;
        $phone = '777123456';
        $clientNote = 'usecase fixture';
        $adminNote = '';
        $statement = $connection->prepare(
            'INSERT INTO rezervace (
                jmeno, email, telefon, zdroj, poznamka_klienta, poznamka_admina, sluzba,
                cena_v_dobe_rezervace, doba_trvani_v_dobe_rezervace, datum_cas, stav,
                duvod_zruseni, zruseno_kym, zruseno_uzivatel, zruseno_at, reminder_sent_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->bind_param(
            'ssssssidisssssss',
            $name,
            $email,
            $phone,
            $source,
            $clientNote,
            $adminNote,
            $serviceId,
            $price,
            $duration,
            $dateTime,
            $status,
            $cancelReason,
            $cancelledBy,
            $cancelledByUser,
            $cancelledAt,
            $reminderSentAt
        );
        $statement->execute();
        $reservationId = (int) $connection->insert_id;
        $statement->close();

        return $reservationId;
    }

    private function findReservationRow(mysqli $connection, int $reservationId): array
    {
        $statement = $connection->prepare(
            'SELECT id, datum_cas, stav, poznamka_admina, duvod_zruseni, zruseno_kym, zruseno_uzivatel, zruseno_at, reminder_sent_at, email
             FROM rezervace
             WHERE id = ?
             LIMIT 1'
        );
        $statement->bind_param('i', $reservationId);
        $statement->execute();
        $result = $statement->get_result();
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : [];
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        $statement->close();

        return is_array($row) ? $row : [];
    }

    private function buildReservationService(mysqli $connection): ReservationService
    {
        $reservationRepository = new ReservationRepository($connection);
        $availabilityRepository = new AvailabilityRepository($connection);
        $serviceRepository = new ServiceRepository($connection);
        $availabilityService = new AvailabilityService($serviceRepository, $availabilityRepository, $reservationRepository);

        return new ReservationService(
            $connection,
            $serviceRepository,
            $availabilityRepository,
            $reservationRepository,
            $availabilityService
        );
    }
}
