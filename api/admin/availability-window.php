<?php
declare(strict_types=1);

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/security.php';

startSecureSession();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$isAdmin = (bool) ($_SESSION['ppstudio_admin_authenticated'] ?? false);
$isAdminLite = (bool) ($_SESSION['ppstudio_admin_lite_authenticated'] ?? false);

if (! $isAdmin && ! $isAdminLite) {
    http_response_code(401);
    echo json_encode(
        ['success' => false, 'message' => 'Nejste přihlášeni.'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(
        ['success' => false, 'message' => 'Nepodporovaná metoda.'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if (! isValidCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
    http_response_code(419);
    echo json_encode(
        ['success' => false, 'message' => 'Platnost formuláře vypršela.'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

$windowId = (int) ($_POST['window_id'] ?? 0);
if ($windowId <= 0) {
    http_response_code(422);
    echo json_encode(
        ['success' => false, 'message' => 'Neplatné okno.'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

$connection = \PPStudio\Database\DatabaseFactory::tryConnect();
if (! $connection instanceof mysqli) {
    http_response_code(500);
    echo json_encode(
        ['success' => false, 'message' => 'Databáze není dostupná.'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$deletedWindow = null;
$availabilityRows = [];

try {
    $connection->begin_transaction();

    $selectStatement = $connection->prepare(
        'SELECT id, start_at, end_at, poznamka
         FROM dostupnost
         WHERE id = ?
         LIMIT 1'
    );
    $selectStatement->bind_param('i', $windowId);
    $selectStatement->execute();
    $selectedResult = $selectStatement->get_result();
    $selectedRow = $selectedResult instanceof mysqli_result ? $selectedResult->fetch_assoc() : null;
    if ($selectedResult instanceof mysqli_result) {
        $selectedResult->free();
    }
    $selectStatement->close();

    if (! is_array($selectedRow)) {
        $connection->rollback();
        http_response_code(404);
        echo json_encode(
            ['success' => false, 'message' => 'Okno už neexistuje.'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $connection->close();
        exit;
    }

    $deleteStatement = $connection->prepare('DELETE FROM dostupnost WHERE id = ? LIMIT 1');
    $deleteStatement->bind_param('i', $windowId);
    $deleteStatement->execute();
    $deletedAffected = $deleteStatement->affected_rows;
    $deleteStatement->close();

    if ($deletedAffected < 1) {
        $connection->rollback();
        http_response_code(500);
        echo json_encode(
            ['success' => false, 'message' => 'Okno se nepodařilo odstranit.'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $connection->close();
        exit;
    }

    $deletedWindow = [
        'id' => (int) ($selectedRow['id'] ?? 0),
        'start_at' => (string) ($selectedRow['start_at'] ?? ''),
        'end_at' => (string) ($selectedRow['end_at'] ?? ''),
        'note' => (string) ($selectedRow['poznamka'] ?? ''),
    ];

    $availabilityQuery = $connection->query(
        'SELECT id, start_at, end_at, poznamka
         FROM dostupnost
         WHERE end_at >= NOW()
         ORDER BY start_at ASC
         LIMIT 400'
    );

    if ($availabilityQuery instanceof mysqli_result) {
        while ($row = $availabilityQuery->fetch_assoc()) {
            $startAt = (string) ($row['start_at'] ?? '');
            $endAt = (string) ($row['end_at'] ?? '');
            $availabilityRows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'date_label' => formatCzechDate(substr($startAt, 0, 10)),
                'time_label' => substr($startAt, 11, 5) . ' - ' . substr($endAt, 11, 5),
                'note' => (string) ($row['poznamka'] ?? ''),
            ];
        }
        $availabilityQuery->free();
    }

    $connection->commit();
} catch (Throwable $exception) {
    $connection->rollback();
    http_response_code(500);
    echo json_encode(
        ['success' => false, 'message' => 'Okno se nepodařilo odstranit.'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    $connection->close();
    exit;
}

$connection->close();

echo json_encode(
    [
        'success' => true,
        'message' => 'Okno odstraněno.',
        'deleted_window' => $deletedWindow,
        'availability_rows' => $availabilityRows,
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
