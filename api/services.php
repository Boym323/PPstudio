<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/site_lock.php';


header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

requirePublicSiteAccessOrJsonError();

$connection = null;

try {
    $connection = \PPStudio\Database\DatabaseFactory::connect();
} catch (Throwable) {
    http_response_code(500);
    echo json_encode(['error' => 'Databaze neni dostupna.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = [];
$query = $connection->query(
    "SELECT s.id, s.nazev, s.stitek, c.nazev AS kategorie, c.poradi AS kategorie_poradi, s.popis, s.cena, s.doba_trvani
     FROM sluzby s
     LEFT JOIN kategorie c ON c.id = s.kategorie_id
     WHERE s.aktivni = 1
       AND c.aktivni = 1
     ORDER BY COALESCE(c.poradi, 9999) ASC,
              COALESCE(NULLIF(c.nazev, ''), 'Ostatní služby') ASC,
              s.nazev ASC"
);

if ($query instanceof mysqli_result) {
    while ($row = $query->fetch_assoc()) {
        $category = trim((string) ($row['kategorie'] ?? ''));
        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['nazev'] ?? ''),
            'category' => $category !== '' ? $category : 'Ostatní služby',
            'category_order' => $row['kategorie_poradi'] !== null ? (int) $row['kategorie_poradi'] : null,
            'badge' => trim((string) ($row['stitek'] ?? '')),
            'description' => trim((string) ($row['popis'] ?? '')),
            'duration' => (int) ($row['doba_trvani'] ?? 0),
            'price' => $row['cena'] !== null ? (float) $row['cena'] : null,
            'label' => (string) ($row['nazev'] ?? '')
                . ' (' . formatDuration($row['doba_trvani'] ?? null) . ') - '
                . formatPrice($row['cena'] ?? null),
        ];
    }
    $query->free();
}

$connection->close();
echo json_encode(['services' => $items], JSON_UNESCAPED_UNICODE);
