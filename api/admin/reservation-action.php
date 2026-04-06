<?php
declare(strict_types=1);

require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/security_events.php';
require __DIR__ . '/../../includes/settings.php';
require __DIR__ . '/../../includes/mailer.php';

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

$dbConfig = require __DIR__ . '/../../config/database.php';
$emailConfig = require __DIR__ . '/../../config/email.php';

$connection = @new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['database']
);

if ($connection->connect_errno) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Nepodařilo se připojit k databázi.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$connection->set_charset($dbConfig['charset']);
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
$statement = $connection->prepare('UPDATE rezervace SET stav = ?, poznamka_admina = ? WHERE id = ?');

if (! $statement) {
    $connection->close();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Rezervaci se nepodařilo upravit.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$statement->bind_param('ssi', $status, $adminNote, $reservationId);
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

$connection->close();

echo json_encode([
    'success' => true,
    'message' => 'Rezervace byla upravena.',
    'data' => [
        'reservation_id' => $reservationId,
        'status_key' => $status,
        'status_label' => reservationStatusLabel($status),
        'admin_note' => $adminNote,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

