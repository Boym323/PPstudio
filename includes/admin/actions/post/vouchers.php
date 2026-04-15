<?php

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
