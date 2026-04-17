<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Database\DatabaseFactory;
use PPStudio\Service\MailerIntegrationService;
use PPStudio\Http\Controller\Admin\AdminSecurityLogDataLoader;
use PPStudio\Http\Controller\Admin\AdminSettingsPostActionHandler;
use PPStudio\Repository\SiteSettingsRepository;
use PPStudio\Service\SiteSettingsService;

final class AdminApplication
{
    private AdminAuthenticationService $authenticationService;

    public function __construct(
        private array $adminConfig,
        private array $emailConfig,
    ) {
        $this->authenticationService = new AdminAuthenticationService();
    }

    public function handle(): never
    {
        $projectRoot = $this->projectRoot();
        $authState = $this->authenticationService->handle($this->adminConfig, [
            'auth_session_key' => 'ppstudio_admin_authenticated',
            'username_session_key' => 'ppstudio_admin_username',
            'throttle_scope' => 'admin',
            'redirect_path' => 'admin.php',
            'event_source' => 'admin_login',
            'event_name_prefix' => 'admin_login',
        ]);
        $error = (string) $authState['error'];

        if (! $authState['is_authenticated']) {
            $loginError = (string) $authState['login_error'];
            include $projectRoot . '/includes/admin/templates/login.php';
            exit;
        }

        $connection = DatabaseFactory::tryConnect();

        $message = '';
        $error = $error ?? '';
        $siteSettings = defaultSiteSettings();
        $availabilityRows = [];
        $reservationRows = [];
        $serviceRows = [];
        $serviceRowsPrepared = [];
        $serviceCategoryRows = [];
        $servicePriceHistoryRows = [];
        $servicePriceChanges = [];
        $servicePriceChangesPreview = [];
        $servicePriceChangesTotal = 0;
        $serviceBaseParams = [];
        $voucherRows = [];
        $voucherTransactionsByVoucher = [];
        $voucherReservationOptions = [];
        $voucherReservationLookup = [];
        $voucherModuleReady = false;
        $voucherForm = [
            'code' => '',
            'value' => '',
            'expires_at' => date('Y-m-d', strtotime('+1 year')),
            'recipient_name' => '',
            'recipient_email' => '',
            'note' => '',
        ];
        $voucherBatchForm = [
            'prefix' => 'PP' . date('y'),
            'count' => '20',
            'value' => '1000',
            'expires_at' => date('Y-m-d', strtotime('+1 year')),
            'recipient_name' => '',
            'note' => '',
        ];
        $dashboardStats = [
            'new_reservations' => 0,
            'upcoming_reservations' => 0,
            'availability_windows' => 0,
            'services_total' => 0,
            'today_reservations' => 0,
            'pending_reservations' => 0,
            'free_slots_today' => 0,
            'avg_ticket_30d' => 0,
            'active_reservations_30d' => 0,
            'active_reservations_prev_30d' => 0,
            'active_reservations_trend_pct' => 0,
        ];
        $dashboardUpcomingReservations = [];
        $dashboardTodayReservations = [];
        $dashboardTomorrowReservations = [];
        $dashboardPendingReservationRows = [];
        $dashboardRecentReservationChanges = [];
        $dashboardAttentionItems = [];
        $dashboardTopServices = [];
        $dashboardTopCategories = [];
        $dashboardStatusBreakdown = [
            'nova' => 0,
            'potvrzena' => 0,
            'dokoncena' => 0,
            'zrusena' => 0,
        ];
        $serviceForm = [
            'id' => 0,
            'nazev' => '',
            'kategorie_id' => '',
            'stitek' => '',
            'kategorie' => '',
            'kategorie_poradi' => '',
            'popis' => '',
            'cena' => '',
            'doba_trvani' => '',
        ];
        $categoryForm = [
            'id' => 0,
            'nazev' => '',
            'poradi' => '',
        ];
        $activeServicesSection = 'procedures';
        $profileMedia = [];
        $galleryMedia = [];
        $certificateFiles = [];
        $subscriptionCalendarUrl = '';
        $manualReservationForm = [
            'jmeno' => '',
            'email' => '',
            'telefon' => '',
            'zdroj' => 'telefon',
            'sluzba_id' => '',
            'datum_cas' => '',
            'poznamka_klienta' => '',
        ];
        $reservationSourceOptions = [
            'telefon' => 'Telefon',
            'instagram' => 'Instagram',
            'messenger' => 'Messenger',
            'osobne' => 'Osobně',
            'jine' => 'Jiné',
        ];
        $plannerDays = [];
        $plannerSlots = [];
        $plannerInitialWindows = [];
        $plannerBookedWindows = [];
        $plannerDayMeta = [];
        $plannerDayRange = 7;
        $plannerWeekOffset = isset($_GET['planner_week']) ? (int) $_GET['planner_week'] : 0;
        $plannerWeekLabel = '';
        $mediaFeedback = '';
        $mediaFeedbackType = '';
        $reservationPerPageOptions = [25, 50];
        $reservationStatusFilterOptions = ['all' => 'Všechny stavy'] + reservationStatusOptions();
        $reservationPeriodFilterOptions = [
            'all' => 'Všechna období',
            'today' => 'Dnes',
            'week' => 'Tento týden',
            'month' => 'Tento měsíc',
        ];
        $reservationFilters = [
            'q' => trim((string) ($_GET['reservation_q'] ?? '')),
            'status' => (string) ($_GET['reservation_status'] ?? 'all'),
            'period' => (string) ($_GET['reservation_period'] ?? 'all'),
            'per_page' => (int) ($_GET['reservation_per_page'] ?? 25),
            'page' => max(1, (int) ($_GET['reservation_page'] ?? 1)),
        ];
        $reservationPagination = [
            'total' => 0,
            'total_pages' => 1,
        ];
        $serviceStatusFilterOptions = [
            'all' => 'Všechny stavy',
            'active' => 'Pouze aktivní',
            'inactive' => 'Pouze neaktivní',
        ];
        $serviceFilters = [
            'q' => trim((string) ($_GET['service_q'] ?? '')),
            'category' => (string) ($_GET['service_category'] ?? 'all'),
            'status' => (string) ($_GET['service_status'] ?? 'all'),
        ];
        $antispamRows = [];
        $antispamLogStats = [
            'total' => 0,
            'shown' => 0,
            'file_exists' => false,
            'size_bytes' => 0,
        ];
        $antispamReasonOptions = [
            'all' => 'Všechny důvody',
            'antispam_rate_limited' => 'Rate limit',
            'antispam_honeypot_filled' => 'Honeypot vyplněn',
            'antispam_missing_or_invalid_token' => 'Chybějící/neplatný token',
            'antispam_submitted_too_fast' => 'Odesláno příliš rychle',
            'antispam_token_expired' => 'Expirovaný token',
        ];
        $antispamLimitOptions = [50, 100, 200, 500];
        $antispamFilters = [
            'reason' => (string) ($_GET['antispam_reason'] ?? 'all'),
            'q' => trim((string) ($_GET['antispam_q'] ?? '')),
            'limit' => (int) ($_GET['antispam_limit'] ?? 100),
            'page' => max(1, (int) ($_GET['antispam_page'] ?? 1)),
        ];
        $antispamPagination = [
            'total_pages' => 1,
        ];
        $reminderLogRows = [];
        $reminderLogStats = [
            'total' => 0,
            'shown' => 0,
            'source' => 'db',
        ];
        $reminderLogEventOptions = [
            'all' => 'Všechny události',
        ];
        $reminderLogSeverityOptions = [
            'all' => 'Všechny úrovně',
            'info' => 'Info',
            'warning' => 'Warning',
            'error' => 'Error',
        ];
        $reminderLogLimitOptions = [50, 100, 200, 500];
        $reminderLogFilters = [
            'q' => trim((string) ($_GET['reminder_q'] ?? '')),
            'event' => (string) ($_GET['reminder_event'] ?? 'all'),
            'severity' => (string) ($_GET['reminder_severity'] ?? 'all'),
            'limit' => (int) ($_GET['reminder_limit'] ?? 100),
            'page' => max(1, (int) ($_GET['reminder_page'] ?? 1)),
        ];
        $reminderLogPagination = [
            'total_pages' => 1,
        ];
        $allowedAdminTabs = [
            'dashboard',
            'antispam-log',
            'reminder-log',
            'dostupnost',
            'rezervace-list',
            'sluzby-admin',
            'poukazy',
            'media',
            'nastaveni',
        ];
        $adminTab = trim((string) ($_GET['tab'] ?? 'dashboard'));
        if ($adminTab === 'kalendar') {
            $adminTab = 'rezervace-list';
        }
        if ($adminTab === 'recenze-napojeni') {
            $adminTab = 'nastaveni';
            $_GET['settings_section'] = 'recenze';
        }
        if ($adminTab === 'emaily') {
            $adminTab = 'nastaveni';
            $_GET['settings_section'] = 'email';
        }
        if (! in_array($adminTab, $allowedAdminTabs, true)) {
            $adminTab = 'dashboard';
        }
        $adminBasePath = '/admin.php';
        $settingsSection = (string) ($_GET['settings_section'] ?? 'studio');
        if (! in_array($settingsSection, ['studio', 'recenze', 'email'], true)) {
            $settingsSection = 'studio';
        }
        $studioSettingFields = [
            'site_name' => 'Název studia',
            'site_url' => 'URL webu',
            'contact_name' => 'Kontaktní osoba',
            'contact_phone' => 'Telefon',
            'contact_email' => 'Kontaktní e-mail',
            'contact_instagram_url' => 'Instagram URL',
            'contact_ico' => 'IČO',
            'contact_opening_hours' => 'Otevírací doba',
            'contact_address' => 'Adresa studia (pro e-maily)',
        ];

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
            && $_POST === []
            && $_FILES === []
            && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $this->iniSizeToBytes((string) ini_get('post_max_size'))
        ) {
            $error = 'Odesílaný formulář je příliš velký pro server. Zmenšete prosím obrázek nebo navyšte limit post_max_size v PHP.';
        }

