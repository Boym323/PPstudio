#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Tento skript lze spouštět jen z CLI.\n";
    exit(1);
}

const SCRIPT_PREFIX = '[reservation-public-flow-tests]';

function fail(string $message): never
{
    fwrite(STDERR, SCRIPT_PREFIX . " [FAIL] {$message}\n");
    exit(1);
}

function assertTrue(bool $condition, string $message): void
{
    if (! $condition) {
        fail($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fail($message . ' Expected: ' . var_export($expected, true) . ' Actual: ' . var_export($actual, true));
    }
}

function assertContains(string $needle, string $haystack, string $message): void
{
    if (! str_contains($haystack, $needle)) {
        fail($message . ' Missing: ' . $needle);
    }
}

function tempSecurityStorageDir(): string
{
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ppstudio-public-flow-' . bin2hex(random_bytes(4));
    if (! mkdir($dir, 0770, true) && ! is_dir($dir)) {
        fail('Nepodarilo se vytvorit docasny security storage.');
    }

    return $dir;
}

function setEnv(array $values): array
{
    $previous = [];

    foreach ($values as $name => $value) {
        $previous[$name] = [
            'env' => getenv($name),
            'server' => array_key_exists($name, $_SERVER) ? $_SERVER[$name] : null,
            'server_exists' => array_key_exists($name, $_SERVER),
        ];
        putenv($name . '=' . $value);
        $_SERVER[$name] = $value;
    }

    return $previous;
}

function restoreEnv(array $previous): void
{
    foreach ($previous as $name => $state) {
        $value = $state['env'] ?? null;
        if ($value === false || $value === null) {
            putenv($name);
        } else {
            putenv($name . '=' . $value);
        }

        if (($state['server_exists'] ?? false) === true) {
            $_SERVER[$name] = $state['server'];
            continue;
        }

        unset($_SERVER[$name]);
    }
}

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

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__), $childEnv);
    if (! is_resource($process)) {
        fail('Nepodarilo se spustit child proces.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        fail('Child proces selhal: ' . trim($stderr ?: $stdout));
    }

    $decoded = json_decode($stdout, true);
    if (! is_array($decoded)) {
        fail('Child proces vratil nevalidni vystup: ' . trim($stdout));
    }

    return $decoded;
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
        require dirname(__DIR__) . '/includes/bootstrap.php';
        require dirname(__DIR__) . '/config/app.php';
        require dirname(__DIR__) . '/includes/functions.php';
        require dirname(__DIR__) . '/includes/security.php';
        require dirname(__DIR__) . '/includes/site_lock.php';

        ppstudioPublicSiteLockService()->renderPage('Test');
    }

    if ($scenario === 'reservation_submit') {
        require dirname(__DIR__) . '/reservation-submit.php';
    }

    fail('Neznama child scenario: ' . $scenario);
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
        fail('Chybi --scenario pro child rezim.');
    }

    childMain($scenario);
}

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/config/app.php';
require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/security.php';
require dirname(__DIR__) . '/includes/site_lock.php';
require dirname(__DIR__) . '/includes/antispam.php';

$storageDir = tempSecurityStorageDir();
$previousEnv = setEnv([
    'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
    'PPSTUDIO_PUBLIC_LOCK_ENABLED' => '1',
    'PPSTUDIO_PUBLIC_LOCK_PASSWORD' => 's3cret',
    'PPSTUDIO_PUBLIC_LOCK_PASSWORD_HASH' => '',
]);

try {
    startSecureSession();
    session_unset();

    $requestSecurity = ppstudioRequestSecurityService();
    assertSame('203.0.113.5', $requestSecurity->clientIpAddress(['REMOTE_ADDR' => '203.0.113.5']), 'Client IP ma byt prevzata z requestu.');
    assertSame('Test UA', $requestSecurity->userAgent(['HTTP_USER_AGENT' => 'Test UA']), 'User agent ma byt prevzat z requestu.');

    $csrfService = ppstudioCsrfService();
    $csrfToken = $csrfService->token();
    assertTrue($csrfService->isValid($csrfToken), 'CSRF token ma byt validni pro stejnou session.');
    assertTrue(! $csrfService->isValid('invalid-token'), 'CSRF token ma odmítnout nespravny token.');

    $antispamService = ppstudioReservationAntispamService();
    $issuedToken = $antispamService->issueToken(time() - 10);
    assertTrue($antispamService->consumeToken($issuedToken) !== null, 'Reservation token ma jit jednorazove spotrebovat.');
    assertTrue($antispamService->consumeToken($issuedToken) === null, 'Reservation token nesmi jit spotrebovat podruhe.');

    $rateLimitFirst = $antispamService->rateLimitCheck('198.51.100.7', 1, 60);
    $rateLimitSecond = $antispamService->rateLimitCheck('198.51.100.7', 1, 60);
    assertTrue((bool) ($rateLimitFirst['allowed'] ?? false), 'Prvni rate-limit check ma projit.');
    assertTrue(! (bool) ($rateLimitSecond['allowed'] ?? true), 'Druhy rate-limit check ma byt blokovany.');
    assertTrue((int) ($rateLimitSecond['retry_after'] ?? 0) > 0, 'Rate-limit ma vratit retry_after.');

    $lockService = ppstudioPublicSiteLockService();
    assertTrue($lockService->enabled(), 'Public lock ma byt zapnuty.');
    assertTrue(! $lockService->hasAccess(), 'Nova session nema mit access do public locku.');
    assertTrue($lockService->passwordMatches('s3cret'), 'Spravne heslo ma projit.');
    assertTrue(! $lockService->passwordMatches('wrong'), 'Spatne heslo ma byt odmitnuto.');
    $_SESSION[$lockService->sessionKey()] = true;
    assertTrue($lockService->hasAccess(), 'Nastavena session ma odemknout public lock.');

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
    assertSame(401, (int) ($lockPage['code'] ?? 0), 'Lock page ma vratit 401.');
    assertContains('Interní vstup', (string) ($lockPage['body'] ?? ''), 'Lock page ma obsahovat formular.');
    assertContains('name="_csrf"', (string) ($lockPage['body'] ?? ''), 'Lock page ma obsahovat CSRF field.');

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
    assertSame(423, (int) ($lockedSubmit['code'] ?? 0), 'Zamceny reservation submit ma vratit 423.');
    assertContains('"status":"locked"', (string) ($lockedSubmit['body'] ?? ''), 'Zamceny reservation submit ma vratit locked status.');

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
    assertSame(419, (int) ($csrfFailure['code'] ?? 0), 'Neplatne CSRF ma vratit 419.');
    assertContains('"status":"csrf"', (string) ($csrfFailure['body'] ?? ''), 'CSRF failure ma vratit csrf status.');

    echo SCRIPT_PREFIX . ' [OK] Public flow smoke tests passed.' . PHP_EOL;
    echo SCRIPT_PREFIX . ' [OK] CSRF, antispam, site lock a controller smoke checks probehly.' . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    fail('Exception: ' . $exception->getMessage());
} finally {
    restoreEnv($previousEnv);

    if (is_dir($storageDir)) {
        $files = glob($storageDir . '/*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($storageDir);
    }
}
