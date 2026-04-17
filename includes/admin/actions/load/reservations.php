<?php

use PPStudio\Http\Controller\Admin\AdminReservationDataLoader;
use PPStudio\Service\AdminReservationService;

$reservationData = (new AdminReservationDataLoader(
    new AdminReservationService($connection)
))->load(
    $reservationFilters,
    $reservationStatusFilterOptions,
    $reservationPeriodFilterOptions,
    $reservationPerPageOptions
);

$reservationFilters = $reservationData['reservation_filters'];
$reservationPagination = $reservationData['reservation_pagination'];
$reservationRows = $reservationData['reservation_rows'];
