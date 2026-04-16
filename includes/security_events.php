<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/security.php';

use PPStudio\Security\SecurityEventLogger;

function ppstudioSecurityEventLogger(): SecurityEventLogger
{
    return ppstudioSecurityEventLoggerService();
}

function securityEventsFallbackPath(): string
{
    return ppstudioSecurityEventLogger()->fallbackPath();
}

function securityEventLogFallback(
    string $eventType,
    string $eventSource,
    string $severity,
    string $ipAddress,
    string $userAgent,
    array $context = []
): void {
    ppstudioSecurityEventLogger()->logFallback($eventType, $eventSource, $severity, $ipAddress, $userAgent, $context);
}

function securityEventLog(
    string $eventType,
    string $eventSource,
    string $severity = 'info',
    ?array $context = null,
    ?string $ipAddress = null,
    ?string $userAgent = null
): bool {
    return ppstudioSecurityEventLogger()->log($eventType, $eventSource, $severity, $context, $ipAddress, $userAgent);
}
