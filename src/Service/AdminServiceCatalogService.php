<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Repository\ServiceRepository;

final class AdminServiceCatalogService
{
    private const ALLOWED_SECTIONS = ['procedures', 'categories', 'price-history'];

    public function __construct(
        private ServiceRepository $serviceRepository
    ) {
    }

    public function loadData(array $serviceFilters, array $serviceStatusFilterOptions): array
    {
        $serviceCategoryRows = $this->serviceRepository->findAdminCategoryRows();
        $serviceCategoryFilterOptions = ['all' => 'Všechny kategorie'];

        foreach ($serviceCategoryRows as $categoryRow) {
            $categoryId = (string) ($categoryRow['id'] ?? '');
            if ($categoryId === '') {
                continue;
            }

            $categoryLabel = (string) ($categoryRow['nazev'] ?? '');
            if ((int) ($categoryRow['aktivni'] ?? 1) !== 1) {
                $categoryLabel .= ' (neaktivní)';
            }

            $serviceCategoryFilterOptions[$categoryId] = $categoryLabel;
        }

        if (! in_array($serviceFilters['status'] ?? 'all', array_keys($serviceStatusFilterOptions), true)) {
            $serviceFilters['status'] = 'all';
        }

        if (! in_array($serviceFilters['category'] ?? 'all', array_keys($serviceCategoryFilterOptions), true)) {
            $serviceFilters['category'] = 'all';
        }

        return [
            'service_filters' => $serviceFilters,
            'service_category_rows' => $serviceCategoryRows,
            'service_category_filter_options' => $serviceCategoryFilterOptions,
            'service_rows' => $this->serviceRepository->findAdminRows($serviceFilters),
            'service_price_history_rows' => $this->serviceRepository->findPriceHistoryRows(),
        ];
    }

    public function buildSectionViewData(
        array $serviceRows,
        array $servicePriceHistoryRows,
        array $serviceFilters,
        array $categoryForm,
        array $query
    ): array {
        $servicePriceHistoryByService = [];

        foreach ($servicePriceHistoryRows as $historyRow) {
            $historyServiceId = (int) ($historyRow['sluzba_id'] ?? 0);
            if ($historyServiceId <= 0) {
                continue;
            }

            if (! isset($servicePriceHistoryByService[$historyServiceId])) {
                $servicePriceHistoryByService[$historyServiceId] = [];
            }

            $servicePriceHistoryByService[$historyServiceId][] = $historyRow;
        }

        $preparedServiceRows = [];

        foreach ($serviceRows as $row) {
            $serviceId = (int) ($row['id'] ?? 0);
            $serviceDescription = trim((string) ($row['popis'] ?? ''));
            $serviceBadge = trim((string) ($row['stitek'] ?? ''));
            $serviceHistoryItems = $servicePriceHistoryByService[$serviceId] ?? [];

            $row['description_text'] = $serviceDescription !== '' ? $serviceDescription : 'Bez popisu';
            $row['description_preview'] = $this->truncateDescription($serviceDescription);
            $row['badge_text'] = $serviceBadge;
            $row['category_label'] = trim((string) ($row['kategorie'] ?? '')) !== '' ? (string) $row['kategorie'] : 'Ostatní služby';
            $row['is_active'] = (int) ($row['service_active'] ?? 1) === 1;
            $row['history_items'] = $serviceHistoryItems;
            $row['history_preview'] = array_slice($serviceHistoryItems, 0, 5);

            $preparedServiceRows[] = $row;
        }

        $servicePriceChanges = [];

        foreach ($servicePriceHistoryByService as $historyServiceId => $serviceHistoryItems) {
            $itemsCount = count($serviceHistoryItems);

            for ($historyIndex = 0; $historyIndex < $itemsCount; $historyIndex++) {
                $newerItem = $serviceHistoryItems[$historyIndex];
                $olderItem = $serviceHistoryItems[$historyIndex + 1] ?? null;

                $servicePriceChanges[] = [
                    'sluzba_id' => $historyServiceId,
                    'sluzba_nazev' => (string) ($newerItem['sluzba_nazev'] ?? ''),
                    'new_price' => $newerItem['cena'] ?? null,
                    'old_price' => $olderItem['cena'] ?? null,
                    'changed_at' => (string) ($newerItem['platna_od'] ?? ''),
                    'is_initial' => $olderItem === null,
                ];
            }
        }

        usort(
            $servicePriceChanges,
            static function (array $left, array $right): int {
                $leftTime = strtotime((string) ($left['changed_at'] ?? '')) ?: 0;
                $rightTime = strtotime((string) ($right['changed_at'] ?? '')) ?: 0;

                return $rightTime <=> $leftTime;
            }
        );

        return [
            'service_base_params' => [
                'tab' => 'sluzby-admin',
                'service_q' => $serviceFilters['q'] ?? '',
                'service_category' => $serviceFilters['category'] ?? 'all',
                'service_status' => $serviceFilters['status'] ?? 'all',
            ],
            'service_rows_prepared' => $preparedServiceRows,
            'service_price_changes' => $servicePriceChanges,
            'service_price_changes_total' => count($servicePriceChanges),
            'service_price_changes_preview' => array_slice($servicePriceChanges, 0, 50),
            'active_services_section' => $this->resolveActiveSection($categoryForm, $query),
        ];
    }

