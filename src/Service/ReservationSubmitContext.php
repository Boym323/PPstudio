<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;

final class ReservationSubmitContext
{
    /**
     * @param array<string, mixed> $siteSettings
     */
    public function __construct(
        public readonly mysqli $connection,
        public readonly ReservationService $reservationService,
        public readonly array $siteSettings
    ) {
    }
}
