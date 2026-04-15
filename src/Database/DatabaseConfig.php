<?php
declare(strict_types=1);

namespace PPStudio\Database;

use InvalidArgumentException;

final class DatabaseConfig
{
    public private(set) string $host;
    public private(set) string $database;
    public private(set) string $username;
    public private(set) string $password;
    public private(set) string $charset;
    public private(set) ?int $port;

    public function __construct(
        string $host,
        string $database,
        string $username,
        string $password,
        string $charset = 'utf8mb4',
        ?int $port = null
    ) {
        $this->host = $host;
        $this->database = $database;
        $this->username = $username;
        $this->password = $password;
        $this->charset = $charset;
        $this->port = $port;
    }

    /**
     * @param array{host?: mixed, database?: mixed, username?: mixed, password?: mixed, charset?: mixed, port?: mixed} $config
     */
    public static function fromArray(array $config): self
    {
        foreach (['host', 'database', 'username', 'password'] as $key) {
            if (! array_key_exists($key, $config) || ! is_string($config[$key])) {
                throw new InvalidArgumentException(sprintf('Missing database config value "%s".', $key));
            }
        }

        $charset = $config['charset'] ?? 'utf8mb4';
        if (! is_string($charset) || $charset === '') {
            $charset = 'utf8mb4';
        }

        $port = $config['port'] ?? null;
        if (is_string($port) && ctype_digit($port)) {
            $port = (int) $port;
        }
        if (! is_int($port) || $port <= 0) {
            $port = null;
        }

        return new self(
            $config['host'],
            $config['database'],
            $config['username'],
            $config['password'],
            $charset,
            $port
        );
    }
}
