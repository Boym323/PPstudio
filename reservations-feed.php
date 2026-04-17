<?php
declare(strict_types=1);

use PPStudio\Http\Controller\ReservationsFeedApplication;
use PPStudio\Http\Request\ReservationsFeedRequest;

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';

$emailConfig = require __DIR__ . '/config/email.php';
(new ReservationsFeedApplication($emailConfig))
    ->handle(ReservationsFeedRequest::fromQuery($_GET));
