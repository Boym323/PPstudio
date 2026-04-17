<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

use PPStudio\Service\PublicServiceCatalogService;

final class SiteServicesPageContextBuilder
{
    /**
     * @return array{serviceCards: array<int, array<string, mixed>>}
     */
    public function build(): array
    {
        $publicServiceCatalogService = PublicServiceCatalogService::create();
        $serviceCards = $publicServiceCatalogService instanceof PublicServiceCatalogService
            ? $publicServiceCatalogService->loadCards()
            : [];

        return [
            'serviceCards' => $serviceCards,
        ];
    }
}
