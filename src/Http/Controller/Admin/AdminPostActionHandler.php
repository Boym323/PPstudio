<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Http\Request\AdminPostActionRequest;
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
    public function handle(
        array $state,
        \mysqli $connection,
        ?AdminPostActionRequest $request = null
    ): array
    {
        $request = $request ?? AdminPostActionRequest::fromGlobals($_SERVER, $_POST, $_FILES, $_SESSION);

        $state = $this->handleSettings($state, $connection, $request);
        $state = $this->handleVoucherActions($state, $connection, $request);
        $state = $this->handleServiceActions($state, $connection, $request);
        $state = $this->handleAvailabilityActions($state, $connection, $request);
        $state = $this->handleReservationActions($state, $connection, $request);

        return $this->handleMediaActions($state, $connection, $request);
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
    private function handleSettings(
        array $state,
        \mysqli $connection,
        AdminPostActionRequest $request
    ): array
    {
        $settingsPostState = (new AdminSettingsPostActionHandler(
            new SiteSettingsService(
                new SiteSettingsRepository($connection),
                defaultSiteSettings()
            )
        ))->handle(
            $request->server(),
            $request->post(),
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
    private function handleVoucherActions(
        array $state,
        \mysqli $connection,
        AdminPostActionRequest $request
    ): array
    {
        $voucherPostResult = (new AdminVoucherModule(
            $connection,
            $this->emailConfig,
            is_array($state['siteSettings'] ?? null) ? $state['siteSettings'] : []
        ))->postActionHandler()->handle(
            $request->server(),
            $request->post(),
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
    private function handleServiceActions(
        array $state,
        \mysqli $connection,
        AdminPostActionRequest $request
    ): array
    {
        $servicePostState = (new AdminServicePostActionHandler(
            new AdminServiceMutationService($connection)
        ))->handle(
            $request->server(),
            $request->post(),
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
    private function handleAvailabilityActions(
        array $state,
        \mysqli $connection,
        AdminPostActionRequest $request
    ): array
    {
        $availabilityPostState = (new AdminAvailabilityPostActionHandler(
            new AdminAvailabilityMutationService(
                $connection,
                is_array($state['siteSettings'] ?? null) ? $state['siteSettings'] : [],
                $this->projectRoot
            )
        ))->handle($request, is_array($state['siteSettings'] ?? null) ? $state['siteSettings'] : []);

        if (is_array($availabilityPostState['site_settings'] ?? null)) {
            $state['siteSettings'] = $availabilityPostState['site_settings'];
        }
        if ($availabilityPostState['message'] !== '') {
            $state['message'] = $availabilityPostState['message'];
        }
        if ($availabilityPostState['error'] !== '') {
            $state['error'] = $availabilityPostState['error'];
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function handleReservationActions(
        array $state,
        \mysqli $connection,
        AdminPostActionRequest $request
    ): array
    {
        $reservationPostResult = (new AdminReservationPostActionHandler(
            (new AdminReservationModule(
                $connection,
                $this->emailConfig,
                is_array($state['siteSettings'] ?? null) ? $state['siteSettings'] : []
            ))->mutationService()
        ))->handle(
            $request->server(),
            $request->post(),
            $request->session(),
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
    private function handleMediaActions(
        array $state,
        \mysqli $connection,
        AdminPostActionRequest $request
    ): array
    {
        $mediaPostResult = (new AdminMediaModule($connection, $this->projectRoot))
            ->postActionHandler()
            ->handle(
                $request->server(),
                $request->post(),
                $request->files(),
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
