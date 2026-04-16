<?php
declare(strict_types=1);

namespace PPStudio\Security;

final class ReservationAntispamService
{
    private const SESSION_KEY = 'ppstudio_reservation_tokens';

    public function __construct(
        private SessionService $sessionService,
        private RequestSecurityService $requestSecurityService,
        private SecurityEventLogger $securityEventLogger
    ) {
    }

    public function issueToken(?int $issuedAt = null): string
    {
        $this->sessionService->start();

        $token = bin2hex(random_bytes(16));
        $issuedAt = $issuedAt ?? time();
        $tokens = $this->sessionTokens();
        $cutoff = time() - (6 * 60 * 60);

        foreach ($tokens as $existingToken => $timestamp) {
            if (! is_string($existingToken) || ! is_int($timestamp) || $timestamp < $cutoff) {
                unset($tokens[$existingToken]);
            }
        }

        $tokens[$token] = $issuedAt;
        $_SESSION[self::SESSION_KEY] = $tokens;

        return $token;
    }

    public function consumeToken(string $token): ?int
    {
        $this->sessionService->start();

        if ($token === '') {
            return null;
        }

        $tokens = $this->sessionTokens();
        if (! isset($tokens[$token])) {
            return null;
        }

        $issuedAt = (int) $tokens[$token];
        unset($tokens[$token]);
        $_SESSION[self::SESSION_KEY] = $tokens;

        return $issuedAt > 0 ? $issuedAt : null;
    }

    public function rateLimitPath(): string
    {
        return $this->requestSecurityService->storageDir() . '/reservation-rate-limit.json';
    }

    public function logPath(): string
    {
        return $this->requestSecurityService->storageDir() . '/reservation-antispam.log';
    }

    /**
     * @return array{allowed: bool, retry_after: int}
     */
    public function rateLimitCheck(?string $ipAddress = null, int $limit = 8, int $windowSeconds = 600): array
    {
        $storageFile = $this->rateLimitPath();
        $now = time();
        $ipAddress = trim((string) ($ipAddress ?? $this->requestSecurityService->clientIpAddress()));
        $key = hash('sha256', 'ppstudio|reservation|rate|' . $ipAddress);
        $retryAfter = 0;
        $allowed = true;

        $handle = @fopen($storageFile, 'c+');
        if (! $handle) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        if (! @flock($handle, LOCK_EX)) {
            fclose($handle);
            return ['allowed' => true, 'retry_after' => 0];
        }

        $contents = stream_get_contents($handle);
        $map = [];
        if (is_string($contents) && trim($contents) !== '') {
            $decoded = json_decode($contents, true);
            if (is_array($decoded)) {
                $map = $decoded;
            }
        }

        foreach ($map as $mapKey => $timestamps) {
            if (! is_array($timestamps)) {
                unset($map[$mapKey]);
                continue;
            }

            $filtered = array_values(array_filter(
                $timestamps,
                static fn($value): bool => is_int($value) && $value > ($now - $windowSeconds)
            ));

            if ($filtered === []) {
                unset($map[$mapKey]);
                continue;
            }

            $map[$mapKey] = $filtered;
        }

        $attempts = $map[$key] ?? [];
        if (! is_array($attempts)) {
            $attempts = [];
        }

        if (count($attempts) >= $limit) {
            $allowed = false;
            $firstAttempt = (int) min($attempts);
            $retryAfter = max(1, ($firstAttempt + $windowSeconds) - $now);
        } else {
            $attempts[] = $now;
            $map[$key] = $attempts;
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return ['allowed' => $allowed, 'retry_after' => $retryAfter];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(
        string $reason,
        array $context = [],
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $ipAddress = trim((string) ($ipAddress ?? $this->requestSecurityService->clientIpAddress()));
        $userAgent = (string) ($userAgent ?? $this->requestSecurityService->userAgent());

        $this->securityEventLogger->log(
            'antispam_' . trim($reason),
            'reservation_form',
            'warning',
            $context,
            $ipAddress,
            $userAgent
        );

        $entry = [
            'time' => date('c'),
            'reason' => $reason,
            'ip' => $ipAddress,
            'ua' => $userAgent,
            'context' => $context,
        ];

        @file_put_contents(
            $this->logPath(),
            json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * @param array<string, mixed> $post
     * @return array{allowed: bool, status?: string, http_code?: int}
     */
    public function validateSubmission(
        array $post,
        ?string $clientIpAddress = null,
        ?string $userAgent = null
    ): array {
        $honeypot = trim((string) ($post['website'] ?? ''));
        $reservationToken = trim((string) ($post['reservation_token'] ?? ''));
        $clientIpAddress = trim((string) ($clientIpAddress ?? $this->requestSecurityService->clientIpAddress()));
        $userAgent = (string) ($userAgent ?? $this->requestSecurityService->userAgent());

        $rateLimitResult = $this->rateLimitCheck($clientIpAddress);
        if (! ($rateLimitResult['allowed'] ?? true)) {
            $this->log('rate_limited', ['retry_after' => (int) ($rateLimitResult['retry_after'] ?? 0)], $clientIpAddress, $userAgent);

            return [
                'allowed' => false,
                'status' => 'rate_limit',
                'http_code' => 429,
            ];
        }

        if ($honeypot !== '') {
            $this->log('honeypot_filled', [], $clientIpAddress, $userAgent);

            return [
                'allowed' => false,
                'status' => 'spam',
                'http_code' => 422,
            ];
        }

        $issuedAt = $this->consumeToken($reservationToken);
        if ($issuedAt === null) {
            $this->log('missing_or_invalid_token', [], $clientIpAddress, $userAgent);

            return [
                'allowed' => false,
                'status' => 'spam',
                'http_code' => 422,
            ];
        }

        $elapsed = time() - $issuedAt;
        if ($elapsed < 3) {
            $this->log('submitted_too_fast', ['elapsed' => $elapsed], $clientIpAddress, $userAgent);

            return [
                'allowed' => false,
                'status' => 'too_fast',
                'http_code' => 422,
            ];
        }

        if ($elapsed > 2 * 60 * 60) {
            $this->log('token_expired', ['elapsed' => $elapsed], $clientIpAddress, $userAgent);

            return [
                'allowed' => false,
                'status' => 'spam',
                'http_code' => 422,
            ];
        }

        return ['allowed' => true];
    }

    private function sessionTokens(): array
    {
        $tokens = $_SESSION[self::SESSION_KEY] ?? [];

        return is_array($tokens) ? $tokens : [];
    }
}
