<?php
declare(strict_types=1);

function ppstudioCliTestFail(string $prefix, string $message): never
{
    fwrite(STDERR, $prefix . " [FAIL] {$message}\n");
    exit(1);
}

function ppstudioCliTestAssertTrue(string $prefix, bool $condition, string $message): void
{
    if (! $condition) {
        ppstudioCliTestFail($prefix, $message);
    }
}

function ppstudioCliTestAssertSame(string $prefix, mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        ppstudioCliTestFail($prefix, $message . ' Expected: ' . var_export($expected, true) . ' Actual: ' . var_export($actual, true));
    }
}

function ppstudioCliTestAssertContains(string $prefix, string $needle, string $haystack, string $message): void
{
    if (! str_contains($haystack, $needle)) {
        ppstudioCliTestFail($prefix, $message . ' Missing: ' . $needle);
    }
}

function ppstudioCliTestTempSecurityStorageDir(string $prefix, string $dirPrefix): string
{
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $dirPrefix . bin2hex(random_bytes(4));
    if (! mkdir($dir, 0770, true) && ! is_dir($dir)) {
        ppstudioCliTestFail($prefix, 'Nepodarilo se vytvorit docasny security storage.');
    }

    return $dir;
}

function ppstudioCliTestCaptureJsonChildResponse(string $prefix, array $command, array $env, string $workingDir): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, $workingDir, $env);
    if (! is_resource($process)) {
        ppstudioCliTestFail($prefix, 'Nepodarilo se spustit child proces.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        ppstudioCliTestFail($prefix, 'Child proces selhal: ' . trim($stderr ?: $stdout));
    }

    $decoded = json_decode($stdout, true);
    if (! is_array($decoded)) {
        ppstudioCliTestFail($prefix, 'Child proces vratil nevalidni vystup: ' . trim($stdout));
    }

    return $decoded;
}

function ppstudioCliTestSetEnv(array $values): array
{
    $previous = [];

    foreach ($values as $name => $value) {
        $previous[$name] = [
            'env' => getenv($name),
            'server' => array_key_exists($name, $_SERVER) ? $_SERVER[$name] : null,
            'server_exists' => array_key_exists($name, $_SERVER),
        ];
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    return $previous;
}

function ppstudioCliTestRestoreEnv(array $previous): void
{
    foreach ($previous as $name => $state) {
        $value = $state['env'] ?? null;
        if ($value === false || $value === null) {
            putenv($name);
            unset($_ENV[$name]);
        } else {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }

        if (($state['server_exists'] ?? false) === true) {
            $_SERVER[$name] = $state['server'];
            continue;
        }

        unset($_SERVER[$name]);
    }
}

function ppstudioCliTestBootstrapBase(): void
{
    require dirname(__DIR__) . '/includes/bootstrap.php';
    require dirname(__DIR__) . '/config/app.php';
    require dirname(__DIR__) . '/includes/functions.php';
    require dirname(__DIR__) . '/includes/security.php';

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}
