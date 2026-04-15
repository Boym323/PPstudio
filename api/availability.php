<?php
declare(strict_types=1);

use PPStudio\Http\Controller\ApiAvailabilityController;

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/site_lock.php';

requirePublicSiteAccessOrJsonError();

ApiAvailabilityController::handleRequest($_GET);
