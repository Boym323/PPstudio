<?php
declare(strict_types=1);

namespace PPStudio\Repository;

use mysqli;

final class ServiceRepository
{
    public function __construct(
        private mysqli $connection
    ) {
    }

    public function findActiveById(int $serviceId): ?array
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
            $service = [
                'id' => $id,
                'nazev' => $nazev,
                'popis' => $popis,
                'cena' => $cena,
                'doba_trvani' => $dobaTrvani,
                'created_at' => $createdAt,
            ];
        }

        $statement->close();

        return $service ?: null;
    }

    public function findActiveWithCategories(): array
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

        if (! $query instanceof \mysqli_result) {
            return [];
        }

        $services = [];

        while ($row = $query->fetch_assoc()) {
            $services[] = $row;
        }

        $query->free();

        return $services;
    }
}
