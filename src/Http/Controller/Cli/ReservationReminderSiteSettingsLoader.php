<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Cli;

use PPStudio\Config\AppConfig;
use mysqli;
use mysqli_result;
use PDO;

final class ReservationReminderSiteSettingsLoader
{
    /**
     * @return array<string, string>
     */
    public function load(mysqli|PDO $connection): array
    {
        $settings = AppConfig::instance()->defaultSiteSettings();

        if ($connection instanceof mysqli) {
            $query = $connection->query('SELECT setting_key, setting_value FROM nastaveni');
            if ($query instanceof mysqli_result) {
                while ($row = $query->fetch_assoc()) {
                    $key = (string) ($row['setting_key'] ?? '');
                    if ($key !== '') {
                        $settings[$key] = (string) ($row['setting_value'] ?? '');
                    }
                }
                $query->free();
            }

            return $settings;
        }

        $statement = $connection->query('SELECT setting_key, setting_value FROM nastaveni');
        foreach ($statement->fetchAll() as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            if ($key !== '') {
                $settings[$key] = (string) ($row['setting_value'] ?? '');
            }
        }

        return $settings;
    }
}
