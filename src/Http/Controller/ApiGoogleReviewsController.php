<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use mysqli;
use PPStudio\Database\DatabaseFactory;
use PPStudio\Service\GoogleReviewsCache;
use PPStudio\Service\GoogleReviewsService;

final class ApiGoogleReviewsController
{
    public function __construct(
        private GoogleReviewsService $googleReviewsService,
        private array $siteSettings
    ) {
    }

    public static function create(): ?self
    {
        $connection = DatabaseFactory::tryConnect();

        if (! $connection instanceof mysqli) {
            return null;
        }

        $siteSettings = \loadSiteSettings($connection);
        $connection->close();

        return new self(
            new GoogleReviewsService(
                new GoogleReviewsCache(
                    dirname(__DIR__, 3) . '/.google-reviews-cache.json',
                    6 * 60 * 60
                )
            ),
            $siteSettings
        );
    }

    public static function handleRequest(): never
    {
        self::sendJsonHeaders();

        $controller = self::create();

        if (! $controller instanceof self) {
            self::unavailable();
        }

        $controller->handle();
    }

    private function handle(): never
    {
        $response = $this->googleReviewsService->loadPayload($this->siteSettings);
        $httpCode = (int) ($response['http_code'] ?? 200);
        $payload = is_array($response['payload'] ?? null) ? $response['payload'] : [];
        $jsonFlags = (int) ($response['json_flags'] ?? (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        http_response_code($httpCode);
        echo json_encode($payload, $jsonFlags);
        exit;
    }

    private static function unavailable(): never
    {
        http_response_code(500);
        echo json_encode(['error' => 'Databaze neni dostupna.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function sendJsonHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
}
