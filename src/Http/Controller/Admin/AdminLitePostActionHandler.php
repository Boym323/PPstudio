<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

final class AdminLitePostActionHandler
{
    public function __construct(
        private string $projectRoot,
        private AdminLiteViewStateFactory $viewStateFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $state
     * @param \mysqli $connection
     * @return array<string, mixed>
     */
    public function handle(array $state, \mysqli $connection): array
    {
        extract($state, EXTR_OVERWRITE);

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
            if (isset($_POST[$disallowedKey])) {
                $liteBlockedActionRequested = true;
                break;
            }
        }

        if ($liteBlockedActionRequested) {
            $error = 'Tato akce není v uživatelském rozhraní povolená.';
        } else {
            include $this->projectRoot . '/includes/admin/actions/post_actions.php';
        }

        return $this->captureDefinedState(get_defined_vars());
    }

    /**
     * @param array<string, mixed> $scope
     * @return array<string, mixed>
     */
    private function captureDefinedState(array $scope): array
    {
        $state = [];
        foreach ($this->viewStateFactory->keys() as $key) {
            if (array_key_exists($key, $scope)) {
                $state[$key] = $scope[$key];
            }
        }

        return $state;
    }
}
