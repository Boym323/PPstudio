<?php
declare(strict_types=1);

namespace PPStudio\Database;

use mysqli;
use mysqli_sql_exception;
use RuntimeException;

final class DatabaseConnection
{
    public function __construct(
        private DatabaseConfig $config
    ) {
    }

    /**
     * @param array{host?: mixed, database?: mixed, username?: mixed, password?: mixed, charset?: mixed} $config
     */
    public static function fromArray(array $config): self
    {
        return new self(DatabaseConfig::fromArray($config));
    }

    public function connect(): mysqli
    {
        try {
            $connection = @new mysqli(
                $this->config->host,
                $this->config->username,
                $this->config->password,
                $this->config->database
            );
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
