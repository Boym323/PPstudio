<?php
declare(strict_types=1);

use PPStudio\Http\Controller\Admin\AdminAvailabilityController;
use PPStudio\Http\Controller\Admin\AdminDashboardController;
use PPStudio\Http\Controller\Admin\AdminMediaController;
use PPStudio\Http\Controller\Admin\AdminReservationController;
use PPStudio\Http\Controller\Admin\AdminServiceController;
use PPStudio\Http\Controller\Admin\AdminVoucherController;

$adminRoot = dirname(__DIR__, 3);

foreach (
    array_merge(
        AdminServiceController::dataFiles($adminRoot),
        AdminAvailabilityController::availabilityWindowDataFiles($adminRoot),
        AdminReservationController::dataFiles($adminRoot),
        AdminDashboardController::dataFiles($adminRoot),
        AdminMediaController::dataFiles($adminRoot),
        AdminAvailabilityController::plannerDataFiles($adminRoot),
        AdminVoucherController::dataFiles($adminRoot)
    ) as $adminDataFile
) {
    require $adminDataFile;
}
