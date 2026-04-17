<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

final class AdminLegacyLoadDataStateFactory
{
    public function __construct(
        private AdminViewStateFactory $viewStateFactory
    ) {
    }

    /**
     * Builds a deterministic admin page state for the legacy load-data wrapper.
     * Only values that the data loader truly depends on are carried over.
     *
     * @param array<string, mixed> $scope
     */
    public function fromLegacyScope(array $scope): AdminPageState
    {
        $defaultState = $this->viewStateFactory->create(
            $_GET,
            is_string($scope['error'] ?? null) ? $scope['error'] : ''
        );

        return AdminPageState::fromArray(array_replace($defaultState, [
            'message' => is_string($scope['message'] ?? null) ? $scope['message'] : (string) $defaultState['message'],
            'error' => is_string($scope['error'] ?? null) ? $scope['error'] : (string) $defaultState['error'],
            'siteSettings' => is_array($scope['siteSettings'] ?? null) ? $scope['siteSettings'] : $defaultState['siteSettings'],
            'serviceFilters' => is_array($scope['serviceFilters'] ?? null) ? $scope['serviceFilters'] : $defaultState['serviceFilters'],
            'serviceStatusFilterOptions' => is_array($scope['serviceStatusFilterOptions'] ?? null)
                ? $scope['serviceStatusFilterOptions']
                : $defaultState['serviceStatusFilterOptions'],
            'serviceRows' => is_array($scope['serviceRows'] ?? null) ? $scope['serviceRows'] : $defaultState['serviceRows'],
            'servicePriceHistoryRows' => is_array($scope['servicePriceHistoryRows'] ?? null)
                ? $scope['servicePriceHistoryRows']
                : $defaultState['servicePriceHistoryRows'],
            'categoryForm' => is_array($scope['categoryForm'] ?? null) ? $scope['categoryForm'] : $defaultState['categoryForm'],
            'reservationFilters' => is_array($scope['reservationFilters'] ?? null)
                ? $scope['reservationFilters']
                : $defaultState['reservationFilters'],
            'reservationStatusFilterOptions' => is_array($scope['reservationStatusFilterOptions'] ?? null)
                ? $scope['reservationStatusFilterOptions']
                : $defaultState['reservationStatusFilterOptions'],
            'reservationPeriodFilterOptions' => is_array($scope['reservationPeriodFilterOptions'] ?? null)
                ? $scope['reservationPeriodFilterOptions']
                : $defaultState['reservationPeriodFilterOptions'],
            'reservationPerPageOptions' => is_array($scope['reservationPerPageOptions'] ?? null)
                ? $scope['reservationPerPageOptions']
                : $defaultState['reservationPerPageOptions'],
            'plannerWeekOffset' => is_numeric($scope['plannerWeekOffset'] ?? null)
                ? (int) $scope['plannerWeekOffset']
                : (int) $defaultState['plannerWeekOffset'],
            'plannerDayRange' => is_numeric($scope['plannerDayRange'] ?? null)
                ? (int) $scope['plannerDayRange']
                : (int) $defaultState['plannerDayRange'],
            'manualReservationForm' => is_array($scope['manualReservationForm'] ?? null)
                ? $scope['manualReservationForm']
                : $defaultState['manualReservationForm'],
            'mediaFeedback' => is_string($scope['mediaFeedback'] ?? null)
                ? $scope['mediaFeedback']
                : (string) $defaultState['mediaFeedback'],
            'mediaFeedbackType' => is_string($scope['mediaFeedbackType'] ?? null)
                ? $scope['mediaFeedbackType']
                : (string) $defaultState['mediaFeedbackType'],
        ]));
    }
}
