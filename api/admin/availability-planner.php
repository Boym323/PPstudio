<?php
declare(strict_types=1);

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../config/app.php';
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

$rangeStart = trim((string) ($_POST['planner_start'] ?? ''));
$rangeEnd = trim((string) ($_POST['planner_end'] ?? ''));
$windowsJson = (string) ($_POST['planner_windows'] ?? '[]');
$windows = json_decode($windowsJson, true);

if (
    $rangeStart === ''
    || $rangeEnd === ''
    || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $rangeStart)
    || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $rangeEnd)
    || ! is_array($windows)
) {
    http_response_code(422);
    echo json_encode(
        ['success' => false, 'message' => 'Dostupnost se nepodařilo uložit.'],
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

$deleteRangeStart = $rangeStart . ' 00:00:00';
$deleteRangeEnd = date('Y-m-d H:i:s', strtotime($rangeEnd . ' +1 day'));
$inserted = 0;
$invalid = 0;
$deleted = 0;
    $availabilityRows = [];
    $note = 'Dostupnost';

try {
    $connection->begin_transaction();

    $deleteStatement = $connection->prepare(
        'DELETE FROM dostupnost
         WHERE start_at >= ?
           AND start_at < ?'
    );
    $deleteStatement->bind_param('ss', $deleteRangeStart, $deleteRangeEnd);
    $deleteStatement->execute();
    $deleted = $deleteStatement->affected_rows;
    $deleteStatement->close();

    $insertStatement = $connection->prepare(
        'INSERT INTO dostupnost (start_at, end_at, poznamka)
         VALUES (?, ?, ?)'
    );

    foreach ($windows as $window) {
        if (! is_array($window)) {
            $invalid += 1;
            continue;
        }

        $startAt = trim((string) ($window['start_at'] ?? ''));
        $endAt = trim((string) ($window['end_at'] ?? ''));
        $isValidDateTime = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $startAt) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $endAt) === 1;

        if (! $isValidDateTime || $startAt >= $endAt) {
            $invalid += 1;
            continue;
        }

        $insertStatement->bind_param('sss', $startAt, $endAt, $note);
        $insertStatement->execute();
        $inserted += 1;
    }

    $insertStatement->close();

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
        ['success' => false, 'message' => 'Dostupnost se nepodařilo uložit.'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    $connection->close();
    exit;
}

$connection->close();

$message = 'Dostupnost uložena.';
if ($invalid > 0) {
    $message .= ' Některé položky byly přeskočeny.';
}

echo json_encode(
    [
        'success' => true,
        'message' => $message,
        'stats' => [
            'inserted' => $inserted,
            'deleted' => $deleted,
            'invalid' => $invalid,
        ],
        'availability_rows' => $availabilityRows,
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
