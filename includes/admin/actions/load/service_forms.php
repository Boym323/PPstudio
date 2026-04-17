<?php

use PPStudio\Http\Controller\Admin\AdminServiceFormDataLoader;
use PPStudio\Repository\ServiceRepository;
use PPStudio\Service\AdminServiceCatalogService;

$serviceFormDataLoader = new AdminServiceFormDataLoader(
    new AdminServiceCatalogService(new ServiceRepository($connection))
);
$serviceFormData = $serviceFormDataLoader->load(
    isset($_GET['edit_service']) ? (int) $_GET['edit_service'] : null,
    isset($_GET['edit_category']) ? (int) $_GET['edit_category'] : null
);

if (is_array($serviceFormData['service_form'] ?? null)) {
    $serviceForm = $serviceFormData['service_form'];
}

if (is_array($serviceFormData['category_form'] ?? null)) {
    $categoryForm = $serviceFormData['category_form'];
}
