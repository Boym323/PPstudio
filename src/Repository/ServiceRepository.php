<?php
declare(strict_types=1);

namespace PPStudio\Repository;

use mysqli;
use mysqli_result;
use PPStudio\Domain\ServiceItem;

final class ServiceRepository
{
    public function __construct(
        private mysqli $connection
    ) {
    }

    public function findActiveItemById(int $serviceId): ?ServiceItem
    {
        $statement = $this->connection->prepare(
            'SELECT s.id, s.nazev, s.popis, s.cena, s.doba_trvani, s.created_at
             FROM sluzby s
             INNER JOIN kategorie k ON k.id = s.kategorie_id
             WHERE s.id = ?
               AND s.aktivni = 1
               AND k.aktivni = 1
             LIMIT 1'
        );

        if (! $statement) {
            return null;
        }

        $statement->bind_param('i', $serviceId);
        $statement->execute();
        $statement->bind_result($id, $nazev, $popis, $cena, $dobaTrvani, $createdAt);
        $service = null;

        if ($statement->fetch()) {
            $service = ServiceItem::fromActiveRow([
                'id' => $id,
                'nazev' => $nazev,
                'popis' => $popis,
                'cena' => $cena,
                'doba_trvani' => $dobaTrvani,
                'created_at' => $createdAt,
            ]);
        }

        $statement->close();

        return $service ?: null;
    }

    public function findActiveById(int $serviceId): ?array
    {
        $service = $this->findActiveItemById($serviceId);

        return $service instanceof ServiceItem ? $service->toLegacyArray() : null;
    }

    /**
     * @return ServiceItem[]
     */
    public function findActiveItemsWithCategories(): array
    {
        $query = $this->connection->query(
            "SELECT s.id, s.nazev, s.stitek, c.nazev AS kategorie, c.poradi AS kategorie_poradi, s.popis, s.cena, s.doba_trvani
             FROM sluzby s
             LEFT JOIN kategorie c ON c.id = s.kategorie_id
             WHERE s.aktivni = 1
               AND c.aktivni = 1
             ORDER BY COALESCE(c.poradi, 9999) ASC,
                      COALESCE(NULLIF(c.nazev, ''), 'Ostatní služby') ASC,
                      s.nazev ASC"
        );

        if (! $query instanceof mysqli_result) {
            return [];
        }

        $services = [];

        while ($row = $query->fetch_assoc()) {
            $services[] = ServiceItem::fromCategoryRow($row);
        }

        $query->free();

        return $services;
    }

    public function findActiveWithCategories(): array
    {
        return array_map(
            static fn (ServiceItem $service): array => [
                'id' => $service->id,
                'nazev' => $service->name,
                'stitek' => $service->badge,
                'kategorie' => $service->category,
                'kategorie_poradi' => $service->categoryOrder,
                'popis' => $service->description,
                'cena' => $service->price,
                'doba_trvani' => $service->durationMinutes,
            ],
            $this->findActiveItemsWithCategories()
        );
    }

    public function findAdminById(int $serviceId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT s.id, s.nazev, s.kategorie_id, s.stitek, c.nazev AS kategorie, c.poradi AS kategorie_poradi, s.popis, s.cena, s.doba_trvani
             FROM sluzby s
             LEFT JOIN kategorie c ON c.id = s.kategorie_id
             WHERE s.id = ?
             LIMIT 1'
        );

        if (! $statement) {
            return null;
        }

        $statement->bind_param('i', $serviceId);
        $statement->execute();
        $result = $statement->get_result();
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;

        if ($result instanceof mysqli_result) {
            $result->free();
        }

        $statement->close();

        return is_array($row) ? $row : null;
    }

    public function findCategoryById(int $categoryId): ?array
    {
        $statement = $this->connection->prepare('SELECT id, nazev, poradi FROM kategorie WHERE id = ? LIMIT 1');

        if (! $statement) {
            return null;
        }

        $statement->bind_param('i', $categoryId);
        $statement->execute();
        $result = $statement->get_result();
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;

        if ($result instanceof mysqli_result) {
            $result->free();
        }

        $statement->close();

        return is_array($row) ? $row : null;
    }

    public function findAdminCategoryRows(): array
    {
        $query = $this->connection->query(
            "SELECT c.id, c.nazev, c.poradi, c.aktivni, COUNT(s.id) AS services_count, SUM(CASE WHEN s.aktivni = 1 THEN 1 ELSE 0 END) AS active_services_count
             FROM kategorie c
             LEFT JOIN sluzby s ON s.kategorie_id = c.id
             GROUP BY c.id, c.nazev, c.poradi, c.aktivni
             ORDER BY COALESCE(c.poradi, 9999) ASC, c.nazev ASC"
        );

        if (! $query instanceof mysqli_result) {
            return [];
        }

        $rows = [];

        while ($row = $query->fetch_assoc()) {
            $rows[] = $row;
        }

        $query->free();

        return $rows;
    }

    public function findAdminRows(array $filters): array
    {
        $where = ['1=1'];

        if (($filters['status'] ?? 'all') === 'active') {
            $where[] = 's.aktivni = 1';
        } elseif (($filters['status'] ?? 'all') === 'inactive') {
            $where[] = 's.aktivni = 0';
        }

        if (($filters['category'] ?? 'all') !== 'all') {
            $where[] = 's.kategorie_id = ' . (int) ($filters['category'] ?? 0);
        }

        $queryNeedle = trim((string) ($filters['q'] ?? ''));
        if ($queryNeedle !== '') {
            $serviceNeedle = $this->connection->real_escape_string($queryNeedle);
            $where[] = "(s.nazev LIKE '%{$serviceNeedle}%'
                OR s.popis LIKE '%{$serviceNeedle}%'
                OR c.nazev LIKE '%{$serviceNeedle}%')";
        }

        $query = $this->connection->query(
            "SELECT s.id, s.nazev, s.kategorie_id, s.stitek, s.aktivni AS service_active, c.nazev AS kategorie, c.poradi AS kategorie_poradi, c.aktivni AS category_active, s.popis, s.cena, s.doba_trvani
             FROM sluzby s
             LEFT JOIN kategorie c ON c.id = s.kategorie_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY COALESCE(c.poradi, 9999) ASC,
                      COALESCE(NULLIF(c.nazev, ''), 'Ostatní služby') ASC,
                      s.nazev ASC"
        );

        if (! $query instanceof mysqli_result) {
            return [];
        }

        $rows = [];

        while ($row = $query->fetch_assoc()) {
            $rows[] = $row;
        }

        $query->free();

        return $rows;
    }

    public function findPriceHistoryRows(int $limit = 200): array
    {
        $query = $this->connection->query(
            "SELECT h.id, h.sluzba_id, h.cena, h.platna_od, h.platna_do, s.nazev AS sluzba_nazev
             FROM historie_cen_sluzeb h
             INNER JOIN sluzby s ON s.id = h.sluzba_id
             ORDER BY h.platna_od DESC, h.id DESC
             LIMIT " . max(1, $limit)
        );

        if (! $query instanceof mysqli_result) {
            return [];
        }

        $rows = [];

        while ($row = $query->fetch_assoc()) {
            $rows[] = $row;
        }

        $query->free();

        return $rows;
    }
}
