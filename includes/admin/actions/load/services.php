<?php

use PPStudio\Repository\ServiceRepository;
use PPStudio\Service\AdminServiceCatalogService;

$serviceCatalogService = new AdminServiceCatalogService(new ServiceRepository($connection));
$serviceCatalogData = $serviceCatalogService->loadData($serviceFilters, $serviceStatusFilterOptions);

if (is_array($serviceCatalogData['service_filters'] ?? null)) {
    $serviceFilters = $serviceCatalogData['service_filters'];
}

if (is_array($serviceCatalogData['service_category_rows'] ?? null)) {
    $serviceCategoryRows = $serviceCatalogData['service_category_rows'];
}

if (is_array($serviceCatalogData['service_category_filter_options'] ?? null)) {
    $serviceCategoryFilterOptions = $serviceCatalogData['service_category_filter_options'];
}

if (is_array($serviceCatalogData['service_rows'] ?? null)) {
    $serviceRows = $serviceCatalogData['service_rows'];
}

if (is_array($serviceCatalogData['service_price_history_rows'] ?? null)) {
    $servicePriceHistoryRows = $serviceCatalogData['service_price_history_rows'];
}

$serviceSectionViewData = $serviceCatalogService->buildSectionViewData(
    $serviceRows,
    $servicePriceHistoryRows,
    $serviceFilters,
    $categoryForm ?? [],
    $_GET
);

if (is_array($serviceSectionViewData['service_base_params'] ?? null)) {
    $serviceBaseParams = $serviceSectionViewData['service_base_params'];
}

if (is_array($serviceSectionViewData['service_rows_prepared'] ?? null)) {
    $serviceRowsPrepared = $serviceSectionViewData['service_rows_prepared'];
}

if (is_array($serviceSectionViewData['service_price_changes'] ?? null)) {
    $servicePriceChanges = $serviceSectionViewData['service_price_changes'];
}

if (is_int($serviceSectionViewData['service_price_changes_total'] ?? null)) {
    $servicePriceChangesTotal = $serviceSectionViewData['service_price_changes_total'];
}

if (is_array($serviceSectionViewData['service_price_changes_preview'] ?? null)) {
    $servicePriceChangesPreview = $serviceSectionViewData['service_price_changes_preview'];
}

if (is_string($serviceSectionViewData['active_services_section'] ?? null) && $serviceSectionViewData['active_services_section'] !== '') {
    $activeServicesSection = $serviceSectionViewData['active_services_section'];
}
