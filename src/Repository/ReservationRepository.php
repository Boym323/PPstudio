<?php
declare(strict_types=1);

namespace PPStudio\Repository;

use mysqli;
use PPStudio\Domain\ReservationData;
use RuntimeException;

final class ReservationRepository
{
    public function __construct(
        private mysqli $connection
    ) {
    }

    public function findBookedBetween(string $start, string $end): array
    {
        return $this->fetchBookedBetween($start, $end, false, false);
    }

    public function lockBookedBetween(string $start, string $end): array
    {
        return $this->fetchBookedBetween($start, $end, true, true);
    }

    public function insert(
        string $name,
        string $email,
        string $phone,
        string $source,
        string $clientNote,
        int $serviceId,
        ?float $servicePrice,
        int $serviceDurationMinutes,
        string $dateTime,
        string $status
    ): int {
        return $this->insertData(new ReservationData(
            $name,
            $email,
            $phone,
            $source,
            $clientNote,
            $serviceId,
            $servicePrice,
            $serviceDurationMinutes,
            $dateTime,
            $status
        ));
    }

    public function insertData(ReservationData $reservation): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO rezervace (jmeno, email, telefon, zdroj, poznamka_klienta, sluzba, cena_v_dobe_rezervace, doba_trvani_v_dobe_rezervace, datum_cas, stav)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (! $statement) {
            throw new RuntimeException('reservation_insert_prepare_failed');
        }

        $name = $reservation->name;
        $email = $reservation->email;
        $phone = $reservation->phone;
        $source = $reservation->source;
        $clientNote = $reservation->clientNote;
        $serviceId = $reservation->serviceId;
        $servicePrice = $reservation->servicePrice;
        $serviceDurationMinutes = max(15, $reservation->serviceDurationMinutes);
        $dateTime = $reservation->dateTime;
        $status = $reservation->status;

        $statement->bind_param(
            'sssssidiss',
            $name,
            $email,
            $phone,
            $source,
            $clientNote,
            $serviceId,
            $servicePrice,
            $serviceDurationMinutes,
            $dateTime,
            $status
        );

        if (! $statement->execute()) {
            $statement->close();
            throw new RuntimeException('reservation_insert_failed');
        }

        $reservationId = (int) $this->connection->insert_id;
        $statement->close();

