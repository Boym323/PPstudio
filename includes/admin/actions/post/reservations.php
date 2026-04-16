<?php

use PPStudio\Service\AdminReservationMutationService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

$reservationMutationService = AdminReservationMutationService::create($connection, $emailConfig, $siteSettings);

if (isset($_POST['update_reservation'])) {
    $updateResult = $reservationMutationService->updateReservation($_POST, $_SESSION);
    if (($updateResult['success'] ?? false) === true) {
        $message = (string) ($updateResult['message'] ?? 'Rezervace byla upravena.');
    } else {
        $error = (string) ($updateResult['error'] ?? 'Rezervaci se nepodařilo upravit.');
    }
}

if (isset($_POST['save_manual_reservation'])) {
    $manualCreateResult = $reservationMutationService->createManualReservation($_POST);
    $manualReservationForm = is_array($manualCreateResult['data']['manual_reservation_form'] ?? null)
        ? $manualCreateResult['data']['manual_reservation_form']
        : $manualReservationForm;

    if (($manualCreateResult['success'] ?? false) === true) {
        $message = (string) ($manualCreateResult['message'] ?? 'Ruční rezervace byla vložena.');
    } else {
        $error = (string) ($manualCreateResult['error'] ?? 'Ruční rezervaci se nepodařilo uložit.');
    }
}

if (isset($_POST['delete_reservation'])) {
    $deleteResult = $reservationMutationService->deleteReservation($_POST);
    if (($deleteResult['success'] ?? false) === true) {
        $message = (string) ($deleteResult['message'] ?? 'Rezervace byla smazána.');
    } else {
        $error = (string) ($deleteResult['error'] ?? 'Rezervaci se nepodařilo smazat.');
    }
}
