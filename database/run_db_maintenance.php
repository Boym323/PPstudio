<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Tento skript lze spouštět jen z CLI.\n";
    exit(1);
}

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/config/app.php';

function maintenanceConnect(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    return \PPStudio\Database\DatabaseFactory::connect();
}

function maintenanceRunSqlFile(mysqli $connection, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Soubor se nepodařilo načíst: {$path}");
    }

    $connection->multi_query($sql);

    do {
        if ($result = $connection->store_result()) {
            $result->free();
        }
    } while ($connection->more_results() && $connection->next_result());
}

function maintenanceFetchAll(mysqli $connection, string $sql): array
{
    $result = $connection->query($sql);
    if (! $result instanceof mysqli_result) {
        return [];
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();

    return is_array($rows) ? $rows : [];
}

function maintenancePrintSection(string $title): void
{
    echo "\n=== {$title} ===\n";
}

try {
    $connection = maintenanceConnect();
} catch (Throwable $exception) {
    fwrite(STDERR, "DB connection failed: " . $exception->getMessage() . "\n");
    exit(1);
}

$baseDir = __DIR__;
$files = [
    $baseDir . '/update_integrity_and_indexes.sql',
    $baseDir . '/cleanup_redundant_indexes.sql',
];

maintenancePrintSection('Applying SQL');

try {
    foreach ($files as $file) {
        maintenanceRunSqlFile($connection, $file);
        echo "OK  {$file}\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "Migration failed: " . $exception->getMessage() . "\n");
    $connection->close();
    exit(1);
}

maintenancePrintSection('Index Verification');

$indexChecks = [
    'rezervace' => maintenanceFetchAll(
        $connection,
        "SELECT DISTINCT index_name
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = 'rezervace'
           AND index_name IN (
             'idx_rezervace_stav_datum_cas',
             'idx_rezervace_datum_cas_stav',
             'idx_rezervace_sluzba_datum_cas_stav',
             'idx_rezervace_reminder_queue',
             'idx_rezervace_stav_datum'
           )
         ORDER BY index_name"
    ),
    'dostupnost' => maintenanceFetchAll(
        $connection,
        "SELECT DISTINCT index_name
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = 'dostupnost'
           AND index_name IN (
             'idx_dostupnost_start_end',
             'idx_dostupnost_end_start',
             'idx_dostupnost_start_at'
           )
         ORDER BY index_name"
    ),
];

foreach ($indexChecks as $table => $rows) {
    echo $table . ': ' . json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

maintenancePrintSection('Constraint Verification');

$constraintRows = maintenanceFetchAll(
    $connection,
    "SELECT table_name, constraint_name
     FROM information_schema.table_constraints
     WHERE table_schema = DATABASE()
       AND constraint_name IN (
         'chk_dostupnost_time_order',
         'chk_historie_cen_sluzeb_time_order',
         'chk_poukazy_nonnegative',
         'chk_poukaz_cerpani_castka_positive'
       )
     ORDER BY table_name, constraint_name"
);
echo json_encode($constraintRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";

maintenancePrintSection('Generated Column Verification');

$generatedColumnRows = maintenanceFetchAll(
    $connection,
    "SELECT column_name, generation_expression
     FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'historie_cen_sluzeb'
       AND column_name = 'otevrena_flag'"
);
echo json_encode($generatedColumnRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";

maintenancePrintSection('Explain Plans');

$sampleDate = date('Y-m-d');
$explainQueries = [
    'availability_windows' => "EXPLAIN SELECT id, start_at, end_at
        FROM dostupnost
        WHERE start_at < DATE_ADD('{$sampleDate} 00:00:00', INTERVAL 1 DAY)
          AND end_at > '{$sampleDate} 00:00:00'
          AND end_at > start_at
        ORDER BY start_at ASC",
    'reservation_today' => "EXPLAIN SELECT r.id, r.datum_cas, r.stav
        FROM rezervace r
        WHERE r.datum_cas >= CURDATE()
          AND r.datum_cas < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ORDER BY r.datum_cas ASC
        LIMIT 25",
    'reservation_status_date' => "EXPLAIN SELECT r.id
        FROM rezervace r
        WHERE r.stav IN ('nova', 'potvrzena')
          AND r.datum_cas >= NOW()
        ORDER BY r.datum_cas ASC
        LIMIT 10",
];

foreach ($explainQueries as $label => $sql) {
    $rows = maintenanceFetchAll($connection, $sql);
    echo $label . ': ' . json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

$connection->close();

maintenancePrintSection('Done');
echo "DB maintenance completed successfully.\n";
