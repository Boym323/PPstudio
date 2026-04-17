<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use PPStudio\Http\Controller\Admin\AdminAvailabilityApiController;
use PPStudio\Http\Controller\Admin\AdminAvailabilityStoryController;
use PPStudio\Http\Controller\Admin\AdminReservationApiController;
use PPStudio\Http\Request\ReservationsFeedRequest;
use PPStudio\Http\View\SitePageCatalog;
use PPStudio\Http\View\SitePageRenderer;
use PPStudio\Security\SecurityFacade;

final class HttpEntryPointApplication
{
    private ?array $emailConfig = null;
    private ?SecurityFacade $securityFacade = null;
    private ?SitePageCatalog $sitePageCatalog = null;
    private ?SitePageRenderer $sitePageRenderer = null;

    public function __construct(private readonly string $projectRoot)
    {
    }

    public function handlePublicPage(string $pageKey): never
    {
        $config = $this->sitePageCatalog()->page($this->projectRoot, $pageKey);
        $this->sitePageRenderer()->render($config, $_SERVER, $_GET);
    }

    public function handleRedirect(string $location, int $statusCode = 302): never
    {
        header('Location: ' . $location, true, $statusCode);
        exit;
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     */
    public function handleReservationSubmit(array $server, array $post): never
    {
        $this->reservationSubmitApplication()->handle($server, $post);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function handleReservationAction(array $query): never
    {
        ReservationActionApplication::create($this->emailConfig())->handleAdminAction($query);
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $server
     */
    public function handleReservationCancel(array $request, array $server): never
    {
        ReservationActionApplication::create($this->emailConfig())->handleCustomerCancel($request, $server);
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     */
    public function handleReservationReschedule(array $request, array $post, array $server): never
    {
        ReservationActionApplication::create($this->emailConfig())->handleCustomerReschedule($request, $post, $server);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function handleVoucherView(array $query): never
    {
        $this->voucherPublicApplication()->handleView($query);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function handleVoucherVerify(array $query): never
    {
        $this->voucherPublicApplication()->handleVerify($query);
    }

    /**
     * @param array<string, mixed> $server
     */
    public function handleSitemap(array $server): never
    {
        (new SitemapController())->handle($server);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function handleReservationsFeed(array $query): never
    {
        (new ReservationsFeedApplication($this->emailConfig()))->handle(ReservationsFeedRequest::fromQuery($query));
    }

    /**
     * @param array<string, mixed> $query
     */
    public function handlePublicAvailabilityApi(array $query): never
    {
        $this->requirePublicJsonAccess();
        ApiAvailabilityController::handleRequest($query);
    }

    public function handlePublicServicesApi(): never
    {
        $this->requirePublicJsonAccess();
        ApiServicesController::handleRequest();
    }

    public function handlePublicGoogleReviewsApi(): never
    {
        $this->requirePublicJsonAccess();
        ApiGoogleReviewsController::handleRequest();
    }

    /**
     * @param array<string, mixed> $query
     */
    public function handleAdminVoucherDownload(array $query): never
    {
        VoucherAdminDownloadApplication::create()->handle($query);
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @param array<string, mixed> $session
     */
    public function handleAdminAvailabilityStory(array $server, array $get, array $post, array $session): never
    {
        $this->securityFacade()->startSecureSession();
        AdminAvailabilityStoryController::handle($server, $get, $post, $session);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $session
     */
    public function handleAdminAvailabilityApi(array $query, array $session): never
    {
        $this->securityFacade()->startSecureSession();
        AdminAvailabilityApiController::handleRequest($query, $session);
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $session
     * @param array<string, mixed> $post
     */
    public function handleAdminAvailabilityPlannerApi(array $server, array $session, array $post): never
    {
        $this->securityFacade()->startSecureSession();
        AdminAvailabilityApiController::handlePlannerSaveRequest($server, $session, $post, $this->projectRoot);
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $session
     * @param array<string, mixed> $post
     */
    public function handleAdminAvailabilityWindowApi(array $server, array $session, array $post): never
    {
        $this->securityFacade()->startSecureSession();
        AdminAvailabilityApiController::handleWindowDeleteRequest($server, $session, $post, $this->projectRoot);
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     */
    public function handleAdminReservationActionApi(array $server, array $post): never
    {
        AdminReservationApiController::handleMutationRequest($server, $post, $this->emailConfig());
    }

    private function securityFacade(): SecurityFacade
    {
        if (! $this->securityFacade instanceof SecurityFacade) {
            $this->securityFacade = new SecurityFacade();
        }

        return $this->securityFacade;
    }

    private function reservationSubmitApplication(): ReservationSubmitApplication
    {
        return new ReservationSubmitApplication(
            $this->emailConfig(),
            $this->securityFacade()->publicSiteLockService(),
            $this->securityFacade()->csrfService(),
            $this->securityFacade()->reservationAntispamService(),
            $this->securityFacade()->requestSecurityService()
        );
    }

    private function voucherPublicApplication(): VoucherPublicApplication
    {
        return VoucherPublicApplication::create(
            $this->securityFacade()->sessionService(),
            $this->securityFacade()->requestSecurityService()
        );
    }

    private function sitePageCatalog(): SitePageCatalog
    {
        if (! $this->sitePageCatalog instanceof SitePageCatalog) {
            $this->sitePageCatalog = new SitePageCatalog();
        }

        return $this->sitePageCatalog;
    }

    private function sitePageRenderer(): SitePageRenderer
    {
        if (! $this->sitePageRenderer instanceof SitePageRenderer) {
            $this->sitePageRenderer = new SitePageRenderer();
        }

        return $this->sitePageRenderer;
    }

    /**
     * @return array<string, mixed>
     */
    private function emailConfig(): array
    {
        if (is_array($this->emailConfig)) {
            return $this->emailConfig;
        }

        $config = require $this->projectRoot . '/config/email.php';
        $this->emailConfig = is_array($config) ? $config : [];

        return $this->emailConfig;
    }

    private function requirePublicJsonAccess(): void
    {
        $this->securityFacade()->publicSiteLockService()->requireAccessOrJsonError();
    }
}
