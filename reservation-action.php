<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/security_events.php';
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/mailer.php';

$emailConfig = require __DIR__ . '/config/email.php';

\PPStudio\Http\Controller\ReservationActionApplication::create($emailConfig)
    ->handleAdminAction($_GET);
