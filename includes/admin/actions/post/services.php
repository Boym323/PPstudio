<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $categoryName = trim((string) ($_POST['category_name'] ?? ''));
    $categoryOrder = trim((string) ($_POST['category_order'] ?? ''));

    $categoryForm = [
        'id' => $categoryId,
        'nazev' => $categoryName,
        'poradi' => $categoryOrder,
    ];

    if ($categoryName === '') {
        $error = 'Název kategorie je povinný.';
    } elseif ($categoryOrder !== '' && ! ctype_digit($categoryOrder)) {
        $error = 'Pořadí kategorie musí být celé kladné číslo nebo prázdné pole.';
    } else {
        $normalizedOrder = $categoryOrder !== '' ? (int) $categoryOrder : 9999;

        if ($categoryId > 0) {
            $statement = $connection->prepare('UPDATE kategorie SET nazev = ?, poradi = ? WHERE id = ?');
            if ($statement) {
                $statement->bind_param('sii', $categoryName, $normalizedOrder, $categoryId);
            }
        } else {
            $statement = $connection->prepare('INSERT INTO kategorie (nazev, poradi) VALUES (?, ?)');
            if ($statement) {
                $statement->bind_param('si', $categoryName, $normalizedOrder);
            }
        }

        if (isset($statement) && $statement) {
            if ($statement->execute()) {
                $message = $categoryId > 0 ? 'Kategorie byla upravena.' : 'Kategorie byla přidána.';
                $categoryForm = ['id' => 0, 'nazev' => '', 'poradi' => ''];
            } else {
                $error = 'Kategorii se nepodařilo uložit. Název kategorie musí být unikátní.';
            }
            $statement->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_category_active'])) {
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $targetActive = (int) ($_POST['target_active'] ?? 1);
    $targetActive = $targetActive === 0 ? 0 : 1;

    if ($categoryId > 0) {
        $selectCategory = $connection->prepare('SELECT nazev, aktivni FROM kategorie WHERE id = ? LIMIT 1');
        if ($selectCategory) {
            $selectCategory->bind_param('i', $categoryId);
            $selectCategory->execute();
            $selectCategory->bind_result($categoryName, $currentActive);
            $exists = $selectCategory->fetch();
            $selectCategory->close();

            if (! $exists) {
                $error = 'Kategorie nebyla nalezena.';
            } elseif ((string) $categoryName === 'Ostatní služby') {
                $error = 'Kategorii „Ostatní služby“ nelze deaktivovat.';
            } elseif ((int) $currentActive === $targetActive) {
                $message = $targetActive === 1 ? 'Kategorie už je aktivní.' : 'Kategorie už je neaktivní.';
            } else {
                $connection->begin_transaction();

                $updateCategory = $connection->prepare('UPDATE kategorie SET aktivni = ? WHERE id = ?');
                if (! $updateCategory) {
                    $connection->rollback();
                    $error = 'Stav kategorie se nepodařilo změnit.';
                } else {
                    $updateCategory->bind_param('ii', $targetActive, $categoryId);
                    $okCategory = $updateCategory->execute();
                    $updateCategory->close();

                    $okServices = true;
                    if ($okCategory && $targetActive === 0) {
                        $deactivateServices = $connection->prepare('UPDATE sluzby SET aktivni = 0 WHERE kategorie_id = ?');
                        if ($deactivateServices) {
                            $deactivateServices->bind_param('i', $categoryId);
                            $okServices = $deactivateServices->execute();
                            $deactivateServices->close();
                        } else {
                            $okServices = false;
                        }
                    }

                    if ($okCategory && $okServices) {
                        $connection->commit();
                        $message = $targetActive === 1
                            ? 'Kategorie byla aktivována.'
                            : 'Kategorie byla deaktivována. Navázané procedury byly také deaktivovány.';
                    } else {
                        $connection->rollback();
                        $error = 'Stav kategorie se nepodařilo změnit.';
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category_order'])) {
    $rawOrder = trim((string) ($_POST['category_order_ids'] ?? ''));

    if ($rawOrder === '') {
        $error = 'Pořadí kategorií je prázdné.';
    } else {
        $parts = array_values(array_filter(array_map('trim', explode(',', $rawOrder)), static fn(string $value): bool => $value !== ''));
        $categoryIds = [];

        foreach ($parts as $part) {
            if (! ctype_digit($part)) {
                $error = 'Neplatný formát pořadí kategorií.';
                break;
            }
            $categoryIds[] = (int) $part;
        }

        if ($error === '') {
            $uniqueCategoryIds = array_values(array_unique($categoryIds));

            if (count($uniqueCategoryIds) !== count($categoryIds)) {
                $error = 'Pořadí kategorií obsahuje duplicity.';
            } else {
                $existingIds = [];
                $query = $connection->query('SELECT id FROM kategorie');
                if ($query instanceof mysqli_result) {
                    while ($row = $query->fetch_assoc()) {
                        $existingIds[] = (int) ($row['id'] ?? 0);
                    }
                    $query->free();
                }

                sort($existingIds);
                $submittedIds = $uniqueCategoryIds;
                sort($submittedIds);

                if ($existingIds !== $submittedIds) {
                    $error = 'Pořadí kategorií neodpovídá aktuálním kategoriím.';
                } else {
                    $statement = $connection->prepare('UPDATE kategorie SET poradi = ? WHERE id = ?');
                    if (! $statement) {
                        $error = 'Pořadí kategorií se nepodařilo uložit.';
                    } else {
                        $rank = 1;
                        foreach ($categoryIds as $categoryId) {
                            $statement->bind_param('ii', $rank, $categoryId);
                            if (! $statement->execute()) {
                                $error = 'Pořadí kategorií se nepodařilo uložit.';
                                break;
                            }
                            $rank++;
                        }
                        $statement->close();

                        if ($error === '') {
                            $message = 'Pořadí kategorií bylo uloženo.';
                        }
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_service'])) {
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $name = trim((string) ($_POST['nazev'] ?? ''));
    $categoryId = (int) ($_POST['kategorie_id'] ?? 0);
    $badge = trim((string) ($_POST['stitek'] ?? ''));
    $description = trim((string) ($_POST['popis'] ?? ''));
    $price = trim((string) ($_POST['cena'] ?? ''));
    $duration = trim((string) ($_POST['doba_trvani'] ?? ''));

    $serviceForm = [
        'id' => $serviceId,
        'nazev' => $name,
        'kategorie_id' => $categoryId > 0 ? (string) $categoryId : '',
        'stitek' => $badge,
        'kategorie' => '',
        'kategorie_poradi' => '',
        'popis' => $description,
        'cena' => $price,
        'doba_trvani' => $duration,
    ];

    if ($name === '' || $duration === '') {
        $error = 'Název a délka trvání procedury jsou povinné.';
    } elseif ($badge !== '' && mb_strlen($badge) > 80) {
        $error = 'Štítek může mít maximálně 80 znaků.';
    } elseif (! ctype_digit($duration) || (int) $duration <= 0) {
        $error = 'Délka trvání musí být kladné číslo v minutách.';
    } elseif ($categoryId <= 0) {
        $error = 'Vyberte prosím kategorii procedury.';
    } elseif ($price !== '' && ! is_numeric(str_replace(',', '.', $price))) {
        $error = 'Cena musí být číslo nebo prázdné pole.';
    } else {
        $normalizedPrice = normalizeNullableFloat($price);
        $durationValue = (int) $duration;
        $resolvedCategoryId = 0;
        $priceChanged = false;
        $originalPrice = null;
        $categoryCheck = $connection->prepare('SELECT id FROM kategorie WHERE id = ? LIMIT 1');
        if ($categoryCheck) {
            $categoryCheck->bind_param('i', $categoryId);
            $categoryCheck->execute();
            $categoryCheck->bind_result($checkedCategoryId);
            if ($categoryCheck->fetch()) {
                $resolvedCategoryId = (int) $checkedCategoryId;
            }
            $categoryCheck->close();
        }

        if ($resolvedCategoryId <= 0) {
            $error = 'Vybraná kategorie neexistuje.';
        } elseif ($serviceId > 0) {
            $servicePriceCheck = $connection->prepare('SELECT cena FROM sluzby WHERE id = ? LIMIT 1');
            if ($servicePriceCheck) {
                $servicePriceCheck->bind_param('i', $serviceId);
                $servicePriceCheck->execute();
                $servicePriceCheck->bind_result($currentPrice);
                if ($servicePriceCheck->fetch()) {
                    $originalPrice = $currentPrice !== null ? (float) $currentPrice : null;
                }
                $servicePriceCheck->close();
            }
            $priceChanged = $originalPrice !== $normalizedPrice;

            $statement = $connection->prepare('UPDATE sluzby SET nazev = ?, kategorie_id = ?, stitek = ?, popis = ?, cena = ?, doba_trvani = ? WHERE id = ?');
            if ($statement) {
                $statement->bind_param('sissdii', $name, $resolvedCategoryId, $badge, $description, $normalizedPrice, $durationValue, $serviceId);
            }
        } else {
            $statement = $connection->prepare('INSERT INTO sluzby (nazev, kategorie_id, stitek, popis, cena, doba_trvani) VALUES (?, ?, ?, ?, ?, ?)');
            if ($statement) {
                $statement->bind_param('sissdi', $name, $resolvedCategoryId, $badge, $description, $normalizedPrice, $durationValue);
            }
        }

        if (isset($statement) && $statement) {
            $connection->begin_transaction();
            try {
                if (! $statement->execute()) {
                    throw new RuntimeException('save_service_failed');
                }

                $savedServiceId = $serviceId > 0 ? $serviceId : (int) $connection->insert_id;
                if ($serviceId <= 0 || $priceChanged) {
                    syncServicePriceHistory($connection, $savedServiceId, $normalizedPrice);
                }

                $connection->commit();
                $message = $serviceId > 0 ? 'Procedura byla upravena.' : 'Nová procedura byla přidána.';
                $serviceForm = ['id' => 0, 'nazev' => '', 'kategorie_id' => '', 'stitek' => '', 'kategorie' => '', 'kategorie_poradi' => '', 'popis' => '', 'cena' => '', 'doba_trvani' => ''];
            } catch (Throwable $exception) {
                $connection->rollback();
                $error = 'Proceduru se nepodařilo uložit.';
            }
            $statement->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_service_active'])) {
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $targetActive = (int) ($_POST['target_active'] ?? 1);
    $targetActive = $targetActive === 0 ? 0 : 1;

    if ($serviceId > 0) {
        $statement = $connection->prepare('UPDATE sluzby SET aktivni = ? WHERE id = ? LIMIT 1');
        if ($statement) {
            $statement->bind_param('ii', $targetActive, $serviceId);
            if ($statement->execute()) {
                $message = $targetActive === 1 ? 'Procedura byla aktivována.' : 'Procedura byla deaktivována.';
            } else {
                $error = 'Stav procedury se nepodařilo změnit.';
            }
            $statement->close();
        }
    }
}
