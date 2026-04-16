<?php
declare(strict_types=1);

namespace PPStudio\Service;

use DateTimeImmutable;
use mysqli;
use mysqli_result;
use PPStudio\Repository\AvailabilityRepository;
use PPStudio\Repository\ReservationRepository;

final class AdminDashboardService
{
    public function __construct(
        private mysqli $connection,
        private AvailabilityRepository $availabilityRepository,
        private ReservationRepository $reservationRepository,
        private AvailabilityService $availabilityService
    ) {
    }

    public function loadDashboardData(): array
    {
        $todayBounds = AvailabilityService::sqlDayBounds(date('Y-m-d'));
        $tomorrowBounds = AvailabilityService::sqlDayBounds((new DateTimeImmutable('tomorrow'))->format('Y-m-d'));
        $todayStart = (new DateTimeImmutable('today'))->format('Y-m-d H:i:s');
        $tomorrowStart = (new DateTimeImmutable('tomorrow'))->format('Y-m-d H:i:s');

        $dashboardStats = [
            'new_reservations' => 0,
            'upcoming_reservations' => 0,
            'availability_windows' => 0,
            'services_total' => 0,
            'today_reservations' => 0,
            'pending_reservations' => 0,
            'avg_ticket_30d' => 0,
            'active_reservations_30d' => 0,
            'active_reservations_prev_30d' => 0,
            'active_reservations_trend_pct' => 0,
            'free_slots_today' => 0,
        ];

        $dashboardStats['availability_windows'] = $this->countAvailabilityWindows();
        $dashboardStats['services_total'] = $this->countActiveServices();
        $dashboardStats = array_merge($dashboardStats, $this->loadHeadlineStats($todayBounds));

        $dashboardUpcomingReservations = $this->loadReservations(
            'SELECT r.datum_cas, r.jmeno, r.stav, s.nazev
             FROM rezervace r
             INNER JOIN sluzby s ON s.id = r.sluzba
             WHERE r.datum_cas >= NOW()
               AND r.stav IN ("nova", "potvrzena")
             ORDER BY r.datum_cas ASC
             LIMIT 6'
        );

        $dashboardTodayReservations = $this->loadReservations(
            'SELECT r.id, r.datum_cas, r.jmeno, r.stav, s.nazev
             FROM rezervace r
             INNER JOIN sluzby s ON s.id = r.sluzba
             WHERE ' . ($todayBounds !== null
                ? "r.datum_cas >= '{$todayBounds['start']}' AND r.datum_cas < '{$todayBounds['end']}'"
                : '1=0') . '
               AND r.stav IN ("nova", "potvrzena", "dokoncena")
             ORDER BY r.datum_cas ASC
             LIMIT 6'
        );

