<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Config\AppConfig;

final class AdminViewStateFactory
{
    /**
     * @param array<string, mixed> $get
     * @return array<string, mixed>
     */
    public function create(array $get, string $error = ''): array
    {
        $plannerWeekOffset = isset($get['planner_week']) ? (int) $get['planner_week'] : 0;
        $editServiceId = isset($get['edit_service']) ? (int) $get['edit_service'] : 0;
        $editCategoryId = isset($get['edit_category']) ? (int) $get['edit_category'] : 0;

        return [
            'message' => '',
            'error' => $error,
            'siteSettings' => AppConfig::instance()->defaultSiteSettings(),
            'availabilityRows' => [],
            'storyDefaultFrom' => '',
            'storyDefaultTo' => '',
            'storyDefaultMonth' => '',
            'storyBackground' => '',
            'storyBackgroundUrl' => '',
            'storyDefaultServices' => [],
            'reservationRows' => [],
            'serviceRows' => [],
            'serviceRowsPrepared' => [],
            'serviceCategoryRows' => [],
            'serviceCategoryFilterOptions' => [],
            'servicePriceHistoryRows' => [],
            'servicePriceChanges' => [],
            'servicePriceChangesPreview' => [],
            'servicePriceChangesTotal' => 0,
            'serviceBaseParams' => [],
            'voucherRows' => [],
            'voucherTransactionsByVoucher' => [],
            'voucherReservationOptions' => [],
            'voucherReservationLookup' => [],
            'voucherModuleReady' => false,
            'voucherSummary' => [],
            'voucherRowsPrepared' => [],
            'voucherReservationOptionsPrepared' => [],
            'voucherForm' => [
                'code' => '',
                'value' => '',
                'expires_at' => date('Y-m-d', strtotime('+1 year')),
                'recipient_name' => '',
                'recipient_email' => '',
                'note' => '',
            ],
            'voucherBatchForm' => [
                'prefix' => 'PP' . date('y'),
                'count' => '20',
                'value' => '1000',
                'expires_at' => date('Y-m-d', strtotime('+1 year')),
                'recipient_name' => '',
                'note' => '',
            ],
            'dashboardStats' => [
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
            ],
            'dashboardUpcomingReservations' => [],
            'dashboardTodayReservations' => [],
            'dashboardTomorrowReservations' => [],
            'dashboardPendingReservationRows' => [],
            'dashboardRecentReservationChanges' => [],
            'dashboardAttentionItems' => [],
            'dashboardTopServices' => [],
            'dashboardTopCategories' => [],
            'dashboardStatusBreakdown' => [
                'nova' => 0,
                'potvrzena' => 0,
                'dokoncena' => 0,
                'zrusena' => 0,
            ],
            'serviceForm' => [
                'id' => 0,
                'nazev' => '',
                'kategorie_id' => '',
                'stitek' => '',
                'kategorie' => '',
                'kategorie_poradi' => '',
                'popis' => '',
                'cena' => '',
                'doba_trvani' => '',
            ],
            'categoryForm' => [
                'id' => 0,
                'nazev' => '',
                'poradi' => '',
            ],
            'editServiceId' => $editServiceId,
            'editCategoryId' => $editCategoryId,
            'activeServicesSection' => 'procedures',
            'requestedServicesSection' => (string) ($get['service_section'] ?? ''),
            'profileMedia' => [],
            'galleryMedia' => [],
            'certificateFiles' => [],
            'activeMediaSection' => $this->resolveMediaSection($get['media_section'] ?? null),
            'subscriptionCalendarUrl' => '',
            'manualReservationForm' => [
                'jmeno' => '',
                'email' => '',
                'telefon' => '',
                'zdroj' => 'telefon',
                'sluzba_id' => '',
                'datum_cas' => '',
                'poznamka_klienta' => '',
            ],
            'reservationSourceOptions' => [
                'telefon' => 'Telefon',
                'instagram' => 'Instagram',
                'messenger' => 'Messenger',
                'osobne' => 'Osobně',
                'jine' => 'Jiné',
            ],
            'plannerDays' => [],
            'plannerSlots' => [],
            'plannerInitialWindows' => [],
            'plannerBookedWindows' => [],
            'plannerEditableDays' => [],
            'plannerDayMeta' => [],
            'plannerDayRange' => 7,
            'plannerWeekOffset' => $plannerWeekOffset,
            'plannerWeekLabel' => '',
            'mediaFeedback' => '',
            'mediaFeedbackType' => '',
            'reservationPerPageOptions' => [25, 50],
            'reservationStatusFilterOptions' => ['all' => 'Všechny stavy'] + \PPStudio\Support\ReservationStatusHelper::options(),
            'reservationPeriodFilterOptions' => [
                'all' => 'Všechna období',
                'today' => 'Dnes',
                'week' => 'Tento týden',
                'month' => 'Tento měsíc',
            ],
            'reservationFilters' => [
                'q' => trim((string) ($get['reservation_q'] ?? '')),
                'status' => (string) ($get['reservation_status'] ?? 'all'),
                'period' => (string) ($get['reservation_period'] ?? 'all'),
                'per_page' => (int) ($get['reservation_per_page'] ?? 25),
                'page' => max(1, (int) ($get['reservation_page'] ?? 1)),
            ],
            'reservationPagination' => [
                'total' => 0,
                'total_pages' => 1,
            ],
            'serviceStatusFilterOptions' => [
                'all' => 'Všechny stavy',
                'active' => 'Pouze aktivní',
                'inactive' => 'Pouze neaktivní',
            ],
            'serviceFilters' => [
                'q' => trim((string) ($get['service_q'] ?? '')),
                'category' => (string) ($get['service_category'] ?? 'all'),
                'status' => (string) ($get['service_status'] ?? 'all'),
            ],
            'antispamRows' => [],
            'antispamRowsPrepared' => [],
            'antispamLogStats' => [
                'total' => 0,
                'shown' => 0,
                'file_exists' => false,
                'size_bytes' => 0,
            ],
            'antispamReasonOptions' => [
                'all' => 'Všechny důvody',
                'antispam_rate_limited' => 'Rate limit',
                'antispam_honeypot_filled' => 'Honeypot vyplněn',
                'antispam_missing_or_invalid_token' => 'Chybějící/neplatný token',
                'antispam_submitted_too_fast' => 'Odesláno příliš rychle',
                'antispam_token_expired' => 'Expirovaný token',
            ],
            'antispamLimitOptions' => [50, 100, 200, 500],
            'antispamFilters' => [
                'reason' => (string) ($get['antispam_reason'] ?? 'all'),
                'q' => trim((string) ($get['antispam_q'] ?? '')),
                'limit' => (int) ($get['antispam_limit'] ?? 100),
                'page' => max(1, (int) ($get['antispam_page'] ?? 1)),
            ],
            'antispamPagination' => [
                'total_pages' => 1,
            ],
            'antispamPaginationView' => [
                'current_page' => 1,
                'total_pages' => 1,
                'prev_url' => '',
                'next_url' => '',
                'pages' => [],
            ],
            'reminderLogRows' => [],
            'reminderLogRowsPrepared' => [],
            'reminderLogStats' => [
                'total' => 0,
                'shown' => 0,
                'source' => 'db',
            ],
            'reminderLogEventOptions' => [
                'all' => 'Všechny události',
            ],
            'reminderLogSeverityOptions' => [
                'all' => 'Všechny úrovně',
                'info' => 'Info',
                'warning' => 'Warning',
                'error' => 'Error',
            ],
            'reminderLogLimitOptions' => [50, 100, 200, 500],
            'reminderLogFilters' => [
                'q' => trim((string) ($get['reminder_q'] ?? '')),
                'event' => (string) ($get['reminder_event'] ?? 'all'),
                'severity' => (string) ($get['reminder_severity'] ?? 'all'),
                'limit' => (int) ($get['reminder_limit'] ?? 100),
                'page' => max(1, (int) ($get['reminder_page'] ?? 1)),
            ],
            'reminderLogPagination' => [
                'total_pages' => 1,
            ],
            'reminderPaginationView' => [
                'current_page' => 1,
                'total_pages' => 1,
                'prev_url' => '',
                'next_url' => '',
                'pages' => [],
            ],
            'allowedAdminTabs' => [
                'dashboard',
                'antispam-log',
                'reminder-log',
                'dostupnost',
                'rezervace-list',
                'sluzby-admin',
                'poukazy',
                'media',
                'nastaveni',
            ],
            'adminTab' => 'dashboard',
            'adminBasePath' => '/admin.php',
            'settingsSection' => 'studio',
            'studioSettingFields' => [
                'site_name' => 'Název studia',
                'site_url' => 'URL webu',
                'contact_name' => 'Kontaktní osoba',
                'contact_phone' => 'Telefon',
                'contact_email' => 'Kontaktní e-mail',
                'contact_instagram_url' => 'Instagram URL',
                'contact_ico' => 'IČO',
                'contact_opening_hours' => 'Otevírací doba',
                'contact_address' => 'Adresa studia (pro e-maily)',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->create([]));
    }

    private function resolveMediaSection(mixed $value): string
    {
        $section = is_string($value) ? $value : '';

        return in_array($section, ['profile', 'gallery', 'certificates'], true)
            ? $section
            : 'profile';
    }
}
