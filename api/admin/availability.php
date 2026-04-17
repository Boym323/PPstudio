<?php
declare(strict_types=1);

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../config/app.php';

(new \PPStudio\Http\Controller\HttpEntryPointApplication(dirname(__DIR__, 2)))->handleAdminAvailabilityApi($_GET, $_SESSION);
