<?php

use PPStudio\Repository\ServiceRepository;
use PPStudio\Service\AdminServiceCatalogService;

$serviceCatalogService = new AdminServiceCatalogService(new ServiceRepository($connection));
$serviceFormData = $serviceCatalogService->loadFormData(
    isset($_GET['edit_service']) ? (int) $_GET['edit_service'] : null,
    isset($_GET['edit_category']) ? (int) $_GET['edit_category'] : null
);

if (is_array($serviceFormData['service_form'] ?? null)) {
    $serviceForm = $serviceFormData['service_form'];
}

if (is_array($serviceFormData['category_form'] ?? null)) {
    $categoryForm = $serviceFormData['category_form'];
}
