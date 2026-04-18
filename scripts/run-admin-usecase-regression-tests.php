#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Tento skript lze spouštět jen z CLI.\n";
    exit(1);
}

const SCRIPT_PREFIX = '[admin-usecase-regression-tests]';

/**
 * @param list<string> $command
 * @param array<string, string> $env
 * @return array{exit_code:int,stdout:string,stderr:string}
 */
function runCommand(array $command, array $env): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__), $env);
    if (! is_resource($process)) {
        fwrite(STDERR, SCRIPT_PREFIX . " [FAIL] Nepodarilo se spustit podproces: " . implode(' ', $command) . PHP_EOL);
        exit(1);
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

/**
 * @param list<string> $command
 */
function assertCommandOk(string $label, array $command): void
{
    $result = runCommand($command, $_ENV);
    if ($result['exit_code'] !== 0) {
        fwrite(STDERR, SCRIPT_PREFIX . ' [FAIL] ' . $label . ' selhal.' . PHP_EOL);
        $output = trim($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']);
        if ($output !== '') {
            fwrite(STDERR, $output . PHP_EOL);
        }

        exit(1);
    }

    $output = trim($result['stdout']);
    if ($output !== '') {
        fwrite(STDOUT, $output . PHP_EOL);
    }
}

assertCommandOk('Admin reservation use-case test', [
    PHP_BINARY,
    __DIR__ . '/run-admin-reservation-usecase-tests.php',
]);

assertCommandOk('Admin voucher use-case test', [
    PHP_BINARY,
    __DIR__ . '/run-admin-voucher-usecase-tests.php',
]);

assertCommandOk('Admin login page test', [
    PHP_BINARY,
    __DIR__ . '/run-admin-login-page-tests.php',
]);

echo SCRIPT_PREFIX . ' [OK] Admin use-case regression tests passed.' . PHP_EOL;
