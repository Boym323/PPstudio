<?php
declare(strict_types=1);

namespace PPStudio\Service;

use DateTimeImmutable;
use mysqli;
use mysqli_result;
use PDO;
use RuntimeException;
use Throwable;

final class ReservationReminderService
{
    public function __construct(
        private mysqli|PDO $connection,
        private array $emailConfig,
        private ?ReservationNotificationService $notificationService = null
    ) {
        $this->notificationService ??= new ReservationNotificationService($emailConfig);
    }

    /**
     * @return array{run_token: string, sent: int, failed: int, skipped: int, candidates: int, window_start: string, window_end: string}
     */
    public function run(array $siteSettings, bool $dryRun = false): array
    {
        $leadSeconds = max(3600, (int) ($this->emailConfig['reservation_reminder_lead_seconds'] ?? 93600));
        $windowSeconds = max(900, (int) ($this->emailConfig['reservation_reminder_window_seconds'] ?? 3600));
        $runToken = $this->createRunToken();
        $now = new DateTimeImmutable('now');
        $windowStart = $now->modify('+' . $leadSeconds . ' seconds')->format('Y-m-d H:i:s');
        $windowEnd = $now->modify('+' . ($leadSeconds + $windowSeconds) . ' seconds')->format('Y-m-d H:i:s');

        try {
            $rows = $this->fetchReminderCandidates($windowStart, $windowEnd);
        } catch (Throwable $exception) {
            $this->logSummary(
                $runToken,
                'run_failed',
                'error',
                [
                    'dry_run' => $dryRun,
                    'lead_seconds' => $leadSeconds,
                    'window_seconds' => $windowSeconds,
                    'error' => $exception->getMessage(),
                    'window_start' => $windowStart,
                    'window_end' => $windowEnd,
                ]
            );

            throw $exception;
        }

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $reservation = $this->mapReservationRow($row);

            if ((string) $reservation['email'] === '') {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $sent++;
                continue;
            }

            if (! $this->notificationService->sendReminderEmail($siteSettings, $reservation)) {
                $failed++;
                $this->logSecurityEvent('reservation_reminder_failed', 'warning', $reservation);
                continue;
            }

            $this->markReminderSent((int) $reservation['id']);
            $sent++;
            $this->logSecurityEvent('reservation_reminder_sent', 'info', $reservation);
        }

        $this->logSummary(
            $runToken,
            'run_finished',
            $failed > 0 ? 'warning' : 'info',
            [
                'dry_run' => $dryRun,
                'sent' => $sent,
                'failed' => $failed,
                'skipped' => $skipped,
                'candidates' => count($rows),
                'window_start' => $windowStart,
                'window_end' => $windowEnd,
            ]
        );
        $this->cleanupLogs();

