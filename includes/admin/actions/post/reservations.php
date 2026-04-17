<?php

use PPStudio\Http\Controller\Admin\AdminReservationPostActionHandler;
use PPStudio\Service\AdminReservationModule;

$reservationPostResult = (new AdminReservationPostActionHandler(
    (new AdminReservationModule($connection, $emailConfig, $siteSettings))->mutationService()
))->handle($_SERVER, $_POST, $_SESSION, $manualReservationForm);

$message = $reservationPostResult['message'] !== '' ? $reservationPostResult['message'] : $message;
$error = $reservationPostResult['error'] !== '' ? $reservationPostResult['error'] : $error;
$manualReservationForm = $reservationPostResult['manual_reservation_form'];
