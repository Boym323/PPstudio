<?php

use PPStudio\Http\Controller\Admin\AdminServiceDataLoader;
use PPStudio\Repository\ServiceRepository;
use PPStudio\Service\AdminServiceCatalogService;

$serviceDataLoader = new AdminServiceDataLoader(
    new AdminServiceCatalogService(new ServiceRepository($connection))
);
$serviceData = $serviceDataLoader->load(
    $serviceFilters,
    $serviceStatusFilterOptions,
    $serviceRows,
    $servicePriceHistoryRows,
    $categoryForm ?? [],
    $_GET
);

if (is_array($serviceData['service_filters'] ?? null)) {
    $serviceFilters = $serviceData['service_filters'];
}

if (is_array($serviceData['service_category_rows'] ?? null)) {
    $serviceCategoryRows = $serviceData['service_category_rows'];
}

if (is_array($serviceData['service_category_filter_options'] ?? null)) {
    $serviceCategoryFilterOptions = $serviceData['service_category_filter_options'];
}

if (is_array($serviceData['service_rows'] ?? null)) {
    $serviceRows = $serviceData['service_rows'];
}

if (is_array($serviceData['service_price_history_rows'] ?? null)) {
    $servicePriceHistoryRows = $serviceData['service_price_history_rows'];
}

if (is_array($serviceData['service_base_params'] ?? null)) {
    $serviceBaseParams = $serviceData['service_base_params'];
}

if (is_array($serviceData['service_rows_prepared'] ?? null)) {
    $serviceRowsPrepared = $serviceData['service_rows_prepared'];
}

if (is_array($serviceData['service_price_changes'] ?? null)) {
    $servicePriceChanges = $serviceData['service_price_changes'];
}

if (is_int($serviceData['service_price_changes_total'] ?? null)) {
    $servicePriceChangesTotal = $serviceData['service_price_changes_total'];
}

if (is_array($serviceData['service_price_changes_preview'] ?? null)) {
    $servicePriceChangesPreview = $serviceData['service_price_changes_preview'];
}

if (is_string($serviceData['active_services_section'] ?? null) && $serviceData['active_services_section'] !== '') {
    $activeServicesSection = $serviceData['active_services_section'];
}