        if ($connection instanceof \mysqli) {
            $siteSettings = loadSiteSettings($connection);
            $subscriptionCalendarUrl = (new MailerIntegrationService($this->emailConfig))
                ->buildSubscriptionCalendarUrl($siteSettings);

            $securityLogDataLoader = new AdminSecurityLogDataLoader($connection);
            $settingsPostActionHandler = new AdminSettingsPostActionHandler(
                new SiteSettingsService(
                    new SiteSettingsRepository($connection),
                    defaultSiteSettings()
                )
            );

            $settingsPostState = $settingsPostActionHandler->handle(
                $_SERVER,
                $_POST,
                $siteSettings,
                $studioSettingFields,
                [
                    'google_reviews_url',
                    'firmy_reviews_url',
                    'firmy_reviews_embed',
                    'google_place_id',
                    'google_reviews_language',
                ],
                ['notification_emails']
            );
            $siteSettings = $settingsPostState['siteSettings'];
            if ($settingsPostState['message'] !== '') {
                $message = $settingsPostState['message'];
            }
            if ($settingsPostState['error'] !== '') {
                $error = $settingsPostState['error'];
            }

            $antispamData = $securityLogDataLoader->loadAntispam(
                $antispamFilters,
                $antispamReasonOptions,
                $antispamLimitOptions
            );
            $antispamRows = $antispamData['antispam_rows'];
            $antispamLogStats = $antispamData['antispam_log_stats'];
            $antispamReasonOptions = $antispamData['antispam_reason_options'];
            $antispamLimitOptions = $antispamData['antispam_limit_options'];
            $antispamFilters = $antispamData['antispam_filters'];
            $antispamPagination = $antispamData['antispam_pagination'];

            $reminderData = $securityLogDataLoader->loadReminderLogs(
                $reminderLogFilters,
                $reminderLogEventOptions,
                $reminderLogSeverityOptions,
                $reminderLogLimitOptions
            );
            $reminderLogRows = $reminderData['reminder_log_rows'];
            $reminderLogStats = $reminderData['reminder_log_stats'];
            $reminderLogEventOptions = $reminderData['reminder_log_event_options'];
            $reminderLogSeverityOptions = $reminderData['reminder_log_severity_options'];
            $reminderLogLimitOptions = $reminderData['reminder_log_limit_options'];
            $reminderLogFilters = $reminderData['reminder_log_filters'];
            $reminderLogPagination = $reminderData['reminder_log_pagination'];

            include $projectRoot . '/includes/admin/actions/load/service_forms.php';
            include $projectRoot . '/includes/admin/actions/post_actions.php';
            include $projectRoot . '/includes/admin/actions/load_data.php';
        } else {
            $error = 'Nepodařilo se připojit k databázi. Zkontrolujte `config/database.php`.';
        }

        include $projectRoot . '/includes/admin/templates/app.php';

        if ($connection instanceof \mysqli) {
            $connection->close();
        }

        exit;
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 4);
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
