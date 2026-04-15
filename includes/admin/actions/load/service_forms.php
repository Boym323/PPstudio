<?php

if (isset($_GET['edit_service'])) {
    $editServiceId = (int) $_GET['edit_service'];
    if ($editServiceId > 0) {
        $statement = $connection->prepare(
            'SELECT s.id, s.nazev, s.kategorie_id, s.stitek, c.nazev AS kategorie, c.poradi AS kategorie_poradi, s.popis, s.cena, s.doba_trvani
             FROM sluzby s
             LEFT JOIN kategorie c ON c.id = s.kategorie_id
             WHERE s.id = ?
             LIMIT 1'
        );
        if ($statement) {
            $statement->bind_param('i', $editServiceId);
            $statement->execute();
            $statement->bind_result($id, $nazev, $kategorieId, $stitek, $kategorie, $kategoriePoradi, $popis, $cena, $dobaTrvani);
            if ($statement->fetch()) {
                $serviceForm = [
                    'id' => (int) $id,
                    'nazev' => (string) $nazev,
                    'kategorie_id' => $kategorieId !== null ? (string) $kategorieId : '',
                    'stitek' => (string) ($stitek ?? ''),
                    'kategorie' => (string) ($kategorie ?? ''),
                    'kategorie_poradi' => $kategoriePoradi !== null ? (string) $kategoriePoradi : '',
                    'popis' => (string) $popis,
                    'cena' => $cena !== null ? number_format((float) $cena, 0, '.', '') : '',
                    'doba_trvani' => $dobaTrvani !== null ? (string) $dobaTrvani : '',
                ];
            }
            $statement->close();
        }
    }
}

if (isset($_GET['edit_category'])) {
    $editCategoryId = (int) $_GET['edit_category'];
    if ($editCategoryId > 0) {
        $statement = $connection->prepare('SELECT id, nazev, poradi FROM kategorie WHERE id = ? LIMIT 1');
        if ($statement) {
            $statement->bind_param('i', $editCategoryId);
            $statement->execute();
            $statement->bind_result($id, $nazev, $poradi);
            if ($statement->fetch()) {
                $categoryForm = [
                    'id' => (int) $id,
                    'nazev' => (string) $nazev,
                    'poradi' => $poradi !== null ? (string) $poradi : '',
                ];
            }
            $statement->close();
        }
    }
}
