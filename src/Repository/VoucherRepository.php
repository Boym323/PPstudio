<?php
declare(strict_types=1);

namespace PPStudio\Repository;

use mysqli;
use mysqli_stmt;

final class VoucherRepository
{
    public function __construct(private mysqli $connection)
    {
    }

    public function findPublicById(int $voucherId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, kod, puvodni_hodnota, zustatek, status, issued_at, expires_at, recipient_name, recipient_email, note
             FROM poukazy
             WHERE id = ?
             LIMIT 1'
        );

        if (! $statement instanceof mysqli_stmt) {
            return null;
        }

        $statement->bind_param('i', $voucherId);
        $statement->execute();
        $result = $statement->get_result();
        $row = $result instanceof \mysqli_result ? $result->fetch_assoc() : null;
        $statement->close();

        return is_array($row) ? $row : null;
    }
}
