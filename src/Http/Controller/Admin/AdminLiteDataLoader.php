<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Service\MailerIntegrationService;

final class AdminLiteDataLoader
{
    public function __construct(
        private string $projectRoot,
        private MailerIntegrationService $mailerIntegrationService,
        private AdminLiteViewStateFactory $viewStateFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $state
     * @param \mysqli $connection
     * @return array<string, mixed>
     */
    public function prime(array $state, \mysqli $connection): array
    {
        extract($state, EXTR_OVERWRITE);

        $siteSettings = loadSiteSettings($connection);
        $subscriptionCalendarUrl = $this->mailerIntegrationService->buildSubscriptionCalendarUrl($siteSettings);

        return $this->captureDefinedState(get_defined_vars());
    }

    /**
     * @param array<string, mixed> $state
     * @param \mysqli $connection
     * @return array<string, mixed>
     */
    public function loadFormData(array $state, \mysqli $connection): array
    {
        extract($state, EXTR_OVERWRITE);

        include $this->projectRoot . '/includes/admin/actions/load/service_forms.php';

        return $this->captureDefinedState(get_defined_vars());
    }

    /**
     * @param array<string, mixed> $state
     * @param \mysqli $connection
     * @return array<string, mixed>
     */
    public function load(array $state, \mysqli $connection): array
    {
        extract($state, EXTR_OVERWRITE);
        include $this->projectRoot . '/includes/admin/actions/load_data.php';

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
