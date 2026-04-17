<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use PPStudio\Database\DatabaseFactory;
use PPStudio\Repository\AvailabilityRepository;
use PPStudio\Repository\ReservationRepository;
use PPStudio\Repository\SiteSettingsRepository;
use PPStudio\Repository\ServiceRepository;
use PPStudio\Security\ReservationLinkSigner;

final class ReservationActionService
{
    public function __construct(
        private array $emailConfig,
        private ReservationNotificationService $notificationService,
        private ReservationLinkSigner $linkSigner
    ) {
    }

    public function handleAdminAction(array $query): array
    {
        $link = $this->linkInput($query);
        $message = 'Požadavek se nepodařilo zpracovat.';

        if (
            ! in_array($link['action'], ['confirm', 'cancel'], true)
            || ! $this->linkSigner->isValidActionSignature($link['id'], $link['action'], $link['exp'], $link['nonce'], $link['sig'])
            || ! $this->linkSigner->consumeNonce($link['id'], $link['action'], $link['exp'], $link['nonce'])
        ) {
            (new \PPStudio\Security\SecurityFacade())->securityEventLogger()->log('reservation_action_invalid_link', 'reservation_action', 'warning', [
                'reservation_id' => $link['id'],
                'action' => $link['action'],
                'expires_at' => $link['exp'],
            ]);

            return $this->result('Odkaz je neplatný nebo expiroval.', 'error', 403);
        }

        $context = $this->reservationContext($link['id']);
        if (! $context['ok']) {
            return $context['result'];
        }

        $connection = $context['connection'];
        $repository = $context['repository'];
        $reservationBefore = $context['reservation'];
        $siteSettings = $context['site_settings'];
        $newStatus = $link['action'] === 'confirm' ? 'potvrzena' : 'zrusena';

        if ($repository->updateStatus($link['id'], $newStatus)) {
            $reservationAfter = $repository->findDetailsById($link['id']);
            if ($reservationAfter !== null) {
                if ($link['action'] === 'confirm' && (string) ($reservationBefore['stav'] ?? '') !== 'potvrzena') {
                    $this->notificationService->sendConfirmedEmail($siteSettings, $reservationAfter);
                    $message = 'Rezervace byla potvrzena a klientce odešel potvrzovací e-mail.';
                    (new \PPStudio\Security\SecurityFacade())->securityEventLogger()->log('reservation_action_confirmed', 'reservation_action', 'info', [
                        'reservation_id' => $link['id'],
                    ]);
                } elseif ($link['action'] === 'cancel' && (string) ($reservationBefore['stav'] ?? '') !== 'zrusena') {
                    $this->notificationService->sendCancelledEmail($siteSettings, $reservationAfter);
                    $message = 'Rezervace byla zrušena a klientce odešlo oznámení.';
                    (new \PPStudio\Security\SecurityFacade())->securityEventLogger()->log('reservation_action_cancelled', 'reservation_action', 'warning', [
                        'reservation_id' => $link['id'],
                    ]);
                } else {
                    $message = 'Rezervace už byla v tomto stavu.';
                }
            }
        }

        $connection->close();

        return $this->result($message);
    }

