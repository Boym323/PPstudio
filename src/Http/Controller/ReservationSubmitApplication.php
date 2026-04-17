<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use PPStudio\Security\CsrfService;
use PPStudio\Security\PublicSiteLockService;
use PPStudio\Security\RequestSecurityService;
use PPStudio\Security\ReservationAntispamService;
use PPStudio\Security\SessionService;
use PPStudio\Service\ReservationNotificationService;
use PPStudio\Service\ReservationSubmitContextFactory;
use PPStudio\Service\ReservationSubmitService;

final class ReservationSubmitApplication
{
    public function __construct(
        private array $emailConfig,
        private PublicSiteLockService $publicSiteLockService,
        private CsrfService $csrfService,
        private ReservationAntispamService $reservationAntispamService,
        private RequestSecurityService $requestSecurityService
    ) {
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     */
    public function handle(array $server, array $post): never
    {
        (new SessionService())->start();

        $controller = new ReservationController(
            new ReservationSubmitService(
                new ReservationSubmitContextFactory(),
                new ReservationNotificationService($this->emailConfig)
            ),
            $this->publicSiteLockService,
            $this->csrfService,
            $this->reservationAntispamService,
            $this->requestSecurityService
        );

        $controller->submit($server, $post);
    }
}
