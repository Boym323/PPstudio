<?php
declare(strict_types=1);

namespace PPStudio\Support;

final class CliTestSupport
{
    public static function fail(string $prefix, string $message): never
    {
        fwrite(STDERR, $prefix . " [FAIL] {$message}\n");
        exit(1);
    }

    public static function assertTrue(string $prefix, bool $condition, string $message): void
    {
        if (! $condition) {
            self::fail($prefix, $message);
        }
    }

    public static function assertSame(string $prefix, mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            self::fail($prefix, $message . ' Expected: ' . var_export($expected, true) . ' Actual: ' . var_export($actual, true));
        }
    }

    public static function assertContains(string $prefix, string $needle, string $haystack, string $message): void
    {
        if (! str_contains($haystack, $needle)) {
            self::fail($prefix, $message . ' Missing: ' . $needle);
        }
    }

    public static function tempSecurityStorageDir(string $prefix, string $dirPrefix): string
    {
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $dirPrefix . bin2hex(random_bytes(4));
        if (! mkdir($dir, 0770, true) && ! is_dir($dir)) {
            self::fail($prefix, 'Nepodarilo se vytvorit docasny security storage.');
        }

        return $dir;
    }

    /**
     * @param array<int, string> $command
     * @param array<string, string> $env
     * @return array<string, mixed>
     */
    public static function captureJsonChildResponse(string $prefix, array $command, array $env, string $workingDir): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $workingDir, $env);
        if (! is_resource($process)) {
            self::fail($prefix, 'Nepodarilo se spustit child proces.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            self::fail($prefix, 'Child proces selhal: ' . trim($stderr ?: $stdout));
        }

        $decoded = json_decode($stdout, true);
        if (! is_array($decoded)) {
            self::fail($prefix, 'Child proces vratil nevalidni vystup: ' . trim($stdout));
        }

        return $decoded;
    }

    /**
     * @param array<string, string> $values
     * @return array<string, array{env:mixed,server:mixed,server_exists:bool}>
     */
    public static function setEnv(array $values): array
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

    /**
     * @param array<string, array{env:mixed,server:mixed,server_exists:bool}> $previous
     */
    public static function restoreEnv(array $previous): void
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

    public static function bootstrapBase(): void
    {
        require dirname(__DIR__, 2) . '/includes/bootstrap.php';
        require dirname(__DIR__, 2) . '/config/app.php';

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }
}
