<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Cli;

use mysqli;
use PDO;
use PPStudio\Service\ReservationReminderService;
use Throwable;

final class ReservationReminderApplication
{
    public function __construct(
        private ReservationReminderConnectionFactory $connectionFactory,
        private ReservationReminderSiteSettingsLoader $siteSettingsLoader,
        private array $emailConfig
    ) {
    }

    public static function create(array $emailConfig): self
    {
        return new self(
            new ReservationReminderConnectionFactory(),
            new ReservationReminderSiteSettingsLoader(),
            $emailConfig
        );
    }

    /**
     * @param array<int, string> $argv
     */
    public function handle(array $argv): never
    {
        $dryRun = in_array('--dry-run', $argv, true);

        try {
            $connection = $this->connectionFactory->create();
        } catch (Throwable $exception) {
            fwrite(STDERR, $exception->getMessage() . "\n");
            exit(1);
        }

        try {
            $siteSettings = $this->siteSettingsLoader->load($connection);
            $reminderService = new ReservationReminderService($connection, $this->emailConfig);
            $result = $reminderService->run($siteSettings, $dryRun);
        } catch (Throwable $exception) {
            $this->closeConnection($connection);
            fwrite(STDERR, $exception->getMessage() . "\n");
            exit(1);
        }

        $this->closeConnection($connection);

        echo sprintf(
            "Reservation reminders%s: run=%s sent=%d failed=%d skipped=%d window=%s..%s\n",
            $dryRun ? ' (dry-run)' : '',
            $result['run_token'],
            $result['sent'],
            $result['failed'],
            $result['skipped'],
            $result['window_start'],
            $result['window_end']
        );

        exit(0);
    }

    /**
     * @param mysqli|PDO $connection
     */
    private function closeConnection(mysqli|PDO $connection): void
    {
        if ($connection instanceof mysqli) {
            $connection->close();
        }
    }
}
