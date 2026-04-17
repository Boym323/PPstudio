<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use PPStudio\Security\ReservationLinkSigner;
use PPStudio\Service\ReservationActionService;
use PPStudio\Service\ReservationNotificationService;

final class ReservationActionController
{
    public function __construct(private ReservationActionService $actionService)
    {
    }

    public static function create(array $emailConfig): self
    {
        return new self(new ReservationActionService(
            $emailConfig,
            new ReservationNotificationService($emailConfig),
            new ReservationLinkSigner($emailConfig)
        ));
    }

    public function adminAction(array $query): array
    {
        return $this->withHttpCode($this->actionService->handleAdminAction($query));
    }

    public function customerCancel(array $request, array $server): array
    {
        return $this->withHttpCode($this->actionService->handleCustomerCancel(
            $request,
            $this->isPost($server)
        ));
    }

    public function customerReschedule(array $request, array $post, array $server): array
    {
        $state = $this->withHttpCode($this->actionService->handleCustomerReschedule(
            $request,
            $post,
            $this->isPost($server)
        ));
        $state['is_ajax_request'] = $this->isAjaxRequest($server);

        return $state;
    }

    public function rescheduleJsonPayload(array $state): array
    {
        $reservation = is_array($state['reservation'] ?? null) ? $state['reservation'] : null;
        $serviceName = (string) ($reservation['service_name'] ?? 'Rezervace');
        $serviceDuration = (int) ($reservation['service_duration'] ?? 0);
        $serviceLabel = $serviceName . ($serviceDuration > 0 ? ' (' . $serviceDuration . ' min)' : '');
        $currentDateTime = $reservation !== null ? \PPStudio\Support\FormatHelper::formatCzechDateTime((string) ($reservation['datum_cas'] ?? '')) : '';

        return [
            'success' => ($state['message_type'] ?? '') === 'success',
            'status' => (string) ($state['message_type'] ?? 'info'),
            'message' => (string) ($state['message'] ?? ''),
            'show_form' => (bool) ($state['show_form'] ?? false),
            'reservation' => [
                'service' => $serviceLabel,
                'slot' => $currentDateTime !== '' ? $currentDateTime : '—',
            ],
        ];
    }

    private function withHttpCode(array $state): array
    {
        $httpCode = (int) ($state['http_code'] ?? 200);
        if ($httpCode !== 200) {
            http_response_code($httpCode);
        }

        return $state;
    }

    private function isPost(array $server): bool
    {
        return strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    private function isAjaxRequest(array $server): bool
    {
        return $this->isPost($server)
            && (
                strtolower((string) ($server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch'
                || stripos((string) ($server['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
            );
    }
}
