<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;

final class AdminReservationDeleteUseCase
{
    public function __construct(private mysqli $connection)
    {
    }

    public function handle(array $post): array
    {
        $reservationId = (int) ($post['reservation_id'] ?? 0);
        if ($reservationId <= 0) {
            return $this->error('Neplatné ID rezervace.', 422);
        }

        $statement = $this->connection->prepare('DELETE FROM rezervace WHERE id = ? LIMIT 1');
        if (! $statement) {
            return $this->error('Rezervaci se nepodařilo smazat.', 500);
        }

        $statement->bind_param('i', $reservationId);
        $ok = $statement->execute();
        $statement->close();

        if (! $ok) {
            return $this->error('Rezervaci se nepodařilo smazat.', 500);
        }

        return $this->success('Rezervace byla smazána.', [
            'reservation_id' => $reservationId,
            'deleted' => true,
        ]);
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
