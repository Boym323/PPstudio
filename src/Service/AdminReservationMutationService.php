<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use PPStudio\Repository\AvailabilityRepository;
use PPStudio\Repository\ReservationRepository;
use PPStudio\Repository\ServiceRepository;

final class AdminReservationMutationService
{
    public function __construct(
        private mysqli $connection,
        private array $emailConfig,
        private array $siteSettings,
        private ReservationRepository $reservationRepository,
        private ReservationService $reservationService,
        private ReservationNotificationService $notificationService
    ) {
    }

    public static function create(mysqli $connection, array $emailConfig, array $siteSettings): self
    {
        $reservationRepository = new ReservationRepository($connection);
        $availabilityRepository = new AvailabilityRepository($connection);
        $serviceRepository = new ServiceRepository($connection);
        $availabilityService = new AvailabilityService($serviceRepository, $availabilityRepository, $reservationRepository);
        $reservationService = new ReservationService(
            $connection,
            $serviceRepository,
            $availabilityRepository,
            $reservationRepository,
            $availabilityService
        );

        return new self(
            $connection,
            $emailConfig,
            $siteSettings,
            $reservationRepository,
            $reservationService,
            new ReservationNotificationService($emailConfig)
        );
    }

    public function updateReservation(array $post, array $session): array
    {
        $reservationId = (int) ($post['reservation_id'] ?? 0);
        $status = trim((string) ($post['stav'] ?? 'nova'));
        $adminNote = trim((string) ($post['poznamka_admina'] ?? ''));
        $cancelReason = trim((string) ($post['duvod_zruseni'] ?? ''));
        $dateTimeRaw = trim((string) ($post['datum_cas'] ?? ''));
        $allowedStatuses = \reservationStatusOptions();

        if ($reservationId <= 0) {
            return $this->error('Neplatné ID rezervace.', 422);
        }

        if (! array_key_exists($status, $allowedStatuses)) {
            return $this->error('Neplatný stav rezervace.', 422);
        }

        $reservationBeforeUpdate = $this->reservationRepository->findDetailsById($reservationId);
        if ($reservationBeforeUpdate === null) {
            return $this->error('Rezervace nebyla nalezena.', 404);
        }

        $previousStatus = (string) ($reservationBeforeUpdate['stav'] ?? '');
        $previousDateTime = (string) ($reservationBeforeUpdate['datum_cas'] ?? '');
        $dateTimeForSave = $this->normalizeDateTimeInput($dateTimeRaw);
        $dateTimeChanged = $dateTimeForSave !== '' && $dateTimeForSave !== $previousDateTime;

        if ($dateTimeForSave === '') {
            return $this->error('Vyplňte prosím termín rezervace.', 422);
        }

        if ($dateTimeChanged && in_array($previousStatus, ['zrusena', 'dokoncena'], true)) {
            return $this->error('Zrušenou nebo dokončenou rezervaci nelze přesunout.', 422);
        }

        if ($status === 'zrusena' && $previousStatus !== 'zrusena' && $cancelReason === '') {
            return $this->error('Při zrušení rezervace vyplňte důvod zrušení.', 422);
        }

        if ($dateTimeChanged) {
            $rescheduleResult = $this->reservationService->rescheduleReservationWithLock($reservationId, $dateTimeForSave);
            if (($rescheduleResult['status'] ?? 'error') === 'slot_unavailable') {
                return $this->error('Vybraný termín už není volný nebo neodpovídá dostupnosti.', 422);
            }
            if (($rescheduleResult['status'] ?? 'error') !== 'ok') {
                return $this->error('Rezervaci se nepodařilo přesunout.', 500);
            }

            $dateTimeForSave = (string) ($rescheduleResult['date_time'] ?? $dateTimeForSave);
        }

        $cancelMeta = $this->resolveCancelledBy($session);
        $statement = $this->prepareReservationUpdateStatement($status, $previousStatus);
        if (! $statement) {
            return $this->error('Rezervaci se nepodařilo upravit.', 500);
        }

        if ($status === 'zrusena') {
            $statement->bind_param(
                'ssssssi',
                $dateTimeForSave,
                $status,
                $adminNote,
                $cancelReason,
                $cancelMeta['cancelled_by'],
                $cancelMeta['cancelled_by_user'],
                $reservationId
            );
        } else {
            $statement->bind_param('sssi', $dateTimeForSave, $status, $adminNote, $reservationId);
        }

        $ok = $statement->execute();
        $statement->close();

        if (! $ok) {
            return $this->error('Rezervaci se nepodařilo upravit.', 500);
        }

        $reservationAfterUpdate = $this->reservationRepository->findDetailsById($reservationId);
        $responseDateTime = $dateTimeForSave;

        if ($reservationAfterUpdate !== null) {
            $newStatus = (string) ($reservationAfterUpdate['stav'] ?? '');
            $newDateTime = (string) ($reservationAfterUpdate['datum_cas'] ?? '');
            $responseDateTime = $newDateTime !== '' ? $newDateTime : $dateTimeForSave;

            if ($previousStatus !== 'potvrzena' && $newStatus === 'potvrzena') {
                $this->notificationService->sendConfirmedEmail($this->siteSettings, $reservationAfterUpdate);
            }

            if ($newStatus !== 'zrusena' && $newDateTime !== '' && $previousDateTime !== '' && $newDateTime !== $previousDateTime) {
                $this->notificationService->sendConfirmedEmail($this->siteSettings, $reservationAfterUpdate, [
                    'previous_datetime' => $previousDateTime,
                ]);
                \securityEventLog('reservation_admin_rescheduled', 'admin_reservation', 'info', [
                    'reservation_id' => $reservationId,
                    'old_datetime' => $previousDateTime,
                    'new_datetime' => $newDateTime,
                ]);
            }

            if ($previousStatus !== 'zrusena' && $newStatus === 'zrusena') {
                $this->notificationService->sendCancelledEmail($this->siteSettings, $reservationAfterUpdate);
                \securityEventLog('reservation_admin_cancelled', 'admin_reservation', 'warning', [
                    'reservation_id' => $reservationId,
                    'cancelled_by' => $cancelMeta['cancelled_by'],
                    'cancelled_by_user' => $cancelMeta['cancelled_by_user'],
                    'cancel_reason' => $cancelReason,
                ]);
            }
        }

        return $this->success('Rezervace byla upravena.', [
            'reservation_id' => $reservationId,
            'status_key' => $status,
            'status_label' => \reservationStatusLabel($status),
            'admin_note' => $adminNote,
            'cancel_reason' => $cancelReason,
            'datetime_label' => \formatCzechDateTime($responseDateTime),
            'datetime_local' => str_replace(' ', 'T', substr($responseDateTime, 0, 16)),
        ]);
    }

