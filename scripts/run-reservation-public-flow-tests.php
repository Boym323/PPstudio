#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Tento skript lze spouštět jen z CLI.\n";
    exit(1);
}

const SCRIPT_PREFIX = '[reservation-public-flow-tests]';

require_once __DIR__ . '/_test_helpers.php';

function captureChildResponse(string $scenario, array $server, array $post, array $env): array
{
    $command = [
        PHP_BINARY,
        __FILE__,
        '--child',
        '--scenario=' . $scenario,
    ];

    $childEnv = array_merge($_ENV, $env, [
        'PPSTUDIO_PUBLIC_FLOW_CHILD' => '1',
        'PPSTUDIO_PUBLIC_FLOW_SCENARIO' => $scenario,
        'PPSTUDIO_PUBLIC_FLOW_SERVER' => json_encode($server, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'PPSTUDIO_PUBLIC_FLOW_POST' => json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    return ppstudioCliTestCaptureJsonChildResponse(SCRIPT_PREFIX, $command, $childEnv, dirname(__DIR__));
}

function childMain(string $scenario): never
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

        (new \PPStudio\Security\SecurityFacade())->publicSiteLockService()->renderPage('Test');
    }

    if ($scenario === 'reservation_submit') {
        require dirname(__DIR__) . '/reservation-submit.php';
    }

    ppstudioCliTestFail(SCRIPT_PREFIX, 'Neznama child scenario: ' . $scenario);
}

$argvCopy = $argv;
array_shift($argvCopy);
$isChild = in_array('--child', $argvCopy, true);

if ($isChild) {
    $scenario = '';
    foreach ($argvCopy as $argument) {
        if (str_starts_with($argument, '--scenario=')) {
            $scenario = substr($argument, strlen('--scenario='));
            break;
        }
    }

    if ($scenario === '') {
        ppstudioCliTestFail(SCRIPT_PREFIX, 'Chybi --scenario pro child rezim.');
    }

    childMain($scenario);
}

ppstudioCliTestBootstrapBase();

$storageDir = ppstudioCliTestTempSecurityStorageDir(SCRIPT_PREFIX, 'ppstudio-public-flow-');
$previousEnv = ppstudioCliTestSetEnv([
    'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
    'PPSTUDIO_PUBLIC_LOCK_ENABLED' => '1',
    'PPSTUDIO_PUBLIC_LOCK_PASSWORD' => 's3cret',
    'PPSTUDIO_PUBLIC_LOCK_PASSWORD_HASH' => '',
]);

try {
    $securityFacade = (new \PPStudio\Security\SecurityFacade());
    $securityFacade->startSecureSession();
    session_unset();

    $requestSecurity = $securityFacade->requestSecurityService();
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, '203.0.113.5', $requestSecurity->clientIpAddress(['REMOTE_ADDR' => '203.0.113.5']), 'Client IP ma byt prevzata z requestu.');
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 'Test UA', $requestSecurity->userAgent(['HTTP_USER_AGENT' => 'Test UA']), 'User agent ma byt prevzat z requestu.');

    $csrfService = $securityFacade->csrfService();
    $csrfToken = $csrfService->token();
    ppstudioCliTestAssertTrue(SCRIPT_PREFIX, $csrfService->isValid($csrfToken), 'CSRF token ma byt validni pro stejnou session.');
    ppstudioCliTestAssertTrue(SCRIPT_PREFIX, ! $csrfService->isValid('invalid-token'), 'CSRF token ma odmítnout nespravny token.');

    $antispamService = $securityFacade->reservationAntispamService();
    $issuedToken = $antispamService->issueToken(time() - 10);
    ppstudioCliTestAssertTrue(SCRIPT_PREFIX, $antispamService->consumeToken($issuedToken) !== null, 'Reservation token ma jit jednorazove spotrebovat.');
    ppstudioCliTestAssertTrue(SCRIPT_PREFIX, $antispamService->consumeToken($issuedToken) === null, 'Reservation token nesmi jit spotrebovat podruhe.');

    $rateLimitFirst = $antispamService->rateLimitCheck('198.51.100.7', 1, 60);
    $rateLimitSecond = $antispamService->rateLimitCheck('198.51.100.7', 1, 60);
    ppstudioCliTestAssertTrue(SCRIPT_PREFIX, (bool) ($rateLimitFirst['allowed'] ?? false), 'Prvni rate-limit check ma projit.');
    ppstudioCliTestAssertTrue(SCRIPT_PREFIX, ! (bool) ($rateLimitSecond['allowed'] ?? true), 'Druhy rate-limit check ma byt blokovany.');
    ppstudioCliTestAssertTrue(SCRIPT_PREFIX, (int) ($rateLimitSecond['retry_after'] ?? 0) > 0, 'Rate-limit ma vratit retry_after.');

    $lockService = $securityFacade->publicSiteLockService();
    ppstudioCliTestAssertTrue(SCRIPT_PREFIX, $lockService->enabled(), 'Public lock ma byt zapnuty.');
    ppstudioCliTestAssertTrue(SCRIPT_PREFIX, ! $lockService->hasAccess(), 'Nova session nema mit access do public locku.');
    ppstudioCliTestAssertTrue(SCRIPT_PREFIX, $lockService->passwordMatches('s3cret'), 'Spravne heslo ma projit.');
    ppstudioCliTestAssertTrue(SCRIPT_PREFIX, ! $lockService->passwordMatches('wrong'), 'Spatne heslo ma byt odmitnuto.');
    $_SESSION[$lockService->sessionKey()] = true;
    ppstudioCliTestAssertTrue(SCRIPT_PREFIX, $lockService->hasAccess(), 'Nastavena session ma odemknout public lock.');

    $lockPage = captureChildResponse(
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
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 401, (int) ($lockPage['code'] ?? 0), 'Lock page ma vratit 401.');
    ppstudioCliTestAssertContains(SCRIPT_PREFIX, 'Interní vstup', (string) ($lockPage['body'] ?? ''), 'Lock page ma obsahovat formular.');
    ppstudioCliTestAssertContains(SCRIPT_PREFIX, 'name="_csrf"', (string) ($lockPage['body'] ?? ''), 'Lock page ma obsahovat CSRF field.');

    $lockedSubmit = captureChildResponse(
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
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 423, (int) ($lockedSubmit['code'] ?? 0), 'Zamceny reservation submit ma vratit 423.');
    ppstudioCliTestAssertContains(SCRIPT_PREFIX, '"status":"locked"', (string) ($lockedSubmit['body'] ?? ''), 'Zamceny reservation submit ma vratit locked status.');

    $csrfFailure = captureChildResponse(
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
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 419, (int) ($csrfFailure['code'] ?? 0), 'Neplatne CSRF ma vratit 419.');
    ppstudioCliTestAssertContains(SCRIPT_PREFIX, '"status":"csrf"', (string) ($csrfFailure['body'] ?? ''), 'CSRF failure ma vratit csrf status.');

    echo SCRIPT_PREFIX . ' [OK] Public flow smoke tests passed.' . PHP_EOL;
    echo SCRIPT_PREFIX . ' [OK] CSRF, antispam, site lock a controller smoke checks probehly.' . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    ppstudioCliTestFail(SCRIPT_PREFIX, 'Exception: ' . $exception->getMessage());
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
