<?php

use PPStudio\Http\Controller\Admin\AdminReservationPostActionHandler;
use PPStudio\Service\AdminReservationMutationService;

$reservationPostResult = (new AdminReservationPostActionHandler(
    AdminReservationMutationService::create($connection, $emailConfig, $siteSettings)
))->handle($_SERVER, $_POST, $_SESSION, $manualReservationForm);

$message = $reservationPostResult['message'] !== '' ? $reservationPostResult['message'] : $message;
$error = $reservationPostResult['error'] !== '' ? $reservationPostResult['error'] : $error;
$manualReservationForm = $reservationPostResult['manual_reservation_form'];
