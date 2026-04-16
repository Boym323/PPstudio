<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/availability.php';
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/mailer.php';

$emailConfig = require __DIR__ . '/config/email.php';

startSecureSession();

$controller = new \PPStudio\Http\Controller\ReservationController(
    new \PPStudio\Service\ReservationSubmitService(
        new \PPStudio\Service\ReservationNotificationService($emailConfig)
    ),
    ppstudioPublicSiteLockService(),
    ppstudioCsrfService(),
    ppstudioReservationAntispamService(),
    ppstudioRequestSecurityService()
);
$controller->submit($_SERVER, $_POST);
