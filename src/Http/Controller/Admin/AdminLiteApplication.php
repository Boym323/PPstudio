<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Database\DatabaseFactory;
use PPStudio\Service\MailerIntegrationService;

final class AdminLiteApplication
{
    private AdminAuthenticationService $authenticationService;

    private AdminLiteViewStateFactory $viewStateFactory;

    private AdminLitePostActionHandler $postActionHandler;

    private AdminLiteDataLoader $dataLoader;

    public function __construct(
        private array $adminConfig,
        private array $emailConfig,
    ) {
        $projectRoot = $this->projectRoot();
        $this->authenticationService = new AdminAuthenticationService();
        $this->viewStateFactory = new AdminLiteViewStateFactory();
        $this->postActionHandler = new AdminLitePostActionHandler($projectRoot, $this->viewStateFactory);
        $this->dataLoader = new AdminLiteDataLoader(
            $projectRoot,
            new MailerIntegrationService($this->emailConfig),
            $this->viewStateFactory
        );
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
        ]);

        if (! $authState['is_authenticated']) {
            $this->renderLogin((string) $authState['login_error']);
        }

        $viewState = $this->viewStateFactory->create($_GET, (string) $authState['error']);
        $viewState['adminTab'] = $this->resolveAdminTab(
            (string) ($_GET['tab'] ?? 'dashboard'),
            $viewState['allowedAdminTabs']
        );

        if ($this->isPostTooLarge()) {
            $viewState['error'] = 'Odesílaný formulář je příliš velký pro server. Zmenšete prosím obrázek nebo navyšte limit post_max_size v PHP.';
        }

        $connection = DatabaseFactory::tryConnect();
        if ($connection instanceof \mysqli) {
            $viewState = $this->dataLoader->prime($viewState, $connection);
            $viewState = $this->dataLoader->loadFormData($viewState, $connection);
            $viewState = $this->postActionHandler->handle($viewState, $connection);
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

    private function isPostTooLarge(): bool
    {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

        return $_SERVER['REQUEST_METHOD'] === 'POST'
            && $contentLength > 0
            && $_POST === []
            && $_FILES === []
            && $contentLength > $this->iniSizeToBytes((string) ini_get('post_max_size'));
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

    private function renderLogin(string $loginError): never
    {
        $projectRoot = $this->projectRoot();
        include $projectRoot . '/includes/admin/templates/login_lite.php';
        exit;
    }

    /**
     * @param array<string, mixed> $viewState
     */
    private function renderApp(array $viewState): void
    {
        extract($viewState, EXTR_OVERWRITE);
        include $this->projectRoot() . '/includes/admin/templates/app_lite.php';
    }
}
