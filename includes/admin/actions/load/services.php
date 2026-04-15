<?php

$serviceCategoryQuery = $connection->query(
    "SELECT c.id, c.nazev, c.poradi, c.aktivni, COUNT(s.id) AS services_count, SUM(CASE WHEN s.aktivni = 1 THEN 1 ELSE 0 END) AS active_services_count
     FROM kategorie c
     LEFT JOIN sluzby s ON s.kategorie_id = c.id
     GROUP BY c.id, c.nazev, c.poradi, c.aktivni
     ORDER BY COALESCE(c.poradi, 9999) ASC, c.nazev ASC"
);
if ($serviceCategoryQuery instanceof mysqli_result) {
    while ($row = $serviceCategoryQuery->fetch_assoc()) {
        $serviceCategoryRows[] = $row;
    }
    $serviceCategoryQuery->free();
}

$serviceCategoryFilterOptions = ['all' => 'Všechny kategorie'];
foreach ($serviceCategoryRows as $categoryRow) {
    $categoryId = (string) ($categoryRow['id'] ?? '');
    if ($categoryId === '') {
        continue;
    }
    $categoryLabel = (string) ($categoryRow['nazev'] ?? '');
    if ((int) ($categoryRow['aktivni'] ?? 1) !== 1) {
        $categoryLabel .= ' (neaktivní)';
    }
    $serviceCategoryFilterOptions[$categoryId] = $categoryLabel;
}

if (! in_array($serviceFilters['status'] ?? 'all', array_keys($serviceStatusFilterOptions), true)) {
    $serviceFilters['status'] = 'all';
}

if (! in_array($serviceFilters['category'] ?? 'all', array_keys($serviceCategoryFilterOptions), true)) {
    $serviceFilters['category'] = 'all';
}

$serviceWhere = ['1=1'];
if (($serviceFilters['status'] ?? 'all') === 'active') {
    $serviceWhere[] = 's.aktivni = 1';
} elseif (($serviceFilters['status'] ?? 'all') === 'inactive') {
    $serviceWhere[] = 's.aktivni = 0';
}

if (($serviceFilters['category'] ?? 'all') !== 'all') {
    $serviceWhere[] = 's.kategorie_id = ' . (int) $serviceFilters['category'];
}

if (($serviceFilters['q'] ?? '') !== '') {
    $serviceNeedle = $connection->real_escape_string($serviceFilters['q']);
    $serviceWhere[] = "(s.nazev LIKE '%{$serviceNeedle}%'
        OR s.popis LIKE '%{$serviceNeedle}%'
        OR c.nazev LIKE '%{$serviceNeedle}%')";
}

$serviceQuery = $connection->query(
    "SELECT s.id, s.nazev, s.kategorie_id, s.stitek, s.aktivni AS service_active, c.nazev AS kategorie, c.poradi AS kategorie_poradi, c.aktivni AS category_active, s.popis, s.cena, s.doba_trvani
     FROM sluzby s
     LEFT JOIN kategorie c ON c.id = s.kategorie_id
     WHERE " . implode(' AND ', $serviceWhere) . "
     ORDER BY COALESCE(c.poradi, 9999) ASC,
              COALESCE(NULLIF(c.nazev, ''), 'Ostatní služby') ASC,
              s.nazev ASC"
);
if ($serviceQuery instanceof mysqli_result) {
    while ($row = $serviceQuery->fetch_assoc()) {
        $serviceRows[] = $row;
    }
    $serviceQuery->free();
}

$servicePriceHistoryQuery = $connection->query(
    "SELECT h.id, h.sluzba_id, h.cena, h.platna_od, h.platna_do, s.nazev AS sluzba_nazev
     FROM historie_cen_sluzeb h
     INNER JOIN sluzby s ON s.id = h.sluzba_id
     ORDER BY h.platna_od DESC, h.id DESC
     LIMIT 200"
);
if ($servicePriceHistoryQuery instanceof mysqli_result) {
    while ($row = $servicePriceHistoryQuery->fetch_assoc()) {
        $servicePriceHistoryRows[] = $row;
    }
    $servicePriceHistoryQuery->free();
}