    public function handleCustomerCancel(array $request, bool $isPost): array
    {
        $link = $this->linkInput($request);

        if ($link['action'] !== 'cancel' || ! $this->linkSigner->isValidActionSignature($link['id'], $link['action'], $link['exp'], $link['nonce'], $link['sig'])) {
            (new \PPStudio\Security\SecurityFacade())->securityEventLogger()->log('reservation_customer_cancel_invalid_link', 'reservation_cancel', 'warning', [
                'reservation_id' => $link['id'],
            ]);

            return $this->result('Odkaz je neplatný nebo expiroval.', 'error', 403, ['show_confirm_form' => false]);
        }

        $context = $this->reservationContext($link['id']);
        if (! $context['ok']) {
            return $context['result'] + ['show_confirm_form' => false];
        }

        $connection = $context['connection'];
        $repository = $context['repository'];
        $reservation = $context['reservation'];
        $statusBefore = (string) ($reservation['stav'] ?? '');

        if (! $this->linkSigner->canUseCustomerAction($reservation)) {
            (new \PPStudio\Security\SecurityFacade())->securityEventLogger()->log('reservation_customer_cancel_cutoff_reached', 'reservation_cancel', 'warning', [
                'reservation_id' => $link['id'],
                'reservation_datetime' => (string) ($reservation['datum_cas'] ?? ''),
            ]);
            $connection->close();

            return $this->result('Rezervaci lze zrušit nejpozději 24 hodin před začátkem procedury.', 'error', 403, [
                'show_confirm_form' => false,
            ]);
        }

        if (! $isPost) {
            $connection->close();

            return $this->result(
                $statusBefore === 'zrusena' ? 'Tato rezervace už je zrušena.' : 'Opravdu chcete zrušit tuto rezervaci?',
                'info',
                200,
                ['show_confirm_form' => $statusBefore !== 'zrusena']
            );
        }

        if (! $this->linkSigner->consumeNonce($link['id'], 'cancel', $link['exp'], $link['nonce'])) {
            $connection->close();

            return $this->result('Odkaz už byl použit nebo expiroval.', 'error', 403, ['show_confirm_form' => false]);
        }

        if ($statusBefore === 'zrusena') {
            $connection->close();

            return $this->result('Rezervace už byla dříve zrušena.', 'info', 200, ['show_confirm_form' => false]);
        }

        if ($statusBefore === 'dokoncena') {
            $connection->close();

            return $this->result('Rezervace je již dokončená a nelze ji zrušit tímto odkazem.', 'error', 200, [
                'show_confirm_form' => false,
            ]);
        }

        if (! $repository->cancelByCustomerLink($link['id'])) {
            $connection->close();

            return $this->result('Rezervaci se nepodařilo zrušit.', 'error', 500, ['show_confirm_form' => false]);
        }

        $reservationAfter = $repository->findDetailsById($link['id']);
        if ($reservationAfter !== null) {
            $this->notificationService->sendCancelledEmail($context['site_settings'], $reservationAfter);
        }

        (new \PPStudio\Security\SecurityFacade())->securityEventLogger()->log('reservation_customer_cancelled', 'reservation_cancel', 'warning', [
            'reservation_id' => $link['id'],
        ]);
        $connection->close();

        return $this->result('Rezervace byla úspěšně zrušena. Potvrzení jsme poslali i na váš e-mail.', 'success', 200, [
            'show_confirm_form' => false,
        ]);
    }

