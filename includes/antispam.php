<?php
declare(strict_types=1);

require_once __DIR__ . '/security_events.php';

function reservationAntispamIssueToken(?int $issuedAt = null): string
{
    return ppstudioReservationAntispamService()->issueToken($issuedAt);
}

function reservationAntispamRateLimitPath(): string
{
    return ppstudioReservationAntispamService()->rateLimitPath();
}

function reservationAntispamLogPath(): string
{
    return ppstudioReservationAntispamService()->logPath();
}

function reservationAntispamConsumeToken(string $token): ?int
{
    return ppstudioReservationAntispamService()->consumeToken($token);
}

function reservationAntispamRateLimitCheck(string $ipAddress, int $limit = 8, int $windowSeconds = 600): array
{
    return ppstudioReservationAntispamService()->rateLimitCheck($ipAddress, $limit, $windowSeconds);
}

function reservationAntispamLog(string $reason, array $context = []): void
{
    ppstudioReservationAntispamService()->log($reason, $context);
}
