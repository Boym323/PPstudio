<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Repository\SiteSettingsRepository;
use PPStudio\Service\AdminAvailabilityMutationService;
use PPStudio\Service\AdminMediaModule;
use PPStudio\Service\AdminReservationModule;
use PPStudio\Service\AdminServiceMutationService;
use PPStudio\Service\AdminVoucherModule;
use PPStudio\Service\SiteSettingsService;

final class AdminPostActionHandler
{
    public function __construct(
        private string $projectRoot,
        private AdminViewStateFactory $viewStateFactory,
        private array $emailConfig = [],
    ) {
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function handle(array $state, \mysqli $connection): array
    {
        $state = $this->handleSettings($state, $connection);
        $state = $this->handleVoucherActions($state, $connection);
        $state = $this->handleServiceActions($state, $connection);
        $state = $this->handleAvailabilityActions($state, $connection);
        $state = $this->handleReservationActions($state, $connection);

        return $this->handleMediaActions($state, $connection);
    }

    /**
     * @param array<string, mixed> $scope
     * @return array<string, mixed>
     */
    public function captureDefinedState(array $scope): array
    {
        $state = [];
        foreach ($this->viewStateFactory->keys() as $key) {
            if (array_key_exists($key, $scope)) {
                $state[$key] = $scope[$key];
            }
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function handleSettings(array $state, \mysqli $connection): array
    {
        $settingsPostState = (new AdminSettingsPostActionHandler(
            new SiteSettingsService(
                new SiteSettingsRepository($connection),
                defaultSiteSettings()
            )
        ))->handle(
            $_SERVER,
            $_POST,
            is_array($state['siteSettings'] ?? null) ? $state['siteSettings'] : [],
            is_array($state['studioSettingFields'] ?? null) ? $state['studioSettingFields'] : [],
            [
                'google_reviews_url',
                'firmy_reviews_url',
                'firmy_reviews_embed',
                'google_place_id',
                'google_reviews_language',
            ],
            ['notification_emails']
        );

        $state['siteSettings'] = $settingsPostState['siteSettings'];
        if ($settingsPostState['message'] !== '') {
            $state['message'] = $settingsPostState['message'];
        }
        if ($settingsPostState['error'] !== '') {
            $state['error'] = $settingsPostState['error'];
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function handleVoucherActions(array $state, \mysqli $connection): array
    {
        $voucherPostResult = (new AdminVoucherModule(
            $connection,
            $this->emailConfig,
            is_array($state['siteSettings'] ?? null) ? $state['siteSettings'] : []
        ))->postActionHandler()->handle(
            $_SERVER,
            $_POST,
            is_array($state['voucherForm'] ?? null) ? $state['voucherForm'] : [],
            is_array($state['voucherBatchForm'] ?? null) ? $state['voucherBatchForm'] : []
        );

        if ($voucherPostResult['message'] !== '') {
            $state['message'] = $voucherPostResult['message'];
        }
        if ($voucherPostResult['error'] !== '') {
            $state['error'] = $voucherPostResult['error'];
        }

        $state['voucherForm'] = $voucherPostResult['voucher_form'];
        $state['voucherBatchForm'] = $voucherPostResult['voucher_batch_form'];

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function handleServiceActions(array $state, \mysqli $connection): array
    {
        $servicePostState = (new AdminServicePostActionHandler(
            new AdminServiceMutationService($connection)
        ))->handle(
            $_SERVER,
            $_POST,
            is_array($state['serviceForm'] ?? null) ? $state['serviceForm'] : [],
            is_array($state['categoryForm'] ?? null) ? $state['categoryForm'] : []
        );

        if ($servicePostState['message'] !== '') {
            $state['message'] = $servicePostState['message'];
        }
        if ($servicePostState['error'] !== '') {
            $state['error'] = $servicePostState['error'];
        }

        if (is_array($servicePostState['service_form'] ?? null)) {
            $state['serviceForm'] = $servicePostState['service_form'];
        }
        if (is_array($servicePostState['category_form'] ?? null)) {
            $state['categoryForm'] = $servicePostState['category_form'];
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function handleAvailabilityActions(array $state, \mysqli $connection): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return $state;
        }

        $availabilityMutationService = new AdminAvailabilityMutationService(
            $connection,
            is_array($state['siteSettings'] ?? null) ? $state['siteSettings'] : [],
            $this->projectRoot
        );

        if (isset($_POST['save_availability_grid'])) {
            $saveGridResult = $availabilityMutationService->saveAvailabilityGrid($_POST);
            if (($saveGridResult['success'] ?? false) === true) {
                $state['message'] = (string) ($saveGridResult['message'] ?? 'Dostupnost v kalendáři byla uložena.');
            } else {
                $state['error'] = (string) ($saveGridResult['error'] ?? 'Kalendář dostupnosti se nepodařilo uložit.');
            }
        }

        if (isset($_POST['delete_window'])) {
            $deleteWindowResult = $availabilityMutationService->deleteWindow($_POST);
            if (($deleteWindowResult['success'] ?? false) === true) {
                $state['message'] = (string) ($deleteWindowResult['message'] ?? 'Volné okno bylo odstraněno.');
            } else {
                $state['error'] = (string) ($deleteWindowResult['error'] ?? 'Okno se nepodařilo odstranit.');
            }
        }

        if (isset($_POST['save_availability_story_background'])) {
            $saveBackgroundResult = $availabilityMutationService->saveStoryBackground($_FILES);
            if (is_array($saveBackgroundResult['data']['site_settings'] ?? null)) {
                $state['siteSettings'] = $saveBackgroundResult['data']['site_settings'];
            }

            if (($saveBackgroundResult['success'] ?? false) === true) {
                $state['message'] = (string) ($saveBackgroundResult['message'] ?? 'Pozadí pro Instagram story bylo uloženo.');
            } else {
                $state['error'] = (string) ($saveBackgroundResult['error'] ?? 'Pozadí pro story se nepodařilo uložit.');
            }
        }

        if (isset($_POST['delete_availability_story_background'])) {
            $deleteBackgroundResult = $availabilityMutationService->deleteStoryBackground();
            if (is_array($deleteBackgroundResult['data']['site_settings'] ?? null)) {
                $state['siteSettings'] = $deleteBackgroundResult['data']['site_settings'];
            }

            if (($deleteBackgroundResult['success'] ?? false) === true) {
                $state['message'] = (string) ($deleteBackgroundResult['message'] ?? 'Pozadí pro Instagram story bylo odstraněno.');
            } else {
                $state['error'] = (string) ($deleteBackgroundResult['error'] ?? 'Pozadí pro story se nepodařilo odstranit.');
            }
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function handleReservationActions(array $state, \mysqli $connection): array
    {
        $reservationPostResult = (new AdminReservationPostActionHandler(
            (new AdminReservationModule(
                $connection,
                $this->emailConfig,
                is_array($state['siteSettings'] ?? null) ? $state['siteSettings'] : []
            ))->mutationService()
        ))->handle(
            $_SERVER,
            $_POST,
            $_SESSION,
            is_array($state['manualReservationForm'] ?? null) ? $state['manualReservationForm'] : []
        );

        if ($reservationPostResult['message'] !== '') {
            $state['message'] = $reservationPostResult['message'];
        }
        if ($reservationPostResult['error'] !== '') {
            $state['error'] = $reservationPostResult['error'];
        }

        $state['manualReservationForm'] = $reservationPostResult['manual_reservation_form'];

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function handleMediaActions(array $state, \mysqli $connection): array
    {
        $mediaPostResult = (new AdminMediaModule($connection, $this->projectRoot))
            ->postActionHandler()
            ->handle(
                $_SERVER,
                $_POST,
                $_FILES,
                (string) ($state['message'] ?? ''),
                (string) ($state['error'] ?? ''),
                (string) ($state['mediaFeedback'] ?? ''),
                (string) ($state['mediaFeedbackType'] ?? '')
            );

        $state['message'] = (string) ($mediaPostResult['message'] ?? $state['message']);
        $state['error'] = (string) ($mediaPostResult['error'] ?? $state['error']);
        $state['mediaFeedback'] = (string) ($mediaPostResult['media_feedback'] ?? $state['mediaFeedback']);
        $state['mediaFeedbackType'] = (string) ($mediaPostResult['media_feedback_type'] ?? $state['mediaFeedbackType']);

        return $state;
    }
}
