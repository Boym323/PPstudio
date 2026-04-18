<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Config\AppConfig;
use mysqli;
use PPStudio\Database\DatabaseFactory;
use PPStudio\Security\SecurityFacade;
use PPStudio\Service\AdminReservationModule;

final class AdminReservationApiController
{
    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     * @param array<string, mixed> $emailConfig
     */
    public static function handleMutationRequest(array $server, array $post, array $emailConfig): never
    {
        $security = new SecurityFacade();
        $security->startSecureSession();
        self::sendJsonHeaders();

        if (! AdminSessionState::isAuthenticated($_SESSION)) {
            self::respondWithoutConnection([
                'success' => false,
                'error' => 'Nejste přihlášeni do administrace.',
            ], 401);
        }

        if (($server['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            self::respondWithoutConnection([
                'success' => false,
                'error' => 'Neplatná metoda požadavku.',
            ], 405);
        }

        if (! $security->isValidCsrfToken((string) ($post['_csrf'] ?? ''))) {
            self::respondWithoutConnection([
                'success' => false,
                'error' => 'Platnost formuláře vypršela. Obnovte stránku.',
            ], 419);
        }

        $connection = DatabaseFactory::tryConnect();
        if (! $connection instanceof mysqli) {
            self::respondWithoutConnection([
                'success' => false,
                'error' => 'Nepodařilo se připojit k databázi.',
            ], 500);
        }

        $siteSettings = (new \PPStudio\Service\SiteSettingsService(new \PPStudio\Repository\SiteSettingsRepository($connection), AppConfig::instance()->defaultSiteSettings()))->load();
        $mutationService = (new AdminReservationModule($connection, $emailConfig, $siteSettings))->mutationService();
        $result = isset($post['delete_reservation'])
            ? $mutationService->deleteReservation($post)
            : $mutationService->updateReservation($post, $_SESSION);

        $httpCode = (int) ($result['http_code'] ?? (($result['success'] ?? false) ? 200 : 500));
        $payload = [
            'success' => (bool) ($result['success'] ?? false),
        ];

        if (($result['success'] ?? false) === true) {
            $payload['message'] = (string) ($result['message'] ?? '');
            $payload['data'] = is_array($result['data'] ?? null) ? $result['data'] : [];
        } else {
            $payload['error'] = (string) ($result['error'] ?? 'Požadavek se nepodařilo zpracovat.');
        }

        self::respond($payload, $httpCode, $connection);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function respondWithoutConnection(array $payload, int $httpCode): never
    {
        http_response_code($httpCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param array<string, mixed> $payload
     */
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
