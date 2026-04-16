<?php

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
            if ($dateTimeChanged) {
                $rescheduleResult = rescheduleReservationWithLock($connection, $reservationId, $dateTimeForSave);
                if (($rescheduleResult['status'] ?? 'error') === 'slot_unavailable') {
                    $error = 'Vybraný termín už není volný nebo neodpovídá dostupnosti.';
                    $statement->close();
                    $statement = null;
                } elseif (($rescheduleResult['status'] ?? 'error') !== 'ok') {
                    $error = 'Rezervaci se nepodařilo přesunout.';
                    $statement->close();
                    $statement = null;
                } else {
                    $dateTimeForSave = (string) ($rescheduleResult['date_time'] ?? $dateTimeForSave);
                }
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
        $status = 'potvrzena';
        $dateTimeForSave = strlen($dateTime) === 16 ? $dateTime . ':00' : $dateTime;
        $reservationInsert = createReservationWithLock(
            $connection,
            $manualReservationForm['jmeno'],
            $manualReservationForm['email'],
            $manualReservationForm['telefon'],
            $manualReservationForm['zdroj'],
            $manualReservationForm['poznamka_klienta'],
            $serviceId,
            $dateTimeForSave,
            $status
        );

        if (($reservationInsert['status'] ?? 'error') === 'ok') {
            $service = is_array($reservationInsert['service'] ?? null) ? $reservationInsert['service'] : [];
            $servicePrice = $reservationInsert['service_price'] ?? null;
            $reservation = [
                'id' => (int) ($reservationInsert['reservation_id'] ?? 0),
                'jmeno' => $manualReservationForm['jmeno'],
                'email' => $manualReservationForm['email'],
                'telefon' => $manualReservationForm['telefon'],
                'zdroj' => $manualReservationForm['zdroj'],
                'poznamka_klienta' => $manualReservationForm['poznamka_klienta'],
                'datum_cas' => (string) ($reservationInsert['date_time'] ?? $dateTimeForSave),
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
        } elseif (in_array($reservationInsert['status'] ?? 'error', ['slot_unavailable', 'service_unavailable'], true)) {
            $error = 'Vybraný termín už není volný nebo neodpovídá dostupnosti.';
        } else {
            $error = 'Ruční rezervaci se nepodařilo uložit.';
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
