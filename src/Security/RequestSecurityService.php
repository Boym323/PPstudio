<?php
declare(strict_types=1);

namespace PPStudio\Security;

final class RequestSecurityService
{
    private ?string $resolvedStorageDir = null;

    public function __construct(
        private SessionService $sessionService
    ) {
    }

    /**
     * @param array<string, mixed>|null $server
     */
    public function clientIpAddress(?array $server = null): string
    {
        $server ??= $_SERVER;

        return trim((string) ($server['REMOTE_ADDR'] ?? 'unknown'));
    }

    /**
     * @param array<string, mixed>|null $server
     */
    public function userAgent(?array $server = null): string
    {
        $server ??= $_SERVER;

        return trim((string) ($server['HTTP_USER_AGENT'] ?? ''));
    }

    public function storageDir(): string
    {
        if (is_string($this->resolvedStorageDir) && $this->resolvedStorageDir !== '') {
            return $this->resolvedStorageDir;
        }

        $configured = trim((string) (\function_exists('ppstudioEnv') ? \ppstudioEnv('PPSTUDIO_SECURITY_STORAGE', '') : ''));
        $candidates = [];
        if ($configured !== '') {
            $candidates[] = $configured;
        }

        $candidates[] = dirname(__DIR__, 2) . '/var/security';
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
                $this->resolvedStorageDir = $candidate;
                return $this->resolvedStorageDir;
            }
        }

        $this->resolvedStorageDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);

        return $this->resolvedStorageDir;
    }

    public function loginRateLimitPath(string $scope): string
    {
        $scope = preg_replace('/[^a-z0-9_-]+/i', '-', trim($scope)) ?: 'admin';

        return $this->storageDir() . '/login-rate-limit-' . strtolower($scope) . '.json';
    }

    public function loginRateLimitKey(string $scope, string $ipAddress, string $username): string
    {
        $normalizedUsername = strtolower(trim($username));

        return hash('sha256', 'ppstudio|login|' . strtolower($scope) . '|' . trim($ipAddress) . '|' . $normalizedUsername);
    }

    public function loginThrottleState(
        string $scope,
        string $ipAddress,
        string $username,
        int $limit = 5,
        int $windowSeconds = 900
    ): array {
        $loaded = $this->loadRateLimitMap($this->loginRateLimitPath($scope));
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

        $key = $this->loginRateLimitKey($scope, $ipAddress, $username);
        $state = $map[$key] ?? ['attempts' => [], 'lock_until' => 0];
        $attempts = is_array($state['attempts']) ? $state['attempts'] : [];
        $lockUntil = (int) ($state['lock_until'] ?? 0);
        $locked = $lockUntil > $now;
        $minutesLeft = $locked ? max(1, (int) ceil(($lockUntil - $now) / 60)) : 0;
        $remaining = max(0, $limit - count($attempts));

        $this->saveRateLimitMap($handle, $map);

        return [
            'locked' => $locked,
            'minutes_left' => $minutesLeft,
            'remaining' => $remaining,
            'attempts' => count($attempts),
        ];
    }

    public function loginThrottleRegisterFailure(
        string $scope,
        string $ipAddress,
        string $username,
        int $limit = 5,
        int $windowSeconds = 900
    ): array {
        $loaded = $this->loadRateLimitMap($this->loginRateLimitPath($scope));
        $handle = $loaded['handle'];
        $map = $loaded['map'];
        $now = time();

        $key = $this->loginRateLimitKey($scope, $ipAddress, $username);
        $state = $map[$key] ?? ['attempts' => [], 'lock_until' => 0];
        $attempts = array_values(array_filter((array) ($state['attempts'] ?? []), static fn($t): bool => is_int($t) && $t > ($now - $windowSeconds)));
        $lockUntil = (int) ($state['lock_until'] ?? 0);

        if ($lockUntil > $now) {
            $minutesLeft = max(1, (int) ceil(($lockUntil - $now) / 60));
            $this->saveRateLimitMap($handle, $map);

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
            $this->saveRateLimitMap($handle, $map);

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

        $this->saveRateLimitMap($handle, $map);

        return [
            'locked' => false,
            'minutes_left' => 0,
            'remaining' => max(0, $limit - count($attempts)),
        ];
    }

    public function loginThrottleReset(string $scope, string $ipAddress, string $username): void
    {
        $loaded = $this->loadRateLimitMap($this->loginRateLimitPath($scope));
        $handle = $loaded['handle'];
        $map = $loaded['map'];
        $key = $this->loginRateLimitKey($scope, $ipAddress, $username);

        unset($map[$key]);

        $this->saveRateLimitMap($handle, $map);
    }

    public function voucherVerifySecret(): string
    {
        $secret = trim((string) (\function_exists('ppstudioEnv') ? \ppstudioEnv('PPSTUDIO_VOUCHER_VERIFY_SECRET', '') : ''));
        if ($secret !== '') {
            return $secret;
        }

        return trim((string) (\function_exists('ppstudioEnv') ? \ppstudioEnv('PPSTUDIO_ACTION_SECRET', '') : ''));
    }

    public function buildVoucherVerifySignature(string $secret, int $voucherId, string $voucherCode): string
    {
        if ($secret === '' || $voucherId <= 0 || $voucherCode === '') {
            return '';
        }

        $payload = 'voucher_verify|' . $voucherId . '|' . $voucherCode;

        return hash_hmac('sha256', $payload, $secret);
    }

    public function isValidVoucherVerifySignature(string $secret, int $voucherId, string $voucherCode, string $signature): bool
    {
        $signature = trim($signature);
        if ($signature === '' || ! preg_match('/^[a-f0-9]{64}$/i', $signature)) {
            return false;
        }

        $expected = $this->buildVoucherVerifySignature($secret, $voucherId, $voucherCode);
        if ($expected === '') {
            return false;
        }

        return hash_equals($expected, $signature);
    }

    public function buildVoucherSignedPublicUrl(
        array $siteSettings,
        int $voucherId,
        string $voucherCode,
        string $path,
        ?string $secret = null
    ): string {
        $secret = trim((string) ($secret ?? $this->voucherVerifySecret()));
        if ($voucherId <= 0 || $voucherCode === '' || $secret === '') {
            return '';
        }

        $signature = $this->buildVoucherVerifySignature($secret, $voucherId, $voucherCode);
        if ($signature === '') {
            return '';
        }

        $siteUrl = rtrim($this->setting($siteSettings, 'site_url', ''), '/');
        if ($siteUrl === '') {
            $scheme = $this->sessionService->isHttpsRequest() ? 'https' : 'http';
            $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
            if ($host === '') {
                return '';
            }
            $siteUrl = $scheme . '://' . $host;
        }

        return $siteUrl . $path . '?v=' . $voucherId . '&sig=' . rawurlencode($signature);
    }

    public function loadRateLimitMap(string $path): array
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

    public function saveRateLimitMap(mixed $handle, array $map): void
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

    private function setting(array $settings, string $key, string $fallback = ''): string
    {
        $value = $settings[$key] ?? $fallback;

        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
