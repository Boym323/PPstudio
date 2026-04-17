<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use mysqli;
use PPStudio\Database\DatabaseFactory;
use PPStudio\Service\AdminAvailabilityStoryService;
use PPStudio\Service\AvailabilityStoryService;

final class AdminAvailabilityStoryController
{
    public static function handle(array $server, array $get, array $post, array $session): never
    {
        if (! self::isAuthenticated($session)) {
            self::respondText('Přístup odepřen.', 403);
        }

        $isPreview = ($server['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($get['preview']);

        if (! $isPreview && (($server['REQUEST_METHOD'] ?? 'GET') !== 'POST' || ! \ppstudioSecurityFacade()->isValidCsrfToken((string) ($post['_csrf'] ?? '')))) {
            self::respondText('Platnost formuláře vypršela.', 400);
        }

        $connection = DatabaseFactory::tryConnect();
        if (! $connection instanceof mysqli) {
            self::respondText('Nepodařilo se připojit k databázi.', 500);
        }

        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagepng')) {
            $connection->close();
            self::respondText('Na serveru chybí podpora GD pro generování obrázku.', 500);
        }

        $source = $isPreview ? $get : $post;
        $storyService = new AdminAvailabilityStoryService($connection);
        $payload = $storyService->buildRenderPayload($source, true);
        $image = (new AvailabilityStoryService())->renderImage(
            (string) ($payload['title'] ?? ''),
            (string) ($payload['month_label'] ?? ''),
            is_array($payload['slot_lines'] ?? null) ? $payload['slot_lines'] : [],
            is_array($payload['service_lines'] ?? null) ? $payload['service_lines'] : [],
            (string) ($payload['style'] ?? 'story'),
            (string) ($payload['background_path'] ?? '')
        );

        $fileName = (string) ($payload['file_name'] ?? 'ppstudio-volne-terminy.png');

        header('Content-Type: image/png');
        header('Content-Disposition: ' . ($isPreview ? 'inline' : 'attachment') . '; filename="' . $fileName . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        imagepng($image);
        imagedestroy($image);
        $connection->close();
        exit;
    }

    private static function isAuthenticated(array $session): bool
    {
        return (bool) ($session['ppstudio_admin_authenticated'] ?? false)
            || (bool) ($session['ppstudio_admin_lite_authenticated'] ?? false);
    }

    private static function respondText(string $message, int $httpCode): never
    {
        http_response_code($httpCode);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
        exit;
    }
}
