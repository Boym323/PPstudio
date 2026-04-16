<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use PPStudio\Database\DatabaseFactory;
use PPStudio\Domain\ServiceItem;
use PPStudio\Repository\ServiceRepository;

final class PublicServiceCatalogService
{
    public function __construct(
        private ServiceRepository $serviceRepository,
        private mysqli $connection
    ) {
    }

    public static function create(): ?self
    {
        $connection = DatabaseFactory::tryConnect();

        if (! $connection instanceof mysqli) {
            return null;
        }

        return new self(new ServiceRepository($connection), $connection);
    }

    public function loadCards(): array
    {
        try {
            $cards = [];

            foreach ($this->serviceRepository->findActiveItemsWithCategories() as $service) {
                $cards[] = [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $this->description($service),
                    'badge' => $this->badge($service),
                    'icon' => $this->icon($service),
                    'category' => $service->category ?? 'Ostatní služby',
                    'duration' => \formatDuration($service->durationMinutes),
                    'price' => \formatPrice($service->price),
                ];
            }

            return $cards;
        } finally {
            $this->connection->close();
        }
    }

    private function description(ServiceItem $service): string
    {
        $description = trim($service->description);

        if ($description !== '') {
            return $description;
        }

        return 'Individuálně vybraná péče podle aktuálních potřeb a cíle návštěvy.';
    }

    private function badge(ServiceItem $service): string
    {
        $badge = trim((string) $service->badge);

        if ($badge !== '') {
            return $badge;
        }

        return trim((string) ($service->category ?? 'Doporučená péče'));
    }

    private function icon(ServiceItem $service): string
    {
        $haystack = mb_strtolower(trim(($service->category ?? '') . ' ' . $service->name));

        return match (true) {
            str_contains($haystack, 'oko'),
            str_contains($haystack, 'řas'),
            str_contains($haystack, 'oboč') => 'fa-eye',
            str_contains($haystack, 'poraden'),
            str_contains($haystack, 'konzult') => 'fa-comments',
            str_contains($haystack, 'masáž'),
            str_contains($haystack, 'relax') => 'fa-spa',
            default => 'fa-leaf',
        };
    }
}
