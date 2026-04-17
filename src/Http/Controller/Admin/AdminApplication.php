<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Database\DatabaseFactory;
use PPStudio\Service\MailerIntegrationService;
use PPStudio\Http\View\AdminPageRenderer;
use PPStudio\Http\View\AdminLoginPageRenderer;

final class AdminApplication
{
    private AdminAuthenticationService $authenticationService;

    private AdminViewStateFactory $viewStateFactory;

    private AdminPostActionHandler $postActionHandler;

    private AdminDataLoader $dataLoader;

    private AdminPageRenderer $pageRenderer;

    private AdminLoginPageRenderer $loginPageRenderer;

    public function __construct(
        private array $adminConfig,
        private array $emailConfig,
    ) {
        $projectRoot = $this->projectRoot();
        $this->authenticationService = new AdminAuthenticationService();
        $this->viewStateFactory = new AdminViewStateFactory();
        $this->postActionHandler = new AdminPostActionHandler($projectRoot, $this->viewStateFactory, $this->emailConfig);
        $this->dataLoader = new AdminDataLoader(
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
            'auth_session_key' => 'ppstudio_admin_authenticated',
            'username_session_key' => 'ppstudio_admin_username',
            'throttle_scope' => 'admin',
            'redirect_path' => 'admin.php',
            'event_source' => 'admin_login',
            'event_name_prefix' => 'admin_login',
        ]);

        if (! $authState['is_authenticated']) {
            $this->renderLogin((string) $authState['login_error']);
        }

        $viewState = $this->viewStateFactory->create($_GET, (string) $authState['error']);
        $viewState['adminTab'] = $this->resolveAdminTab(
            (string) ($_GET['tab'] ?? 'dashboard'),
            is_array($viewState['allowedAdminTabs'] ?? null) ? $viewState['allowedAdminTabs'] : []
        );
        $viewState['settingsSection'] = $this->resolveSettingsSection(
            (string) ($_GET['tab'] ?? 'dashboard'),
            (string) ($_GET['settings_section'] ?? 'studio')
        );

        if ($this->isPostTooLarge()) {
            $viewState['error'] = 'Odesílaný formulář je příliš velký pro server. Zmenšete prosím obrázek nebo navyšte limit post_max_size v PHP.';
        }

        $connection = DatabaseFactory::tryConnect();
        if ($connection instanceof \mysqli) {
            $viewState = $this->dataLoader->prime($viewState, $connection);
            $viewState = $this->dataLoader->loadFormData($viewState, $connection);
            $viewState = $this->postActionHandler->handle($viewState, $connection);
            $viewState = $this->dataLoader->loadSecurityLogs($viewState, $connection);
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

    /**
     * @param array<int, string> $allowedAdminTabs
     */
    private function resolveAdminTab(string $adminTab, array $allowedAdminTabs): string
    {
        if ($adminTab === 'kalendar') {
            $adminTab = 'rezervace-list';
        }
        if ($adminTab === 'recenze-napojeni' || $adminTab === 'emaily') {
            $adminTab = 'nastaveni';
        }
        if (! in_array($adminTab, $allowedAdminTabs, true)) {
            return 'dashboard';
        }

        return $adminTab;
    }

    private function resolveSettingsSection(string $tab, string $settingsSection): string
    {
        if ($tab === 'recenze-napojeni') {
            return 'recenze';
        }
        if ($tab === 'emaily') {
            return 'email';
        }
        if (! in_array($settingsSection, ['studio', 'recenze', 'email'], true)) {
            return 'studio';
        }

        return $settingsSection;
    }

    private function isPostTooLarge(): bool
    {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

        return $_SERVER['REQUEST_METHOD'] === 'POST'
            && $contentLength > 0
            && $_POST === []
            && $_FILES === []
            && $contentLength > $this->iniSizeToBytes((string) ini_get('post_max_size'));
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    private function renderLogin(string $loginError): never
    {
        $this->loginPageRenderer->render(
            $loginError,
            'Přihlášení do administrace',
            'Administrace',
            'Přihlášení do správy studia'
        );
    }

    /**
     * @param array<string, mixed> $viewState
     */
    private function renderApp(array $viewState): void
    {
        $projectRoot = $this->projectRoot();
        $this->pageRenderer->render($viewState, [
            'pageTitlePrefix' => 'Admin',
            'sidebarTemplate' => $projectRoot . '/src/Http/View/Templates/admin-sidebar.php',
            'introTemplate' => $projectRoot . '/src/Http/View/Templates/admin-intro.php',
            'defaultSection' => $projectRoot . '/src/Http/View/Templates/admin-sections/dashboard.php',
            'sectionByTab' => [
                'dashboard' => $projectRoot . '/src/Http/View/Templates/admin-sections/dashboard.php',
                'antispam-log' => $projectRoot . '/src/Http/View/Templates/admin-sections/antispam.php',
                'reminder-log' => $projectRoot . '/src/Http/View/Templates/admin-sections/reminder_logs.php',
                'dostupnost' => $projectRoot . '/src/Http/View/Templates/admin-sections/availability.php',
                'rezervace-list' => $projectRoot . '/src/Http/View/Templates/admin-sections/reservations.php',
                'sluzby-admin' => $projectRoot . '/src/Http/View/Templates/admin-sections/services.php',
                'poukazy' => $projectRoot . '/src/Http/View/Templates/admin-sections/vouchers.php',
                'media' => $projectRoot . '/src/Http/View/Templates/admin-sections/media.php',
                'nastaveni' => $projectRoot . '/src/Http/View/Templates/admin-sections/settings.php',
            ],
        ]);
    }

    private function iniSizeToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $number = (float) $value;
        $unit = strtolower(substr($value, -1));

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }
}
