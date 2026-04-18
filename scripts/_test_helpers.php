<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

use PPStudio\Support\CliTestSupport;

function ppstudioCliTestFail(string $prefix, string $message): never
{
    CliTestSupport::fail($prefix, $message);
}

function ppstudioCliTestAssertTrue(string $prefix, bool $condition, string $message): void
{
    CliTestSupport::assertTrue($prefix, $condition, $message);
}

function ppstudioCliTestAssertSame(string $prefix, mixed $expected, mixed $actual, string $message): void
{
    CliTestSupport::assertSame($prefix, $expected, $actual, $message);
}

function ppstudioCliTestAssertContains(string $prefix, string $needle, string $haystack, string $message): void
{
    CliTestSupport::assertContains($prefix, $needle, $haystack, $message);
}

function ppstudioCliTestTempSecurityStorageDir(string $prefix, string $dirPrefix): string
{
    return CliTestSupport::tempSecurityStorageDir($prefix, $dirPrefix);
}

function ppstudioCliTestCaptureJsonChildResponse(string $prefix, array $command, array $env, string $workingDir): array
{
    return CliTestSupport::captureJsonChildResponse($prefix, $command, $env, $workingDir);
}

function ppstudioCliTestSetEnv(array $values): array
{
    return CliTestSupport::setEnv($values);
}

function ppstudioCliTestRestoreEnv(array $previous): void
{
    CliTestSupport::restoreEnv($previous);
}

function ppstudioCliTestBootstrapBase(): void
{
    CliTestSupport::bootstrapBase();
}
