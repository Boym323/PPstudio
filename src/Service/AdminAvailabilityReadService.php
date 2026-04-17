<?php
declare(strict_types=1);

namespace PPStudio\Service;

use DateTimeImmutable;
use mysqli;
use mysqli_result;
use PPStudio\Support\DateHelper;
use PPStudio\Support\FormatHelper;

final class AdminAvailabilityReadService
{
    public function __construct(
        private mysqli $connection
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function loadAvailabilityRows(int $limit = 400): array
    {
        $query = $this->connection->query(
            'SELECT id, start_at, end_at, poznamka
             FROM dostupnost
             WHERE end_at >= NOW()
             ORDER BY start_at ASC
             LIMIT ' . max(1, $limit)
        );

        if (! $query instanceof mysqli_result) {
            return [];
        }

        $rows = $query->fetch_all(MYSQLI_ASSOC);
        $query->free();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array{id:int,date_label:string,time_label:string,note:string}>
     */
    public function loadAvailabilityRowsForApi(int $limit = 400): array
    {
        return $this->formatAvailabilityRowsForApi($this->loadAvailabilityRows($limit));
    }

    /**
     * @param array<int, array<string, mixed>> $availabilityRows
     * @return array<int, array{id:int,date_label:string,time_label:string,note:string}>
     */
    public function formatAvailabilityRowsForApi(array $availabilityRows): array
    {
        $formattedRows = [];

        foreach ($availabilityRows as $row) {
            $startAt = (string) ($row['start_at'] ?? '');
            $endAt = (string) ($row['end_at'] ?? '');

            $formattedRows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'date_label' => FormatHelper::formatCzechDate(substr($startAt, 0, 10)),
                'time_label' => substr($startAt, 11, 5) . ' - ' . substr($endAt, 11, 5),
                'note' => (string) ($row['poznamka'] ?? ''),
            ];
        }

        return $formattedRows;
    }

    /**
     * @return array{
     *   plannerWeekLabel:string,
     *   plannerDays:array<int,string>,
     *   plannerEditableDays:array<int,string>,
     *   plannerDayMeta:array<string,array{is_weekend:bool,holiday_name:?string,is_holiday:bool}>,
     *   plannerBookedWindows:array<int,array<string,string>>,
     *   plannerSlots:array<int,string>,
     *   plannerInitialWindows:array<int,array{start_at:string,end_at:string}>
     * }
     */
    public function loadPlannerData(int $plannerWeekOffset, int $plannerDayRange = 7): array
    {
        $plannerDayRange = max(1, $plannerDayRange);
        $plannerDays = [];
        $plannerDayMeta = [];
        $plannerSlots = [];
        $plannerInitialWindows = [];
        $plannerBookedWindows = [];
        $plannerStartDate = (new DateTimeImmutable('monday this week'))->modify(sprintf('%+d weeks', $plannerWeekOffset));
        $plannerEndDate = $plannerStartDate->modify('+' . ($plannerDayRange - 1) . ' days');
        $plannerWeekLabel = FormatHelper::formatCzechDate($plannerStartDate->format('Y-m-d'))
            . ' - '
            . FormatHelper::formatCzechDate($plannerEndDate->format('Y-m-d'));
        $plannerToday = (new DateTimeImmutable('today'))->format('Y-m-d');

        for ($i = 0; $i < $plannerDayRange; $i++) {
            $plannerDays[] = $plannerStartDate->modify('+' . $i . ' days')->format('Y-m-d');
        }

        $plannerEditableDays = array_values(array_filter(
            $plannerDays,
            static fn (string $plannerDay): bool => $plannerDay >= $plannerToday
        ));

        foreach ($plannerDays as $plannerDay) {
            $holidayName = DateHelper::getCzechHolidayName($plannerDay);
            $plannerDayMeta[$plannerDay] = [
                'is_weekend' => DateHelper::isWeekendDate($plannerDay),
                'holiday_name' => $holidayName,
                'is_holiday' => $holidayName !== null,
            ];
        }

        $bookedStartAt = $plannerStartDate->format('Y-m-d 00:00:00');
        $bookedEndAt = $plannerEndDate->modify('+1 day')->format('Y-m-d 00:00:00');
        $bookedReservationStatement = $this->connection->prepare(
            'SELECT r.datum_cas, r.jmeno, s.nazev, s.doba_trvani
             FROM rezervace r
             INNER JOIN sluzby s ON s.id = r.sluzba
             WHERE r.datum_cas >= ?
               AND r.datum_cas < ?
               AND r.stav IN ("nova", "potvrzena", "dokoncena")
             ORDER BY r.datum_cas ASC'
        );

        if ($bookedReservationStatement) {
            $bookedReservationStatement->bind_param('ss', $bookedStartAt, $bookedEndAt);
            $bookedReservationStatement->execute();
            $bookedReservationStatement->bind_result($bookedDateTime, $bookedClientName, $bookedServiceName, $bookedDurationMinutes);

            while ($bookedReservationStatement->fetch()) {
                $startAt = (string) ($bookedDateTime ?? '');
                if ($startAt === '') {
                    continue;
                }

                $durationMinutes = max(15, (int) $bookedDurationMinutes);
                $start = new DateTimeImmutable($startAt);
                $end = $start->modify('+' . $durationMinutes . ' minutes');

                $plannerBookedWindows[] = [
                    'start_at' => $start->format('Y-m-d H:i:s'),
                    'end_at' => $end->format('Y-m-d H:i:s'),
                    'service_name' => (string) ($bookedServiceName ?? ''),
                    'client_name' => (string) ($bookedClientName ?? ''),
                ];
            }

            $bookedReservationStatement->close();
        }

        for ($minutes = 8 * 60; $minutes < 20 * 60; $minutes += 30) {
            $plannerSlots[] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        foreach ($this->loadAvailabilityRows() as $row) {
            $date = substr((string) ($row['start_at'] ?? ''), 0, 10);
            if (in_array($date, $plannerDays, true)) {
                $plannerInitialWindows[] = [
                    'start_at' => (string) ($row['start_at'] ?? ''),
                    'end_at' => (string) ($row['end_at'] ?? ''),
                ];
            }
        }

        return [
            'plannerWeekLabel' => $plannerWeekLabel,
            'plannerDays' => $plannerDays,
            'plannerEditableDays' => $plannerEditableDays,
            'plannerDayMeta' => $plannerDayMeta,
            'plannerBookedWindows' => $plannerBookedWindows,
            'plannerSlots' => $plannerSlots,
            'plannerInitialWindows' => $plannerInitialWindows,
        ];
    }
}
