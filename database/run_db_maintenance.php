<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/config/app.php';

exit((new \PPStudio\Support\DatabaseMaintenanceRunner(dirname(__DIR__)))->run());