    public function createManualReservation(array $post): array
    {
        $form = [
            'jmeno' => trim((string) ($post['jmeno'] ?? '')),
            'email' => trim((string) ($post['email'] ?? '')),
            'telefon' => trim((string) ($post['telefon'] ?? '')),
            'zdroj' => trim((string) ($post['zdroj'] ?? 'telefon')),
            'sluzba_id' => trim((string) ($post['sluzba_id'] ?? '')),
            'datum_cas' => trim((string) ($post['datum_cas'] ?? '')),
            'poznamka_klienta' => trim((string) ($post['poznamka_klienta'] ?? '')),
        ];

        $serviceId = (int) $form['sluzba_id'];
        $dateTimeForSave = $this->normalizeDateTimeInput($form['datum_cas']);

        if ($form['jmeno'] === '' || $serviceId <= 0 || $dateTimeForSave === '') {
            return $this->error('Pro ruční rezervaci vyplňte jméno, službu a termín.', 422, [
                'manual_reservation_form' => $form,
            ]);
        }

        if ($form['email'] !== '' && ! filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->error('Zadaný e-mail není platný.', 422, [
                'manual_reservation_form' => $form,
            ]);
        }

        $reservationInsert = $this->reservationService->createReservationWithLock(
            $form['jmeno'],
            $form['email'],
            $form['telefon'],
            $form['zdroj'],
            $form['poznamka_klienta'],
            $serviceId,
            $dateTimeForSave,
            'potvrzena'
        );

        if (($reservationInsert['status'] ?? 'error') === 'ok') {
            $service = is_array($reservationInsert['service'] ?? null) ? $reservationInsert['service'] : [];
            $servicePrice = $reservationInsert['service_price'] ?? null;
            $reservation = [
                'id' => (int) ($reservationInsert['reservation_id'] ?? 0),
                'jmeno' => $form['jmeno'],
                'email' => $form['email'],
                'telefon' => $form['telefon'],
                'zdroj' => $form['zdroj'],
                'poznamka_klienta' => $form['poznamka_klienta'],
                'datum_cas' => (string) ($reservationInsert['date_time'] ?? $dateTimeForSave),
                'service_name' => (string) ($service['nazev'] ?? 'Vybraná procedura'),
                'service_price' => $servicePrice,
                'service_duration' => (int) ($service['doba_trvani'] ?? 60),
            ];

            if ($form['email'] !== '') {
                $this->notificationService->sendConfirmedEmail($this->siteSettings, $reservation);
            }

            return $this->success('Ruční rezervace byla vložena.', [
                'reservation_id' => (int) ($reservationInsert['reservation_id'] ?? 0),
                'manual_reservation_form' => $this->emptyManualReservationForm(),
            ]);
        }

        if (in_array($reservationInsert['status'] ?? 'error', ['slot_unavailable', 'service_unavailable'], true)) {
            return $this->error('Vybraný termín už není volný nebo neodpovídá dostupnosti.', 422, [
                'manual_reservation_form' => $form,
            ]);
        }

        return $this->error('Ruční rezervaci se nepodařilo uložit.', 500, [
            'manual_reservation_form' => $form,
        ]);
    }

