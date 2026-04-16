<?php

use PPStudio\Repository\AvailabilityRepository;
use PPStudio\Repository\ReservationRepository;
use PPStudio\Repository\ServiceRepository;
use PPStudio\Service\AdminDashboardService;
use PPStudio\Service\AvailabilityService;

$availabilityRepository = new AvailabilityRepository($connection);
$reservationRepository = new ReservationRepository($connection);
$serviceRepository = new ServiceRepository($connection);
$availabilityService = new AvailabilityService(
    $serviceRepository,
    $availabilityRepository,
    $reservationRepository
);

$dashboardService = new AdminDashboardService(
    $connection,
    $availabilityRepository,
    $reservationRepository,
    $availabilityService
);

$dashboardData = $dashboardService->loadDashboardData();
foreach ($dashboardData as $key => $value) {
    $$key = $value;
}
