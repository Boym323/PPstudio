<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use DateTimeImmutable;
use mysqli;
use PPStudio\Database\DatabaseFactory;
use PPStudio\Service\AdminAvailabilityMutationService;
use PPStudio\Service\AvailabilityModule;

final class AdminAvailabilityApiController
{
    public static function handleRequest(array $query, array $session): never
    {
        self::sendJsonHeaders();

        if (! self::isAuthenticated($session)) {
            self::respondWithoutConnection(['error' => 'Nejste přihlášeni do administrace.'], 401);
        }

        $serviceId = (int) ($query['service_id'] ?? 0);
        $date = trim((string) ($query['date'] ?? ''));

        if ($serviceId <= 0) {
            self::respondWithoutConnection(['error' => 'Neplatná služba.'], 422);
        }

        if ($date !== '' && ! self::isValidDate($date)) {
            self::respondWithoutConnection(['error' => 'Neplatný formát data.'], 422);
        }

        $connection = DatabaseFactory::tryConnect();
        if (! $connection instanceof mysqli) {
            self::respondWithoutConnection(['error' => 'Databáze není dostupná.'], 500);
        }

        $availabilityService = (new AvailabilityModule($connection))->availabilityService();

        if ($date !== '') {
            self::respond(
                ['times' => $availabilityService->getAvailableTimesForDate($serviceId, $date)],
                200,
                $connection
            );
        }

        self::respond(
            ['days' => $availabilityService->getAvailableDays($serviceId)],
            200,
            $connection
        );
    }

    public static function handlePlannerSaveRequest(array $server, array $session, array $post, string $projectRoot): never
    {
        self::sendJsonHeaders();

        if (! self::isAuthenticated($session)) {
            self::respondWithoutConnection(['success' => false, 'message' => 'Nejste přihlášeni.'], 401);
        }

        if (($server['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            self::respondWithoutConnection(['success' => false, 'message' => 'Nepodporovaná metoda.'], 405);
        }

        if (! (new \PPStudio\Security\SecurityFacade())->isValidCsrfToken((string) ($post['_csrf'] ?? ''))) {
            self::respondWithoutConnection(['success' => false, 'message' => 'Platnost formuláře vypršela.'], 419);
        }

        $connection = DatabaseFactory::tryConnect();
        if (! $connection instanceof mysqli) {
            self::respondWithoutConnection(['success' => false, 'message' => 'Databáze není dostupná.'], 500);
        }

        $service = new AdminAvailabilityMutationService($connection, [], $projectRoot);
        $result = $service->saveAvailabilityGridDetailed($post);

        self::respondMutationResult($result, $connection);
    }

    public static function handleWindowDeleteRequest(array $server, array $session, array $post, string $projectRoot): never
    {
        self::sendJsonHeaders();

        if (! self::isAuthenticated($session)) {
            self::respondWithoutConnection(['success' => false, 'message' => 'Nejste přihlášeni.'], 401);
        }

        if (($server['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            self::respondWithoutConnection(['success' => false, 'message' => 'Nepodporovaná metoda.'], 405);
        }

        if (! (new \PPStudio\Security\SecurityFacade())->isValidCsrfToken((string) ($post['_csrf'] ?? ''))) {
            self::respondWithoutConnection(['success' => false, 'message' => 'Platnost formuláře vypršela.'], 419);
        }

        $connection = DatabaseFactory::tryConnect();
        if (! $connection instanceof mysqli) {
            self::respondWithoutConnection(['success' => false, 'message' => 'Databáze není dostupná.'], 500);
        }

        $service = new AdminAvailabilityMutationService($connection, [], $projectRoot);
        $result = $service->deleteWindowDetailed($post);

        self::respondMutationResult($result, $connection);
    }

    private static function isAuthenticated(array $session): bool
    {
        return (bool) ($session['ppstudio_admin_authenticated'] ?? false)
            || (bool) ($session['ppstudio_admin_lite_authenticated'] ?? false);
    }

    private static function isValidDate(string $date): bool
    {
        $dateObject = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();

        return $dateObject instanceof DateTimeImmutable
            && $dateObject->format('Y-m-d') === $date
            && ($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0));
    }

    private static function respondMutationResult(array $result, mysqli $connection): never
    {
        $success = (bool) ($result['success'] ?? false);
        $payload = [
            'success' => $success,
            'message' => (string) ($success
                ? ($result['message'] ?? '')
                : ($result['error'] ?? '')),
        ];

        $data = $result['data'] ?? null;
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($key === 'status_code') {
                    continue;
                }

                $payload[$key] = $value;
            }
        }

        $httpCode = 200;
        if (! $success) {
            $httpCode = is_array($data) && is_int($data['status_code'] ?? null)
                ? (int) $data['status_code']
                : 500;
        }

        self::respond(
            $payload,
            $httpCode,
            $connection
        );
    }

    private static function respondWithoutConnection(array $payload, int $httpCode): never
    {
        http_response_code($httpCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function respond(array $payload, int $httpCode, mysqli $connection): never
    {
        http_response_code($httpCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $connection->close();
        exit;
    }

    private static function sendJsonHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
}
