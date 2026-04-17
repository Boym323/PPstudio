<?php
declare(strict_types=1);

namespace PPStudio\Http\Request;

final class ReservationsFeedRequest
{
    public function __construct(
        private string $token
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function fromQuery(array $query): self
    {
        return new self(
            trim((string) ($query['token'] ?? ''))
        );
    }

    public function token(): string
    {
        return $this->token;
    }
}
