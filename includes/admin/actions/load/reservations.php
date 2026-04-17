<?php

use PPStudio\Http\Controller\Admin\AdminReservationDataLoader;
use PPStudio\Service\AdminReservationModule;

$reservationData = (new AdminReservationDataLoader(
    (new AdminReservationModule($connection))->adminReservationService()
))->load(
    $reservationFilters,
    $reservationStatusFilterOptions,
    $reservationPeriodFilterOptions,
    $reservationPerPageOptions
);

$reservationFilters = $reservationData['reservation_filters'];
$reservationPagination = $reservationData['reservation_pagination'];
$reservationRows = $reservationData['reservation_rows'];
