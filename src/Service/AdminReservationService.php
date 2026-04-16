<?php
declare(strict_types=1);

namespace PPStudio\Service;

use DateTimeImmutable;
use mysqli;
use mysqli_result;

final class AdminReservationService
{
    public function __construct(
        private mysqli $connection
    ) {
    }

    /**
     * @param array{q:string,status:string,period:string,per_page:int,page:int} $filters
     * @param array<string,string> $statusFilterOptions
     * @param array<string,string> $periodFilterOptions
     * @param int[] $perPageOptions
     * @return array{filters: array{q:string,status:string,period:string,per_page:int,page:int}, pagination: array{total:int,total_pages:int}, rows: array<int, array<string, mixed>>}
     */
    public function loadReservations(array $filters, array $statusFilterOptions, array $periodFilterOptions, array $perPageOptions): array
    {
        $filters = $this->normalizeFilters($filters, $statusFilterOptions, $periodFilterOptions, $perPageOptions);
        [$whereSql, $paramTypes, $params] = $this->buildWhereClause($filters);

        $total = $this->countReservations($whereSql, $paramTypes, $params);
        $pagination = [
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / max(1, $filters['per_page']))),
        ];

        if ($filters['page'] > $pagination['total_pages']) {
            $filters['page'] = $pagination['total_pages'];
        }

        $rows = $this->fetchReservations(
            $whereSql,
            $paramTypes,
            $params,
            $filters['per_page'],
            ($filters['page'] - 1) * $filters['per_page']
        );

        return [
            'filters' => $filters,
            'pagination' => $pagination,
            'rows' => $rows,
        ];
    }

    /**
     * @param array{q:string,status:string,period:string,per_page:int,page:int} $filters
     * @param array<string,string> $statusFilterOptions
     * @param array<string,string> $periodFilterOptions
     * @param int[] $perPageOptions
     * @return array{q:string,status:string,period:string,per_page:int,page:int}
     */
    private function normalizeFilters(array $filters, array $statusFilterOptions, array $periodFilterOptions, array $perPageOptions): array
    {
        if (! in_array($filters['status'], array_keys($statusFilterOptions), true)) {
            $filters['status'] = 'all';
        }

        if (! in_array($filters['period'], array_keys($periodFilterOptions), true)) {
            $filters['period'] = 'all';
        }

        if (! in_array($filters['per_page'], $perPageOptions, true)) {
            $filters['per_page'] = 25;
        }

        if ($filters['page'] < 1) {
            $filters['page'] = 1;
        }

        return $filters;
    }

    /**
     * @param array{q:string,status:string,period:string,per_page:int,page:int} $filters
     * @return array{0:string,1:string,2:array<int, mixed>}
     */
    private function buildWhereClause(array $filters): array
    {
        $where = ['1=1'];
        $paramTypes = '';
        $params = [];

        if ($filters['status'] !== 'all') {
            $where[] = 'r.stav = ?';
            $paramTypes .= 's';
            $params[] = $filters['status'];
        }

        if ($filters['period'] === 'today') {
            $todayBounds = AvailabilityService::sqlDayBounds(date('Y-m-d'));
            if ($todayBounds !== null) {
                $where[] = 'r.datum_cas >= ? AND r.datum_cas < ?';
                $paramTypes .= 'ss';
                $params[] = $todayBounds['start'];
                $params[] = $todayBounds['end'];
            }
        } elseif ($filters['period'] === 'week') {
            $weekStart = (new DateTimeImmutable('monday this week'))->setTime(0, 0, 0);
            $weekEnd = $weekStart->modify('+1 week');
            $where[] = 'r.datum_cas >= ? AND r.datum_cas < ?';
            $paramTypes .= 'ss';
            $params[] = $weekStart->format('Y-m-d H:i:s');
            $params[] = $weekEnd->format('Y-m-d H:i:s');
        } elseif ($filters['period'] === 'month') {
            $monthStart = (new DateTimeImmutable('first day of this month'))->setTime(0, 0, 0);
            $monthEnd = $monthStart->modify('+1 month');
            $where[] = 'r.datum_cas >= ? AND r.datum_cas < ?';
            $paramTypes .= 'ss';
            $params[] = $monthStart->format('Y-m-d H:i:s');
            $params[] = $monthEnd->format('Y-m-d H:i:s');
        }

        if ($filters['q'] !== '') {
            $needle = '%' . $filters['q'] . '%';
            $where[] = '(r.jmeno LIKE ? OR r.email LIKE ? OR r.telefon LIKE ?)';
            $paramTypes .= 'sss';
            $params[] = $needle;
            $params[] = $needle;
            $params[] = $needle;
        }

        return [implode(' AND ', $where), $paramTypes, $params];
    }

    /**
     * @param array<int, mixed> $params
     */
    private function countReservations(string $whereSql, string $paramTypes, array $params): int
    {
        $statement = $this->connection->prepare(
            "SELECT COUNT(*) AS total
             FROM rezervace r
             INNER JOIN sluzby s ON s.id = r.sluzba
             WHERE {$whereSql}"
        );

        if (! $statement) {
            return 0;
        }

        $this->bindDynamicParams($statement, $paramTypes, $params);
        $statement->execute();
        $result = $statement->get_result();
        $row = $result instanceof mysqli_result ? ($result->fetch_assoc() ?: []) : [];
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        $statement->close();

        return (int) ($row['total'] ?? 0);
    }

    /**
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchReservations(string $whereSql, string $paramTypes, array $params, int $limit, int $offset): array
    {
        $statement = $this->connection->prepare(
            "SELECT r.id, r.sluzba AS service_id, r.jmeno, r.email, r.telefon, r.zdroj, r.poznamka_klienta, r.poznamka_admina, r.datum_cas, r.stav, r.cena_v_dobe_rezervace,
                    r.duvod_zruseni, r.zruseno_kym, r.zruseno_uzivatel, r.zruseno_at, r.reminder_sent_at, s.nazev
             FROM rezervace r
             INNER JOIN sluzby s ON s.id = r.sluzba
             WHERE {$whereSql}
             ORDER BY r.datum_cas ASC
             LIMIT ?
             OFFSET ?"
        );

        if (! $statement) {
            return [];
        }

        $rowParamTypes = $paramTypes . 'ii';
        $rowParams = [...$params, $limit, $offset];
        $this->bindDynamicParams($statement, $rowParamTypes, $rowParams);
        $statement->execute();
        $result = $statement->get_result();
        $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        $statement->close();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<int, mixed> $params
     */
    private function bindDynamicParams(\mysqli_stmt $statement, string $types, array $params): void
    {
        if ($types === '' || $params === []) {
            return;
        }

        $bindParams = [$types];
        foreach ($params as $index => $value) {
            $bindParams[] = &$params[$index];
        }

        $statement->bind_param(...$bindParams);
    }
}
