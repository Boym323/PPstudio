<?php
declare(strict_types=1);

namespace PPStudio\Database;

use mysqli;
use mysqli_sql_exception;
use RuntimeException;
use Throwable;

final class DatabaseConnection
{
    public function __construct(
        private DatabaseConfig $config
    ) {
    }

    /**
     * @param array{host?: mixed, database?: mixed, username?: mixed, password?: mixed, charset?: mixed, port?: mixed} $config
     */
    public static function fromArray(array $config): self
    {
        return new self(DatabaseConfig::fromArray($config));
    }

    /**
     * @param array{host?: mixed, database?: mixed, username?: mixed, password?: mixed, charset?: mixed, port?: mixed} $config
     */
    public static function connectFromArray(array $config): mysqli
    {
        return self::fromArray($config)->connect();
    }

    /**
     * @param array{host?: mixed, database?: mixed, username?: mixed, password?: mixed, charset?: mixed, port?: mixed} $config
     */
    public static function tryFromArray(array $config): ?mysqli
    {
        try {
            return self::connectFromArray($config);
        } catch (Throwable) {
            return null;
        }
    }

    public function connect(): mysqli
    {
        try {
            if ($this->config->port !== null) {
                $connection = @new mysqli(
                    $this->config->host,
                    $this->config->username,
                    $this->config->password,
                    $this->config->database,
                    $this->config->port
                );
            } else {
                $connection = @new mysqli(
                    $this->config->host,
                    $this->config->username,
                    $this->config->password,
                    $this->config->database
                );
            }
        } catch (mysqli_sql_exception $exception) {
            throw new RuntimeException('Database connection failed: ' . $exception->getMessage(), (int) $exception->getCode(), $exception);
        }

        if ($connection->connect_errno) {
            throw new RuntimeException('Database connection failed: ' . $connection->connect_error, $connection->connect_errno);
        }

        try {
            $charsetSet = $connection->set_charset($this->config->charset);
        } catch (mysqli_sql_exception $exception) {
            $connection->close();

            throw new RuntimeException('Database charset setup failed: ' . $exception->getMessage(), (int) $exception->getCode(), $exception);
        }

        if (! $charsetSet) {
            $error = $connection->error;
            $connection->close();

            throw new RuntimeException('Database charset setup failed: ' . $error);
        }

        return $connection;
    }
}