        $dashboardTomorrowReservations = $this->loadReservations(
            'SELECT r.id, r.datum_cas, r.jmeno, r.stav, s.nazev
             FROM rezervace r
             INNER JOIN sluzby s ON s.id = r.sluzba
             WHERE ' . ($tomorrowBounds !== null
                ? "r.datum_cas >= '{$tomorrowBounds['start']}' AND r.datum_cas < '{$tomorrowBounds['end']}'"
                : '1=0') . '
               AND r.stav IN ("nova", "potvrzena", "dokoncena")
             ORDER BY r.datum_cas ASC
             LIMIT 6'
        );

        $dashboardPendingReservationRows = $this->loadReservations(
            'SELECT r.id, r.datum_cas, r.jmeno, r.email, r.telefon, s.nazev
             FROM rezervace r
             INNER JOIN sluzby s ON s.id = r.sluzba
             WHERE r.datum_cas >= NOW()
               AND r.stav = "nova"
             ORDER BY r.datum_cas ASC
             LIMIT 6'
        );

        $recentReservationChanges = $this->loadRecentReservationChanges();
        $thirtyDayStats = $this->loadThirtyDayAggregates();

        $dashboardStatusBreakdown = $thirtyDayStats['status_breakdown'];
        $dashboardTopServices = $thirtyDayStats['top_services'];
        $dashboardTopCategories = $thirtyDayStats['top_categories'];
        $dashboardStats['avg_ticket_30d'] = $thirtyDayStats['avg_ticket_30d'];
        $dashboardStats['active_reservations_30d'] = $thirtyDayStats['active_reservations_30d'];

        $current30d = (int) $dashboardStats['active_reservations_30d'];
        $previous30d = (int) $dashboardStats['active_reservations_prev_30d'];
        if ($previous30d > 0) {
            $dashboardStats['active_reservations_trend_pct'] = (int) round((($current30d - $previous30d) / $previous30d) * 100);
        } elseif ($current30d > 0) {
            $dashboardStats['active_reservations_trend_pct'] = 100;
        }

        $dashboardStats['free_slots_today'] = $this->countFreeSlotsToday($todayStart, $tomorrowStart);

        $dashboardAttentionItems = $this->buildAttentionItems(
            $dashboardStats,
            count($dashboardTomorrowReservations),
            $recentReservationChanges
        );

        return [
            'dashboardStats' => $dashboardStats,
            'dashboardUpcomingReservations' => $dashboardUpcomingReservations,
            'dashboardTodayReservations' => $dashboardTodayReservations,
            'dashboardTomorrowReservations' => $dashboardTomorrowReservations,
            'dashboardPendingReservationRows' => $dashboardPendingReservationRows,
            'dashboardRecentReservationChanges' => $recentReservationChanges,
            'dashboardTopServices' => $dashboardTopServices,
            'dashboardTopCategories' => $dashboardTopCategories,
            'dashboardStatusBreakdown' => $dashboardStatusBreakdown,
            'dashboardAttentionItems' => $dashboardAttentionItems,
        ];
    }

    private function countAvailabilityWindows(): int
    {
        $query = $this->connection->query(
            'SELECT COUNT(*) AS total
             FROM dostupnost
             WHERE end_at >= NOW()'
        );

        if (! $query instanceof mysqli_result) {
            return 0;
        }

        $row = $query->fetch_assoc() ?: [];
        $query->free();

        return (int) ($row['total'] ?? 0);
    }

    private function countActiveServices(): int
    {
        $query = $this->connection->query(
            'SELECT COUNT(*) AS total
             FROM sluzby
             WHERE aktivni = 1'
        );

        if (! $query instanceof mysqli_result) {
            return 0;
        }

        $row = $query->fetch_assoc() ?: [];
        $query->free();

        return (int) ($row['total'] ?? 0);
    }

    private function loadHeadlineStats(?array $todayBounds): array
    {
        $todayRangeSql = $todayBounds !== null
            ? "r.datum_cas >= '{$todayBounds['start']}' AND r.datum_cas < '{$todayBounds['end']}'"
            : '0';

        $query = $this->connection->query(
            'SELECT
                COALESCE(SUM(r.stav = "nova"), 0) AS new_reservations,
                COALESCE(SUM(r.datum_cas >= NOW() AND r.stav IN ("nova", "potvrzena")), 0) AS upcoming_reservations,
                COALESCE(SUM(' . $todayRangeSql . ' AND r.stav IN ("nova", "potvrzena", "dokoncena")), 0) AS today_reservations,
                COALESCE(SUM(r.datum_cas >= NOW() AND r.stav = "nova"), 0) AS pending_reservations,
                COALESCE(ROUND(AVG(CASE
                    WHEN r.datum_cas >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     AND r.stav IN ("nova", "potvrzena", "dokoncena")
                    THEN COALESCE(r.cena_v_dobe_rezervace, s.cena)
                END)), 0) AS avg_ticket_30d,
                COALESCE(SUM(r.datum_cas >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND r.stav IN ("nova", "potvrzena", "dokoncena")), 0) AS active_reservations_30d,
                COALESCE(SUM(r.datum_cas >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                    AND r.datum_cas < DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND r.stav IN ("nova", "potvrzena", "dokoncena")), 0) AS active_reservations_prev_30d
             FROM rezervace r
             LEFT JOIN sluzby s ON s.id = r.sluzba'
        );

        if (! $query instanceof mysqli_result) {
            return [];
        }

        $row = $query->fetch_assoc() ?: [];
        $query->free();

        return [
            'new_reservations' => (int) ($row['new_reservations'] ?? 0),
            'upcoming_reservations' => (int) ($row['upcoming_reservations'] ?? 0),
            'today_reservations' => (int) ($row['today_reservations'] ?? 0),
            'pending_reservations' => (int) ($row['pending_reservations'] ?? 0),
            'avg_ticket_30d' => (int) ($row['avg_ticket_30d'] ?? 0),
            'active_reservations_30d' => (int) ($row['active_reservations_30d'] ?? 0),
            'active_reservations_prev_30d' => (int) ($row['active_reservations_prev_30d'] ?? 0),
        ];
    }

    /**
     * @return array{status_breakdown: array<string, int>, top_services: array<int, array<string, int|string>>, top_categories: array<int, array<string, int|string>>, avg_ticket_30d: int, active_reservations_30d: int}
     */
    private function loadThirtyDayAggregates(): array
    {
        $query = $this->connection->query(
            'SELECT r.stav, r.cena_v_dobe_rezervace, s.cena, s.nazev AS service_name,
                    COALESCE(NULLIF(c.nazev, ""), "Ostatní služby") AS category_name
             FROM rezervace r
             INNER JOIN sluzby s ON s.id = r.sluzba
             LEFT JOIN kategorie c ON c.id = s.kategorie_id
             WHERE r.datum_cas >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND r.stav IN ("nova", "potvrzena", "dokoncena")
             ORDER BY r.datum_cas DESC'
        );

        $statusBreakdown = [
            'nova' => 0,
            'potvrzena' => 0,
            'dokoncena' => 0,
            'zrusena' => 0,
        ];
        $serviceCounts = [];
        $categoryCounts = [];
        $ticketSum = 0.0;
        $ticketCount = 0;

        if ($query instanceof mysqli_result) {
            while ($row = $query->fetch_assoc()) {
                $status = (string) ($row['stav'] ?? '');
                if ($status !== '' && array_key_exists($status, $statusBreakdown)) {
                    $statusBreakdown[$status]++;
                }

                $serviceName = trim((string) ($row['service_name'] ?? ''));
                if ($serviceName === '') {
                    $serviceName = 'Procedura';
                }
                $categoryName = trim((string) ($row['category_name'] ?? ''));
                if ($categoryName === '') {
                    $categoryName = 'Ostatní služby';
                }

                $serviceCounts[$serviceName] = ($serviceCounts[$serviceName] ?? 0) + 1;
                $categoryCounts[$categoryName] = ($categoryCounts[$categoryName] ?? 0) + 1;

                $ticketSum += (float) ($row['cena_v_dobe_rezervace'] ?? $row['cena'] ?? 0);
                $ticketCount++;
            }

            $query->free();
        }

        $topServices = [];
        foreach ($serviceCounts as $serviceName => $count) {
            $topServices[] = [
                'nazev' => $serviceName,
                'reservations_count' => $count,
            ];
        }

        usort(
            $topServices,
            static fn (array $left, array $right): int => ($right['reservations_count'] <=> $left['reservations_count'])
                ?: strcasecmp((string) ($left['nazev'] ?? ''), (string) ($right['nazev'] ?? ''))
        );

        $topCategories = [];
        foreach ($categoryCounts as $categoryName => $count) {
            $topCategories[] = [
                'category_name' => $categoryName,
                'reservations_count' => $count,
            ];
        }

        usort(
            $topCategories,
            static fn (array $left, array $right): int => ($right['reservations_count'] <=> $left['reservations_count'])
                ?: strcasecmp((string) ($left['category_name'] ?? ''), (string) ($right['category_name'] ?? ''))
        );

        return [
            'status_breakdown' => $statusBreakdown,
            'top_services' => array_slice($topServices, 0, 5),
            'top_categories' => array_slice($topCategories, 0, 5),
            'avg_ticket_30d' => $ticketCount > 0 ? (int) round($ticketSum / $ticketCount) : 0,
            'active_reservations_30d' => $ticketCount,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadReservations(string $sql): array
    {
        $query = $this->connection->query($sql);
        if (! $query instanceof mysqli_result) {
            return [];
        }

        $rows = [];
        while ($row = $query->fetch_assoc()) {
            $rows[] = $row;
        }

        $query->free();

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRecentReservationChanges(): array
    {
        $query = $this->connection->query(
            "SELECT created_at, event_type, context_json
             FROM security_events
             WHERE event_type IN (
                'reservation_admin_rescheduled',
                'reservation_admin_cancelled',
                'reservation_customer_rescheduled',
                'reservation_customer_cancelled'
             )
             ORDER BY created_at DESC
             LIMIT 6"
        );

        if (! $query instanceof mysqli_result) {
            return [];
        }

        $rows = [];
        while ($row = $query->fetch_assoc()) {
            $context = json_decode((string) ($row['context_json'] ?? ''), true);
            if (! is_array($context)) {
                $context = [];
            }

            $eventType = (string) ($row['event_type'] ?? '');
            $changeTypeLabel = match ($eventType) {
                'reservation_admin_rescheduled' => 'Přesunula jste rezervaci',
                'reservation_customer_rescheduled' => 'Klientka přesunula rezervaci',
                'reservation_admin_cancelled' => 'Zrušila jste rezervaci',
                'reservation_customer_cancelled' => 'Klientka zrušila rezervaci',
                default => 'Změna rezervace',
            };

            $rows[] = [
                'time' => (string) ($row['created_at'] ?? ''),
                'label' => $changeTypeLabel,
                'reservation_id' => (int) ($context['reservation_id'] ?? 0),
                'old_datetime' => (string) ($context['old_datetime'] ?? ''),
                'new_datetime' => (string) ($context['new_datetime'] ?? ''),
                'cancel_reason' => trim((string) ($context['cancel_reason'] ?? '')),
                'cancelled_by' => trim((string) ($context['cancelled_by'] ?? '')),
                'event_type' => $eventType,
            ];
        }

        $query->free();

        return $rows;
    }

    private function countFreeSlotsToday(string $todayStartSql, string $tomorrowStartSql): int
    {
        $slotSizeSeconds = 30 * 60;
        $nowTimestamp = time();
        $todayStart = new DateTimeImmutable('today');
        $tomorrowStart = $todayStart->modify('+1 day');
        $availableSlots = [];
        $bookedSlots = [];

        $windows = $this->availabilityService->normalizeAvailabilityWindows(
            $this->availabilityRepository->findWindowsBetween($todayStartSql, $tomorrowStartSql),
            false
        );
        $bookedIntervals = $this->availabilityService->normalizeBookedIntervals(
            $this->reservationRepository->findBookedBetween($todayStartSql, $tomorrowStartSql)
        );

        foreach ($windows as $window) {
            $startTs = max($window->start->getTimestamp(), $todayStart->getTimestamp(), $nowTimestamp);
            $endTs = min($window->end->getTimestamp(), $tomorrowStart->getTimestamp());

            if ($endTs <= $startTs) {
                continue;
            }

            $cursorTs = (int) (ceil($startTs / $slotSizeSeconds) * $slotSizeSeconds);
            while ($cursorTs < $endTs) {
                $availableSlots[(string) $cursorTs] = true;
                $cursorTs += $slotSizeSeconds;
            }
        }

        foreach ($bookedIntervals as $interval) {
            $startTs = max($interval->start->getTimestamp(), $nowTimestamp, $todayStart->getTimestamp());
            $endTs = min($interval->end->getTimestamp(), $tomorrowStart->getTimestamp());

            if ($endTs <= $startTs) {
                continue;
            }

            $cursorTs = (int) (floor($startTs / $slotSizeSeconds) * $slotSizeSeconds);
            while ($cursorTs < $endTs) {
                $bookedSlots[(string) $cursorTs] = true;
                $cursorTs += $slotSizeSeconds;
            }
        }

        return count(array_diff_key($availableSlots, $bookedSlots));
    }

    private function buildAttentionItems(array $dashboardStats, int $tomorrowReservationsCount, array $dashboardRecentReservationChanges): array
    {
        $dashboardAttentionItems = [];

        $pendingReservationsCount = (int) ($dashboardStats['pending_reservations'] ?? 0);
        $todayReservationsCount = (int) ($dashboardStats['today_reservations'] ?? 0);
        $freeSlotsTodayCount = (int) ($dashboardStats['free_slots_today'] ?? 0);

        if ($pendingReservationsCount > 0) {
            $dashboardAttentionItems[] = [
                'tone' => 'warning',
                'title' => 'Čekají nové rezervace k potvrzení',
                'text' => $pendingReservationsCount === 1
                    ? 'Máte 1 novou rezervaci, která čeká na potvrzení.'
                    : 'Máte ' . $pendingReservationsCount . ' nových rezervací, které čekají na potvrzení.',
            ];
        }

        if ($todayReservationsCount === 0) {
            $dashboardAttentionItems[] = [
                'tone' => 'info',
                'title' => 'Dnes zatím není žádná rezervace',
                'text' => 'Zkontrolujte dostupnost a případně uvolněte termíny pro dnešní den.',
            ];
        } elseif ($freeSlotsTodayCount > 0) {
            $dashboardAttentionItems[] = [
                'tone' => 'success',
                'title' => 'Dnes ještě zbývají volné sloty',
                'text' => $freeSlotsTodayCount . ' volných 30min slotů je stále k dispozici pro rychlé doobjednání.',
            ];
        }

        if ($tomorrowReservationsCount === 0) {
            $dashboardAttentionItems[] = [
                'tone' => 'info',
                'title' => 'Zítřek je zatím prázdný',
                'text' => 'Na zítřek zatím nevidím žádnou rezervaci. Může se hodit zkontrolovat dostupnost nebo propagaci volných termínů.',
            ];
        }

        if ($dashboardRecentReservationChanges !== []) {
            $latestChange = $dashboardRecentReservationChanges[0];
            $dashboardAttentionItems[] = [
                'tone' => 'neutral',
                'title' => 'Poslední změna v rezervacích',
                'text' => trim((string) ($latestChange['label'] ?? 'Změna rezervace')) . ' ' . mb_strtolower(formatCzechDateTime((string) ($latestChange['time'] ?? ''))),
            ];
        }

        return $dashboardAttentionItems;
    }
}
