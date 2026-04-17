<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use PPStudio\Http\View\VoucherAdminDownloadPageRenderer;
use PPStudio\Security\SessionService;
use PPStudio\Service\VoucherAdminDownloadService;

final class VoucherAdminDownloadApplication
{
    public function __construct(
        private SessionService $sessionService,
        private VoucherAdminDownloadService $voucherAdminDownloadService,
        private VoucherAdminDownloadPageRenderer $renderer
    ) {
    }

    public static function create(): self
    {
        return new self(
            new SessionService(),
            new VoucherAdminDownloadService(\ppstudioRequestSecurityService()),
            new VoucherAdminDownloadPageRenderer()
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    public function handle(array $query): never
    {
        $this->sessionService->start();

        if (! $this->isAuthenticated($_SESSION)) {
            $this->renderer->render([
                'ok' => false,
                'mode' => 'plain_text',
                'http_code' => 401,
                'message' => 'Nejste přihlášeni.',
            ]);
        }

        $voucherId = (int) ($query['id'] ?? 0);
        if ($voucherId <= 0) {
            $this->renderer->render([
                'ok' => false,
                'mode' => 'plain_text',
                'http_code' => 422,
                'message' => 'Neplatné ID poukazu.',
            ]);
        }

        $state = $this->voucherAdminDownloadService->loadDownloadState($voucherId);
        $this->renderer->render($state);
    }

    /**
     * @param array<string, mixed> $session
     */
    private function isAuthenticated(array $session): bool
    {
        return (bool) ($session['ppstudio_admin_authenticated'] ?? false)
            || (bool) ($session['ppstudio_admin_lite_authenticated'] ?? false);
    }
}
