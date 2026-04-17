<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Service\AdminReservationService;

final class AdminReservationDataLoader
{
    public function __construct(private AdminReservationService $reservationService)
    {
    }

    /**
     * @param array{q:string,status:string,period:string,per_page:int,page:int} $filters
     * @param array<string,string> $statusFilterOptions
     * @param array<string,string> $periodFilterOptions
     * @param int[] $perPageOptions
     * @return array{
     *     reservation_filters: array{q:string,status:string,period:string,per_page:int,page:int},
     *     reservation_pagination: array{total:int,total_pages:int},
     *     reservation_rows: array<int, array<string, mixed>>
     * }
     */
    public function load(
        array $filters,
        array $statusFilterOptions,
        array $periodFilterOptions,
        array $perPageOptions
    ): array {
        $reservationData = $this->reservationService->loadReservations(
            $filters,
            $statusFilterOptions,
            $periodFilterOptions,
            $perPageOptions
        );

        return [
            'reservation_filters' => $reservationData['filters'],
            'reservation_pagination' => $reservationData['pagination'],
            'reservation_rows' => $reservationData['rows'],
        ];
    }
}
