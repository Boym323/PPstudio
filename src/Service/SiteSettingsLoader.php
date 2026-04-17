<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Database\DatabaseFactory;
use PPStudio\Repository\SiteSettingsRepository;

final class SiteSettingsLoader
{
    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $siteSettings = \defaultSiteSettings();
        $connection = DatabaseFactory::tryConnect();

        if (! $connection instanceof \mysqli) {
            return $siteSettings;
        }

        try {
            return (new SiteSettingsService(
                new SiteSettingsRepository($connection),
                \defaultSiteSettings()
            ))->load();
        } finally {
            $connection->close();
        }
    }
}
