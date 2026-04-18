<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use PPStudio\Http\Controller\Admin\AdminSessionState;
use PPStudio\Http\Controller\Admin\AdminSessionBootstrap;
use PPStudio\Http\View\VoucherPublicPageRenderer;
use PPStudio\Security\RequestSecurityService;
use PPStudio\Service\VoucherPublicService;

final class VoucherPublicApplication
{
    public function __construct(
        private VoucherPublicService $voucherPublicService,
        private VoucherPublicPageRenderer $renderer
    ) {
    }

    public static function create(RequestSecurityService $requestSecurityService): self
    {
        return new self(
            new VoucherPublicService($requestSecurityService),
            new VoucherPublicPageRenderer()
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    public function handleView(array $query): never
    {
        AdminSessionBootstrap::start();

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
        AdminSessionBootstrap::start();

        $state = $this->voucherPublicService->loadVoucherVerifyState(
            (int) ($query['v'] ?? 0),
            trim((string) ($query['sig'] ?? '')),
            AdminSessionState::isAuthenticated($_SESSION)
        );

        $this->renderer->render($state);
    }
}
