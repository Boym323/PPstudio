<?php
declare(strict_types=1);

namespace PPStudio\Repository;

use mysqli;
use RuntimeException;

final class AvailabilityRepository
{
    public function __construct(
        private mysqli $connection
    ) {
    }

    public function findWindowsBetween(string $start, string $end): array
    {
        return $this->fetchWindowsBetween($start, $end, false, false);
    }

    public function lockWindowsBetween(string $start, string $end): array
    {
        return $this->fetchWindowsBetween($start, $end, true, true);
    }

    private function fetchWindowsBetween(string $start, string $end, bool $forUpdate, bool $throwOnPrepareFailure): array
    {
        $sql = 'SELECT id, start_at, end_at
                FROM dostupnost
                WHERE start_at < ?
                  AND end_at > ?
                  AND end_at > start_at
                ORDER BY start_at ASC';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->connection->prepare($sql);
        if (! $statement) {
            if ($throwOnPrepareFailure) {
                throw new RuntimeException('availability_prepare_failed');
            }

            return [];
        }

        $statement->bind_param('ss', $end, $start);
        $statement->execute();
        $statement->bind_result($id, $startAt, $endAt);
        $windows = [];

        while ($statement->fetch()) {
            $windows[] = [
                'id' => (int) $id,
                'start_at' => (string) $startAt,
                'end_at' => (string) $endAt,
            ];
        }

        $statement->close();

        return $windows;
    }
}
