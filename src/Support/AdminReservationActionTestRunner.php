<?php
declare(strict_types=1);

namespace PPStudio\Support;

use DateTimeImmutable;
use mysqli;
use mysqli_result;
use PPStudio\Database\DatabaseFactory;
use Throwable;

final class AdminReservationActionTestRunner
{
    public function __construct(
        private readonly string $scriptPrefix = '[admin-reservation-action-tests]'
    ) {
    }

    public function run(): int
    {
        ppstudioCliTestBootstrapBase();

        $storageDir = ppstudioCliTestTempSecurityStorageDir($this->scriptPrefix, 'ppstudio-admin-reservation-action-');
        $actionSecret = 'admin-reservation-action-' . bin2hex(random_bytes(16));
        $previousEnv = ppstudioCliTestSetEnv([
            'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
            'PPSTUDIO_ACTION_SECRET' => $actionSecret,
            'HTTP_HOST' => 'admin-tests.local',
            'HTTPS' => 'off',
        ]);

        $connection = DatabaseFactory::connect();
        $token = bin2hex(random_bytes(4));
        $categoryName = 'IT Category ' . $token;
        $serviceName = 'IT Service ' . $token;

        $statement = $connection->prepare('INSERT INTO kategorie (nazev, poradi, aktivni) VALUES (?, 9999, 1)');
        $statement->bind_param('s', $categoryName);
        $statement->execute();
        $categoryId = (int) $connection->insert_id;
        $statement->close();

        $duration = 60;
        $price = 1234.0;
        $description = 'Admin reservation action smoke test ' . $token;
        $statement = $connection->prepare('INSERT INTO sluzby (nazev, kategorie_id, popis, cena, doba_trvani, aktivni) VALUES (?, ?, ?, ?, ?, 1)');
        $statement->bind_param('sisdi', $serviceName, $categoryId, $description, $price, $duration);
        $statement->execute();
        $serviceId = (int) $connection->insert_id;
        $statement->close();

        $reservationDateTime = (new DateTimeImmutable('+365 days'))->format('Y-m-d H:i:s');
        $source = 'integration:' . $token;
        $reservationNote = 'admin reservation action smoke test ' . $token;
        $status = 'nova';
        $statement = $connection->prepare(
            'INSERT INTO rezervace (jmeno, email, telefon, zdroj, poznamka_klienta, sluzba, cena_v_dobe_rezervace, doba_trvani_v_dobe_rezervace, datum_cas, stav)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $name = 'Test Admin ' . $token;
        $email = 'admin-action-' . $token . '@example.test';
        $phone = '777111222';
        $servicePrice = 1234.0;
        $serviceDuration = 60;
        $statement->bind_param(
            'ssssssidiss',
            $name,
            $email,
            $phone,
            $source,
            $reservationNote,
            $serviceId,
            $servicePrice,
            $serviceDuration,
            $reservationDateTime,
            $status
        );
        $statement->execute();
        $reservationId = (int) $connection->insert_id;
        $statement->close();

        try {
            (new \PPStudio\Security\SecurityFacade())->startSecureSession();
            session_unset();

            $csrfToken = (new \PPStudio\Security\SecurityFacade())->getCsrfToken();
            $sessionId = session_id();
            $_SESSION['ppstudio_admin_authenticated'] = true;
            session_write_close();

            $baseServer = [
                'REQUEST_METHOD' => 'POST',
                'HTTP_HOST' => 'admin-tests.local',
                'HTTP_ACCEPT' => 'application/json',
            ];
            $baseEnv = [
                'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
                'PPSTUDIO_ACTION_SECRET' => $actionSecret,
                'HTTP_HOST' => 'admin-tests.local',
                'HTTPS' => 'off',
            ];

            $unauthorizedResponse = $this->captureChildResponse(
                $baseServer,
                [
                    '_csrf' => $csrfToken,
                    'delete_reservation' => '1',
                    'reservation_id' => $reservationId,
                ],
                $baseEnv
            );

            ppstudioCliTestAssertSame($this->scriptPrefix, 401, (int) ($unauthorizedResponse['code'] ?? 0), 'api/admin/reservation-action ma bez session vratit HTTP 401.');
            ppstudioCliTestAssertContains($this->scriptPrefix, 'Nejste přihlášeni do administrace.', (string) ($unauthorizedResponse['body'] ?? ''), 'api/admin/reservation-action ma vratit auth chybu.');

            $csrfErrorResponse = $this->captureChildResponse(
                $baseServer,
                [
                    '_csrf' => 'invalid-token',
                    'delete_reservation' => '1',
                    'reservation_id' => $reservationId,
                ],
                $baseEnv + [
                    'PPSTUDIO_ADMIN_RESERVATION_ACTION_SESSION_ID' => $sessionId,
                ]
            );

            ppstudioCliTestAssertSame($this->scriptPrefix, 419, (int) ($csrfErrorResponse['code'] ?? 0), 'api/admin/reservation-action ma s neplatnym CSRF vratit HTTP 419.');
            ppstudioCliTestAssertContains($this->scriptPrefix, 'Platnost formuláře vypršela. Obnovte stránku.', (string) ($csrfErrorResponse['body'] ?? ''), 'api/admin/reservation-action ma vratit CSRF chybu.');

            $response = $this->captureChildResponse(
                $baseServer,
                [
                    '_csrf' => $csrfToken,
                    'delete_reservation' => '1',
                    'reservation_id' => $reservationId,
                ],
                $baseEnv + [
                    'PPSTUDIO_ADMIN_RESERVATION_ACTION_SESSION_ID' => $sessionId,
                ]
            );

            ppstudioCliTestAssertSame($this->scriptPrefix, 200, (int) ($response['code'] ?? 0), 'api/admin/reservation-action ma vratit HTTP 200.');
            ppstudioCliTestAssertContains($this->scriptPrefix, '"success":true', (string) ($response['body'] ?? ''), 'api/admin/reservation-action ma vratit success payload.');
            ppstudioCliTestAssertContains($this->scriptPrefix, 'Rezervace byla smazána.', (string) ($response['body'] ?? ''), 'api/admin/reservation-action ma potvrdit smazani.');

            $check = $connection->prepare('SELECT COUNT(*) AS total FROM rezervace WHERE id = ?');
            $check->bind_param('i', $reservationId);
            $check->execute();
            $result = $check->get_result();
            $row = $result instanceof mysqli_result ? $result->fetch_assoc() : [];
            if ($result instanceof mysqli_result) {
                $result->free();
            }
            $check->close();

            ppstudioCliTestAssertSame($this->scriptPrefix, 0, (int) ($row['total'] ?? 0), 'Rezervace ma byt po smazani opravdu odstranena.');

            echo $this->scriptPrefix . ' [OK] Admin reservation action smoke test passed.' . PHP_EOL;
            return 0;
        } catch (Throwable $exception) {
            ppstudioCliTestFail($this->scriptPrefix, 'Exception: ' . $exception->getMessage());
        } finally {
            $cleanup = $connection->prepare('DELETE FROM rezervace WHERE id = ?');
            $cleanup->bind_param('i', $reservationId);
            $cleanup->execute();
            $cleanup->close();

            $deleteService = $connection->prepare('DELETE FROM sluzby WHERE id = ?');
            $deleteService->bind_param('i', $serviceId);
            $deleteService->execute();
            $deleteService->close();

            $deleteCategory = $connection->prepare('DELETE FROM kategorie WHERE id = ?');
            $deleteCategory->bind_param('i', $categoryId);
            $deleteCategory->execute();
            $deleteCategory->close();

            $connection->close();
            ppstudioCliTestRestoreEnv($previousEnv);

            if (is_dir($storageDir)) {
                $files = glob($storageDir . '/*') ?: [];
                foreach ($files as $file) {
                    @unlink($file);
                }
                @rmdir($storageDir);
            }
        }
    }

    private function captureChildResponse(array $server, array $post, array $env): array
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/scripts/run-admin-reservation-action-tests.php',
            '--child',
        ];

        $childEnv = array_merge($_ENV, $env, [
            'PPSTUDIO_ADMIN_RESERVATION_ACTION_CHILD' => '1',
            'PPSTUDIO_ADMIN_RESERVATION_ACTION_SERVER' => json_encode($server, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'PPSTUDIO_ADMIN_RESERVATION_ACTION_POST' => json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return ppstudioCliTestCaptureJsonChildResponse($this->scriptPrefix, $command, $childEnv, dirname(__DIR__, 2));
    }
}
