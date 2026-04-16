<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;

final class AdminAvailabilityMutationService
{
    public function __construct(
        private mysqli $connection,
        private array $siteSettings,
        private string $projectRoot
    ) {
    }

    public function saveAvailabilityGrid(array $post): array
    {
        $rangeStart = trim((string) ($post['planner_start'] ?? ''));
        $rangeEnd = trim((string) ($post['planner_end'] ?? ''));
        $windowsJson = (string) ($post['planner_windows'] ?? '[]');
        $windows = json_decode($windowsJson, true);

        if ($rangeStart === '' || $rangeEnd === '' || ! is_array($windows)) {
            return $this->error('Kalendář dostupnosti se nepodařilo uložit.');
        }

        $deleteStatement = $this->connection->prepare(
            'DELETE FROM dostupnost
             WHERE start_at >= ?
               AND start_at < ?'
        );

        if (! $deleteStatement) {
            return $this->error('Kalendář dostupnosti se nepodařilo uložit.');
        }

        $deleteRangeStart = $rangeStart . ' 00:00:00';
        $deleteRangeEnd = date('Y-m-d H:i:s', strtotime($rangeEnd . ' +1 day'));
        $deleteStatement->bind_param('ss', $deleteRangeStart, $deleteRangeEnd);
        $deleteOk = $deleteStatement->execute();
        $deleteStatement->close();

        if (! $deleteOk) {
            return $this->error('Kalendář dostupnosti se nepodařilo uložit.');
        }

        $insertStatement = $this->connection->prepare(
            'INSERT INTO dostupnost (start_at, end_at, poznamka)
             VALUES (?, ?, ?)'
        );

        if (! $insertStatement) {
            return $this->error('Kalendář dostupnosti se nepodařilo uložit.');
        }

        $note = 'Kalendář dostupnosti';
        foreach ($windows as $window) {
            $startAt = (string) ($window['start_at'] ?? '');
            $endAt = (string) ($window['end_at'] ?? '');

            if ($startAt === '' || $endAt === '' || $startAt >= $endAt) {
                continue;
            }

            $insertStatement->bind_param('sss', $startAt, $endAt, $note);
            if (! $insertStatement->execute()) {
                $insertStatement->close();

                return $this->error('Kalendář dostupnosti se nepodařilo uložit.');
            }
        }

        $insertStatement->close();

        return $this->success('Dostupnost v kalendáři byla uložena.');
    }

    public function deleteWindow(array $post): array
    {
        $windowId = (int) ($post['window_id'] ?? 0);
        if ($windowId <= 0) {
            return $this->error('Okno se nepodařilo odstranit.');
        }

        $statement = $this->connection->prepare('DELETE FROM dostupnost WHERE id = ? LIMIT 1');
        if (! $statement) {
            return $this->error('Okno se nepodařilo odstranit.');
        }

        $statement->bind_param('i', $windowId);
        $ok = $statement->execute();
        $statement->close();

        if (! $ok) {
            return $this->error('Okno se nepodařilo odstranit.');
        }

        return $this->success('Volné okno bylo odstraněno.');
    }

    public function saveStoryBackground(array $files): array
    {
        $backgroundError = null;
        $backgroundFile = $files['story_background'] ?? null;

        if (! is_array($backgroundFile)) {
            return $this->error('Vyberte prosím obrázek pozadí.');
        }

        $backgroundPath = \storeUploadedImage($backgroundFile, $this->projectRoot . '/uploads', $backgroundError);
        if ($backgroundPath === null) {
            return $this->error(
                $backgroundError !== null && $backgroundError !== ''
                    ? 'Pozadí pro story se nepodařilo uložit. ' . $backgroundError
                    : 'Pozadí pro story se nepodařilo uložit.'
            );
        }

        $previousBackground = trim((string) ($this->siteSettings['availability_story_background'] ?? ''));
        if (! \saveSiteSetting($this->connection, 'availability_story_background', $backgroundPath)) {
            return $this->error('Pozadí pro story se nepodařilo uložit do nastavení.');
        }

        if ($previousBackground !== '' && str_starts_with($previousBackground, 'uploads/')) {
            $previousPath = $this->projectRoot . '/' . ltrim($previousBackground, '/');
            if (is_file($previousPath)) {
                @unlink($previousPath);
            }
        }

        $siteSettings = $this->siteSettings;
        $siteSettings['availability_story_background'] = $backgroundPath;

        return $this->success('Pozadí pro Instagram story bylo uloženo.', [
            'site_settings' => $siteSettings,
        ]);
    }

    public function deleteStoryBackground(): array
    {
        $previousBackground = trim((string) ($this->siteSettings['availability_story_background'] ?? ''));

        if (! \saveSiteSetting($this->connection, 'availability_story_background', '')) {
            return $this->error('Pozadí pro story se nepodařilo odstranit.');
        }

        if ($previousBackground !== '' && str_starts_with($previousBackground, 'uploads/')) {
            $previousPath = $this->projectRoot . '/' . ltrim($previousBackground, '/');
            if (is_file($previousPath)) {
                @unlink($previousPath);
            }
        }

        $siteSettings = $this->siteSettings;
        $siteSettings['availability_story_background'] = '';

        return $this->success('Pozadí pro Instagram story bylo odstraněno.', [
            'site_settings' => $siteSettings,
        ]);
    }

    private function success(string $message, array $data = []): array
    {
        return [
            'success' => true,
            'message' => $message,
            'error' => null,
            'data' => $data,
        ];
    }

    private function error(string $message, array $data = []): array
    {
        return [
            'success' => false,
            'message' => null,
            'error' => $message,
            'data' => $data,
        ];
    }
}
