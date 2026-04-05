<?php

if (! function_exists('syncServicePriceHistory')) {
    function syncServicePriceHistory(mysqli $connection, int $serviceId, ?float $newPrice): void
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

if (! function_exists('normalizeContactMapUrlSetting')) {
    function normalizeContactMapUrlSetting(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/<iframe\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i', $value, $matches)) {
            $value = trim((string) ($matches[1] ?? ''));
        }

        $value = preg_replace('#^https?://(?:www\.)?mapy\.com/#i', 'https://mapy.cz/', $value) ?: $value;
        $value = preg_replace('#^https?://(?:www\.)?mapy\.(?:cz|com)/s/([a-z0-9]+)$#i', 'https://frame.mapy.cz/s/$1', $value) ?: $value;

        return $value;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $savedAll = true;
    foreach (array_keys($studioSettingFields) as $settingKey) {
        $settingValue = trim((string) ($_POST[$settingKey] ?? ''));
        if ($settingKey === 'contact_map_url') {
            $settingValue = normalizeContactMapUrlSetting($settingValue);
        }
        if (! saveSiteSetting($connection, $settingKey, $settingValue)) {
            $savedAll = false;
            break;
        }
        $siteSettings[$settingKey] = $settingValue;
    }

    if ($savedAll) {
        $message = 'Nastavení studia bylo uloženo.';
    } else {
        $error = 'Nastavení studia se nepodařilo uložit.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_integrations'])) {
    $integrationKeys = [
        'google_reviews_url',
        'firmy_reviews_url',
        'firmy_reviews_embed',
        'google_place_id',
        'google_reviews_language',
        'instagram_url',
        'instagram_feed_embed',
    ];
    $savedAll = true;

    foreach ($integrationKeys as $settingKey) {
        $settingValue = trim((string) ($_POST[$settingKey] ?? ''));
        if (! saveSiteSetting($connection, $settingKey, $settingValue)) {
            $savedAll = false;
            break;
        }
        $siteSettings[$settingKey] = $settingValue;
    }

    if ($savedAll) {
        $message = 'Napojení recenzí a sociálních odkazů bylo uloženo.';
    } else {
        $error = 'Napojení se nepodařilo uložit.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_email_settings'])) {
    $emailSettingKeys = [
        'notification_emails',
    ];
    $savedAll = true;

    foreach ($emailSettingKeys as $settingKey) {
        $settingValue = trim((string) ($_POST[$settingKey] ?? ''));
        if (! saveSiteSetting($connection, $settingKey, $settingValue)) {
            $savedAll = false;
            break;
        }
        $siteSettings[$settingKey] = $settingValue;
    }

    if ($savedAll) {
        $message = 'E-mailové notifikace byly uloženy.';
    } else {
        $error = 'E-mailové notifikace se nepodařilo uložit.';
    }
}

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
    $description = trim((string) ($_POST['popis'] ?? ''));
    $price = trim((string) ($_POST['cena'] ?? ''));
    $duration = trim((string) ($_POST['doba_trvani'] ?? ''));

    $serviceForm = [
        'id' => $serviceId,
        'nazev' => $name,
        'kategorie_id' => $categoryId > 0 ? (string) $categoryId : '',
        'kategorie' => '',
        'kategorie_poradi' => '',
        'popis' => $description,
        'cena' => $price,
        'doba_trvani' => $duration,
    ];

    if ($name === '' || $duration === '') {
        $error = 'Název a délka trvání procedury jsou povinné.';
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

            $statement = $connection->prepare('UPDATE sluzby SET nazev = ?, kategorie_id = ?, popis = ?, cena = ?, doba_trvani = ? WHERE id = ?');
            if ($statement) {
                $statement->bind_param('sisdii', $name, $resolvedCategoryId, $description, $normalizedPrice, $durationValue, $serviceId);
            }
        } else {
            $statement = $connection->prepare('INSERT INTO sluzby (nazev, kategorie_id, popis, cena, doba_trvani) VALUES (?, ?, ?, ?, ?)');
            if ($statement) {
                $statement->bind_param('sisdi', $name, $resolvedCategoryId, $description, $normalizedPrice, $durationValue);
            }
        }

        if (isset($statement) && $statement) {
            if ($statement->execute()) {
                $savedServiceId = $serviceId > 0 ? $serviceId : (int) $connection->insert_id;
                if ($serviceId <= 0 || $priceChanged) {
                    syncServicePriceHistory($connection, $savedServiceId, $normalizedPrice);
                }
                $message = $serviceId > 0 ? 'Procedura byla upravena.' : 'Nová procedura byla přidána.';
                $serviceForm = ['id' => 0, 'nazev' => '', 'kategorie_id' => '', 'kategorie' => '', 'kategorie_poradi' => '', 'popis' => '', 'cena' => '', 'doba_trvani' => ''];
            } else {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_availability_grid'])) {
    $rangeStart = trim((string) ($_POST['planner_start'] ?? ''));
    $rangeEnd = trim((string) ($_POST['planner_end'] ?? ''));
    $windowsJson = (string) ($_POST['planner_windows'] ?? '[]');
    $windows = json_decode($windowsJson, true);

    if ($rangeStart === '' || $rangeEnd === '' || ! is_array($windows)) {
        $error = 'Kalendář dostupnosti se nepodařilo uložit.';
    } else {
        $deleteStatement = $connection->prepare(
            'DELETE FROM dostupnost
             WHERE DATE(start_at) >= ?
               AND DATE(start_at) <= ?'
        );

        if ($deleteStatement) {
            $deleteStatement->bind_param('ss', $rangeStart, $rangeEnd);
            $deleteStatement->execute();
            $deleteStatement->close();
        }

        $insertStatement = $connection->prepare(
            'INSERT INTO dostupnost (start_at, end_at, poznamka)
             VALUES (?, ?, ?)'
        );

        if ($insertStatement) {
            foreach ($windows as $window) {
                $startAt = (string) ($window['start_at'] ?? '');
                $endAt = (string) ($window['end_at'] ?? '');
                $note = 'Kalendář dostupnosti';

                if ($startAt !== '' && $endAt !== '' && $startAt < $endAt) {
                    $insertStatement->bind_param('sss', $startAt, $endAt, $note);
                    $insertStatement->execute();
                }
            }

            $insertStatement->close();
        }

        $message = 'Dostupnost v kalendáři byla uložena.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_window'])) {
    $windowId = (int) ($_POST['window_id'] ?? 0);
    if ($windowId > 0) {
        $statement = $connection->prepare('DELETE FROM dostupnost WHERE id = ? LIMIT 1');
        if ($statement) {
            $statement->bind_param('i', $windowId);
            if ($statement->execute()) {
                $message = 'Volné okno bylo odstraněno.';
            } else {
                $error = 'Okno se nepodařilo odstranit.';
            }
            $statement->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_reservation'])) {
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    $status = trim((string) ($_POST['stav'] ?? 'nova'));
    $adminNote = trim((string) ($_POST['poznamka_admina'] ?? ''));
    if (array_key_exists($status, reservationStatusOptions()) && $reservationId > 0) {
        $reservationBeforeUpdate = loadReservationDetails($connection, $reservationId);
        $statement = $connection->prepare('UPDATE rezervace SET stav = ?, poznamka_admina = ? WHERE id = ?');
        if ($statement) {
            $statement->bind_param('ssi', $status, $adminNote, $reservationId);
            if ($statement->execute()) {
                $reservationAfterUpdate = loadReservationDetails($connection, $reservationId);

                if ($reservationBeforeUpdate !== null && $reservationAfterUpdate !== null) {
                    $previousStatus = (string) ($reservationBeforeUpdate['stav'] ?? '');
                    $newStatus = (string) ($reservationAfterUpdate['stav'] ?? '');

                    if ($previousStatus !== 'potvrzena' && $newStatus === 'potvrzena') {
                        sendReservationConfirmedEmail($emailConfig, $siteSettings, $reservationAfterUpdate);
                    }

                    if ($previousStatus !== 'zrusena' && $newStatus === 'zrusena') {
                        sendReservationCancelledEmail($emailConfig, $siteSettings, $reservationAfterUpdate);
                    }
                }

                $message = 'Rezervace byla upravena.';
            } else {
                $error = 'Rezervaci se nepodařilo upravit.';
            }
            $statement->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_manual_reservation'])) {
    $manualReservationForm = [
        'jmeno' => trim((string) ($_POST['jmeno'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'telefon' => trim((string) ($_POST['telefon'] ?? '')),
        'zdroj' => trim((string) ($_POST['zdroj'] ?? 'telefon')),
        'sluzba_id' => trim((string) ($_POST['sluzba_id'] ?? '')),
        'datum_cas' => trim((string) ($_POST['datum_cas'] ?? '')),
        'poznamka_klienta' => trim((string) ($_POST['poznamka_klienta'] ?? '')),
    ];

    $serviceId = (int) $manualReservationForm['sluzba_id'];
    $dateTime = str_replace('T', ' ', $manualReservationForm['datum_cas']);

    if ($manualReservationForm['jmeno'] === '' || $serviceId <= 0 || $dateTime === '') {
        $error = 'Pro ruční rezervaci vyplňte jméno, službu a termín.';
    } elseif ($manualReservationForm['email'] !== '' && ! filter_var($manualReservationForm['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Zadaný e-mail není platný.';
    } elseif (! isValidReservationSlot($connection, $serviceId, $dateTime . ':00') && ! isValidReservationSlot($connection, $serviceId, $dateTime)) {
        $error = 'Vybraný termín už není volný nebo neodpovídá dostupnosti.';
    } else {
        $service = getServiceById($connection, $serviceId);
        $statement = $connection->prepare(
            'INSERT INTO rezervace (jmeno, email, telefon, zdroj, poznamka_klienta, sluzba, cena_v_dobe_rezervace, datum_cas, stav)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if ($statement) {
            $status = 'potvrzena';
            $dateTimeForSave = strlen($dateTime) === 16 ? $dateTime . ':00' : $dateTime;
            $servicePrice = isset($service['cena']) ? (float) $service['cena'] : null;
            $statement->bind_param(
                'sssssidss',
                $manualReservationForm['jmeno'],
                $manualReservationForm['email'],
                $manualReservationForm['telefon'],
                $manualReservationForm['zdroj'],
                $manualReservationForm['poznamka_klienta'],
                $serviceId,
                $servicePrice,
                $dateTimeForSave,
                $status
            );

            if ($statement->execute()) {
                $reservation = [
                    'id' => $connection->insert_id,
                    'jmeno' => $manualReservationForm['jmeno'],
                    'email' => $manualReservationForm['email'],
                    'telefon' => $manualReservationForm['telefon'],
                    'zdroj' => $manualReservationForm['zdroj'],
                    'poznamka_klienta' => $manualReservationForm['poznamka_klienta'],
                    'datum_cas' => $dateTimeForSave,
                    'service_name' => (string) ($service['nazev'] ?? 'Vybraná procedura'),
                    'service_price' => $servicePrice,
                    'service_duration' => (int) ($service['doba_trvani'] ?? 60),
                ];

                if ($manualReservationForm['email'] !== '') {
                    sendReservationConfirmedEmail($emailConfig, $siteSettings, $reservation);
                }

                $manualReservationForm = [
                    'jmeno' => '',
                    'email' => '',
                    'telefon' => '',
                    'zdroj' => 'telefon',
                    'sluzba_id' => '',
                    'datum_cas' => '',
                    'poznamka_klienta' => '',
                ];
                $message = 'Ruční rezervace byla vložena.';
            } else {
                $error = 'Ruční rezervaci se nepodařilo uložit.';
            }

            $statement->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reservation'])) {
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    if ($reservationId > 0) {
        $statement = $connection->prepare('DELETE FROM rezervace WHERE id = ? LIMIT 1');
        if ($statement) {
            $statement->bind_param('i', $reservationId);
            if ($statement->execute()) {
                $message = 'Rezervace byla smazána.';
            } else {
                $error = 'Rezervaci se nepodařilo smazat.';
            }
            $statement->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_media'])) {
    $category = trim((string) ($_POST['category'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
    $externalUrl = trim((string) ($_POST['external_url'] ?? ''));
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);

    if (! in_array($category, ['profile', 'gallery'], true)) {
        $error = 'Neplatná kategorie obrázku.';
    } else {
        $uploadError = null;
        $path = storeUploadedImage($_FILES['image'] ?? [], __DIR__ . '/../../../uploads', $uploadError);

        if ($path === null) {
            $error = $uploadError !== null && $uploadError !== ''
                ? 'Obrázek se nepodařilo nahrát. ' . $uploadError
                : 'Obrázek se nepodařilo nahrát.';
            $mediaFeedback = $error;
            $mediaFeedbackType = 'error';
        } else {
            if ($category === 'profile') {
                $connection->query("DELETE FROM media WHERE category = 'profile'");
            }

            $statement = $connection->prepare(
                'INSERT INTO media (category, image_path, title, subtitle, external_url, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            if ($statement) {
                $statement->bind_param('sssssi', $category, $path, $title, $subtitle, $externalUrl, $sortOrder);
                if ($statement->execute()) {
                    $message = 'Obrázek byl uložen.';
                    $mediaFeedback = $message;
                    $mediaFeedbackType = 'success';
                } else {
                    $error = 'Obrázek se nepodařilo uložit.';
                    $mediaFeedback = $error;
                    $mediaFeedbackType = 'error';
                }
                $statement->close();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_media'])) {
    $mediaId = (int) ($_POST['media_id'] ?? 0);
    if ($mediaId > 0) {
        $statement = $connection->prepare('SELECT image_path FROM media WHERE id = ? LIMIT 1');
        if ($statement) {
            $statement->bind_param('i', $mediaId);
            $statement->execute();
            $statement->bind_result($imagePath);
            $existingPath = null;
            if ($statement->fetch()) {
                $existingPath = (string) $imagePath;
            }
            $statement->close();

            $deleteStatement = $connection->prepare('DELETE FROM media WHERE id = ? LIMIT 1');
            if ($deleteStatement) {
                $deleteStatement->bind_param('i', $mediaId);
                if ($deleteStatement->execute()) {
                    if ($existingPath !== null) {
                        $fullPath = __DIR__ . '/../../../' . ltrim($existingPath, '/');
                        if (is_file($fullPath)) {
                            @unlink($fullPath);
                        }
                    }
                    $message = 'Obrázek byl odstraněn.';
                } else {
                    $error = 'Obrázek se nepodařilo odstranit.';
                }
                $deleteStatement->close();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_certificate_file'])) {
    $projectRoot = dirname(__DIR__, 3);
    $certificateTitle = trim((string) ($_POST['certificate_title'] ?? ''));
    $uploadError = null;
    $path = storeUploadedCertificateFile(
        $_FILES['certificate_file'] ?? [],
        $projectRoot . '/uploads',
        $uploadError
    );

    if ($path === null) {
        $error = $uploadError !== null && $uploadError !== ''
            ? 'Certifikát se nepodařilo nahrát. ' . $uploadError
            : 'Certifikát se nepodařilo nahrát.';
        $mediaFeedback = $error;
        $mediaFeedbackType = 'error';
    } else {
        $uploadedName = basename((string) $path);
        if ($certificateTitle !== '' && preg_match('/^cert_[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif|pdf)$/i', $uploadedName)) {
            setCertificateMetadataTitle($projectRoot . '/uploads', $uploadedName, $certificateTitle);
        }
        $message = 'Certifikát byl nahrán.';
        $mediaFeedback = $message;
        $mediaFeedbackType = 'success';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_certificate_title'])) {
    $projectRoot = dirname(__DIR__, 3);
    $fileName = basename((string) ($_POST['certificate_name'] ?? ''));
    $title = trim((string) ($_POST['certificate_title'] ?? ''));

    if ($fileName === '' || ! preg_match('/^cert_[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif|pdf)$/i', $fileName)) {
        $mediaFeedback = 'Neplatný certifikát.';
        $mediaFeedbackType = 'error';
    } elseif ($title === '') {
        $mediaFeedback = 'Vyplňte název certifikátu.';
        $mediaFeedbackType = 'error';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($title) : strlen($title)) > 120) {
        $mediaFeedback = 'Název certifikátu je příliš dlouhý (max. 120 znaků).';
        $mediaFeedbackType = 'error';
    } elseif (! setCertificateMetadataTitle($projectRoot . '/uploads', $fileName, $title)) {
        $mediaFeedback = 'Název certifikátu se nepodařilo uložit.';
        $mediaFeedbackType = 'error';
    } else {
        $mediaFeedback = 'Název certifikátu byl uložen.';
        $mediaFeedbackType = 'success';
        $message = $mediaFeedback;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_certificate_file'])) {
    $projectRoot = dirname(__DIR__, 3);
    $fileName = basename((string) ($_POST['certificate_name'] ?? ''));
    if ($fileName === '' || ! preg_match('/^cert_[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif|pdf)$/i', $fileName)) {
        $mediaFeedback = 'Neplatný certifikát pro smazání.';
        $mediaFeedbackType = 'error';
    } else {
        $fullPath = $projectRoot . '/uploads/' . $fileName;
        if (is_file($fullPath) && @unlink($fullPath)) {
            removeCertificateMetadata($projectRoot . '/uploads', $fileName);
            $previewFileName = function_exists('certificatePreviewFilenameFromOriginal')
                ? certificatePreviewFilenameFromOriginal($fileName)
                : null;
            if (is_string($previewFileName) && $previewFileName !== '') {
                $previewPath = $projectRoot . '/uploads/' . $previewFileName;
                if (is_file($previewPath)) {
                    @unlink($previewPath);
                }
            }
            $mediaFeedback = 'Certifikát byl odstraněn.';
            $mediaFeedbackType = 'success';
            $message = $mediaFeedback;
        } else {
            $mediaFeedback = 'Certifikát se nepodařilo odstranit.';
            $mediaFeedbackType = 'error';
        }
    }
}
