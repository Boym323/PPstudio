<?php
declare(strict_types=1);

use PPStudio\Http\Controller\ApiGoogleReviewsController;

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/site_lock.php';
require __DIR__ . '/../includes/settings.php';

requirePublicSiteAccessOrJsonError();

ApiGoogleReviewsController::handleRequest();
