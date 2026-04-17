<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Http\Request\AdminPostActionRequest;

final class AdminLitePostActionHandler
{
    private AdminPostActionHandler $adminPostActionHandler;

    public function __construct(
        private string $projectRoot,
        private AdminLiteViewStateFactory $viewStateFactory,
        private array $emailConfig = [],
    ) {
        $this->adminPostActionHandler = new AdminPostActionHandler(
            $this->projectRoot,
            new AdminViewStateFactory(),
            $this->emailConfig
        );
    }

    /**
     * @param array<string, mixed> $state
     * @param \mysqli $connection
     * @return array<string, mixed>
     */
    public function handle(array $state, \mysqli $connection, ?AdminPostActionRequest $request = null): array
    {
        $request = $request ?? AdminPostActionRequest::fromHttpGlobals($_SERVER, $_GET, $_POST, $_FILES, $_SESSION);

        $liteDisallowedPostActionKeys = [
            'save_settings',
            'save_integrations',
            'save_email_settings',
            'save_media',
            'delete_media',
            'save_certificate_file',
            'delete_certificate_file',
        ];

        $liteBlockedActionRequested = false;
        foreach ($liteDisallowedPostActionKeys as $disallowedKey) {
            if ($request->hasPostKey($disallowedKey)) {
                $liteBlockedActionRequested = true;
                break;
            }
        }

        if ($liteBlockedActionRequested) {
            $state['error'] = 'Tato akce není v uživatelském rozhraní povolená.';

            return $this->captureDefinedState($state);
        }

        return $this->captureDefinedState(
            $this->adminPostActionHandler->handle($state, $connection, $request)
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array<string, mixed>
     */
    private function captureDefinedState(array $scope): array
    {
        return AdminStateSubset::subset($scope, $this->viewStateFactory->keys());
    }
}
