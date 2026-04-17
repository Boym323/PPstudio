<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';

$emailConfig = require __DIR__ . '/config/email.php';

(new \PPStudio\Http\Controller\ReservationSubmitApplication(
    $emailConfig,
    ppstudioSecurityFacade()->publicSiteLockService(),
    ppstudioSecurityFacade()->csrfService(),
    ppstudioSecurityFacade()->reservationAntispamService(),
    ppstudioSecurityFacade()->requestSecurityService()
))->handle($_SERVER, $_POST);