    public function handleCustomerReschedule(array $request, array $post, bool $isPost): array
    {
        $link = $this->linkInput($request);

        if ($link['action'] !== 'reschedule' || ! $this->linkSigner->isValidActionSignature($link['id'], $link['action'], $link['exp'], $link['nonce'], $link['sig'])) {
            (new \PPStudio\Security\SecurityFacade())->securityEventLogger()->log('reservation_customer_reschedule_invalid_link', 'reservation_reschedule', 'warning', [
                'reservation_id' => $link['id'],
            ]);

            return $this->result('Odkaz je neplatný nebo expiroval.', 'error', 403, [
                'show_form' => false,
                'reservation' => null,
                'site_settings' => [],
            ]);
        }

        $context = $this->reservationContext($link['id']);
        if (! $context['ok']) {
            return $context['result'] + [
                'show_form' => false,
                'reservation' => null,
                'site_settings' => [],
            ];
        }

        $connection = $context['connection'];
        $repository = $context['repository'];
        $reservation = $context['reservation'];
        $siteSettings = $context['site_settings'];
        $statusBefore = (string) ($reservation['stav'] ?? '');

        if (! $this->linkSigner->canUseCustomerAction($reservation)) {
            (new \PPStudio\Security\SecurityFacade())->securityEventLogger()->log('reservation_customer_reschedule_cutoff_reached', 'reservation_reschedule', 'warning', [
                'reservation_id' => $link['id'],
                'reservation_datetime' => (string) ($reservation['datum_cas'] ?? ''),
            ]);
            $connection->close();

            return $this->rescheduleResult('Termín lze přesunout nejpozději 24 hodin před začátkem procedury.', 'error', false, $reservation, $siteSettings, 403);
        }

        if (! $isPost) {
            $connection->close();

            if ($statusBefore === 'zrusena') {
                return $this->rescheduleResult('Tato rezervace už je zrušena.', 'info', false, $reservation, $siteSettings);
            }

            if ($statusBefore === 'dokoncena') {
                return $this->rescheduleResult('Tato rezervace je již dokončená a nelze ji přesouvat.', 'error', false, $reservation, $siteSettings);
            }

            return $this->rescheduleResult('Vyberte nový termín rezervace.', 'info', true, $reservation, $siteSettings);
        }

        if ($statusBefore === 'zrusena' || $statusBefore === 'dokoncena') {
            $connection->close();

            return $this->rescheduleResult('Tuto rezervaci už nelze přesunout.', 'error', false, $reservation, $siteSettings);
        }

        $newDate = trim((string) ($post['rezervacni_datum'] ?? ''));
        $newTime = trim((string) ($post['rezervacni_cas'] ?? ''));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate) || ! preg_match('/^\d{2}:\d{2}$/', $newTime)) {
            $connection->close();

            return $this->rescheduleResult('Vyberte prosím platný den a čas.', 'error', true, $reservation, $siteSettings);
        }

        $newDateTime = $newDate . ' ' . $newTime . ':00';
        $oldDateTime = (string) ($reservation['datum_cas'] ?? '');
        $newTimestamp = strtotime($newDateTime);
        $oldTimestamp = strtotime($oldDateTime);

        if (! $newTimestamp || ! $oldTimestamp) {
            $connection->close();

            return $this->rescheduleResult('Termín se nepodařilo zpracovat.', 'error', true, $reservation, $siteSettings);
        }

        if (date('Y-m-d H:i', $newTimestamp) === date('Y-m-d H:i', $oldTimestamp)) {
            $connection->close();

            return $this->rescheduleResult('Zvolte prosím jiný termín než původní.', 'error', true, $reservation, $siteSettings);
        }

        if (! $this->linkSigner->consumeNonce($link['id'], 'reschedule', $link['exp'], $link['nonce'])) {
            $connection->close();

            return $this->rescheduleResult('Odkaz už byl použit nebo expiroval.', 'error', false, $reservation, $siteSettings, 403);
        }

        $rescheduleResult = $this->reservationService($connection)->rescheduleReservationWithLock($link['id'], $newDateTime);
        if (($rescheduleResult['status'] ?? 'error') === 'slot_unavailable') {
            $connection->close();

            return $this->rescheduleResult('Zvolený termín už není dostupný. Vyberte prosím jiný.', 'error', true, $reservation, $siteSettings);
        }

        if (($rescheduleResult['status'] ?? 'error') !== 'ok') {
            $connection->close();

            return $this->rescheduleResult('Termín se nepodařilo změnit.', 'error', true, $reservation, $siteSettings, 500);
        }

        $reservationAfter = $repository->findDetailsById($link['id']);
        if ($reservationAfter !== null) {
            $this->notificationService->sendConfirmedEmail($siteSettings, $reservationAfter, [
                'previous_datetime' => $oldDateTime,
            ]);
            $reservation = $reservationAfter;
        }

        (new \PPStudio\Security\SecurityFacade())->securityEventLogger()->log('reservation_customer_rescheduled', 'reservation_reschedule', 'info', [
            'reservation_id' => $link['id'],
            'old_datetime' => $oldDateTime,
            'new_datetime' => $newDateTime,
        ]);
        $connection->close();

        return $this->rescheduleResult('Termín byl úspěšně změněn. Potvrzení jsme poslali i na váš e-mail.', 'success', false, $reservation, $siteSettings);
    }

    private function reservationContext(int $reservationId): array
    {
        $connection = DatabaseFactory::tryConnect();

        if (! $connection instanceof mysqli) {
            return [
                'ok' => false,
                'result' => $this->result('Databáze není dostupná.', 'error', 500),
            ];
        }

        $repository = new ReservationRepository($connection);
        $siteSettings = new SiteSettingsService(new SiteSettingsRepository($connection), \defaultSiteSettings())->load();
        $reservation = $repository->findDetailsById($reservationId);

        if ($reservation === null) {
            $connection->close();

            return [
                'ok' => false,
                'result' => $this->result('Rezervace nebyla nalezena.', 'error', 404),
            ];
        }

        return [
            'ok' => true,
            'connection' => $connection,
            'repository' => $repository,
            'site_settings' => $siteSettings,
            'reservation' => $reservation,
        ];
    }

    private function reservationService(mysqli $connection): ReservationService
    {
        $serviceRepository = new ServiceRepository($connection);
        $availabilityRepository = new AvailabilityRepository($connection);
        $reservationRepository = new ReservationRepository($connection);

        return new ReservationService(
            $connection,
            $serviceRepository,
            $availabilityRepository,
            $reservationRepository,
            new AvailabilityService($serviceRepository, $availabilityRepository, $reservationRepository)
        );
    }

    private function rescheduleResult(
        string $message,
        string $messageType,
        bool $showForm,
        ?array $reservation,
        array $siteSettings,
        int $httpCode = 200
    ): array {
        return $this->result($message, $messageType, $httpCode, [
            'show_form' => $showForm,
            'reservation' => $reservation,
            'site_settings' => $siteSettings,
        ]);
    }

    private function result(string $message, string $messageType = 'info', int $httpCode = 200, array $extra = []): array
    {
        return array_merge([
            'message' => $message,
            'message_type' => $messageType,
            'http_code' => $httpCode,
        ], $extra);
    }

    private function linkInput(array $input): array
    {
        return [
            'id' => (int) ($input['id'] ?? 0),
            'action' => trim((string) ($input['action'] ?? '')),
            'exp' => (int) ($input['exp'] ?? 0),
            'nonce' => trim((string) ($input['nonce'] ?? '')),
            'sig' => trim((string) ($input['sig'] ?? '')),
        ];
    }
}
