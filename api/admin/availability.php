<?php
declare(strict_types=1);

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/security.php';
require __DIR__ . '/../../includes/availability.php';

startSecureSession();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$isAdmin = (bool) ($_SESSION['ppstudio_admin_authenticated'] ?? false);
$isAdminLite = (bool) ($_SESSION['ppstudio_admin_lite_authenticated'] ?? false);

if (! $isAdmin && ! $isAdminLite) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Nejste přihlášeni do administrace.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$serviceId = (int) ($_GET['service_id'] ?? 0);
$date = trim((string) ($_GET['date'] ?? ''));

if ($serviceId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Neplatná služba.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($date !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(422);
    echo json_encode(['error' => 'Neplatný formát data.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}


$connection = \PPStudio\Database\DatabaseFactory::tryConnect();

if (! $connection instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['error' => 'Databáze není dostupná.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($date !== '') {
    $times = getAvailableTimesForDate($connection, $serviceId, $date);
    echo json_encode(['times' => $times], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $connection->close();
    exit;
}

$days = getAvailableDays($connection, $serviceId);
echo json_encode(['days' => $days], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$connection->close();
