<?php

use PPStudio\Service\AdminAvailabilityMutationService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

$availabilityMutationService = new AdminAvailabilityMutationService(
    $connection,
    $siteSettings,
    dirname(__DIR__, 4)
);

if (isset($_POST['save_availability_grid'])) {
    $saveGridResult = $availabilityMutationService->saveAvailabilityGrid($_POST);
    if (($saveGridResult['success'] ?? false) === true) {
        $message = (string) ($saveGridResult['message'] ?? 'Dostupnost v kalendáři byla uložena.');
    } else {
        $error = (string) ($saveGridResult['error'] ?? 'Kalendář dostupnosti se nepodařilo uložit.');
    }
}

if (isset($_POST['delete_window'])) {
    $deleteWindowResult = $availabilityMutationService->deleteWindow($_POST);
    if (($deleteWindowResult['success'] ?? false) === true) {
        $message = (string) ($deleteWindowResult['message'] ?? 'Volné okno bylo odstraněno.');
    } else {
        $error = (string) ($deleteWindowResult['error'] ?? 'Okno se nepodařilo odstranit.');
    }
}

if (isset($_POST['save_availability_story_background'])) {
    $saveBackgroundResult = $availabilityMutationService->saveStoryBackground($_FILES);
    if (is_array($saveBackgroundResult['data']['site_settings'] ?? null)) {
        $siteSettings = $saveBackgroundResult['data']['site_settings'];
    }

    if (($saveBackgroundResult['success'] ?? false) === true) {
        $message = (string) ($saveBackgroundResult['message'] ?? 'Pozadí pro Instagram story bylo uloženo.');
    } else {
        $error = (string) ($saveBackgroundResult['error'] ?? 'Pozadí pro story se nepodařilo uložit.');
    }
}

if (isset($_POST['delete_availability_story_background'])) {
    $deleteBackgroundResult = $availabilityMutationService->deleteStoryBackground();
    if (is_array($deleteBackgroundResult['data']['site_settings'] ?? null)) {
        $siteSettings = $deleteBackgroundResult['data']['site_settings'];
    }

    if (($deleteBackgroundResult['success'] ?? false) === true) {
        $message = (string) ($deleteBackgroundResult['message'] ?? 'Pozadí pro Instagram story bylo odstraněno.');
    } else {
        $error = (string) ($deleteBackgroundResult['error'] ?? 'Pozadí pro story se nepodařilo odstranit.');
    }
}
