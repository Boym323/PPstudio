<?php

use PPStudio\Service\AdminServiceMutationService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

$serviceMutationService = new AdminServiceMutationService($connection);

$applyServiceMutationResult = static function (array $result) use (&$message, &$error, &$serviceForm, &$categoryForm): void {
    if (is_array($result['data']['service_form'] ?? null)) {
        $serviceForm = $result['data']['service_form'];
    }

    if (is_array($result['data']['category_form'] ?? null)) {
        $categoryForm = $result['data']['category_form'];
    }

    if (is_string($result['message'] ?? null) && ($result['message'] ?? '') !== '') {
        $message = (string) $result['message'];
    }

    if (is_string($result['error'] ?? null) && ($result['error'] ?? '') !== '') {
        $error = (string) $result['error'];
    }
};

if (isset($_POST['save_category'])) {
    $applyServiceMutationResult($serviceMutationService->saveCategory($_POST));
}

if (isset($_POST['toggle_category_active'])) {
    $applyServiceMutationResult($serviceMutationService->toggleCategoryActive($_POST));
}

if (isset($_POST['save_category_order'])) {
    $applyServiceMutationResult($serviceMutationService->saveCategoryOrder($_POST));
}

if (isset($_POST['save_service'])) {
    $applyServiceMutationResult($serviceMutationService->saveService($_POST));
}

if (isset($_POST['toggle_service_active'])) {
    $applyServiceMutationResult($serviceMutationService->toggleServiceActive($_POST));
}
