<?php
declare(strict_types=1);

use PPStudio\Http\Controller\Admin\AdminAvailabilityApiController;

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/security.php';

startSecureSession();

AdminAvailabilityApiController::handleRequest($_GET, $_SESSION);
