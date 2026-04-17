<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Service\MailerIntegrationService;

final class AdminLiteDataLoader
{
    private AdminDataLoader $adminDataLoader;

    public function __construct(
        private string $projectRoot,
        private MailerIntegrationService $mailerIntegrationService,
        private AdminLiteViewStateFactory $viewStateFactory,
        private array $emailConfig = [],
    ) {
        $this->adminDataLoader = new AdminDataLoader(
            $this->projectRoot,
            $this->mailerIntegrationService,
            new AdminViewStateFactory(),
            $this->emailConfig
        );
    }

    /**
     * @param array<string, mixed> $state
     * @param \mysqli $connection
     * @return array<string, mixed>
     */
    public function prime(array $state, \mysqli $connection): array
    {
        return $this->captureDefinedState(
            $this->adminDataLoader->prime($state, $connection)
        );
    }

    /**
     * @param array<string, mixed> $state
     * @param \mysqli $connection
     * @return array<string, mixed>
     */
    public function loadFormData(array $state, \mysqli $connection): array
    {
        return $this->captureDefinedState(
            $this->adminDataLoader->loadFormData($state, $connection)
        );
    }

    /**
     * @param array<string, mixed> $state
     * @param \mysqli $connection
     * @return array<string, mixed>
     */
    public function load(array $state, \mysqli $connection): array
    {
        return $this->captureDefinedState(
            $this->adminDataLoader->load($state, $connection)
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
