<?php
declare(strict_types=1);

function isHttpsRequest(): bool
{
    if (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function getClientIpAddress(): string
{
    return trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function getCsrfToken(): string
{
    startSecureSession();

    if (! isset($_SESSION['ppstudio_csrf_token']) || ! is_string($_SESSION['ppstudio_csrf_token'])) {
        $_SESSION['ppstudio_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['ppstudio_csrf_token'];
}

function csrfInputField(string $fieldName = '_csrf'): string
{
    $token = escape(getCsrfToken());
    $name = escape($fieldName);

    return '<input type="hidden" name="' . $name . '" value="' . $token . '">';
}

function isValidCsrfToken(?string $token): bool
{
    startSecureSession();

    if (! is_string($token) || $token === '') {
        return false;
    }

    $sessionToken = (string) ($_SESSION['ppstudio_csrf_token'] ?? '');

    return $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function ppstudioSecurityStorageDir(): string
{
    static $resolvedDir = null;

    if (is_string($resolvedDir) && $resolvedDir !== '') {
        return $resolvedDir;
    }

    $configured = trim((string) ppstudioEnv('PPSTUDIO_SECURITY_STORAGE', ''));
    $candidates = [];
    if ($configured !== '') {
        $candidates[] = $configured;
    }

    $candidates[] = dirname(__DIR__) . '/var/security';
    $candidates[] = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ppstudio-security';

    foreach ($candidates as $candidate) {
        $candidate = rtrim((string) $candidate, DIRECTORY_SEPARATOR);
        if ($candidate === '') {
            continue;
        }

        if (! is_dir($candidate) && ! @mkdir($candidate, 0770, true) && ! is_dir($candidate)) {
            continue;
        }

        if (! is_writable($candidate)) {
            @chmod($candidate, 0770);
        }

        if (is_writable($candidate)) {
            $resolvedDir = $candidate;
            return $resolvedDir;
        }
    }

    $resolvedDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);

    return $resolvedDir;
}

function ppstudioLoginRateLimitPath(string $scope): string
{
    $scope = preg_replace('/[^a-z0-9_-]+/i', '-', trim($scope)) ?: 'admin';

    return ppstudioSecurityStorageDir() . '/login-rate-limit-' . strtolower($scope) . '.json';
}

function ppstudioLoadRateLimitMap(string $path): array
{
    $handle = @fopen($path, 'c+');
    if (! $handle) {
        return ['handle' => null, 'map' => []];
    }

    if (! @flock($handle, LOCK_EX)) {
        fclose($handle);
        return ['handle' => null, 'map' => []];
    }

    $content = stream_get_contents($handle);
    $map = [];
    if (is_string($content) && trim($content) !== '') {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $map = $decoded;
        }
    }

    return ['handle' => $handle, 'map' => $map];
}

function ppstudioSaveRateLimitMap($handle, array $map): void
{
    if (! is_resource($handle)) {
        return;
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function ppstudioLoginRateLimitKey(string $scope, string $ipAddress, string $username): string
{
    $normalizedUsername = strtolower(trim($username));

    return hash('sha256', 'ppstudio|login|' . strtolower($scope) . '|' . trim($ipAddress) . '|' . $normalizedUsername);
}

function ppstudioLoginThrottleState(
    string $scope,
    string $ipAddress,
    string $username,
    int $limit = 5,
    int $windowSeconds = 900
): array {
    $path = ppstudioLoginRateLimitPath($scope);
    $loaded = ppstudioLoadRateLimitMap($path);
    $handle = $loaded['handle'];
    $map = $loaded['map'];

    $now = time();
    foreach ($map as $key => $value) {
        $attempts = array_values(array_filter((array) ($value['attempts'] ?? []), static fn($t): bool => is_int($t) && $t > ($now - $windowSeconds)));
        $lockUntil = (int) ($value['lock_until'] ?? 0);
        if ($attempts === [] && $lockUntil <= $now) {
            unset($map[$key]);
            continue;
        }
        $map[$key] = [
            'attempts' => $attempts,
            'lock_until' => $lockUntil,
        ];
    }

    $key = ppstudioLoginRateLimitKey($scope, $ipAddress, $username);
    $state = $map[$key] ?? ['attempts' => [], 'lock_until' => 0];
    $attempts = is_array($state['attempts']) ? $state['attempts'] : [];
    $lockUntil = (int) ($state['lock_until'] ?? 0);
    $locked = $lockUntil > $now;
    $minutesLeft = $locked ? max(1, (int) ceil(($lockUntil - $now) / 60)) : 0;
    $remaining = max(0, $limit - count($attempts));

    ppstudioSaveRateLimitMap($handle, $map);

    return [
        'locked' => $locked,
        'minutes_left' => $minutesLeft,
        'remaining' => $remaining,
        'attempts' => count($attempts),
    ];
}

function ppstudioLoginThrottleRegisterFailure(
    string $scope,
    string $ipAddress,
    string $username,
    int $limit = 5,
    int $windowSeconds = 900
): array {
    $path = ppstudioLoginRateLimitPath($scope);
    $loaded = ppstudioLoadRateLimitMap($path);
    $handle = $loaded['handle'];
    $map = $loaded['map'];
    $now = time();

    $key = ppstudioLoginRateLimitKey($scope, $ipAddress, $username);
    $state = $map[$key] ?? ['attempts' => [], 'lock_until' => 0];
    $attempts = array_values(array_filter((array) ($state['attempts'] ?? []), static fn($t): bool => is_int($t) && $t > ($now - $windowSeconds)));
    $lockUntil = (int) ($state['lock_until'] ?? 0);

    if ($lockUntil > $now) {
        $minutesLeft = max(1, (int) ceil(($lockUntil - $now) / 60));
        ppstudioSaveRateLimitMap($handle, $map);

        return [
            'locked' => true,
            'minutes_left' => $minutesLeft,
            'remaining' => 0,
        ];
    }

    $attempts[] = $now;
    if (count($attempts) >= $limit) {
        $map[$key] = [
            'attempts' => [],
            'lock_until' => $now + $windowSeconds,
        ];
        ppstudioSaveRateLimitMap($handle, $map);

        return [
            'locked' => true,
            'minutes_left' => max(1, (int) ceil($windowSeconds / 60)),
            'remaining' => 0,
        ];
    }

    $map[$key] = [
        'attempts' => $attempts,
        'lock_until' => 0,
    ];

    ppstudioSaveRateLimitMap($handle, $map);

    return [
        'locked' => false,
        'minutes_left' => 0,
        'remaining' => max(0, $limit - count($attempts)),
    ];
}

function ppstudioLoginThrottleReset(string $scope, string $ipAddress, string $username): void
{
    $path = ppstudioLoginRateLimitPath($scope);
    $loaded = ppstudioLoadRateLimitMap($path);
    $handle = $loaded['handle'];
    $map = $loaded['map'];
    $key = ppstudioLoginRateLimitKey($scope, $ipAddress, $username);

    unset($map[$key]);

    ppstudioSaveRateLimitMap($handle, $map);
}

function ppstudioVoucherVerifySecret(): string
{
    $secret = trim((string) ppstudioEnv('PPSTUDIO_VOUCHER_VERIFY_SECRET', ''));
    if ($secret !== '') {
        return $secret;
    }

    return trim((string) ppstudioEnv('PPSTUDIO_ACTION_SECRET', ''));
}

function buildVoucherVerifySignature(string $secret, int $voucherId, string $voucherCode): string
{
    if ($secret === '' || $voucherId <= 0 || $voucherCode === '') {
        return '';
    }

    $payload = 'voucher_verify|' . $voucherId . '|' . $voucherCode;

    return hash_hmac('sha256', $payload, $secret);
}

function isValidVoucherVerifySignature(string $secret, int $voucherId, string $voucherCode, string $signature): bool
{
    $signature = trim($signature);
    if ($signature === '' || ! preg_match('/^[a-f0-9]{64}$/i', $signature)) {
        return false;
    }

    $expected = buildVoucherVerifySignature($secret, $voucherId, $voucherCode);
    if ($expected === '') {
        return false;
    }

    return hash_equals($expected, $signature);
}

function buildVoucherVerifyUrl(array $siteSettings, int $voucherId, string $voucherCode, ?string $secret = null): string
{
    $secret = trim((string) ($secret ?? ppstudioVoucherVerifySecret()));
    if ($voucherId <= 0 || $voucherCode === '' || $secret === '') {
        return '';
    }

    $signature = buildVoucherVerifySignature($secret, $voucherId, $voucherCode);
    if ($signature === '') {
        return '';
    }

    $siteUrl = rtrim(setting($siteSettings, 'site_url', ''), '/');
    if ($siteUrl === '') {
        $scheme = isHttpsRequest() ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return '';
        }
        $siteUrl = $scheme . '://' . $host;
    }

    return $siteUrl . '/voucher/verify?v=' . $voucherId . '&sig=' . rawurlencode($signature);
}