        return $reservationId;
    }

    public function findDetailsById(int $reservationId): ?array
    {
        return $this->fetchDetailsById($reservationId, false);
    }

    public function findDetailsByIdForUpdate(int $reservationId): ?array
    {
        return $this->fetchDetailsById($reservationId, true);
    }

    private function fetchDetailsById(int $reservationId, bool $forUpdate): ?array
    {
        $sql = 'SELECT r.id, r.sluzba, r.jmeno, r.email, r.telefon, r.poznamka_klienta, r.poznamka_admina, r.datum_cas, r.stav,
                       r.cena_v_dobe_rezervace, r.reminder_sent_at,
                       COALESCE(r.doba_trvani_v_dobe_rezervace, s.doba_trvani) AS doba_trvani_v_dobe_rezervace,
                       s.nazev, s.doba_trvani
                FROM rezervace r
                INNER JOIN sluzby s ON s.id = r.sluzba
                WHERE r.id = ?
                LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->connection->prepare(
            $sql
        );

        if (! $statement) {
            return null;
        }

        $statement->bind_param('i', $reservationId);
        $statement->execute();
        $statement->bind_result($id, $serviceId, $name, $email, $phone, $clientNote, $adminNote, $dateTime, $status, $servicePrice, $reminderSentAt, $reservationDuration, $serviceName, $serviceDuration);
        $reservation = null;

        if ($statement->fetch()) {
            $reservation = [
                'id' => $id,
                'service_id' => (int) $serviceId,
                'jmeno' => $name,
                'email' => $email,
                'telefon' => $phone,
                'poznamka_klienta' => $clientNote,
                'poznamka_admina' => $adminNote,
                'datum_cas' => $dateTime,
                'stav' => $status,
                'service_price' => $servicePrice !== null ? (float) $servicePrice : null,
                'reminder_sent_at' => $reminderSentAt,
                'reservation_duration' => max(15, (int) ($reservationDuration ?? $serviceDuration ?? 0)),
                'service_name' => $serviceName,
                'service_duration' => $serviceDuration,
            ];
        }

        $statement->close();

        return $reservation;
    }

    public function updateStatus(int $reservationId, string $status): bool
    {
        $statement = $this->connection->prepare('UPDATE rezervace SET stav = ? WHERE id = ?');

        if (! $statement) {
            return false;
        }

        $statement->bind_param('si', $status, $reservationId);
        $updated = $statement->execute();
        $statement->close();

        return $updated;
    }

    public function cancelByCustomerLink(int $reservationId): bool
    {
        $cancelReason = 'Zrušeno zákazníkem přes odkaz v potvrzovacím e-mailu';
        $cancelledBy = 'customer_link';
        $cancelledByUser = 'customer';
        $statement = $this->connection->prepare(
            'UPDATE rezervace
             SET stav = "zrusena",
                 duvod_zruseni = ?,
                 zruseno_kym = ?,
                 zruseno_uzivatel = ?,
                 zruseno_at = NOW()
             WHERE id = ?
             LIMIT 1'
        );

        if (! $statement) {
            return false;
        }

        $statement->bind_param('sssi', $cancelReason, $cancelledBy, $cancelledByUser, $reservationId);
        $updated = $statement->execute();
        $statement->close();

        return $updated;
    }

    public function updateDateTime(int $reservationId, string $dateTime): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE rezervace
             SET datum_cas = ?,
                 reminder_sent_at = NULL
             WHERE id = ?
             LIMIT 1'
        );

        if (! $statement) {
            return false;
        }

        $statement->bind_param('si', $dateTime, $reservationId);
        $updated = $statement->execute();
        $statement->close();

        return $updated;
    }

    public function updateDateTimeAndResetReminder(int $reservationId, string $dateTime): bool
    {
        return $this->updateDateTime($reservationId, $dateTime);
    }

    public function findBookedBetweenExcludingId(string $start, string $end, int $excludedReservationId): array
    {
        return $this->fetchBookedBetween($start, $end, false, false, $excludedReservationId);
    }

    public function lockBookedBetweenExcludingId(string $start, string $end, int $excludedReservationId): array
    {
        return $this->fetchBookedBetween($start, $end, true, true, $excludedReservationId);
    }

    private function fetchBookedBetween(
        string $start,
        string $end,
        bool $forUpdate,
        bool $throwOnPrepareFailure,
        ?int $excludedReservationId = null
    ): array
    {
        $sql = 'SELECT r.datum_cas, COALESCE(r.doba_trvani_v_dobe_rezervace, 0) AS duration_minutes
                FROM rezervace r
                WHERE r.datum_cas >= ?
                  AND r.datum_cas < ?
                  AND r.stav IN ("nova", "potvrzena", "dokoncena")';

        if ($excludedReservationId !== null) {
            $sql .= ' AND r.id <> ?';
        }

        $sql .= '
                ORDER BY r.datum_cas ASC';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->connection->prepare($sql);
        if (! $statement) {
            if ($throwOnPrepareFailure) {
                throw new RuntimeException('reservation_lock_prepare_failed');
            }

            return [];
        }

        if ($excludedReservationId !== null) {
            $statement->bind_param('ssi', $start, $end, $excludedReservationId);
        } else {
            $statement->bind_param('ss', $start, $end);
        }
        $statement->execute();
        $statement->bind_result($startAt, $durationMinutes);
        $booked = [];

        while ($statement->fetch()) {
            $booked[] = [
                'start_at' => (string) $startAt,
                'duration_minutes' => (int) $durationMinutes,
            ];
        }

        $statement->close();

        return $booked;
    }
}
