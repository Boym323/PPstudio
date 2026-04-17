<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

final class ReservationActionPageRenderer
{
    /**
     * @param array<string, mixed> $state
     */
    public function renderAdminAction(array $state): never
    {
        $message = (string) ($state['message'] ?? 'Požadavek se nepodařilo zpracovat.');
        $siteName = \defaultSiteName();

        $this->renderTemplate('reservation-action-admin', [
            'message' => $message,
            'site_name' => $siteName,
        ]);
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $request
     */
    public function renderCustomerCancel(array $state, array $request): never
    {
        $this->renderTemplate('reservation-action-cancel', [
            'page_title' => \defaultSiteName() . ' | Zrušení rezervace',
            'reservation_id' => (int) ($request['id'] ?? 0),
            'action' => trim((string) ($request['action'] ?? '')),
            'expires_at' => (int) ($request['exp'] ?? 0),
            'nonce' => trim((string) ($request['nonce'] ?? '')),
            'signature' => trim((string) ($request['sig'] ?? '')),
            'message' => (string) ($state['message'] ?? ''),
            'message_type' => (string) ($state['message_type'] ?? 'info'),
            'show_confirm_form' => (bool) ($state['show_confirm_form'] ?? false),
        ]);
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $request
     */
    public function renderCustomerReschedule(array $state, array $request): never
    {
        $reservation = is_array($state['reservation'] ?? null) ? $state['reservation'] : null;
        $siteSettings = is_array($state['site_settings'] ?? null) ? $state['site_settings'] : [];
        $serviceName = (string) ($reservation['service_name'] ?? 'Rezervace');
        $serviceDuration = (int) ($reservation['service_duration'] ?? 0);
        $serviceLabel = $serviceName . ($serviceDuration > 0 ? ' (' . $serviceDuration . ' min)' : '');
        $currentDateTime = \formatCzechDateTime((string) ($reservation['datum_cas'] ?? ''));
        $currentDateTimeMachine = '';

        if ($reservation !== null && isset($reservation['datum_cas'])) {
            $timestamp = strtotime((string) $reservation['datum_cas']);
            if ($timestamp) {
                $currentDateTimeMachine = date('Y-m-d H:i', $timestamp);
            }
        }

        $this->renderTemplate('reservation-action-reschedule', [
            'page_title' => \defaultSiteName() . ' | Přesun termínu',
            'reservation_id' => (int) ($request['id'] ?? 0),
            'action' => trim((string) ($request['action'] ?? '')),
            'expires_at' => (int) ($request['exp'] ?? 0),
            'nonce' => trim((string) ($request['nonce'] ?? '')),
            'signature' => trim((string) ($request['sig'] ?? '')),
            'message' => (string) ($state['message'] ?? ''),
            'message_type' => (string) ($state['message_type'] ?? 'info'),
            'show_form' => (bool) ($state['show_form'] ?? false),
            'reservation' => $reservation,
            'site_settings' => $siteSettings,
            'site_name' => \setting($siteSettings, 'site_name', \defaultSiteName()),
            'service_label' => $serviceLabel,
            'current_date_time' => $currentDateTime,
            'current_date_time_machine' => $currentDateTimeMachine,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function renderJson(array $payload): never
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function renderTemplate(string $name, array $variables): never
    {
        extract($variables, EXTR_SKIP);
        require __DIR__ . '/Templates/' . $name . '.php';
        exit;
    }
}
