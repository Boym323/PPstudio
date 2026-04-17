<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use PPStudio\Http\Controller\Admin\AdminSessionState;
use PPStudio\Http\View\VoucherPublicPageRenderer;
use PPStudio\Security\RequestSecurityService;
use PPStudio\Security\SessionService;
use PPStudio\Service\VoucherPublicService;

final class VoucherPublicApplication
{
    public function __construct(
        private SessionService $sessionService,
        private VoucherPublicService $voucherPublicService,
        private VoucherPublicPageRenderer $renderer
    ) {
    }

    public static function create(SessionService $sessionService, RequestSecurityService $requestSecurityService): self
    {
        return new self(
            $sessionService,
            new VoucherPublicService($requestSecurityService),
            new VoucherPublicPageRenderer()
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    public function handleView(array $query): never
    {
        $this->sessionService->start();

        $state = $this->voucherPublicService->loadVoucherViewState(
            (int) ($query['v'] ?? 0),
            trim((string) ($query['sig'] ?? ''))
        );

        $this->renderer->render($state);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function handleVerify(array $query): never
    {
        $this->sessionService->start();

        $state = $this->voucherPublicService->loadVoucherVerifyState(
            (int) ($query['v'] ?? 0),
            trim((string) ($query['sig'] ?? '')),
            AdminSessionState::isAuthenticated($_SESSION)
        );

        $this->renderer->render($state);
    }
}
