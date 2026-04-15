<?php
declare(strict_types=1);

namespace PPStudio\Domain;

use DateTimeImmutable;

final class AvailabilityWindow
{
    public function __construct(
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $end,
        public readonly ?int $id = null
    ) {
    }

    public static function fromDatabaseRow(array $row): ?self
    {
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) ($row['start_at'] ?? ''));
        $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) ($row['end_at'] ?? ''));

        if (! $start instanceof DateTimeImmutable || ! $end instanceof DateTimeImmutable || $end <= $start) {
            return null;
        }

        return new self($start, $end, isset($row['id']) ? (int) $row['id'] : null);
    }

    public function contains(ReservationSlot $slot): bool
    {
        return $slot->start >= $this->start && $slot->end <= $this->end;
    }

    public function toArray(bool $includeId = true): array
    {
        $window = [
            'start' => $this->start,
            'end' => $this->end,
        ];

        if ($includeId && $this->id !== null) {
            $window = ['id' => $this->id] + $window;
        }

        return $window;
    }
}
