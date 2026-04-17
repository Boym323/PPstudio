<?php
declare(strict_types=1);

use PPStudio\Http\Controller\Cli\ReservationReminderApplication;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Tento skript lze spouštět jen z CLI.\n";
    exit(1);
}

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';

$emailConfig = require __DIR__ . '/config/email.php';
ReservationReminderApplication::create($emailConfig)->handle($argv ?? []);
