<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use PPStudio\Repository\SiteSettingsRepository;
use PPStudio\Service\SiteSettingsService;

function ppstudioSiteSettingsService(mysqli $connection): SiteSettingsService
{
    return new SiteSettingsService(
        new SiteSettingsRepository($connection),
        defaultSiteSettings()
    );
}

function loadSiteSettings(mysqli $connection): array
{
    return ppstudioSiteSettingsService($connection)->load();
}

function saveSiteSetting(mysqli $connection, string $key, string $value): bool
{
    return ppstudioSiteSettingsService($connection)->save($key, $value);
}
