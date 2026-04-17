<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Service\AdminServiceCatalogService;

final class AdminServiceFormDataLoader
{
    public function __construct(
        private AdminServiceCatalogService $serviceCatalogService
    ) {
    }

    /**
     * @return array{
     *     service_form?: array<string, mixed>,
     *     category_form?: array<string, mixed>
     * }
     */
    public function load(?int $editServiceId, ?int $editCategoryId): array
    {
        return $this->serviceCatalogService->loadFormData($editServiceId, $editCategoryId);
    }
}
