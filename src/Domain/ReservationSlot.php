<?php
declare(strict_types=1);

namespace PPStudio\Domain;

use DateTimeImmutable;

final class ReservationSlot
{
    public function __construct(
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $end
    ) {
    }

    public static function fromStartAndDuration(DateTimeImmutable $start, int $durationMinutes): self
    {
        $durationMinutes = max(15, $durationMinutes);

        return new self($start, $start->modify('+' . $durationMinutes . ' minutes'));
    }

    public static function fromBookedRow(array $row): ?self
    {
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) ($row['start_at'] ?? ''));

        if (! $start instanceof DateTimeImmutable) {
            return null;
        }

        return self::fromStartAndDuration($start, (int) ($row['duration_minutes'] ?? 0));
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $this->end > $other->start;
    }

    public function toArray(): array
    {
        return [
            'start' => $this->start,
            'end' => $this->end,
        ];
    }
}
