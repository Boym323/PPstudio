<?php
declare(strict_types=1);

use PPStudio\Http\Controller\Admin\AdminAvailabilityController;
use PPStudio\Http\Controller\Admin\AdminMediaController;
use PPStudio\Http\Controller\Admin\AdminReservationController;
use PPStudio\Http\Controller\Admin\AdminServiceController;
use PPStudio\Http\Controller\Admin\AdminSettingsController;
use PPStudio\Http\Controller\Admin\AdminVoucherController;

$adminRoot = dirname(__DIR__, 3);

require $adminRoot . '/includes/admin/actions/post/helpers.php';

foreach (
    array_merge(
        AdminSettingsController::postActionFiles($adminRoot),
        AdminVoucherController::postActionFiles($adminRoot),
        AdminSettingsController::integrationPostActionFiles($adminRoot),
        AdminServiceController::postActionFiles($adminRoot),
        AdminAvailabilityController::postActionFiles($adminRoot),
        AdminReservationController::postActionFiles($adminRoot),
        AdminMediaController::postActionFiles($adminRoot)
    ) as $adminPostActionFile
) {
    require $adminPostActionFile;
}
