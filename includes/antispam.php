<?php
declare(strict_types=1);

require_once __DIR__ . '/security_events.php';

function reservationAntispamIssueToken(?int $issuedAt = null): string
{
    startSecureSession();

    $token = bin2hex(random_bytes(16));
    $issuedAt = $issuedAt ?? time();
    $tokens = $_SESSION['ppstudio_reservation_tokens'] ?? [];
    if (! is_array($tokens)) {
        $tokens = [];
    }

    $cutoff = time() - (6 * 60 * 60);
    foreach ($tokens as $existingToken => $timestamp) {
        if (! is_string($existingToken) || ! is_int($timestamp) || $timestamp < $cutoff) {
            unset($tokens[$existingToken]);
        }
    }

    $tokens[$token] = $issuedAt;
    $_SESSION['ppstudio_reservation_tokens'] = $tokens;

    return $token;
}

function reservationAntispamRateLimitPath(): string
{
    return ppstudioSecurityStorageDir() . '/reservation-rate-limit.json';
}

function reservationAntispamLogPath(): string
{
    return ppstudioSecurityStorageDir() . '/reservation-antispam.log';
}

function reservationAntispamConsumeToken(string $token): ?int
{
    startSecureSession();

    if ($token === '') {
        return null;
    }

    $tokens = $_SESSION['ppstudio_reservation_tokens'] ?? [];
    if (! is_array($tokens) || ! isset($tokens[$token])) {
        return null;
    }

    $issuedAt = (int) $tokens[$token];
    unset($tokens[$token]);
    $_SESSION['ppstudio_reservation_tokens'] = $tokens;

    return $issuedAt > 0 ? $issuedAt : null;
}

function reservationAntispamRateLimitCheck(string $ipAddress, int $limit = 8, int $windowSeconds = 600): array
{
    $storageFile = reservationAntispamRateLimitPath();
    $now = time();
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
        $filtered = array_values(array_filter($timestamps, static fn($value): bool => is_int($value) && $value > ($now - $windowSeconds)));
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

function reservationAntispamLog(string $reason, array $context = []): void
{
    securityEventLog(
        'antispam_' . trim($reason),
        'reservation_form',
        'warning',
        $context
    );

    // Backward-compatible local log file (fallback/audit trail).
    $logFile = reservationAntispamLogPath();
    $entry = [
        'time' => date('c'),
        'reason' => $reason,
        'ip' => getClientIpAddress(),
        'ua' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'context' => $context,
    ];

    @file_put_contents(
        $logFile,
        json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}
