<?php
declare(strict_types=1);

namespace PPStudio\Domain;

final class ReservationData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $source,
        public readonly string $clientNote,
        public readonly int $serviceId,
        public readonly ?float $servicePrice,
        public readonly string $dateTime,
        public readonly string $status
    ) {
    }
}
