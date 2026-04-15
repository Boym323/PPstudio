<?php

$voucherTableQuery = $connection->query("SHOW TABLES LIKE 'poukazy'");
if ($voucherTableQuery instanceof mysqli_result) {
    $voucherModuleReady = (bool) $voucherTableQuery->fetch_row();
    $voucherTableQuery->free();
}

if ($voucherModuleReady) {
    $voucherQuery = $connection->query(
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
    if ($voucherQuery instanceof mysqli_result) {
        while ($row = $voucherQuery->fetch_assoc()) {
            $voucherRows[] = $row;
        }
        $voucherQuery->free();
    }

    if ($voucherRows !== []) {
        $voucherIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $voucherRows);
        $voucherIds = array_values(array_filter($voucherIds, static fn(int $id): bool => $id > 0));
        $reservationIdsFromTransactions = [];

        if ($voucherIds !== []) {
            $idList = implode(',', $voucherIds);
            $voucherTxQuery = $connection->query(
                "SELECT id, poukaz_id, castka, typ, rezervace_id, poznamka, created_at
                 FROM poukaz_cerpani
                 WHERE poukaz_id IN ({$idList})
                 ORDER BY created_at DESC, id DESC
                 LIMIT 1200"
            );
            if ($voucherTxQuery instanceof mysqli_result) {
                while ($row = $voucherTxQuery->fetch_assoc()) {
                    $voucherId = (int) ($row['poukaz_id'] ?? 0);
                    if ($voucherId <= 0) {
                        continue;
                    }
                    if (! isset($voucherTransactionsByVoucher[$voucherId])) {
                        $voucherTransactionsByVoucher[$voucherId] = [];
                    }
                    if (count($voucherTransactionsByVoucher[$voucherId]) >= 12) {
                        continue;
                    }
                    $reservationId = (int) ($row['rezervace_id'] ?? 0);
                    if ($reservationId > 0) {
                        $reservationIdsFromTransactions[$reservationId] = $reservationId;
                    }
                    $voucherTransactionsByVoucher[$voucherId][] = $row;
                }
                $voucherTxQuery->free();
            }

            if ($reservationIdsFromTransactions !== []) {
                $reservationIdList = implode(',', array_values($reservationIdsFromTransactions));
                $voucherReservationLookupQuery = $connection->query(
                    "SELECT r.id, r.jmeno, r.datum_cas, s.nazev AS sluzba_nazev
                     FROM rezervace r
                     LEFT JOIN sluzby s ON s.id = r.sluzba
                     WHERE r.id IN ({$reservationIdList})"
                );
                if ($voucherReservationLookupQuery instanceof mysqli_result) {
                    while ($lookupRow = $voucherReservationLookupQuery->fetch_assoc()) {
                        $lookupId = (int) ($lookupRow['id'] ?? 0);
                        if ($lookupId <= 0) {
                            continue;
                        }
                        $voucherReservationLookup[$lookupId] = [
                            'jmeno' => (string) ($lookupRow['jmeno'] ?? ''),
                            'datum_cas' => (string) ($lookupRow['datum_cas'] ?? ''),
                            'sluzba_nazev' => (string) ($lookupRow['sluzba_nazev'] ?? ''),
                        ];
                    }
                    $voucherReservationLookupQuery->free();
                }
            }
        }
    }

    $voucherReservationsQuery = $connection->query(
        'SELECT r.id, r.jmeno, r.telefon, r.datum_cas, s.nazev AS service_name, COALESCE(r.cena_v_dobe_rezervace, s.cena, 0) AS reservation_price
         FROM rezervace r
         LEFT JOIN sluzby s ON s.id = r.sluzba
         WHERE r.stav IN ("nova", "potvrzena", "dokoncena")
           AND r.datum_cas >= DATE_SUB(NOW(), INTERVAL 90 DAY)
         ORDER BY r.datum_cas DESC
         LIMIT 250'
    );
    if ($voucherReservationsQuery instanceof mysqli_result) {
        while ($row = $voucherReservationsQuery->fetch_assoc()) {
            $voucherReservationOptions[] = $row;
        }
        $voucherReservationsQuery->free();
    }
}
