<?php
declare(strict_types=1);

namespace PPStudio\Support;

use Throwable;

final class AdminLoginPageTestRunner
{
    public function __construct(
        private readonly string $scriptPrefix = '[admin-login-page-tests]'
    ) {
    }

    public function run(): int
    {
        $storageDir = CliTestSupport::tempSecurityStorageDir($this->scriptPrefix, 'ppstudio-admin-login-page-');
        $previousEnv = CliTestSupport::setEnv([
            'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
            'HTTP_HOST' => 'admin-login-tests.local',
            'HTTPS' => 'off',
        ]);

        try {
            $this->assertLoginPage(
                dirname(__DIR__, 2) . '/admin.php',
                'Přihlášení do administrace',
                'Přihlášení do správy studia'
            );
            $this->assertLoginPage(
                dirname(__DIR__, 2) . '/admin-lite.php',
                'Přihlášení uživatele',
                'Přihlášení do provozní správy'
            );

            echo $this->scriptPrefix . ' [OK] Admin login page smoke test passed.' . PHP_EOL;
            return 0;
        } catch (Throwable $exception) {
            CliTestSupport::fail($this->scriptPrefix, 'Exception: ' . $exception->getMessage());
        } finally {
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

    private function assertLoginPage(string $scriptPath, string $titleFragment, string $headingFragment): void
    {
        $result = $this->runCommand([PHP_BINARY, $scriptPath], $_ENV);

        CliTestSupport::assertSame($this->scriptPrefix, 0, $result['exit_code'], 'Login page script ma skoncit s exit code 0.');
        CliTestSupport::assertContains($this->scriptPrefix, $titleFragment, $result['stdout'], 'Login page ma obsahovat ocekavany title fragment.');
        CliTestSupport::assertContains($this->scriptPrefix, $headingFragment, $result['stdout'], 'Login page ma obsahovat ocekavany heading fragment.');
        CliTestSupport::assertTrue($this->scriptPrefix, trim((string) $result['stderr']) === '', 'Login page script nema psat na stderr.');
    }

    /**
     * @param list<string> $command
     * @param array<string, mixed> $env
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    private function runCommand(array $command, array $env): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__, 2), $env);
        if (! is_resource($process)) {
            CliTestSupport::fail($this->scriptPrefix, 'Nepodarilo se spustit login smoke test proces.');
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
}
