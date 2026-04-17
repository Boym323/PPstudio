<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Repository\AvailabilityRepository;
use PPStudio\Repository\ReservationRepository;
use PPStudio\Repository\ServiceRepository;
use PPStudio\Repository\SiteSettingsRepository;
use PPStudio\Http\View\AdminSecurityLogViewPresenter;
use PPStudio\Service\AdminAvailabilityReadService;
use PPStudio\Service\AdminAvailabilityStoryService;
use PPStudio\Service\AdminDashboardService;
use PPStudio\Service\AdminMediaModule;
use PPStudio\Service\AdminReservationModule;
use PPStudio\Service\AdminServiceCatalogService;
use PPStudio\Service\AdminVoucherModule;
use PPStudio\Service\AvailabilityService;
use PPStudio\Service\MailerIntegrationService;
use PPStudio\Service\SiteSettingsService;

final class AdminDataLoader
{
    public function __construct(
        private string $projectRoot,
        private MailerIntegrationService $mailerIntegrationService,
        private AdminViewStateFactory $viewStateFactory,
        private array $emailConfig = [],
    ) {
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function prime(array $state, \mysqli $connection): array
    {
        $siteSettings = (new SiteSettingsService(
            new SiteSettingsRepository($connection),
            defaultSiteSettings()
        ))->load();

        $state['siteSettings'] = $siteSettings;
        $state['subscriptionCalendarUrl'] = $this->mailerIntegrationService->buildSubscriptionCalendarUrl($siteSettings);

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function loadFormData(array $state, \mysqli $connection): array
    {
        $serviceFormData = (new AdminServiceFormDataLoader(
            new AdminServiceCatalogService(new ServiceRepository($connection))
        ))->load(
            isset($_GET['edit_service']) ? (int) $_GET['edit_service'] : null,
            isset($_GET['edit_category']) ? (int) $_GET['edit_category'] : null
        );

        if (is_array($serviceFormData['service_form'] ?? null)) {
            $state['serviceForm'] = $serviceFormData['service_form'];
        }

        if (is_array($serviceFormData['category_form'] ?? null)) {
            $state['categoryForm'] = $serviceFormData['category_form'];
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function loadSecurityLogs(array $state, \mysqli $connection): array
    {
        $securityLogDataLoader = new AdminSecurityLogDataLoader($connection);
        $securityLogPresenter = new AdminSecurityLogViewPresenter();
        $adminBasePath = (string) ($state['adminBasePath'] ?? '/admin.php');

        $antispamData = $securityLogDataLoader->loadAntispam(
            is_array($state['antispamFilters'] ?? null) ? $state['antispamFilters'] : [],
            is_array($state['antispamReasonOptions'] ?? null) ? $state['antispamReasonOptions'] : [],
            is_array($state['antispamLimitOptions'] ?? null) ? $state['antispamLimitOptions'] : []
        );

        $state['antispamRows'] = $antispamData['antispam_rows'];
        $state['antispamLogStats'] = $antispamData['antispam_log_stats'];
        $state['antispamReasonOptions'] = $antispamData['antispam_reason_options'];
        $state['antispamLimitOptions'] = $antispamData['antispam_limit_options'];
        $state['antispamFilters'] = $antispamData['antispam_filters'];
        $state['antispamPagination'] = $antispamData['antispam_pagination'];
        $state['antispamRowsPrepared'] = $securityLogPresenter->prepareAntispamRows($state['antispamRows']);
        $state['antispamPaginationView'] = $securityLogPresenter->buildPaginationView(
            $adminBasePath,
            'antispam-log',
            'antispam_page',
            $state['antispamFilters'],
            $state['antispamPagination'],
            [
                'tab' => 'antispam-log',
                'antispam_q' => (string) ($state['antispamFilters']['q'] ?? ''),
                'antispam_reason' => (string) ($state['antispamFilters']['reason'] ?? 'all'),
                'antispam_limit' => (string) ((int) ($state['antispamFilters']['limit'] ?? 100)),
            ]
        );

        $reminderData = $securityLogDataLoader->loadReminderLogs(
            is_array($state['reminderLogFilters'] ?? null) ? $state['reminderLogFilters'] : [],
            is_array($state['reminderLogEventOptions'] ?? null) ? $state['reminderLogEventOptions'] : [],
            is_array($state['reminderLogSeverityOptions'] ?? null) ? $state['reminderLogSeverityOptions'] : [],
            is_array($state['reminderLogLimitOptions'] ?? null) ? $state['reminderLogLimitOptions'] : []
        );

        $state['reminderLogRows'] = $reminderData['reminder_log_rows'];
        $state['reminderLogStats'] = $reminderData['reminder_log_stats'];
        $state['reminderLogEventOptions'] = $reminderData['reminder_log_event_options'];
        $state['reminderLogSeverityOptions'] = $reminderData['reminder_log_severity_options'];
        $state['reminderLogLimitOptions'] = $reminderData['reminder_log_limit_options'];
        $state['reminderLogFilters'] = $reminderData['reminder_log_filters'];
        $state['reminderLogPagination'] = $reminderData['reminder_log_pagination'];
        $state['reminderLogRowsPrepared'] = $securityLogPresenter->prepareReminderRows($state['reminderLogRows']);
        $state['reminderPaginationView'] = $securityLogPresenter->buildPaginationView(
            $adminBasePath,
            'reminder-log',
            'reminder_page',
            $state['reminderLogFilters'],
            $state['reminderLogPagination'],
            [
                'tab' => 'reminder-log',
                'reminder_q' => (string) ($state['reminderLogFilters']['q'] ?? ''),
                'reminder_event' => (string) ($state['reminderLogFilters']['event'] ?? 'all'),
                'reminder_severity' => (string) ($state['reminderLogFilters']['severity'] ?? 'all'),
                'reminder_limit' => (string) ((int) ($state['reminderLogFilters']['limit'] ?? 100)),
            ]
        );

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function load(array $state, \mysqli $connection): array
    {
        $state = $this->loadServices($state, $connection);
        $state = $this->loadAvailability($state, $connection);
        $state = $this->loadReservations($state, $connection);
        $state = $this->loadDashboard($state, $connection);
        $state = $this->loadMedia($state, $connection);
        $state = $this->loadAvailabilityPlanner($state, $connection);

        return $this->loadVouchers($state, $connection);
    }

    public function loadPageState(AdminPageState $state, \mysqli $connection): AdminPageState
    {
        return $state->merge($this->load($state->toArray(), $connection));
    }

    /**
     * @param array<string, mixed> $state
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
    private function loadServices(array $state, \mysqli $connection): array
    {
        $serviceData = (new AdminServiceDataLoader(
            new AdminServiceCatalogService(new ServiceRepository($connection))
        ))->load(
            is_array($state['serviceFilters'] ?? null) ? $state['serviceFilters'] : [],
            is_array($state['serviceStatusFilterOptions'] ?? null) ? $state['serviceStatusFilterOptions'] : [],
            is_array($state['serviceRows'] ?? null) ? $state['serviceRows'] : [],
            is_array($state['servicePriceHistoryRows'] ?? null) ? $state['servicePriceHistoryRows'] : [],
            is_array($state['categoryForm'] ?? null) ? $state['categoryForm'] : [],
            $_GET
        );

        foreach ([
            'service_filters' => 'serviceFilters',
            'service_category_rows' => 'serviceCategoryRows',
            'service_category_filter_options' => 'serviceCategoryFilterOptions',
            'service_rows' => 'serviceRows',
            'service_price_history_rows' => 'servicePriceHistoryRows',
            'service_base_params' => 'serviceBaseParams',
            'service_rows_prepared' => 'serviceRowsPrepared',
            'service_price_changes' => 'servicePriceChanges',
            'service_price_changes_total' => 'servicePriceChangesTotal',
            'service_price_changes_preview' => 'servicePriceChangesPreview',
            'active_services_section' => 'activeServicesSection',
        ] as $sourceKey => $targetKey) {
            if (array_key_exists($sourceKey, $serviceData)) {
                $state[$targetKey] = $serviceData[$sourceKey];
            }
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function loadAvailability(array $state, \mysqli $connection): array
    {
        $availabilityReadService = new AdminAvailabilityReadService($connection);
        $state['availabilityRows'] = $availabilityReadService->loadAvailabilityRows();

        $storyViewModel = (new AdminAvailabilityStoryService($connection))->buildDefaultViewModel(
            is_array($state['siteSettings'] ?? null) ? $state['siteSettings'] : [],
            is_array($state['serviceCategoryRows'] ?? null) ? $state['serviceCategoryRows'] : []
        );

        $state['storyDefaultFrom'] = (string) ($storyViewModel['storyDefaultFrom'] ?? '');
        $state['storyDefaultTo'] = (string) ($storyViewModel['storyDefaultTo'] ?? '');
        $state['storyDefaultMonth'] = (string) ($storyViewModel['storyDefaultMonth'] ?? '');
        $state['storyBackground'] = (string) ($storyViewModel['storyBackground'] ?? '');
        $state['storyBackgroundUrl'] = (string) ($storyViewModel['storyBackgroundUrl'] ?? '');
        $state['storyDefaultServices'] = is_array($storyViewModel['storyDefaultServices'] ?? null)
            ? $storyViewModel['storyDefaultServices']
            : [];

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function loadReservations(array $state, \mysqli $connection): array
    {
        $reservationData = (new AdminReservationDataLoader(
            (new AdminReservationModule($connection))->adminReservationService()
        ))->load(
            is_array($state['reservationFilters'] ?? null) ? $state['reservationFilters'] : [],
            is_array($state['reservationStatusFilterOptions'] ?? null) ? $state['reservationStatusFilterOptions'] : [],
            is_array($state['reservationPeriodFilterOptions'] ?? null) ? $state['reservationPeriodFilterOptions'] : [],
            is_array($state['reservationPerPageOptions'] ?? null) ? $state['reservationPerPageOptions'] : []
        );

        $state['reservationFilters'] = $reservationData['reservation_filters'];
        $state['reservationPagination'] = $reservationData['reservation_pagination'];
        $state['reservationRows'] = $reservationData['reservation_rows'];

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function loadDashboard(array $state, \mysqli $connection): array
    {
        $availabilityRepository = new AvailabilityRepository($connection);
        $reservationRepository = new ReservationRepository($connection);
        $serviceRepository = new ServiceRepository($connection);
        $availabilityService = new AvailabilityService(
            $serviceRepository,
            $availabilityRepository,
            $reservationRepository
        );

        $dashboardData = (new AdminDashboardService(
            $connection,
            $availabilityRepository,
            $reservationRepository,
            $availabilityService
        ))->loadDashboardData();

        foreach ($dashboardData as $key => $value) {
            $state[$key] = $value;
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function loadMedia(array $state, \mysqli $connection): array
    {
        $mediaData = (new AdminMediaModule($connection, $this->projectRoot))
            ->dataLoader()
            ->load($connection);

        $state['profileMedia'] = is_array($mediaData['profile_media'] ?? null) ? $mediaData['profile_media'] : [];
        $state['galleryMedia'] = is_array($mediaData['gallery_media'] ?? null) ? $mediaData['gallery_media'] : [];
        $state['certificateFiles'] = is_array($mediaData['certificate_files'] ?? null) ? $mediaData['certificate_files'] : [];

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function loadAvailabilityPlanner(array $state, \mysqli $connection): array
    {
        $plannerData = (new AdminAvailabilityReadService($connection))->loadPlannerData(
            (int) ($state['plannerWeekOffset'] ?? 0),
            (int) ($state['plannerDayRange'] ?? 7)
        );

        $state['plannerWeekLabel'] = (string) ($plannerData['plannerWeekLabel'] ?? '');
        $state['plannerDays'] = is_array($plannerData['plannerDays'] ?? null) ? $plannerData['plannerDays'] : [];
        $state['plannerEditableDays'] = is_array($plannerData['plannerEditableDays'] ?? null) ? $plannerData['plannerEditableDays'] : [];
        $state['plannerDayMeta'] = is_array($plannerData['plannerDayMeta'] ?? null) ? $plannerData['plannerDayMeta'] : [];
        $state['plannerBookedWindows'] = is_array($plannerData['plannerBookedWindows'] ?? null) ? $plannerData['plannerBookedWindows'] : [];
        $state['plannerSlots'] = is_array($plannerData['plannerSlots'] ?? null) ? $plannerData['plannerSlots'] : [];
        $state['plannerInitialWindows'] = is_array($plannerData['plannerInitialWindows'] ?? null) ? $plannerData['plannerInitialWindows'] : [];

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function loadVouchers(array $state, \mysqli $connection): array
    {
        $voucherModule = new AdminVoucherModule(
            $connection,
            $this->emailConfig,
            is_array($state['siteSettings'] ?? null) ? $state['siteSettings'] : []
        );
        $voucherData = $voucherModule->dataLoader()->load();

        $state['voucherModuleReady'] = (bool) ($voucherData['voucher_module_ready'] ?? false);
        $state['voucherRows'] = is_array($voucherData['voucher_rows'] ?? null) ? $voucherData['voucher_rows'] : [];
        $state['voucherTransactionsByVoucher'] = is_array($voucherData['voucher_transactions_by_voucher'] ?? null)
            ? $voucherData['voucher_transactions_by_voucher']
            : [];
        $state['voucherReservationOptions'] = is_array($voucherData['voucher_reservation_options'] ?? null)
            ? $voucherData['voucher_reservation_options']
            : [];
        $state['voucherReservationLookup'] = is_array($voucherData['voucher_reservation_lookup'] ?? null)
            ? $voucherData['voucher_reservation_lookup']
            : [];

        $voucherSectionViewData = $voucherModule->catalogService()->buildSectionViewData(
            $state['voucherRows'],
            $state['voucherTransactionsByVoucher'],
            $state['voucherReservationOptions'],
            $state['voucherReservationLookup']
        );

        $state['voucherSummary'] = is_array($voucherSectionViewData['voucher_summary'] ?? null)
            ? $voucherSectionViewData['voucher_summary']
            : [];
        $state['voucherRowsPrepared'] = is_array($voucherSectionViewData['voucher_rows_prepared'] ?? null)
            ? $voucherSectionViewData['voucher_rows_prepared']
            : $state['voucherRows'];
        $state['voucherReservationOptionsPrepared'] = is_array($voucherSectionViewData['voucher_reservation_options_prepared'] ?? null)
            ? $voucherSectionViewData['voucher_reservation_options_prepared']
            : $state['voucherReservationOptions'];

        return $state;
    }
}
