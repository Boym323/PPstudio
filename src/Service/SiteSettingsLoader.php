<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Config\AppConfig;
use PPStudio\Database\DatabaseFactory;
use PPStudio\Repository\SiteSettingsRepository;

final class SiteSettingsLoader
{
    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $appConfig = AppConfig::instance();
        $siteSettings = $appConfig->defaultSiteSettings();
        $connection = DatabaseFactory::tryConnect();

        if (! $connection instanceof \mysqli) {
            return $siteSettings;
        }

        try {
            return (new SiteSettingsService(
                new SiteSettingsRepository($connection),
                $appConfig->defaultSiteSettings()
            ))->load();
        } finally {
            $connection->close();
        }
    }
}
