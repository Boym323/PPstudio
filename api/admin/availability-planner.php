<?php
declare(strict_types=1);

use PPStudio\Http\Controller\Admin\AdminAvailabilityApiController;

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../config/app.php';

ppstudioSecurityFacade()->startSecureSession();

AdminAvailabilityApiController::handlePlannerSaveRequest($_SERVER, $_SESSION, $_POST, dirname(__DIR__, 2));
