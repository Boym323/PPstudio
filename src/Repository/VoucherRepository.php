<?php
declare(strict_types=1);

namespace PPStudio\Repository;

use mysqli;
use mysqli_result;
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

    public function findPrintById(int $voucherId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, kod, puvodni_hodnota, zustatek, status, issued_at, expires_at, recipient_name, note
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

    public function findById(int $voucherId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, kod, puvodni_hodnota, zustatek, status, issued_at, expires_at, recipient_name, recipient_email, note, emailed_at
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
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        $statement->close();

        return is_array($row) ? $row : null;
    }

    public function findByIdForUpdate(int $voucherId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, kod, puvodni_hodnota, zustatek, status, expires_at
             FROM poukazy
             WHERE id = ?
             LIMIT 1
             FOR UPDATE'
        );

        if (! $statement instanceof mysqli_stmt) {
            return null;
        }

        $statement->bind_param('i', $voucherId);
        $statement->execute();
        $result = $statement->get_result();
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        $statement->close();

        return is_array($row) ? $row : null;
    }

    public function isVoucherModuleReady(): bool
    {
        $query = $this->connection->query("SHOW TABLES LIKE 'poukazy'");
        if (! $query instanceof mysqli_result) {
            return false;
        }

        $isReady = (bool) $query->fetch_row();
        $query->free();

        return $isReady;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAdminRows(): array
    {
        $rows = [];
        $query = $this->connection->query(
            'SELECT p.id, p.kod, p.puvodni_hodnota, p.zustatek, p.status, p.issued_at, p.expires_at, p.recipient_name, p.recipient_email, p.note, p.emailed_at, p.updated_at,
                    CASE
                        WHEN p.status = "storno" THEN "storno"
                        WHEN p.expires_at IS NOT NULL AND p.expires_at < CURDATE() THEN "expirovan"
                        WHEN p.zustatek <= 0 THEN "vycerpan"
                        ELSE "aktivni"
                    END AS effective_status
             FROM poukazy p
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT 300'
        );

        if (! $query instanceof mysqli_result) {
            return $rows;
        }

        while ($row = $query->fetch_assoc()) {
            $rows[] = $row;
        }

        $query->free();

        return $rows;
    }

    public function create(
        string $code,
        float $value,
        ?string $expiresAt,
        string $recipientName,
        string $recipientEmail,
        string $note
    ): bool {
        $remaining = $value;
        $statement = $this->connection->prepare(
            'INSERT INTO poukazy (kod, puvodni_hodnota, zustatek, status, issued_at, expires_at, recipient_name, recipient_email, note)
             VALUES (?, ?, ?, "aktivni", NOW(), ?, ?, ?, ?)'
        );

        if (! $statement instanceof mysqli_stmt) {
            return false;
        }

        $statement->bind_param('sddssss', $code, $value, $remaining, $expiresAt, $recipientName, $recipientEmail, $note);
        $created = $statement->execute();
        $statement->close();

        return $created;
    }

    public function createBatchVoucher(
        string $code,
        float $value,
        ?string $expiresAt,
        string $recipientName,
        string $note
    ): bool {
        $remaining = $value;
        $statement = $this->connection->prepare(
            'INSERT INTO poukazy (kod, puvodni_hodnota, zustatek, status, issued_at, expires_at, recipient_name, note)
             VALUES (?, ?, ?, "aktivni", NOW(), ?, ?, ?)'
        );

        if (! $statement instanceof mysqli_stmt) {
            return false;
        }

        $statement->bind_param('sddsss', $code, $value, $remaining, $expiresAt, $recipientName, $note);
        $created = $statement->execute();
        $statement->close();

        return $created;
    }

    public function lastErrorCode(): int
    {
        return (int) $this->connection->errno;
    }

    public function markEmailed(int $voucherId, string $recipientEmail): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE poukazy
             SET recipient_email = ?, emailed_at = NOW()
             WHERE id = ?
             LIMIT 1'
        );

        if (! $statement instanceof mysqli_stmt) {
            return false;
        }

        $statement->bind_param('si', $recipientEmail, $voucherId);
        $updated = $statement->execute();
        $statement->close();

        return $updated;
    }

    public function updateBalanceAndStatus(int $voucherId, float $remaining, string $status): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE poukazy
             SET zustatek = ?, status = ?, updated_at = NOW()
             WHERE id = ?'
        );

        if (! $statement instanceof mysqli_stmt) {
            return false;
        }

        $statement->bind_param('dsi', $remaining, $status, $voucherId);
        $updated = $statement->execute();
        $statement->close();

        return $updated;
    }

    public function insertRedeemTransaction(int $voucherId, float $amount, ?int $reservationId, string $note): bool
    {
        $statement = $this->connection->prepare(
            'INSERT INTO poukaz_cerpani (poukaz_id, castka, typ, rezervace_id, poznamka)
             VALUES (?, ?, "cerpani", ?, ?)'
        );

        if (! $statement instanceof mysqli_stmt) {
            return false;
        }

        $statement->bind_param('idis', $voucherId, $amount, $reservationId, $note);
        $inserted = $statement->execute();
        $statement->close();

        return $inserted;
    }

    /**
     * @param array<int, int> $voucherIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function findTransactionsGroupedByVoucher(array $voucherIds): array
    {
        $groupedTransactions = [];

        $voucherIds = array_values(array_filter($voucherIds, static fn (int $id): bool => $id > 0));
        if ($voucherIds === []) {
            return $groupedTransactions;
        }

        $idList = implode(',', $voucherIds);
        $query = $this->connection->query(
            "SELECT id, poukaz_id, castka, typ, rezervace_id, poznamka, created_at
             FROM poukaz_cerpani
             WHERE poukaz_id IN ({$idList})
             ORDER BY created_at DESC, id DESC
             LIMIT 1200"
        );

        if (! $query instanceof mysqli_result) {
            return $groupedTransactions;
        }

        while ($row = $query->fetch_assoc()) {
            $voucherId = (int) ($row['poukaz_id'] ?? 0);
            if ($voucherId <= 0) {
                continue;
            }

            if (! isset($groupedTransactions[$voucherId])) {
                $groupedTransactions[$voucherId] = [];
            }

            if (count($groupedTransactions[$voucherId]) >= 12) {
                continue;
            }

            $groupedTransactions[$voucherId][] = $row;
        }

        $query->free();

        return $groupedTransactions;
    }

    /**
     * @param array<int, int> $reservationIds
     * @return array<int, array<string, string>>
     */
    public function findReservationLookupByIds(array $reservationIds): array
    {
        $lookup = [];

        $reservationIds = array_values(array_filter($reservationIds, static fn (int $id): bool => $id > 0));
        if ($reservationIds === []) {
            return $lookup;
        }

        $idList = implode(',', $reservationIds);
        $query = $this->connection->query(
            "SELECT r.id, r.jmeno, r.datum_cas, s.nazev AS sluzba_nazev
             FROM rezervace r
             LEFT JOIN sluzby s ON s.id = r.sluzba
             WHERE r.id IN ({$idList})"
        );

        if (! $query instanceof mysqli_result) {
            return $lookup;
        }

        while ($row = $query->fetch_assoc()) {
            $reservationId = (int) ($row['id'] ?? 0);
            if ($reservationId <= 0) {
                continue;
            }

            $lookup[$reservationId] = [
                'jmeno' => (string) ($row['jmeno'] ?? ''),
                'datum_cas' => (string) ($row['datum_cas'] ?? ''),
                'sluzba_nazev' => (string) ($row['sluzba_nazev'] ?? ''),
            ];
        }

        $query->free();

        return $lookup;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findRedeemReservationOptions(): array
    {
        $rows = [];
        $query = $this->connection->query(
            'SELECT r.id, r.jmeno, r.telefon, r.datum_cas, s.nazev AS service_name, COALESCE(r.cena_v_dobe_rezervace, s.cena, 0) AS reservation_price
             FROM rezervace r
             LEFT JOIN sluzby s ON s.id = r.sluzba
             WHERE r.stav IN ("nova", "potvrzena", "dokoncena")
               AND r.datum_cas >= DATE_SUB(NOW(), INTERVAL 90 DAY)
             ORDER BY r.datum_cas DESC
             LIMIT 250'
        );

        if (! $query instanceof mysqli_result) {
            return $rows;
        }

        while ($row = $query->fetch_assoc()) {
            $rows[] = $row;
        }

        $query->free();

        return $rows;
    }
}
