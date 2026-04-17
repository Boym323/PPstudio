<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;

final class ServicePriceHistorySynchronizer
{
    public function sync(mysqli $connection, int $serviceId, ?float $newPrice): void
    {
        if ($serviceId <= 0) {
            return;
        }

        $closeOpenHistory = $connection->prepare(
            'UPDATE historie_cen_sluzeb
             SET platna_do = NOW()
             WHERE sluzba_id = ?
               AND platna_do IS NULL'
        );

        if ($closeOpenHistory) {
            $closeOpenHistory->bind_param('i', $serviceId);
            $closeOpenHistory->execute();
            $closeOpenHistory->close();
        }

        if ($newPrice === null) {
            return;
        }

        $insertHistory = $connection->prepare(
            'INSERT INTO historie_cen_sluzeb (sluzba_id, cena, platna_od, platna_do)
             VALUES (?, ?, NOW(), NULL)'
        );

        if ($insertHistory) {
            $insertHistory->bind_param('id', $serviceId, $newPrice);
            $insertHistory->execute();
            $insertHistory->close();
        }
    }
}
