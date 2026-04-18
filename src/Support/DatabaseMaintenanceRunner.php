<?php
declare(strict_types=1);

namespace PPStudio\Support;

use mysqli;
use mysqli_result;
use RuntimeException;
use Throwable;

final class DatabaseMaintenanceRunner
{
    public function __construct(
        private readonly string $projectRoot
    ) {
    }

    public function run(): int
    {
        if (PHP_SAPI !== 'cli') {
            http_response_code(403);
            echo "Tento skript lze spouštět jen z CLI.\n";
            return 1;
        }

        require_once $this->projectRoot . '/includes/bootstrap.php';
        require_once $this->projectRoot . '/config/app.php';

        try {
            $connection = $this->connect();
        } catch (Throwable $exception) {
            fwrite(STDERR, "DB connection failed: " . $exception->getMessage() . "\n");
            return 1;
        }

        $files = [
            $this->projectRoot . '/database/update_integrity_and_indexes.sql',
            $this->projectRoot . '/database/cleanup_redundant_indexes.sql',
        ];

        $this->printSection('Applying SQL');

        try {
            foreach ($files as $file) {
                $this->runSqlFile($connection, $file);
                echo "OK  {$file}\n";
            }
        } catch (Throwable $exception) {
            fwrite(STDERR, "Migration failed: " . $exception->getMessage() . "\n");
            $connection->close();
            return 1;
        }

        $this->printSection('Index Verification');

        $indexChecks = [
            'rezervace' => $this->fetchAll(
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
            'dostupnost' => $this->fetchAll(
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

        $this->printSection('Constraint Verification');

        $constraintRows = $this->fetchAll(
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

        $this->printSection('Generated Column Verification');

        $generatedColumnRows = $this->fetchAll(
            $connection,
            "SELECT column_name, generation_expression
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'historie_cen_sluzeb'
               AND column_name = 'otevrena_flag'"
        );
        echo json_encode($generatedColumnRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";

        $this->printSection('Explain Plans');

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
            $rows = $this->fetchAll($connection, $sql);
            echo $label . ': ' . json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }

        $connection->close();

        $this->printSection('Done');
        echo "DB maintenance completed successfully.\n";

        return 0;
    }

    private function connect(): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        return \PPStudio\Database\DatabaseFactory::connect();
    }

    private function runSqlFile(mysqli $connection, string $path): void
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(mysqli $connection, string $sql): array
    {
        $result = $connection->query($sql);
        if (! $result instanceof mysqli_result) {
            return [];
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        return is_array($rows) ? $rows : [];
    }

    private function printSection(string $title): void
    {
        echo "\n=== {$title} ===\n";
    }
}
