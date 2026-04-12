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

if (! function_exists('voucherModuleTableReady')) {
    function voucherModuleTableReady(mysqli $connection): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        $query = $connection->query("SHOW TABLES LIKE 'poukazy'");
        if (! ($query instanceof mysqli_result)) {
            $ready = false;
            return $ready;
        }

        $ready = (bool) $query->fetch_row();
        $query->free();

        return $ready;
    }
}

if (! function_exists('generateVoucherCode')) {
    function generateVoucherCode(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?? '';
        if ($prefix === '') {
            $prefix = 'PP' . date('y');
        }

        return $prefix . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}

if (! function_exists('voucherEffectiveStatus')) {
    function voucherEffectiveStatus(array $voucher): string
    {
        $storedStatus = (string) ($voucher['status'] ?? 'aktivni');
        if ($storedStatus === 'storno') {
            return 'storno';
        }

        $expiresAt = trim((string) ($voucher['expires_at'] ?? ''));
        if ($expiresAt !== '' && $expiresAt < date('Y-m-d')) {
            return 'expirovan';
        }

        $remaining = (float) ($voucher['zustatek'] ?? 0);
        if ($remaining <= 0.0001) {
            return 'vycerpan';
        }

        return 'aktivni';
    }
}

if (! function_exists('normalizeVoucherRecipientEmail')) {
    function normalizeVoucherRecipientEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $savedAll = true;
    foreach (array_keys($studioSettingFields) as $settingKey) {
        $settingValue = trim((string) ($_POST[$settingKey] ?? ''));
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_voucher_batch'])) {
    $voucherBatchForm = [
        'prefix' => trim((string) ($_POST['voucher_batch_prefix'] ?? '')),
        'count' => trim((string) ($_POST['voucher_batch_count'] ?? '20')),
        'value' => trim((string) ($_POST['voucher_batch_value'] ?? '1000')),
        'expires_at' => trim((string) ($_POST['voucher_batch_expires_at'] ?? '')),
        'recipient_name' => trim((string) ($_POST['voucher_batch_recipient_name'] ?? '')),
        'note' => trim((string) ($_POST['voucher_batch_note'] ?? '')),
    ];

    if (! voucherModuleTableReady($connection)) {
        $error = 'Modul poukazů není v databázi dostupný.';
    } else {
        $count = max(1, min(200, (int) $voucherBatchForm['count']));
        $value = (float) str_replace(',', '.', $voucherBatchForm['value']);
        $expiresAt = $voucherBatchForm['expires_at'];

        if ($value <= 0) {
            $error = 'Hodnota poukazu musí být vyšší než 0 Kč.';
        } elseif ($expiresAt !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) {
            $error = 'Platnost poukazu má neplatný formát data.';
        } else {
            $insert = $connection->prepare(
                'INSERT INTO poukazy (kod, puvodni_hodnota, zustatek, status, issued_at, expires_at, recipient_name, note)
                 VALUES (?, ?, ?, "aktivni", NOW(), ?, ?, ?)'
            );

            if (! $insert) {
                $error = 'Sérii poukazů se nepodařilo vygenerovat.';
            } else {
                $generated = 0;
                $generatedCodes = [];
                $maxAttempts = $count * 20;
                $attempts = 0;

                while ($generated < $count && $attempts < $maxAttempts) {
                    $attempts++;
                    $code = generateVoucherCode($voucherBatchForm['prefix']);
                    $remaining = $value;
                    $expiresAtNullable = $expiresAt !== '' ? $expiresAt : null;
                    $recipientName = $voucherBatchForm['recipient_name'];
                    $note = $voucherBatchForm['note'];

                    $insert->bind_param('sddsss', $code, $value, $remaining, $expiresAtNullable, $recipientName, $note);
                    $ok = $insert->execute();

                    if ($ok) {
                        $generated++;
                        $generatedCodes[] = $code;
                        continue;
                    }

                    if ((int) $connection->errno !== 1062) {
                        break;
                    }
                }

                $insert->close();

                if ($generated === $count) {
                    $message = 'Vygenerováno ' . $generated . ' poukazů. První kódy: ' . implode(', ', array_slice($generatedCodes, 0, 5)) . (count($generatedCodes) > 5 ? '…' : '');
                } elseif ($generated > 0) {
                    $error = 'Vygenerováno jen ' . $generated . ' z ' . $count . ' poukazů. Zkuste akci zopakovat.';
                } else {
                    $error = 'Sérii poukazů se nepodařilo vygenerovat.';
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_voucher'])) {
    $voucherForm = [
        'code' => trim((string) ($_POST['voucher_code'] ?? '')),
        'value' => trim((string) ($_POST['voucher_value'] ?? '')),
        'expires_at' => trim((string) ($_POST['voucher_expires_at'] ?? '')),
        'recipient_name' => trim((string) ($_POST['voucher_recipient_name'] ?? '')),
        'recipient_email' => normalizeVoucherRecipientEmail((string) ($_POST['voucher_recipient_email'] ?? '')),
        'note' => trim((string) ($_POST['voucher_note'] ?? '')),
    ];

    if (! voucherModuleTableReady($connection)) {
        $error = 'Modul poukazů není v databázi dostupný.';
    } else {
        $value = (float) str_replace(',', '.', $voucherForm['value']);
        $code = $voucherForm['code'] !== '' ? strtoupper($voucherForm['code']) : generateVoucherCode('PP' . date('y'));
        $code = preg_replace('/[^A-Z0-9\-]/', '', $code) ?? '';
        $expiresAt = $voucherForm['expires_at'];
        $recipientEmail = $voucherForm['recipient_email'];

        if ($code === '') {
            $error = 'Kód poukazu je neplatný.';
        } elseif ($value <= 0) {
            $error = 'Hodnota poukazu musí být vyšší než 0 Kč.';
        } elseif ($expiresAt !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) {
            $error = 'Platnost poukazu má neplatný formát data.';
        } elseif ($recipientEmail !== '' && ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'E-mail příjemce poukazu není ve správném formátu.';
        } else {
            $remaining = $value;
            $expiresAtNullable = $expiresAt !== '' ? $expiresAt : null;
            $recipientName = $voucherForm['recipient_name'];
            $note = $voucherForm['note'];

            $insert = $connection->prepare(
                'INSERT INTO poukazy (kod, puvodni_hodnota, zustatek, status, issued_at, expires_at, recipient_name, recipient_email, note)
                 VALUES (?, ?, ?, "aktivni", NOW(), ?, ?, ?, ?)'
            );

            if (! $insert) {
                $error = 'Poukaz se nepodařilo uložit.';
            } else {
                $insert->bind_param('sddssss', $code, $value, $remaining, $expiresAtNullable, $recipientName, $recipientEmail, $note);
                if ($insert->execute()) {
                    $message = 'Poukaz byl uložen.';
                    $voucherForm = [
                        'code' => '',
                        'value' => '',
                        'expires_at' => date('Y-m-d', strtotime('+1 year')),
                        'recipient_name' => '',
                        'recipient_email' => '',
                        'note' => '',
                    ];
                } else {
                    $error = (int) $connection->errno === 1062
                        ? 'Kód poukazu už existuje. Zadejte jiný.'
                        : 'Poukaz se nepodařilo uložit.';
                }
                $insert->close();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_voucher_email'])) {
    $voucherId = (int) ($_POST['voucher_id'] ?? 0);
    $recipientEmail = normalizeVoucherRecipientEmail((string) ($_POST['voucher_recipient_email'] ?? ''));

    if (! voucherModuleTableReady($connection)) {
        $error = 'Modul poukazů není v databázi dostupný.';
    } elseif ($voucherId <= 0) {
        $error = 'Vyberte prosím platný poukaz.';
    } elseif ($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Zadejte platný e-mail, na který se má poukaz odeslat.';
    } else {
        $voucherStatement = $connection->prepare(
            'SELECT id, kod, puvodni_hodnota, zustatek, status, issued_at, expires_at, recipient_name, recipient_email, note, emailed_at
             FROM poukazy
             WHERE id = ?
             LIMIT 1'
        );

        if (! $voucherStatement) {
            $error = 'Poukaz se nepodařilo načíst.';
        } else {
            $voucherStatement->bind_param('i', $voucherId);
            $voucherStatement->execute();
            $voucherStatement->bind_result(
                $fetchedId,
                $fetchedCode,
                $fetchedOriginalValue,
                $fetchedRemaining,
                $fetchedStatus,
                $fetchedIssuedAt,
                $fetchedExpiresAt,
                $fetchedRecipientName,
                $fetchedRecipientEmail,
                $fetchedNote,
                $fetchedEmailedAt
            );
            $voucher = null;
            if ($voucherStatement->fetch()) {
                $voucher = [
                    'id' => (int) $fetchedId,
                    'kod' => (string) $fetchedCode,
                    'puvodni_hodnota' => $fetchedOriginalValue !== null ? (float) $fetchedOriginalValue : null,
                    'zustatek' => $fetchedRemaining !== null ? (float) $fetchedRemaining : null,
                    'status' => (string) $fetchedStatus,
                    'issued_at' => (string) $fetchedIssuedAt,
                    'expires_at' => $fetchedExpiresAt,
                    'recipient_name' => (string) $fetchedRecipientName,
                    'recipient_email' => (string) $fetchedRecipientEmail,
                    'note' => (string) $fetchedNote,
                    'emailed_at' => $fetchedEmailedAt,
                ];
            }
            $voucherStatement->close();

            if (! is_array($voucher)) {
                $error = 'Poukaz nebyl nalezen.';
            } else {
                $voucher['recipient_email'] = $recipientEmail;
                $effectiveStatus = voucherEffectiveStatus($voucher);

                if ($effectiveStatus !== 'aktivni') {
                    $error = 'E-mailem lze odeslat jen aktivní poukaz.';
                } elseif (! ($emailConfig['enabled'] ?? false)) {
                    $error = 'E-mailové odesílání není v nastavení aktivní.';
                } elseif (! sendVoucherEmail($emailConfig, $siteSettings, $voucher, $recipientEmail)) {
                    $error = 'Poukaz se nepodařilo odeslat e-mailem.';
                } else {
                    $updateStatement = $connection->prepare(
                        'UPDATE poukazy
                         SET recipient_email = ?, emailed_at = NOW()
                         WHERE id = ?
                         LIMIT 1'
                    );

                    if ($updateStatement) {
                        $updateStatement->bind_param('si', $recipientEmail, $voucherId);
                        $updateStatement->execute();
                        $updateStatement->close();
                    }

                    $message = 'Poukaz byl odeslán na e-mail ' . $recipientEmail . '.';
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_voucher'])) {
    $voucherId = (int) ($_POST['voucher_id'] ?? 0);
    $redeemAmount = (float) str_replace(',', '.', trim((string) ($_POST['redeem_amount'] ?? '')));
    $reservationIdRaw = trim((string) ($_POST['redeem_reservation_id'] ?? ''));
    $reservationId = $reservationIdRaw !== '' ? (int) $reservationIdRaw : null;
    $redeemNote = trim((string) ($_POST['redeem_note'] ?? ''));

    if (! voucherModuleTableReady($connection)) {
        $error = 'Modul poukazů není v databázi dostupný.';
    } elseif ($voucherId <= 0 || $redeemAmount <= 0) {
        $error = 'Vyberte poukaz a zadejte částku čerpání.';
    } else {
        $connection->begin_transaction();
        try {
            $lock = $connection->prepare(
                'SELECT id, kod, puvodni_hodnota, zustatek, status, expires_at
                 FROM poukazy
                 WHERE id = ?
                 LIMIT 1
                 FOR UPDATE'
            );

            if (! $lock) {
                throw new RuntimeException('Poukaz se nepodařilo načíst.');
            }

            $lock->bind_param('i', $voucherId);
            $lock->execute();
            $result = $lock->get_result();
            $voucher = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
            $lock->close();

            if (! is_array($voucher)) {
                throw new RuntimeException('Poukaz nebyl nalezen.');
            }

            $effectiveStatus = voucherEffectiveStatus($voucher);
            if ($effectiveStatus === 'storno') {
                throw new RuntimeException('Poukaz je stornovaný.');
            }
            if ($effectiveStatus === 'expirovan') {
                throw new RuntimeException('Poukaz je expirovaný.');
            }

            $remaining = (float) ($voucher['zustatek'] ?? 0);
            if ($redeemAmount > $remaining + 0.0001) {
                throw new RuntimeException('Čerpání je vyšší než aktuální zůstatek poukazu.');
            }

            $newRemaining = round(max(0, $remaining - $redeemAmount), 2);
            $newStatus = $newRemaining <= 0.0001 ? 'vycerpan' : 'aktivni';

            $update = $connection->prepare('UPDATE poukazy SET zustatek = ?, status = ?, updated_at = NOW() WHERE id = ?');
            if (! $update) {
                throw new RuntimeException('Poukaz se nepodařilo aktualizovat.');
            }
            $update->bind_param('dsi', $newRemaining, $newStatus, $voucherId);
            $update->execute();
            $update->close();

            $insertTx = $connection->prepare(
                'INSERT INTO poukaz_cerpani (poukaz_id, castka, typ, rezervace_id, poznamka)
                 VALUES (?, ?, "cerpani", ?, ?)'
            );
            if (! $insertTx) {
                throw new RuntimeException('Čerpání se nepodařilo uložit.');
            }
            $insertTx->bind_param('idis', $voucherId, $redeemAmount, $reservationId, $redeemNote);
            $insertTx->execute();
            $insertTx->close();

            $connection->commit();
            $message = 'Čerpání poukazu bylo uloženo. Zůstatek: ' . number_format($newRemaining, 0, ',', ' ') . ' Kč.';
        } catch (Throwable $exception) {
            $connection->rollback();
            $error = $exception->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_integrations'])) {
    $integrationKeys = [
        'google_reviews_url',
        'firmy_reviews_url',
        'firmy_reviews_embed',
        'google_place_id',
        'google_reviews_language',
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_availability_story_background'])) {
    $backgroundError = null;
    $backgroundPath = null;

    if (! isset($_FILES['story_background']) || ! is_array($_FILES['story_background'])) {
        $error = 'Vyberte prosím obrázek pozadí.';
    } else {
        $backgroundPath = storeUploadedImage($_FILES['story_background'], __DIR__ . '/../../../uploads', $backgroundError);

        if ($backgroundPath === null) {
            $error = $backgroundError !== null && $backgroundError !== ''
                ? 'Pozadí pro story se nepodařilo uložit. ' . $backgroundError
                : 'Pozadí pro story se nepodařilo uložit.';
        } else {
            $previousBackground = trim((string) ($siteSettings['availability_story_background'] ?? ''));
            if ($previousBackground !== '' && str_starts_with($previousBackground, 'uploads/')) {
                $previousPath = __DIR__ . '/../../../' . ltrim($previousBackground, '/');
                if (is_file($previousPath)) {
                    @unlink($previousPath);
                }
            }

            if (saveSiteSetting($connection, 'availability_story_background', $backgroundPath)) {
                $siteSettings['availability_story_background'] = $backgroundPath;
                $message = 'Pozadí pro Instagram story bylo uloženo.';
            } else {
                $error = 'Pozadí pro story se nepodařilo uložit do nastavení.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_availability_story_background'])) {
    $previousBackground = trim((string) ($siteSettings['availability_story_background'] ?? ''));

    if (saveSiteSetting($connection, 'availability_story_background', '')) {
        $siteSettings['availability_story_background'] = '';
        if ($previousBackground !== '' && str_starts_with($previousBackground, 'uploads/')) {
            $previousPath = __DIR__ . '/../../../' . ltrim($previousBackground, '/');
            if (is_file($previousPath)) {
                @unlink($previousPath);
            }
        }
        $message = 'Pozadí pro Instagram story bylo odstraněno.';
    } else {
        $error = 'Pozadí pro story se nepodařilo odstranit.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_reservation'])) {
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    $status = trim((string) ($_POST['stav'] ?? 'nova'));
    $adminNote = trim((string) ($_POST['poznamka_admina'] ?? ''));
    $cancelReason = trim((string) ($_POST['duvod_zruseni'] ?? ''));
    $dateTimeRaw = trim((string) ($_POST['datum_cas'] ?? ''));
    if (array_key_exists($status, reservationStatusOptions()) && $reservationId > 0) {
        $reservationBeforeUpdate = loadReservationDetails($connection, $reservationId);
        if ($reservationBeforeUpdate === null) {
            $error = 'Rezervace nebyla nalezena.';
            $statement = null;
        } else {
            $previousStatus = (string) ($reservationBeforeUpdate['stav'] ?? '');
            $previousDateTime = (string) ($reservationBeforeUpdate['datum_cas'] ?? '');
            $serviceId = (int) ($reservationBeforeUpdate['service_id'] ?? 0);
            $dateTimeForSave = str_replace('T', ' ', $dateTimeRaw);
            if (strlen($dateTimeForSave) === 16) {
                $dateTimeForSave .= ':00';
            }
            $dateTimeChanged = $dateTimeForSave !== '' && $dateTimeForSave !== $previousDateTime;

            if ($dateTimeForSave === '') {
                $error = 'Vyplňte prosím termín rezervace.';
                $statement = null;
            } elseif ($dateTimeChanged && in_array($previousStatus, ['zrusena', 'dokoncena'], true)) {
                $error = 'Zrušenou nebo dokončenou rezervaci nelze přesunout.';
                $statement = null;
            } elseif ($dateTimeChanged && ! isValidReservationSlot($connection, $serviceId, $dateTimeForSave)) {
                $error = 'Vybraný termín už není volný nebo neodpovídá dostupnosti.';
                $statement = null;
            } elseif ($status === 'zrusena' && $previousStatus !== 'zrusena' && $cancelReason === '') {
                $error = 'Při zrušení rezervace vyplňte důvod zrušení.';
                $statement = null;
            } elseif ($status === 'zrusena') {
                $cancelledBy = (bool) ($_SESSION['ppstudio_admin_authenticated'] ?? false) ? 'admin_full' : 'admin_lite';
                $cancelledByUser = (bool) ($_SESSION['ppstudio_admin_authenticated'] ?? false)
                    ? trim((string) ($_SESSION['ppstudio_admin_username'] ?? 'admin'))
                    : trim((string) ($_SESSION['ppstudio_admin_lite_username'] ?? 'staff'));
                if ($previousStatus === 'zrusena') {
                    $statement = $connection->prepare(
                        'UPDATE rezervace
                         SET datum_cas = ?, stav = ?, poznamka_admina = ?, duvod_zruseni = ?, zruseno_kym = ?, zruseno_uzivatel = COALESCE(zruseno_uzivatel, ?), zruseno_at = COALESCE(zruseno_at, NOW())
                         WHERE id = ?'
                    );
                } else {
                    $statement = $connection->prepare(
                        'UPDATE rezervace
                         SET datum_cas = ?, stav = ?, poznamka_admina = ?, duvod_zruseni = ?, zruseno_kym = ?, zruseno_uzivatel = ?, zruseno_at = NOW()
                         WHERE id = ?'
                    );
                }
            } else {
                $statement = $connection->prepare('UPDATE rezervace SET datum_cas = ?, stav = ?, poznamka_admina = ? WHERE id = ?');
            }
        }
        if ($statement) {
            if ($status === 'zrusena') {
                $statement->bind_param('ssssssi', $dateTimeForSave, $status, $adminNote, $cancelReason, $cancelledBy, $cancelledByUser, $reservationId);
            } else {
                $statement->bind_param('sssi', $dateTimeForSave, $status, $adminNote, $reservationId);
            }
            if ($statement->execute()) {
                $reservationAfterUpdate = loadReservationDetails($connection, $reservationId);

                if ($reservationBeforeUpdate !== null && $reservationAfterUpdate !== null) {
                    $previousStatus = (string) ($reservationBeforeUpdate['stav'] ?? '');
                    $newStatus = (string) ($reservationAfterUpdate['stav'] ?? '');

                    if ($previousStatus !== 'potvrzena' && $newStatus === 'potvrzena') {
                        sendReservationConfirmedEmail($emailConfig, $siteSettings, $reservationAfterUpdate);
                    }
                    $newDateTime = (string) ($reservationAfterUpdate['datum_cas'] ?? '');
                    if ($newStatus !== 'zrusena' && $newDateTime !== '' && $previousDateTime !== '' && $newDateTime !== $previousDateTime) {
                        sendReservationConfirmedEmail($emailConfig, $siteSettings, $reservationAfterUpdate, [
                            'previous_datetime' => $previousDateTime,
                        ]);
                        securityEventLog('reservation_admin_rescheduled', 'admin_reservation', 'info', [
                            'reservation_id' => $reservationId,
                            'old_datetime' => $previousDateTime,
                            'new_datetime' => $newDateTime,
                        ]);
                    }

                    if ($previousStatus !== 'zrusena' && $newStatus === 'zrusena') {
                        sendReservationCancelledEmail($emailConfig, $siteSettings, $reservationAfterUpdate);
                        securityEventLog('reservation_admin_cancelled', 'admin_reservation', 'warning', [
                            'reservation_id' => $reservationId,
                            'cancelled_by' => $cancelledBy,
                            'cancelled_by_user' => $cancelledByUser,
                            'cancel_reason' => $cancelReason,
                        ]);
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
