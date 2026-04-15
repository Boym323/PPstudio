<?php
declare(strict_types=1);

namespace PPStudio\Repository;

use mysqli;
use mysqli_result;

final class SiteSettingsRepository
{
    public function __construct(
        private mysqli $connection
    ) {
    }

    public function findAll(): array
    {
        $query = $this->connection->query('SELECT setting_key, setting_value FROM nastaveni');

        if (! $query instanceof mysqli_result) {
            return [];
        }

        $settings = [];

        while ($row = $query->fetch_assoc()) {
            $key = (string) ($row['setting_key'] ?? '');

            if ($key !== '') {
                $settings[$key] = (string) ($row['setting_value'] ?? '');
            }
        }

        $query->free();

        return $settings;
    }

    public function save(string $key, string $value): bool
    {
        $statement = $this->connection->prepare(
            'INSERT INTO nastaveni (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );

        if (! $statement) {
            return false;
        }

        $statement->bind_param('ss', $key, $value);
        $success = $statement->execute();
        $statement->close();

        return $success;
    }
}
