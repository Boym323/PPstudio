<?php
declare(strict_types=1);

use PPStudio\Http\Controller\Admin\AdminAvailabilityStoryController;

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/admin/availability_story.php';

ppstudioSecurityFacade()->startSecureSession();

AdminAvailabilityStoryController::handle($_SERVER, $_GET, $_POST, $_SESSION);
