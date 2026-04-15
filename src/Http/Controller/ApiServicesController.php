<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use mysqli;
use PPStudio\Database\DatabaseFactory;
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

        foreach ($this->serviceRepository->findActiveWithCategories() as $row) {
            $category = trim((string) ($row['kategorie'] ?? ''));
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['nazev'] ?? ''),
                'category' => $category !== '' ? $category : 'Ostatní služby',
                'category_order' => $row['kategorie_poradi'] !== null ? (int) $row['kategorie_poradi'] : null,
                'badge' => trim((string) ($row['stitek'] ?? '')),
                'description' => trim((string) ($row['popis'] ?? '')),
                'duration' => (int) ($row['doba_trvani'] ?? 0),
                'price' => $row['cena'] !== null ? (float) $row['cena'] : null,
                'label' => (string) ($row['nazev'] ?? '')
                    . ' (' . $this->formatDuration($row['doba_trvani'] ?? null) . ') - '
                    . $this->formatPrice($row['cena'] ?? null),
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

    private function formatPrice(mixed $price): string
    {
        if ($price === null || $price === '') {
            return 'Cena na dotaz';
        }

        return number_format((float) $price, 0, ',', ' ') . ' Kč';
    }

    private function formatDuration(mixed $duration): string
    {
        if ($duration === null || $duration === '') {
            return 'Dle vybrané procedury';
        }

        return (int) $duration . ' min';
    }

    private static function sendJsonHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
}
