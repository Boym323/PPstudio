<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use mysqli;
use PPStudio\Database\DatabaseFactory;
use PPStudio\Domain\ServiceItem;
use PPStudio\Repository\ServiceRepository;

final class ApiServicesController
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

    public static function handleRequest(): never
    {
        $controller = self::create();

        if (! $controller instanceof self) {
            self::unavailable();
        }

        $controller->handle();
    }

    public function handle(): never
    {
        $this->sendJsonHeaders();

        $items = [];

        foreach ($this->serviceRepository->findActiveItemsWithCategories() as $service) {
            $items[] = [
                'id' => $service->id,
                'name' => $service->name,
                'category' => $service->category ?? 'Ostatní služby',
                'category_order' => $service->categoryOrder,
                'badge' => $service->badge ?? '',
                'description' => $service->description,
                'duration' => $service->durationMinutes,
                'price' => $service->price,
                'label' => $service->name
                    . ' (' . $this->formatDuration($service) . ') - '
                    . $this->formatPrice($service),
            ];
        }

        $this->respond(['services' => $items]);
    }

    public static function unavailable(): never
    {
        self::sendJsonHeaders();
        http_response_code(500);
        echo json_encode(['error' => 'Databaze neni dostupna.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function respond(array $payload, int $httpCode = 200): never
    {
        http_response_code($httpCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->connection->close();
        exit;
    }

    private function formatPrice(ServiceItem $service): string
    {
        if ($service->price === null) {
            return 'Cena na dotaz';
        }

        return number_format($service->price, 0, ',', ' ') . ' Kč';
    }

    private function formatDuration(ServiceItem $service): string
    {
        if ($service->durationMinutes <= 0) {
            return 'Dle vybrané procedury';
        }

        return $service->durationMinutes . ' min';
    }

    private static function sendJsonHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
}