        return [
            'run_token' => $runToken,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'candidates' => count($rows),
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
        ];
    }

    private function fetchReminderCandidates(string $windowStart, string $windowEnd): array
    {
        $sql = 'SELECT r.id, r.sluzba, r.jmeno, r.email, r.telefon, r.poznamka_klienta, r.poznamka_admina, r.datum_cas, r.stav,
                       r.cena_v_dobe_rezervace, r.reminder_sent_at, s.nazev, s.doba_trvani
                FROM rezervace r
                INNER JOIN sluzby s ON s.id = r.sluzba
                WHERE r.stav = "potvrzena"
                  AND r.email <> ""
                  AND r.reminder_sent_at IS NULL
                  AND r.datum_cas >= ?
                  AND r.datum_cas < ?
                ORDER BY r.datum_cas ASC';

        if ($this->connection instanceof mysqli) {
            $statement = $this->connection->prepare($sql);
            if (! $statement) {
                throw new RuntimeException('Prepare failed: ' . $this->connection->error);
            }

            $statement->bind_param('ss', $windowStart, $windowEnd);
            $statement->execute();
            $result = $statement->get_result();
            $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            if ($result instanceof mysqli_result) {
                $result->free();
            }
            $statement->close();

            return is_array($rows) ? $rows : [];
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute([$windowStart, $windowEnd]);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    private function markReminderSent(int $reservationId): void
    {
        $sql = 'UPDATE rezervace SET reminder_sent_at = NOW() WHERE id = ? AND reminder_sent_at IS NULL LIMIT 1';

        if ($this->connection instanceof mysqli) {
            $statement = $this->connection->prepare($sql);
            if (! $statement) {
                return;
            }
            $statement->bind_param('i', $reservationId);
            $statement->execute();
            $statement->close();
            return;
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute([$reservationId]);
    }

    private function logSummary(
        string $runToken,
        string $eventType,
        string $severity = 'info',
        ?array $context = null
    ): void {
        $contextJson = $this->contextJson(is_array($context) ? $context : []);

        try {
            if ($this->connection instanceof mysqli) {
                $statement = $this->connection->prepare(
                    'INSERT INTO reservation_reminder_logs (run_token, event_type, severity, reservation_id, context_json)
                     VALUES (?, ?, ?, NULL, ?)'
                );
                if (! $statement) {
                    return;
                }
                $statement->bind_param('ssss', $runToken, $eventType, $severity, $contextJson);
                $statement->execute();
                $statement->close();
                return;
            }

            $statement = $this->connection->prepare(
                'INSERT INTO reservation_reminder_logs (run_token, event_type, severity, reservation_id, context_json)
                 VALUES (?, ?, ?, NULL, ?)'
            );
            $statement->execute([$runToken, $eventType, $severity, $contextJson]);
        } catch (Throwable) {
            // Logging nesmi nikdy zastavit odesilani reminderu.
        }
    }

    private function cleanupLogs(): void
    {
        $sql = 'DELETE FROM reservation_reminder_logs
                WHERE created_at < (NOW() - INTERVAL 90 DAY)
                ORDER BY created_at ASC
                LIMIT 500';

        try {
            if ($this->connection instanceof mysqli) {
                $statement = $this->connection->prepare($sql);
                if (! $statement) {
                    return;
                }
                $statement->execute();
                $statement->close();
                return;
            }

            $statement = $this->connection->prepare($sql);
            $statement->execute();
        } catch (Throwable) {
            // Cleanup je best-effort, nesmi shodit beh.
        }
    }

    private function mapReservationRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'service_id' => (int) ($row['sluzba'] ?? 0),
            'jmeno' => (string) ($row['jmeno'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'telefon' => (string) ($row['telefon'] ?? ''),
            'poznamka_klienta' => $row['poznamka_klienta'] ?? null,
            'poznamka_admina' => $row['poznamka_admina'] ?? null,
            'datum_cas' => (string) ($row['datum_cas'] ?? ''),
            'stav' => (string) ($row['stav'] ?? ''),
            'service_price' => isset($row['cena_v_dobe_rezervace']) ? (float) $row['cena_v_dobe_rezervace'] : null,
            'reminder_sent_at' => $row['reminder_sent_at'] ?? null,
            'service_name' => (string) ($row['nazev'] ?? ''),
            'service_duration' => isset($row['doba_trvani']) ? (int) $row['doba_trvani'] : null,
        ];
    }

    private function logSecurityEvent(string $eventType, string $severity, array $reservation): void
    {
        \(new \PPStudio\Security\SecurityFacade())->securityEventLogger()->log(
            $eventType,
            'reservation_reminder',
            $severity,
            [
                'reservation_id' => $reservation['id'],
                'reservation_datetime' => $reservation['datum_cas'],
                'email' => $reservation['email'],
            ],
            'system',
            'cli'
        );
    }

    private function createRunToken(): string
    {
        try {
            return date('YmdHis') . '-' . bin2hex(random_bytes(6));
        } catch (Throwable) {
            return date('YmdHis') . '-' . uniqid('', true);
        }
    }

    private function contextJson(array $context): string
    {
        return (string) (json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
    }
}
