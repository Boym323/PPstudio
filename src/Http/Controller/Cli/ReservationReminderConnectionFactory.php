<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Cli;

use mysqli;
use PDO;
use PPStudio\Database\DatabaseFactory;
use RuntimeException;

final class ReservationReminderConnectionFactory
{
    /**
     * @return mysqli|PDO
     */
    public function create(): mysqli|PDO
    {
        $dbConfig = DatabaseFactory::loadProjectConfig(['port' => 3306]);
        $host = (string) ($dbConfig['host'] ?? '127.0.0.1');
        $database = (string) ($dbConfig['database'] ?? '');
        $username = (string) ($dbConfig['username'] ?? '');
        $password = (string) ($dbConfig['password'] ?? '');
        $charset = (string) ($dbConfig['charset'] ?? 'utf8mb4');

        if (class_exists('mysqli')) {
            return DatabaseFactory::connect(['port' => 3306]);
        }

        if (class_exists('PDO') && extension_loaded('pdo_mysql')) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $host,
                $database,
                $charset !== '' ? $charset : 'utf8mb4'
            );

            return new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }

        throw new RuntimeException('CLI PHP nemá MySQL driver (`mysqli` ani `pdo_mysql`). Spusťte skript přes stejnou PHP binárku, kterou používá Web Station.');
    }
}
