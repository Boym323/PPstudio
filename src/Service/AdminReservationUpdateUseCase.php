<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use mysqli_stmt;
use PPStudio\Http\Controller\Admin\AdminSessionState;
use PPStudio\Repository\ReservationRepository;
use PPStudio\Security\SecurityFacade;

final class AdminReservationUpdateUseCase
{
    public function __construct(
        private mysqli $connection,
        private array $siteSettings,
        private ReservationRepository $reservationRepository,
        private ReservationService $reservationService,
        private ReservationNotificationService $notificationService,
        private ?SecurityFacade $securityFacade = null
    ) {
        $this->securityFacade ??= new SecurityFacade();
    }

    public function handle(array $post, array $session): array
    {
        $reservationId = (int) ($post['reservation_id'] ?? 0);
        $status = trim((string) ($post['stav'] ?? 'nova'));
        $adminNote = trim((string) ($post['poznamka_admina'] ?? ''));
        $cancelReason = trim((string) ($post['duvod_zruseni'] ?? ''));
        $dateTimeRaw = trim((string) ($post['datum_cas'] ?? ''));
        $allowedStatuses = \PPStudio\Support\ReservationStatusHelper::options();

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
        if (! $statement instanceof mysqli_stmt) {
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
                $this->securityFacade->securityEventLogger()->log('reservation_admin_rescheduled', 'admin_reservation', 'info', [
                    'reservation_id' => $reservationId,
                    'old_datetime' => $previousDateTime,
                    'new_datetime' => $newDateTime,
                ]);
            }

            if ($previousStatus !== 'zrusena' && $newStatus === 'zrusena') {
                $this->notificationService->sendCancelledEmail($this->siteSettings, $reservationAfterUpdate);
                $this->securityFacade->securityEventLogger()->log('reservation_admin_cancelled', 'admin_reservation', 'warning', [
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
            'status_label' => \PPStudio\Support\ReservationStatusHelper::label($status),
            'admin_note' => $adminNote,
            'cancel_reason' => $cancelReason,
            'datetime_label' => \PPStudio\Support\FormatHelper::formatCzechDateTime($responseDateTime),
            'datetime_local' => str_replace(' ', 'T', substr($responseDateTime, 0, 16)),
        ]);
    }

    private function prepareReservationUpdateStatement(string $status, string $previousStatus): ?mysqli_stmt
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
        return AdminSessionState::cancelledBy($session);
    }

    private function normalizeDateTimeInput(string $value): string
    {
        $normalized = trim(str_replace('T', ' ', $value));
        if ($normalized !== '' && strlen($normalized) === 16) {
            $normalized .= ':00';
        }

        return $normalized;
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
