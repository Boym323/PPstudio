<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use PPStudio\Http\Request\ReservationsFeedRequest;

final class ReservationsFeedAuthorizationService
{
    /**
     * @param array<string, mixed> $emailConfig
     */
    public function isAuthorized(ReservationsFeedRequest $request, array $emailConfig): bool
    {
        $expectedToken = trim((string) ($emailConfig['calendar_token'] ?? ''));
        $providedToken = $request->token();

        return $providedToken !== '' && $providedToken === $expectedToken;
    }
}
