<?php
declare(strict_types=1);

namespace PPStudio\Support;

use PPStudio\Security\SecurityFacade;
use Throwable;

final class ReservationPublicFlowTestRunner
{
    public function __construct(
        private readonly string $scriptPrefix = '[reservation-public-flow-tests]'
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

        $storageDir = ppstudioCliTestTempSecurityStorageDir($this->scriptPrefix, 'ppstudio-public-flow-');
        $previousEnv = ppstudioCliTestSetEnv([
            'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
            'PPSTUDIO_PUBLIC_LOCK_ENABLED' => '1',
            'PPSTUDIO_PUBLIC_LOCK_PASSWORD' => 's3cret',
            'PPSTUDIO_PUBLIC_LOCK_PASSWORD_HASH' => '',
        ]);

        try {
            $securityFacade = new SecurityFacade();
            $securityFacade->startSecureSession();
            session_unset();

            $requestSecurity = $securityFacade->requestSecurityService();
            ppstudioCliTestAssertSame($this->scriptPrefix, '203.0.113.5', $requestSecurity->clientIpAddress(['REMOTE_ADDR' => '203.0.113.5']), 'Client IP ma byt prevzata z requestu.');
            ppstudioCliTestAssertSame($this->scriptPrefix, 'Test UA', $requestSecurity->userAgent(['HTTP_USER_AGENT' => 'Test UA']), 'User agent ma byt prevzat z requestu.');

            $csrfService = $securityFacade->csrfService();
            $csrfToken = $csrfService->token();
            ppstudioCliTestAssertTrue($this->scriptPrefix, $csrfService->isValid($csrfToken), 'CSRF token ma byt validni pro stejnou session.');
            ppstudioCliTestAssertTrue($this->scriptPrefix, ! $csrfService->isValid('invalid-token'), 'CSRF token ma odmítnout nespravny token.');

            $antispamService = $securityFacade->reservationAntispamService();
            $issuedToken = $antispamService->issueToken(time() - 10);
            ppstudioCliTestAssertTrue($this->scriptPrefix, $antispamService->consumeToken($issuedToken) !== null, 'Reservation token ma jit jednorazove spotrebovat.');
            ppstudioCliTestAssertTrue($this->scriptPrefix, $antispamService->consumeToken($issuedToken) === null, 'Reservation token nesmi jit spotrebovat podruhe.');

            $rateLimitFirst = $antispamService->rateLimitCheck('198.51.100.7', 1, 60);
            $rateLimitSecond = $antispamService->rateLimitCheck('198.51.100.7', 1, 60);
            ppstudioCliTestAssertTrue($this->scriptPrefix, (bool) ($rateLimitFirst['allowed'] ?? false), 'Prvni rate-limit check ma projit.');
            ppstudioCliTestAssertTrue($this->scriptPrefix, ! (bool) ($rateLimitSecond['allowed'] ?? true), 'Druhy rate-limit check ma byt blokovany.');
            ppstudioCliTestAssertTrue($this->scriptPrefix, (int) ($rateLimitSecond['retry_after'] ?? 0) > 0, 'Rate-limit ma vratit retry_after.');

            $lockService = $securityFacade->publicSiteLockService();
            ppstudioCliTestAssertTrue($this->scriptPrefix, $lockService->enabled(), 'Public lock ma byt zapnuty.');
            ppstudioCliTestAssertTrue($this->scriptPrefix, ! $lockService->hasAccess(), 'Nova session nema mit access do public locku.');
            ppstudioCliTestAssertTrue($this->scriptPrefix, $lockService->passwordMatches('s3cret'), 'Spravne heslo ma projit.');
            ppstudioCliTestAssertTrue($this->scriptPrefix, ! $lockService->passwordMatches('wrong'), 'Spatne heslo ma byt odmitnuto.');
            $_SESSION[$lockService->sessionKey()] = true;
            ppstudioCliTestAssertTrue($this->scriptPrefix, $lockService->hasAccess(), 'Nastavena session ma odemknout public lock.');

            $lockPage = $this->captureChildResponse(
                'lock_page',
                [
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/rezervace.php',
                ],
                [],
                [
                    'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
                    'PPSTUDIO_PUBLIC_LOCK_ENABLED' => '1',
                    'PPSTUDIO_PUBLIC_LOCK_PASSWORD' => 's3cret',
                    'PPSTUDIO_PUBLIC_LOCK_PASSWORD_HASH' => '',
                ]
            );
            ppstudioCliTestAssertSame($this->scriptPrefix, 401, (int) ($lockPage['code'] ?? 0), 'Lock page ma vratit 401.');
            ppstudioCliTestAssertContains($this->scriptPrefix, 'Interní vstup', (string) ($lockPage['body'] ?? ''), 'Lock page ma obsahovat formular.');
            ppstudioCliTestAssertContains($this->scriptPrefix, 'name="_csrf"', (string) ($lockPage['body'] ?? ''), 'Lock page ma obsahovat CSRF field.');

            $lockedSubmit = $this->captureChildResponse(
                'reservation_submit',
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/reservation-submit.php',
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'fetch',
                    'REMOTE_ADDR' => '198.51.100.9',
                ],
                [
                    'jmeno' => 'Test',
                ],
                [
                    'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
                    'PPSTUDIO_PUBLIC_LOCK_ENABLED' => '1',
                    'PPSTUDIO_PUBLIC_LOCK_PASSWORD' => 's3cret',
                    'PPSTUDIO_PUBLIC_LOCK_PASSWORD_HASH' => '',
                ]
            );
            ppstudioCliTestAssertSame($this->scriptPrefix, 423, (int) ($lockedSubmit['code'] ?? 0), 'Zamceny reservation submit ma vratit 423.');
            ppstudioCliTestAssertContains($this->scriptPrefix, '"status":"locked"', (string) ($lockedSubmit['body'] ?? ''), 'Zamceny reservation submit ma vratit locked status.');

            $csrfFailure = $this->captureChildResponse(
                'reservation_submit',
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/reservation-submit.php',
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'fetch',
                    'REMOTE_ADDR' => '198.51.100.9',
                ],
                [
                    '_csrf' => 'invalid-token',
                    'jmeno' => 'Test',
                    'email' => 'test@example.test',
                    'sluzba_id' => '1',
                    'rezervacni_datum' => '2027-01-01',
                    'rezervacni_cas' => '10:00',
                ],
                [
                    'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
                    'PPSTUDIO_PUBLIC_LOCK_ENABLED' => '0',
                    'PPSTUDIO_PUBLIC_LOCK_PASSWORD_HASH' => '',
                ]
            );
            ppstudioCliTestAssertSame($this->scriptPrefix, 419, (int) ($csrfFailure['code'] ?? 0), 'Neplatne CSRF ma vratit 419.');
            ppstudioCliTestAssertContains($this->scriptPrefix, '"status":"csrf"', (string) ($csrfFailure['body'] ?? ''), 'CSRF failure ma vratit csrf status.');

