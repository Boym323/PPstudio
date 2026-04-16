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

use PPStudio\Service\AdminReservationMutationService;

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
$reservationMutationService = AdminReservationMutationService::create($connection, $emailConfig, $siteSettings);

if (isset($_POST['delete_reservation'])) {
    $result = $reservationMutationService->deleteReservation($_POST);
} else {
    $result = $reservationMutationService->updateReservation($_POST, $_SESSION);
}

$httpCode = (int) ($result['http_code'] ?? (($result['success'] ?? false) ? 200 : 500));
$payload = [
    'success' => (bool) ($result['success'] ?? false),
];

if (($result['success'] ?? false) === true) {
    $payload['message'] = (string) ($result['message'] ?? '');
    $payload['data'] = is_array($result['data'] ?? null) ? $result['data'] : [];
} else {
    $payload['error'] = (string) ($result['error'] ?? 'Požadavek se nepodařilo zpracovat.');
}

$connection->close();
http_response_code($httpCode);

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
