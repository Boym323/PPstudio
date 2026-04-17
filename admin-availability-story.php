<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';

(new \PPStudio\Http\Controller\HttpEntryPointApplication(__DIR__))->handleAdminAvailabilityStory($_SERVER, $_GET, $_POST, $_SESSION);
