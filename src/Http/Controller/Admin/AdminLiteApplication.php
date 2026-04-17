<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Database\DatabaseFactory;
use PPStudio\Http\Request\AdminPostActionRequest;
use PPStudio\Service\MailerIntegrationService;
use PPStudio\Http\View\AdminPageRenderer;
use PPStudio\Http\View\AdminLoginPageRenderer;

final class AdminLiteApplication
{
    private AdminAuthenticationService $authenticationService;

    private AdminLiteViewStateFactory $viewStateFactory;

    private AdminLitePostActionHandler $postActionHandler;

    private AdminLiteDataLoader $dataLoader;

    private AdminPageRenderer $pageRenderer;

    private AdminLoginPageRenderer $loginPageRenderer;

    public function __construct(
        private array $adminConfig,
        private array $emailConfig,
    ) {
        $projectRoot = $this->projectRoot();
        $this->authenticationService = new AdminAuthenticationService();
        $this->viewStateFactory = new AdminLiteViewStateFactory();
        $this->postActionHandler = new AdminLitePostActionHandler($projectRoot, $this->viewStateFactory, $this->emailConfig);
        $this->dataLoader = new AdminLiteDataLoader(
            $projectRoot,
            new MailerIntegrationService($this->emailConfig),
            $this->viewStateFactory,
            $this->emailConfig
        );
        $this->pageRenderer = new AdminPageRenderer();
        $this->loginPageRenderer = new AdminLoginPageRenderer();
    }

    public function handle(): never
    {
        $authState = $this->authenticationService->handle($this->adminConfig, [
            'auth_session_key' => 'ppstudio_admin_lite_authenticated',
            'username_session_key' => 'ppstudio_admin_lite_username',
            'throttle_scope' => 'admin-lite',
            'redirect_path' => 'admin-lite.php',
            'event_source' => 'admin_lite_login',
            'event_name_prefix' => 'admin_lite_login',
        ], $_SERVER, $_POST, $_SESSION);

        if (! $authState['is_authenticated']) {
            $this->renderLogin((string) $authState['login_error']);
        }

        $request = AdminPostActionRequest::fromHttpGlobals($_SERVER, $_GET, $_POST, $_FILES, $_SESSION);
        $query = $request->query();
        $viewState = $this->viewStateFactory->create($query, (string) $authState['error']);
        $viewState['adminTab'] = $this->resolveAdminTab(
            (string) ($query['tab'] ?? 'dashboard'),
            $viewState['allowedAdminTabs']
        );

        if ($request->isPostTooLarge((string) ini_get('post_max_size'))) {
            $viewState['error'] = 'Odesílaný formulář je příliš velký pro server. Zmenšete prosím obrázek nebo navyšte limit post_max_size v PHP.';
        }

        $connection = DatabaseFactory::tryConnect();
        if ($connection instanceof \mysqli) {
            $viewState = $this->dataLoader->prime($viewState, $connection);
            $viewState = $this->dataLoader->loadFormData($viewState, $connection);
            $viewState = $this->postActionHandler->handle($viewState, $connection, $request);
            $viewState = $this->dataLoader->load($viewState, $connection);
        } else {
            $viewState['error'] = 'Nepodařilo se připojit k databázi. Zkontrolujte `config/database.php`.';
        }

        $this->renderApp($viewState);

        if ($connection instanceof \mysqli) {
            $connection->close();
        }

        exit;
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    /**
     * @param array<int, string> $allowedAdminTabs
     */
    private function resolveAdminTab(string $adminTab, array $allowedAdminTabs): string
    {
        if (! in_array($adminTab, $allowedAdminTabs, true)) {
            return 'dashboard';
        }

        return $adminTab;
    }

    private function renderLogin(string $loginError): never
    {
        $this->loginPageRenderer->render(
            $loginError,
            'Přihlášení uživatele',
            'Uživatelské rozhraní',
            'Přihlášení do provozní správy'
        );
    }

    /**
     * @param array<string, mixed> $viewState
     */
    private function renderApp(array $viewState): void
    {
        $projectRoot = $this->projectRoot();
        $this->pageRenderer->render($viewState, [
            'pageTitlePrefix' => 'Uživatelský admin',
            'sidebarTemplate' => $projectRoot . '/src/Http/View/Templates/admin-sidebar-lite.php',
            'introTemplate' => $projectRoot . '/src/Http/View/Templates/admin-intro-lite.php',
            'defaultSection' => $projectRoot . '/src/Http/View/Templates/admin-sections/dashboard.php',
            'sectionByTab' => [
                'dashboard' => $projectRoot . '/src/Http/View/Templates/admin-sections/dashboard.php',
                'dostupnost' => $projectRoot . '/src/Http/View/Templates/admin-sections/availability.php',
                'rezervace-list' => $projectRoot . '/src/Http/View/Templates/admin-sections/reservations.php',
                'sluzby-admin' => $projectRoot . '/src/Http/View/Templates/admin-sections/services.php',
            ],
        ]);
    }
}