    public function loadFormData(?int $editServiceId, ?int $editCategoryId): array
    {
        $data = [];

        if (($editServiceId ?? 0) > 0) {
            $serviceRow = $this->serviceRepository->findAdminById((int) $editServiceId);
            if (is_array($serviceRow)) {
                $data['service_form'] = [
                    'id' => (int) ($serviceRow['id'] ?? 0),
                    'nazev' => (string) ($serviceRow['nazev'] ?? ''),
                    'kategorie_id' => isset($serviceRow['kategorie_id']) && $serviceRow['kategorie_id'] !== null ? (string) $serviceRow['kategorie_id'] : '',
                    'stitek' => (string) ($serviceRow['stitek'] ?? ''),
                    'kategorie' => (string) ($serviceRow['kategorie'] ?? ''),
                    'kategorie_poradi' => isset($serviceRow['kategorie_poradi']) && $serviceRow['kategorie_poradi'] !== null ? (string) $serviceRow['kategorie_poradi'] : '',
                    'popis' => (string) ($serviceRow['popis'] ?? ''),
                    'cena' => isset($serviceRow['cena']) && $serviceRow['cena'] !== null ? number_format((float) $serviceRow['cena'], 0, '.', '') : '',
                    'doba_trvani' => isset($serviceRow['doba_trvani']) && $serviceRow['doba_trvani'] !== null ? (string) $serviceRow['doba_trvani'] : '',
                ];
            }
        }

        if (($editCategoryId ?? 0) > 0) {
            $categoryRow = $this->serviceRepository->findCategoryById((int) $editCategoryId);
            if (is_array($categoryRow)) {
                $data['category_form'] = [
                    'id' => (int) ($categoryRow['id'] ?? 0),
                    'nazev' => (string) ($categoryRow['nazev'] ?? ''),
                    'poradi' => isset($categoryRow['poradi']) && $categoryRow['poradi'] !== null ? (string) $categoryRow['poradi'] : '',
                ];
            }
        }

        return $data;
    }

    private function resolveActiveSection(array $categoryForm, array $query): string
    {
        if ((int) ($categoryForm['id'] ?? 0) > 0 || isset($query['edit_category'])) {
            return 'categories';
        }

        $requestedSection = (string) ($query['service_section'] ?? '');

        if (in_array($requestedSection, self::ALLOWED_SECTIONS, true)) {
            return $requestedSection;
        }

        return 'procedures';
    }

    private function truncateDescription(string $description): string
    {
        if ($description === '') {
            return 'Bez popisu';
        }

        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($description, 0, 90, '…', 'UTF-8');
        }

        return strlen($description) > 90 ? substr($description, 0, 87) . '...' : $description;
    }
}
