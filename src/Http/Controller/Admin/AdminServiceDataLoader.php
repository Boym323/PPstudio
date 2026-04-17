<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Service\AdminServiceCatalogService;

final class AdminServiceDataLoader
{
    public function __construct(
        private AdminServiceCatalogService $serviceCatalogService
    ) {
    }

    /**
     * @param array<string, mixed> $serviceFilters
     * @param array<string, string> $serviceStatusFilterOptions
     * @param array<int, array<string, mixed>> $serviceRows
     * @param array<int, array<string, mixed>> $servicePriceHistoryRows
     * @param array<string, mixed> $categoryForm
     * @param array<string, mixed> $query
     * @return array{
     *     service_filters?: array<string, mixed>,
     *     service_category_rows?: array<int, array<string, mixed>>,
     *     service_category_filter_options?: array<string, string>,
     *     service_rows?: array<int, array<string, mixed>>,
     *     service_price_history_rows?: array<int, array<string, mixed>>,
     *     service_base_params?: array<string, mixed>,
     *     service_rows_prepared?: array<int, array<string, mixed>>,
     *     service_price_changes?: array<int, array<string, mixed>>,
     *     service_price_changes_total?: int,
     *     service_price_changes_preview?: array<int, array<string, mixed>>,
     *     active_services_section?: string
     * }
     */
    public function load(
        array $serviceFilters,
        array $serviceStatusFilterOptions,
        array $serviceRows,
        array $servicePriceHistoryRows,
        array $categoryForm,
        array $query
    ): array {
        $serviceCatalogData = $this->serviceCatalogService->loadData($serviceFilters, $serviceStatusFilterOptions);
        $normalizedServiceFilters = is_array($serviceCatalogData['service_filters'] ?? null)
            ? $serviceCatalogData['service_filters']
            : $serviceFilters;
        $normalizedServiceRows = is_array($serviceCatalogData['service_rows'] ?? null)
            ? $serviceCatalogData['service_rows']
            : $serviceRows;
        $normalizedServicePriceHistoryRows = is_array($serviceCatalogData['service_price_history_rows'] ?? null)
            ? $serviceCatalogData['service_price_history_rows']
            : $servicePriceHistoryRows;

        $sectionViewData = $this->serviceCatalogService->buildSectionViewData(
            $normalizedServiceRows,
            $normalizedServicePriceHistoryRows,
            $normalizedServiceFilters,
            $categoryForm,
            $query
        );

        return array_replace($serviceCatalogData, $sectionViewData);
    }
}
