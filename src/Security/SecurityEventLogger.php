<?php
declare(strict_types=1);

namespace PPStudio\Security;

use mysqli;
use PPStudio\Database\DatabaseFactory;

final class SecurityEventLogger
{
    public function __construct(
        private RequestSecurityService $requestSecurityService
    ) {
    }

    public function fallbackPath(): string
    {
        return $this->requestSecurityService->storageDir() . '/security-events.log';
    }

    public function logFallback(
        string $eventType,
        string $eventSource,
        string $severity,
        string $ipAddress,
        string $userAgent,
        array $context = []
    ): void {
        $entry = [
            'time' => date('c'),
            'event_type' => $eventType,
            'event_source' => $eventSource,
            'severity' => $severity,
            'ip' => $ipAddress,
            'ua' => $userAgent,
            'context' => $context,
        ];

        @file_put_contents(
            $this->fallbackPath(),
            json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    public function log(
        string $eventType,
        string $eventSource,
        string $severity = 'info',
        ?array $context = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): bool {
        $eventType = trim($eventType);
        $eventSource = trim($eventSource);
        $severity = trim($severity) !== '' ? trim($severity) : 'info';
        $context = is_array($context) ? $context : [];
        $ipAddress = $ipAddress ?? $this->requestSecurityService->clientIpAddress();
        $userAgent = $userAgent ?? (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        if ($eventType === '' || $eventSource === '') {
            return false;
        }

        if (! class_exists('mysqli')) {
            $this->logFallback($eventType, $eventSource, $severity, $ipAddress, $userAgent, $context);
            return false;
        }

        $connection = DatabaseFactory::tryConnect();
        if (! $connection instanceof mysqli) {
            $this->logFallback($eventType, $eventSource, $severity, $ipAddress, $userAgent, $context);
            return false;
        }

        $statement = $connection->prepare(
            'INSERT INTO security_events (event_type, event_source, severity, ip_address, user_agent, context_json)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        if (! $statement) {
            $connection->close();
            $this->logFallback($eventType, $eventSource, $severity, $ipAddress, $userAgent, $context);
            return false;
        }

        $contextJson = (string) (json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        $statement->bind_param('ssssss', $eventType, $eventSource, $severity, $ipAddress, $userAgent, $contextJson);
        $success = $statement->execute();
        $statement->close();

        if ($success) {
            $cleanupStatement = $connection->prepare(
                'DELETE FROM security_events
                 WHERE created_at < (NOW() - INTERVAL 90 DAY)
                 ORDER BY created_at ASC
                 LIMIT 500'
            );
            if ($cleanupStatement) {
                $cleanupStatement->execute();
                $cleanupStatement->close();
            }
        }

        $connection->close();

        if (! $success) {
            $this->logFallback($eventType, $eventSource, $severity, $ipAddress, $userAgent, $context);
        }

        return $success;
    }
}
