#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Tento skript lze spouštět jen z CLI.\n";
    exit(1);
}

require_once __DIR__ . '/_test_helpers.php';

exit((new \PPStudio\Support\AdminReservationActionTestRunner('[admin-reservation-action-tests]'))->run($argv));
