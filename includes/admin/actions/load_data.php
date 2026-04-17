<?php
declare(strict_types=1);

$adminRoot = dirname(__DIR__, 3);
$__ppstudioAdminViewStateFactory = new \PPStudio\Http\Controller\Admin\AdminViewStateFactory();
$__ppstudioAdminDataLoader = new \PPStudio\Http\Controller\Admin\AdminDataLoader(
    $adminRoot,
    new \PPStudio\Service\MailerIntegrationService(is_array($emailConfig ?? null) ? $emailConfig : []),
    $__ppstudioAdminViewStateFactory,
    is_array($emailConfig ?? null) ? $emailConfig : []
);
$__ppstudioLegacyLoadDataStateFactory = new \PPStudio\Http\Controller\Admin\AdminLegacyLoadDataStateFactory(
    $__ppstudioAdminViewStateFactory
);

$adminPageState = $__ppstudioAdminDataLoader->loadPageState(
    $__ppstudioLegacyLoadDataStateFactory->fromLegacyScope([
        'message' => $message ?? null,
        'error' => $error ?? null,
        'siteSettings' => $siteSettings ?? null,
        'serviceFilters' => $serviceFilters ?? null,
        'serviceStatusFilterOptions' => $serviceStatusFilterOptions ?? null,
        'serviceRows' => $serviceRows ?? null,
        'servicePriceHistoryRows' => $servicePriceHistoryRows ?? null,
        'categoryForm' => $categoryForm ?? null,
        'reservationFilters' => $reservationFilters ?? null,
        'reservationStatusFilterOptions' => $reservationStatusFilterOptions ?? null,
        'reservationPeriodFilterOptions' => $reservationPeriodFilterOptions ?? null,
        'reservationPerPageOptions' => $reservationPerPageOptions ?? null,
        'plannerWeekOffset' => $plannerWeekOffset ?? null,
        'plannerDayRange' => $plannerDayRange ?? null,
        'manualReservationForm' => $manualReservationForm ?? null,
        'mediaFeedback' => $mediaFeedback ?? null,
        'mediaFeedbackType' => $mediaFeedbackType ?? null,
    ]),
    $connection
);

$__ppstudioAdminState = $adminPageState->toArray();

unset(
    $__ppstudioAdminDataLoader,
    $__ppstudioAdminState,
    $__ppstudioAdminViewStateFactory,
    $__ppstudioLegacyLoadDataStateFactory,
    $adminRoot
);
