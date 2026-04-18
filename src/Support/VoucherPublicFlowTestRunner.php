<?php
declare(strict_types=1);

namespace PPStudio\Support;

use DateTimeImmutable;
use PPStudio\Database\DatabaseFactory;
use PPStudio\Security\SecurityFacade;
use Throwable;

final class VoucherPublicFlowTestRunner
{
    public function __construct(
        private readonly string $scriptPrefix = '[voucher-public-flow-tests]'
    ) {
    }

    /**
     * @param array<int, string> $argv
     */
    public function run(array $argv): int
    {
        $argvCopy = $argv;
        array_shift($argvCopy);
        $isChild = in_array('--child', $argvCopy, true);

        if ($isChild) {
            return $this->runChildFromArgv($argvCopy);
        }

        ppstudioCliTestBootstrapBase();

        $storageDir = ppstudioCliTestTempSecurityStorageDir($this->scriptPrefix, 'ppstudio-voucher-flow-');
        $voucherVerifySecret = 'voucher-flow-' . bin2hex(random_bytes(16));
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
        $note = 'voucher smoke test ' . $token;
        $statement->bind_param('sddssss', $code, $originalValue, $remainingValue, $status, $expiresAt, $recipientName, $note);
        $statement->execute();
        $voucherId = (int) $connection->insert_id;
        $statement->close();

        try {
            (new SecurityFacade())->startSecureSession();
            session_unset();

            $security = new SecurityFacade();
            $secret = $security->voucherVerifySecret();
            $signature = $security->buildVoucherVerifySignature($secret, $voucherId, $code);
            ppstudioCliTestAssertTrue($this->scriptPrefix, $signature !== '', 'Podpis poukazu nesmi byt prazdny.');

            $viewResponse = $this->captureChildResponse(
                'voucher_view',
                [
                    'REQUEST_METHOD' => 'GET',
                    'HTTP_HOST' => 'voucher-tests.local',
                ],
                [
                    'v' => $voucherId,
                    'sig' => $signature,
                ],
                [
                    'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
                    'PPSTUDIO_VOUCHER_VERIFY_SECRET' => $voucherVerifySecret,
                    'PPSTUDIO_ACTION_SECRET' => $voucherVerifySecret,
                    'HTTP_HOST' => 'voucher-tests.local',
                    'HTTPS' => 'off',
                ]
            );
            ppstudioCliTestAssertSame($this->scriptPrefix, 200, (int) ($viewResponse['code'] ?? 0), 'voucher-view ma vratit HTTP 200.');
            ppstudioCliTestAssertContains($this->scriptPrefix, 'Dárkový poukaz', (string) ($viewResponse['body'] ?? ''), 'voucher-view ma obsahovat titul.');
            ppstudioCliTestAssertContains($this->scriptPrefix, $code, (string) ($viewResponse['body'] ?? ''), 'voucher-view ma zobrazit kod poukazu.');

            $verifyPublicResponse = $this->captureChildResponse(
                'voucher_verify_public',
                [
                    'REQUEST_METHOD' => 'GET',
                    'HTTP_HOST' => 'voucher-tests.local',
                ],
                [
                    'v' => $voucherId,
                    'sig' => $signature,
                ],
                [
                    'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
                    'PPSTUDIO_VOUCHER_VERIFY_SECRET' => $voucherVerifySecret,
                    'PPSTUDIO_ACTION_SECRET' => $voucherVerifySecret,
                    'HTTP_HOST' => 'voucher-tests.local',
                    'HTTPS' => 'off',
                ]
            );
            ppstudioCliTestAssertSame($this->scriptPrefix, 200, (int) ($verifyPublicResponse['code'] ?? 0), 'voucher-verify ma vratit HTTP 200.');
            ppstudioCliTestAssertContains($this->scriptPrefix, 'Ověření dárkového poukazu', (string) ($verifyPublicResponse['body'] ?? ''), 'voucher-verify ma obsahovat titul.');
            ppstudioCliTestAssertContains($this->scriptPrefix, $code, (string) ($verifyPublicResponse['body'] ?? ''), 'voucher-verify ma zobrazit kod poukazu.');

            $verifyPrivilegedResponse = $this->captureChildResponse(
                'voucher_verify_privileged',
                [
                    'REQUEST_METHOD' => 'GET',
                    'HTTP_HOST' => 'voucher-tests.local',
                ],
                [
                    'v' => $voucherId,
                    'sig' => $signature,
                ],
                [
                    'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
                    'PPSTUDIO_VOUCHER_VERIFY_SECRET' => $voucherVerifySecret,
                    'PPSTUDIO_ACTION_SECRET' => $voucherVerifySecret,
                    'HTTP_HOST' => 'voucher-tests.local',
                    'HTTPS' => 'off',
                ]
            );
            ppstudioCliTestAssertSame($this->scriptPrefix, 200, (int) ($verifyPrivilegedResponse['code'] ?? 0), 'Privilegovany voucher-verify ma vratit HTTP 200.');
            ppstudioCliTestAssertContains($this->scriptPrefix, 'Aktuální zůstatek', (string) ($verifyPrivilegedResponse['body'] ?? ''), 'Privilegovany voucher-verify ma zobrazit zůstatek.');

            echo $this->scriptPrefix . ' [OK] Voucher flow smoke tests passed.' . PHP_EOL;
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

    /**
     * @param array<int, string> $argvCopy
     */
    private function runChildFromArgv(array $argvCopy): int
    {
        $scenario = '';
        foreach ($argvCopy as $argument) {
            if (str_starts_with($argument, '--scenario=')) {
                $scenario = substr($argument, strlen('--scenario='));
                break;
            }
        }

        if ($scenario === '') {
            ppstudioCliTestFail($this->scriptPrefix, 'Chybi --scenario pro child rezim.');
        }

        return $this->childMain($scenario);
    }

    private function captureChildResponse(string $scenario, array $server, array $get, array $env): array
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/scripts/run-voucher-public-flow-tests.php',
            '--child',
            '--scenario=' . $scenario,
        ];

        $childEnv = array_merge($_ENV, $env, [
            'PPSTUDIO_VOUCHER_FLOW_CHILD' => '1',
            'PPSTUDIO_VOUCHER_FLOW_SCENARIO' => $scenario,
            'PPSTUDIO_VOUCHER_FLOW_SERVER' => json_encode($server, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'PPSTUDIO_VOUCHER_FLOW_GET' => json_encode($get, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return ppstudioCliTestCaptureJsonChildResponse($this->scriptPrefix, $command, $childEnv, dirname(__DIR__, 2));
    }

    private function childMain(string $scenario): int
    {
        $server = json_decode((string) getenv('PPSTUDIO_VOUCHER_FLOW_SERVER'), true);
        $get = json_decode((string) getenv('PPSTUDIO_VOUCHER_FLOW_GET'), true);
        $server = is_array($server) ? $server : [];
        $get = is_array($get) ? $get : [];
        $_SERVER = array_merge($_SERVER, $server);
        $_GET = $get;
        $_REQUEST = array_merge($_GET, $_POST);

        if ($scenario === 'voucher_view') {
            ob_start();
            register_shutdown_function(static function (): void {
                $body = ob_get_clean();
                $code = (int) http_response_code();
                if ($code <= 0) {
                    $code = 200;
                }
                echo json_encode([
                    'code' => $code,
                    'body' => is_string($body) ? $body : '',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            });
            require dirname(__DIR__, 2) . '/voucher-view.php';
            return 0;
        }

        if ($scenario === 'voucher_verify_public' || $scenario === 'voucher_verify_privileged') {
            if ($scenario === 'voucher_verify_privileged') {
                session_start();
                $_SESSION['ppstudio_admin_authenticated'] = true;
            }

            ob_start();
            register_shutdown_function(static function (): void {
                $body = ob_get_clean();
                $code = (int) http_response_code();
                if ($code <= 0) {
                    $code = 200;
                }
                echo json_encode([
                    'code' => $code,
                    'body' => is_string($body) ? $body : '',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            });
            require dirname(__DIR__, 2) . '/voucher-verify.php';
            return 0;
        }

        ppstudioCliTestFail($this->scriptPrefix, 'Neznama child scenario: ' . $scenario);
    }
}
