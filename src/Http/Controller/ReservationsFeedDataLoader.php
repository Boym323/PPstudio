<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use PPStudio\Config\AppConfig;
use mysqli;
use PPStudio\Repository\SiteSettingsRepository;
use PPStudio\Service\MailerIntegrationService;
use PPStudio\Service\SiteSettingsService;

final class ReservationsFeedDataLoader
{
    /**
     * @param array<string, mixed> $emailConfig
     * @return array{ical: string}
     */
    public function load(mysqli $connection, array $emailConfig): array
    {
        $siteSettings = (new SiteSettingsService(
            new SiteSettingsRepository($connection),
            AppConfig::instance()->defaultSiteSettings()
        ))->load();

        return [
            'ical' => (new MailerIntegrationService($emailConfig))
                ->buildReservationsFeedIcal($connection, $siteSettings),
        ];
    }
}
