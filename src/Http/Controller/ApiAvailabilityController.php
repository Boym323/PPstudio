<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use mysqli;
use PPStudio\Database\DatabaseFactory;
use PPStudio\Repository\AvailabilityRepository;
use PPStudio\Repository\ReservationRepository;
use PPStudio\Repository\ServiceRepository;
use PPStudio\Service\AvailabilityService;
use PPStudio\Support\DateHelper;

final class ApiAvailabilityController
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private mysqli $connection
    ) {
    }

    public static function create(): ?self
    {
        $connection = DatabaseFactory::tryConnect();

        if (! $connection instanceof mysqli) {
            return null;
        }

        return new self(
            new AvailabilityService(
                new ServiceRepository($connection),
                new AvailabilityRepository($connection),
                new ReservationRepository($connection)
            ),
            $connection
        );
    }

    public static function handleRequest(array $query): never
    {
        self::sendJsonHeaders();

        $serviceId = (int) ($query['service_id'] ?? 0);
        $date = trim((string) ($query['date'] ?? ''));

        if ($serviceId <= 0) {
            self::respondWithoutConnection(['error' => 'Neplatna sluzba.'], 422);
        }

        if ($date !== '' && ! DateHelper::isValidDate($date)) {
            self::respondWithoutConnection(['error' => 'Neplatny format data.'], 422);
        }

        $controller = self::create();

        if (! $controller instanceof self) {
            self::respondWithoutConnection(['error' => 'Databaze neni dostupna.'], 500);
        }

        if ($date !== '') {
            $controller->respond([
                'times' => $controller->availabilityService->getAvailableTimesForDate($serviceId, $date),
            ]);
        }

        $controller->respond([
            'days' => $controller->availabilityService->getAvailableDays($serviceId),
        ]);
    }

    private static function respondWithoutConnection(array $payload, int $httpCode): never
    {
        http_response_code($httpCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function respond(array $payload, int $httpCode = 200): never
    {
        http_response_code($httpCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->connection->close();
        exit;
    }

    private static function sendJsonHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
}
