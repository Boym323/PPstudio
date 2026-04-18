<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Config\AppConfig;
use mysqli;
use PPStudio\Database\DatabaseFactory;
use PPStudio\Repository\AvailabilityRepository;
use PPStudio\Repository\ReservationRepository;
use PPStudio\Repository\SiteSettingsRepository;
use PPStudio\Repository\ServiceRepository;

final class ReservationSubmitContextFactory
{
    public function create(): ?ReservationSubmitContext
    {
        $connection = DatabaseFactory::tryConnect();
        if (! $connection instanceof mysqli) {
            return null;
        }

        return new ReservationSubmitContext(
            $connection,
            $this->createReservationService($connection),
            (new SiteSettingsService(
                new SiteSettingsRepository($connection),
                AppConfig::instance()->defaultSiteSettings()
            ))->load()
        );
    }

    private function createReservationService(mysqli $connection): ReservationService
    {
        $serviceRepository = new ServiceRepository($connection);
        $availabilityRepository = new AvailabilityRepository($connection);
        $reservationRepository = new ReservationRepository($connection);

        return new ReservationService(
            $connection,
            $serviceRepository,
            $availabilityRepository,
            $reservationRepository,
            new AvailabilityService($serviceRepository, $availabilityRepository, $reservationRepository)
        );
    }
}
