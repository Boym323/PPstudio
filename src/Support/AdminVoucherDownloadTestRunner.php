<?php
declare(strict_types=1);

namespace PPStudio\Support;

use DateTimeImmutable;
use PPStudio\Database\DatabaseFactory;
use Throwable;

final class AdminVoucherDownloadTestRunner
{
    public function __construct(
        private readonly string $scriptPrefix = '[admin-voucher-dl-tests]'
    ) {
    }

    public function run(): int
    {
        ppstudioCliTestBootstrapBase();

        $storageDir = ppstudioCliTestTempSecurityStorageDir($this->scriptPrefix, 'ppstudio-admin-voucher-dl-');
        $voucherVerifySecret = 'admin-voucher-dl-' . bin2hex(random_bytes(16));
        $previousEnv = ppstudioCliTestSetEnv([
            'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
            'PPSTUDIO_VOUCHER_VERIFY_SECRET' => $voucherVerifySecret,
            'PPSTUDIO_ACTION_SECRET' => $voucherVerifySecret,
            'HTTP_HOST' => 'voucher-tests.local',
            'HTTPS' => 'off',
        ]);

        $connection = DatabaseFactory::connect();
        $token = bin2hex(random_bytes(4));
        $code = 'IT-VOUCHER-' . strtoupper($token);
        $recipientName = 'Test Recipient ' . $token;
        $expiresAt = (new DateTimeImmutable('+30 days'))->format('Y-m-d');
        $statement = $connection->prepare(
            'INSERT INTO poukazy (kod, puvodni_hodnota, zustatek, status, issued_at, expires_at, recipient_name, note)
             VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)'
        );
        $originalValue = 1500.0;
        $remainingValue = 1500.0;
        $status = 'aktivni';
        $note = 'admin voucher dl smoke test ' . $token;
        $statement->bind_param('sddssss', $code, $originalValue, $remainingValue, $status, $expiresAt, $recipientName, $note);
        $statement->execute();
        $voucherId = (int) $connection->insert_id;
        $statement->close();

        try {
            (new \PPStudio\Security\SecurityFacade())->startSecureSession();
            session_unset();
            $_SESSION['ppstudio_admin_authenticated'] = true;
            $sessionId = session_id();
            session_write_close();

            $baseServer = [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'voucher-tests.local',
            ];
            $baseEnv = [
                'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
                'PPSTUDIO_VOUCHER_VERIFY_SECRET' => $voucherVerifySecret,
                'PPSTUDIO_ACTION_SECRET' => $voucherVerifySecret,
                'HTTP_HOST' => 'voucher-tests.local',
                'HTTPS' => 'off',
            ];

            $unauthorizedResponse = $this->captureChildResponse(
                $baseServer,
                [
                    'id' => $voucherId,
                ],
                $baseEnv
            );

            ppstudioCliTestAssertSame($this->scriptPrefix, 401, (int) ($unauthorizedResponse['code'] ?? 0), 'admin-voucher-dl ma bez session vratit HTTP 401.');
            ppstudioCliTestAssertContains($this->scriptPrefix, 'Nejste přihlášeni.', (string) ($unauthorizedResponse['body'] ?? ''), 'admin-voucher-dl ma vratit auth chybu.');

            $response = $this->captureChildResponse(
                $baseServer,
                [
                    'id' => $voucherId,
                ],
                $baseEnv + [
                    'PPSTUDIO_ADMIN_VOUCHER_DL_SESSION_ID' => $sessionId,
                ]
            );

            ppstudioCliTestAssertSame($this->scriptPrefix, 200, (int) ($response['code'] ?? 0), 'admin-voucher-dl ma vratit HTTP 200.');
            ppstudioCliTestAssertContains($this->scriptPrefix, 'Dárkový poukaz', (string) ($response['body'] ?? ''), 'admin-voucher-dl ma obsahovat titul poukazu.');
            ppstudioCliTestAssertContains($this->scriptPrefix, 'Tisk / Uložit jako PDF', (string) ($response['body'] ?? ''), 'admin-voucher-dl ma obsahovat tiskovou akci.');
            ppstudioCliTestAssertContains($this->scriptPrefix, $code, (string) ($response['body'] ?? ''), 'admin-voucher-dl ma zobrazit kod poukazu.');

            echo $this->scriptPrefix . ' [OK] Admin voucher DL smoke test passed.' . PHP_EOL;
            return 0;
        } catch (Throwable $exception) {
            ppstudioCliTestFail($this->scriptPrefix, 'Exception: ' . $exception->getMessage());
        } finally {
            $delete = $connection->prepare('DELETE FROM poukazy WHERE id = ?');
            $delete->bind_param('i', $voucherId);
            $delete->execute();
            $delete->close();
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

    private function captureChildResponse(array $server, array $get, array $env): array
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/scripts/run-admin-voucher-dl-tests.php',
            '--child',
        ];

        $childEnv = array_merge($_ENV, $env, [
            'PPSTUDIO_ADMIN_VOUCHER_DL_CHILD' => '1',
            'PPSTUDIO_ADMIN_VOUCHER_DL_SERVER' => json_encode($server, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'PPSTUDIO_ADMIN_VOUCHER_DL_GET' => json_encode($get, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return ppstudioCliTestCaptureJsonChildResponse($this->scriptPrefix, $command, $childEnv, dirname(__DIR__, 2));
    }
}
