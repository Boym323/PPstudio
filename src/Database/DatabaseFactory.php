<?php
declare(strict_types=1);

namespace PPStudio\Database;

use mysqli;
use RuntimeException;
use Throwable;

final class DatabaseFactory
{
    public function __construct(
        private string $configPath
    ) {
    }

    public static function projectDefault(): self
    {
        return new self(dirname(__DIR__, 2) . '/config/database.php');
    }

    /**
     * @param array{host?: mixed, database?: mixed, username?: mixed, password?: mixed, charset?: mixed, port?: mixed} $defaults
     */
    public static function connect(array $defaults = []): mysqli
    {
        return self::projectDefault()->create($defaults);
    }

    /**
     * @param array{host?: mixed, database?: mixed, username?: mixed, password?: mixed, charset?: mixed, port?: mixed} $defaults
     */
    public static function tryConnect(array $defaults = []): ?mysqli
    {
        try {
            return self::connect($defaults);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array{host?: mixed, database?: mixed, username?: mixed, password?: mixed, charset?: mixed, port?: mixed} $defaults
     * @return array{host?: mixed, database?: mixed, username?: mixed, password?: mixed, charset?: mixed, port?: mixed}
     */
    public static function loadProjectConfig(array $defaults = []): array
    {
        return self::projectDefault()->loadConfig($defaults);
    }

    /**
     * @param array{host?: mixed, database?: mixed, username?: mixed, password?: mixed, charset?: mixed, port?: mixed} $defaults
     */
    public function create(array $defaults = []): mysqli
    {
        return DatabaseConnection::connectFromArray($this->loadConfig($defaults));
    }

    /**
     * @param array{host?: mixed, database?: mixed, username?: mixed, password?: mixed, charset?: mixed, port?: mixed} $defaults
     */
    public function tryCreate(array $defaults = []): ?mysqli
    {
        try {
            return $this->create($defaults);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array{host?: mixed, database?: mixed, username?: mixed, password?: mixed, charset?: mixed, port?: mixed} $defaults
     * @return array{host?: mixed, database?: mixed, username?: mixed, password?: mixed, charset?: mixed, port?: mixed}
     */
    private function loadConfig(array $defaults = []): array
    {
        if (! is_file($this->configPath)) {
            throw new RuntimeException('Database config file not found: ' . $this->configPath);
        }

        $config = require $this->configPath;
        if (! is_array($config)) {
            throw new RuntimeException('Database config file must return an array: ' . $this->configPath);
        }

        return $config + $defaults;
    }
}