    public function deleteReservation(array $post): array
    {
        $reservationId = (int) ($post['reservation_id'] ?? 0);
        if ($reservationId <= 0) {
            return $this->error('Neplatné ID rezervace.', 422);
        }

        $statement = $this->connection->prepare('DELETE FROM rezervace WHERE id = ? LIMIT 1');
        if (! $statement) {
            return $this->error('Rezervaci se nepodařilo smazat.', 500);
        }

        $statement->bind_param('i', $reservationId);
        $ok = $statement->execute();
        $statement->close();

        if (! $ok) {
            return $this->error('Rezervaci se nepodařilo smazat.', 500);
        }

        return $this->success('Rezervace byla smazána.', [
            'reservation_id' => $reservationId,
            'deleted' => true,
        ]);
    }

    private function prepareReservationUpdateStatement(string $status, string $previousStatus): ?\mysqli_stmt
    {
        if ($status === 'zrusena') {
            if ($previousStatus === 'zrusena') {
                return $this->connection->prepare(
                    'UPDATE rezervace
                     SET datum_cas = ?, stav = ?, poznamka_admina = ?, duvod_zruseni = ?, zruseno_kym = ?, zruseno_uzivatel = COALESCE(zruseno_uzivatel, ?), zruseno_at = COALESCE(zruseno_at, NOW())
                     WHERE id = ?'
                );
            }

            return $this->connection->prepare(
                'UPDATE rezervace
                 SET datum_cas = ?, stav = ?, poznamka_admina = ?, duvod_zruseni = ?, zruseno_kym = ?, zruseno_uzivatel = ?, zruseno_at = NOW()
                 WHERE id = ?'
            );
        }

        return $this->connection->prepare(
            'UPDATE rezervace
             SET datum_cas = ?, stav = ?, poznamka_admina = ?
             WHERE id = ?'
        );
    }

    /**
     * @return array{cancelled_by:string,cancelled_by_user:string}
     */
    private function resolveCancelledBy(array $session): array
    {
        $isAdmin = (bool) ($session['ppstudio_admin_authenticated'] ?? false);

        return [
            'cancelled_by' => $isAdmin ? 'admin_full' : 'admin_lite',
            'cancelled_by_user' => $isAdmin
                ? trim((string) ($session['ppstudio_admin_username'] ?? 'admin'))
                : trim((string) ($session['ppstudio_admin_lite_username'] ?? 'staff')),
        ];
    }

    private function normalizeDateTimeInput(string $value): string
    {
        $normalized = trim(str_replace('T', ' ', $value));
        if ($normalized !== '' && strlen($normalized) === 16) {
            $normalized .= ':00';
        }

        return $normalized;
    }

    private function emptyManualReservationForm(): array
    {
        return [
            'jmeno' => '',
            'email' => '',
            'telefon' => '',
            'zdroj' => 'telefon',
            'sluzba_id' => '',
            'datum_cas' => '',
            'poznamka_klienta' => '',
        ];
    }

    private function success(string $message, array $data = []): array
    {
        return [
            'success' => true,
            'message' => $message,
            'error' => null,
            'http_code' => 200,
            'data' => $data,
        ];
    }

    private function error(string $message, int $httpCode, array $data = []): array
    {
        return [
            'success' => false,
            'message' => null,
            'error' => $message,
            'http_code' => $httpCode,
            'data' => $data,
        ];
    }
}
