<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/security.php';

use PPStudio\Security\SecurityEventLogger;

function ppstudioSecurityEventLogger(): SecurityEventLogger
{
    static $logger = null;

    if (! $logger instanceof SecurityEventLogger) {
        $logger = new SecurityEventLogger(ppstudioRequestSecurityService());
    }

    return $logger;
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
