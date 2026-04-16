<?php

use PPStudio\Service\AdminReservationService;

$reservationData = (new AdminReservationService($connection))->loadReservations(
    $reservationFilters,
    $reservationStatusFilterOptions,
    $reservationPeriodFilterOptions,
    $reservationPerPageOptions
);

$reservationFilters = $reservationData['filters'];
$reservationPagination = $reservationData['pagination'];
$reservationRows = $reservationData['rows'];
