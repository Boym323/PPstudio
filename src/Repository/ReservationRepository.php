<?php
declare(strict_types=1);

namespace PPStudio\Repository;

use mysqli;
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
        string $dateTime,
        string $status
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO rezervace (jmeno, email, telefon, zdroj, poznamka_klienta, sluzba, cena_v_dobe_rezervace, datum_cas, stav)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (! $statement) {
            throw new RuntimeException('reservation_insert_prepare_failed');
        }

        $statement->bind_param(
            'sssssidss',
            $name,
            $email,
            $phone,
            $source,
            $clientNote,
            $serviceId,
            $servicePrice,
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

    private function fetchBookedBetween(string $start, string $end, bool $forUpdate, bool $throwOnPrepareFailure): array
    {
        $sql = 'SELECT r.datum_cas, s.doba_trvani
                FROM rezervace r
                INNER JOIN sluzby s ON s.id = r.sluzba
                WHERE r.datum_cas >= ?
                  AND r.datum_cas < ?
                  AND r.stav IN ("nova", "potvrzena", "dokoncena")
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

        $statement->bind_param('ss', $start, $end);
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
