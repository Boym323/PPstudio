<?php
declare(strict_types=1);

function securityEventsFallbackPath(): string
{
    if (function_exists('ppstudioSecurityStorageDir')) {
        return ppstudioSecurityStorageDir() . '/security-events.log';
    }

    return dirname(__DIR__) . '/var/security/security-events.log';
}

function securityEventLogFallback(
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
        securityEventsFallbackPath(),
        json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function securityEventLog(
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
    $ipAddress = $ipAddress ?? (function_exists('getClientIpAddress') ? getClientIpAddress() : (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $userAgent = $userAgent ?? (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

    if ($eventType === '' || $eventSource === '') {
        return false;
    }

    $dbConfigPath = dirname(__DIR__) . '/config/database.php';
    if (! is_file($dbConfigPath)) {
        securityEventLogFallback($eventType, $eventSource, $severity, $ipAddress, $userAgent, $context);
        return false;
    }

    $dbConfig = require $dbConfigPath;
    if (! is_array($dbConfig)) {
        securityEventLogFallback($eventType, $eventSource, $severity, $ipAddress, $userAgent, $context);
        return false;
    }

    $connection = @new mysqli(
        (string) ($dbConfig['host'] ?? ''),
        (string) ($dbConfig['username'] ?? ''),
        (string) ($dbConfig['password'] ?? ''),
        (string) ($dbConfig['database'] ?? '')
    );

    if ($connection->connect_errno) {
        securityEventLogFallback($eventType, $eventSource, $severity, $ipAddress, $userAgent, $context);
        return false;
    }

    $connection->set_charset((string) ($dbConfig['charset'] ?? 'utf8mb4'));

    $statement = $connection->prepare(
        'INSERT INTO security_events (event_type, event_source, severity, ip_address, user_agent, context_json)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    if (! $statement) {
        $connection->close();
        securityEventLogFallback($eventType, $eventSource, $severity, $ipAddress, $userAgent, $context);
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
        securityEventLogFallback($eventType, $eventSource, $severity, $ipAddress, $userAgent, $context);
    }

    return $success;
}
