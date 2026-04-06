<?php
declare(strict_types=1);

function loadSiteSettings(mysqli $connection): array
{
    $settings = defaultSiteSettings();

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

function saveSiteSetting(mysqli $connection, string $key, string $value): bool
{
    $statement = $connection->prepare(
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