            echo $this->scriptPrefix . ' [OK] Public flow smoke tests passed.' . PHP_EOL;
            echo $this->scriptPrefix . ' [OK] CSRF, antispam, site lock a controller smoke checks probehly.' . PHP_EOL;

            return 0;
        } catch (Throwable $exception) {
            ppstudioCliTestFail($this->scriptPrefix, 'Exception: ' . $exception->getMessage());
        } finally {
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

    private function captureChildResponse(string $scenario, array $server, array $post, array $env): array
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/scripts/run-reservation-public-flow-tests.php',
            '--child',
            '--scenario=' . $scenario,
        ];

        $childEnv = array_merge($_ENV, $env, [
            'PPSTUDIO_PUBLIC_FLOW_CHILD' => '1',
            'PPSTUDIO_PUBLIC_FLOW_SCENARIO' => $scenario,
            'PPSTUDIO_PUBLIC_FLOW_SERVER' => json_encode($server, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'PPSTUDIO_PUBLIC_FLOW_POST' => json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return ppstudioCliTestCaptureJsonChildResponse($this->scriptPrefix, $command, $childEnv, dirname(__DIR__, 2));
    }

    private function childMain(string $scenario): int
    {
        foreach ([
            'PPSTUDIO_SECURITY_STORAGE',
            'PPSTUDIO_PUBLIC_LOCK_ENABLED',
            'PPSTUDIO_PUBLIC_LOCK_PASSWORD',
            'PPSTUDIO_PUBLIC_LOCK_PASSWORD_HASH',
        ] as $envName) {
            $envValue = getenv($envName);
            if ($envValue !== false) {
                $_SERVER[$envName] = $envValue;
            }
        }

        $server = json_decode((string) getenv('PPSTUDIO_PUBLIC_FLOW_SERVER'), true);
        $post = json_decode((string) getenv('PPSTUDIO_PUBLIC_FLOW_POST'), true);
        $server = is_array($server) ? $server : [];
        $post = is_array($post) ? $post : [];

        $_SERVER = array_merge($_SERVER, $server);
        $_POST = $post;

        ob_start();
        register_shutdown_function(static function (): void {
            $body = ob_get_clean();
            echo json_encode([
                'code' => http_response_code(),
                'body' => is_string($body) ? $body : '',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        });

        if ($scenario === 'lock_page') {
            ppstudioCliTestBootstrapBase();

            (new SecurityFacade())->publicSiteLockService()->renderPage('Test');
            return 0;
        }

        if ($scenario === 'reservation_submit') {
            require dirname(__DIR__, 2) . '/reservation-submit.php';
            return 0;
        }

        ppstudioCliTestFail($this->scriptPrefix, 'Neznama child scenario: ' . $scenario);
    }
}
