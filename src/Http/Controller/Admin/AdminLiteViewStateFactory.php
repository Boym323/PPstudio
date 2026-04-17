<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

final class AdminLiteViewStateFactory
{
    /**
     * @param array<string, mixed> $get
     * @return array<string, mixed>
     */
    public function create(array $get, string $error = ''): array
    {
        $plannerWeekOffset = isset($get['planner_week']) ? (int) $get['planner_week'] : 0;

        return [
            'message' => '',
            'error' => $error,
            'siteSettings' => defaultSiteSettings(),
            'availabilityRows' => [],
            'reservationRows' => [],
            'serviceRows' => [],
            'serviceRowsPrepared' => [],
            'serviceCategoryRows' => [],
            'servicePriceHistoryRows' => [],
            'servicePriceChanges' => [],
            'servicePriceChangesPreview' => [],
            'servicePriceChangesTotal' => 0,
            'serviceBaseParams' => [],
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
            'activeServicesSection' => 'procedures',
            'profileMedia' => [],
            'galleryMedia' => [],
            'certificateFiles' => [],
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
            'plannerDayMeta' => [],
            'plannerDayRange' => 7,
            'plannerWeekOffset' => $plannerWeekOffset,
            'plannerWeekLabel' => '',
            'mediaFeedback' => '',
            'mediaFeedbackType' => '',
            'reservationPerPageOptions' => [25, 50],
            'reservationStatusFilterOptions' => ['all' => 'Všechny stavy'] + reservationStatusOptions(),
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
            'antispamRows' => [],
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
            ],
            'allowedAdminTabs' => [
                'dashboard',
                'dostupnost',
                'rezervace-list',
                'sluzby-admin',
            ],
            'adminTab' => 'dashboard',
            'adminBasePath' => '/admin-lite.php',
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
}
