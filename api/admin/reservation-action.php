<?php
declare(strict_types=1);

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/security_events.php';
require __DIR__ . '/../../includes/settings.php';
require __DIR__ . '/../../includes/mailer.php';
require __DIR__ . '/../../includes/availability.php';

startSecureSession();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$isAdmin = (bool) ($_SESSION['ppstudio_admin_authenticated'] ?? false);
$isAdminLite = (bool) ($_SESSION['ppstudio_admin_lite_authenticated'] ?? false);

if (! $isAdmin && ! $isAdminLite) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Nejste přihlášeni do administrace.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Neplatná metoda požadavku.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (! isValidCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
    http_response_code(419);
    echo json_encode([
        'success' => false,
        'error' => 'Platnost formuláře vypršela. Obnovte stránku.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$emailConfig = require __DIR__ . '/../../config/email.php';

$connection = \PPStudio\Database\DatabaseFactory::tryConnect();

if (! $connection instanceof mysqli) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Nepodařilo se připojit k databázi.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$siteSettings = loadSiteSettings($connection);
$reservationId = (int) ($_POST['reservation_id'] ?? 0);

if ($reservationId <= 0) {
    $connection->close();
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Neplatné ID rezervace.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$isDelete = isset($_POST['delete_reservation']);

if ($isDelete) {
    $statement = $connection->prepare('DELETE FROM rezervace WHERE id = ? LIMIT 1');
    if (! $statement) {
        $connection->close();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Rezervaci se nepodařilo smazat.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $statement->bind_param('i', $reservationId);
    $ok = $statement->execute();
    $statement->close();
    $connection->close();

    if (! $ok) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Rezervaci se nepodařilo smazat.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Rezervace byla smazána.',
        'data' => [
            'reservation_id' => $reservationId,
            'deleted' => true,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$status = trim((string) ($_POST['stav'] ?? 'nova'));
$adminNote = trim((string) ($_POST['poznamka_admina'] ?? ''));
$cancelReason = trim((string) ($_POST['duvod_zruseni'] ?? ''));
$dateTimeRaw = trim((string) ($_POST['datum_cas'] ?? ''));
$allowedStatuses = reservationStatusOptions();

if (! array_key_exists($status, $allowedStatuses)) {
    $connection->close();
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Neplatný stav rezervace.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$reservationBeforeUpdate = loadReservationDetails($connection, $reservationId);
if ($reservationBeforeUpdate === null) {
    $connection->close();
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Rezervace nebyla nalezena.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$previousStatus = (string) ($reservationBeforeUpdate['stav'] ?? '');
$previousDateTime = (string) ($reservationBeforeUpdate['datum_cas'] ?? '');
$serviceId = (int) ($reservationBeforeUpdate['service_id'] ?? 0);
$dateTimeForSave = str_replace('T', ' ', $dateTimeRaw);
if (strlen($dateTimeForSave) === 16) {
    $dateTimeForSave .= ':00';
}
$dateTimeChanged = $dateTimeForSave !== '' && $dateTimeForSave !== $previousDateTime;

if ($dateTimeForSave === '') {
    $connection->close();
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Vyplňte prosím termín rezervace.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($dateTimeChanged && in_array($previousStatus, ['zrusena', 'dokoncena'], true)) {
    $connection->close();
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Zrušenou nebo dokončenou rezervaci nelze přesunout.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($status === 'zrusena' && $previousStatus !== 'zrusena' && $cancelReason === '') {
    $connection->close();
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Při zrušení rezervace vyplňte důvod zrušení.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$cancelledBy = $isAdmin ? 'admin_full' : 'admin_lite';
$cancelledByUser = $isAdmin
    ? trim((string) ($_SESSION['ppstudio_admin_username'] ?? 'admin'))
    : trim((string) ($_SESSION['ppstudio_admin_lite_username'] ?? 'staff'));

$statement = null;
if ($status === 'zrusena') {
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

if (! $statement) {
    $connection->close();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Rezervaci se nepodařilo upravit.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($dateTimeChanged) {
    $rescheduleResult = rescheduleReservationWithLock($connection, $reservationId, $dateTimeForSave);
    if (($rescheduleResult['status'] ?? 'error') === 'slot_unavailable') {
        $statement->close();
        $connection->close();
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'Vybraný termín už není volný nebo neodpovídá dostupnosti.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if (($rescheduleResult['status'] ?? 'error') !== 'ok') {
        $statement->close();
        $connection->close();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Rezervaci se nepodařilo přesunout.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $dateTimeForSave = (string) ($rescheduleResult['date_time'] ?? $dateTimeForSave);
}

if ($status === 'zrusena') {
    if ($previousStatus === 'zrusena') {
        $statement->bind_param('ssssssi', $dateTimeForSave, $status, $adminNote, $cancelReason, $cancelledBy, $cancelledByUser, $reservationId);
    } else {
        $statement->bind_param('ssssssi', $dateTimeForSave, $status, $adminNote, $cancelReason, $cancelledBy, $cancelledByUser, $reservationId);
    }
} else {
    $statement->bind_param('sssi', $dateTimeForSave, $status, $adminNote, $reservationId);
}
$ok = $statement->execute();
$statement->close();

if (! $ok) {
    $connection->close();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Rezervaci se nepodařilo upravit.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$reservationAfterUpdate = loadReservationDetails($connection, $reservationId);
$responseDateTimeLabel = formatCzechDateTime($dateTimeForSave);
$responseDateTimeLocal = substr($dateTimeForSave, 0, 16);
if ($reservationBeforeUpdate !== null && $reservationAfterUpdate !== null) {
    $newStatus = (string) ($reservationAfterUpdate['stav'] ?? '');
    $newDateTime = (string) ($reservationAfterUpdate['datum_cas'] ?? '');
    $responseDateTimeLabel = formatCzechDateTime($newDateTime);
    $responseDateTimeLocal = str_replace(' ', 'T', substr($newDateTime, 0, 16));

    if ($previousStatus !== 'potvrzena' && $newStatus === 'potvrzena') {
        sendReservationConfirmedEmail($emailConfig, $siteSettings, $reservationAfterUpdate);
    }
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

$connection->close();

echo json_encode([
    'success' => true,
    'message' => 'Rezervace byla upravena.',
    'data' => [
        'reservation_id' => $reservationId,
        'status_key' => $status,
        'status_label' => reservationStatusLabel($status),
        'admin_note' => $adminNote,
        'cancel_reason' => $cancelReason,
        'datetime_label' => $responseDateTimeLabel,
        'datetime_local' => $responseDateTimeLocal,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
