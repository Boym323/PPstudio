<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use Throwable;

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
        $result = $this->saveAvailabilityGridDetailed($post);

        if (($result['success'] ?? false) === true) {
            return $this->success('Dostupnost v kalendáři byla uložena.', (array) ($result['data'] ?? []));
        }

        return $this->error('Kalendář dostupnosti se nepodařilo uložit.', (array) ($result['data'] ?? []));
    }

    public function saveAvailabilityGridDetailed(array $post): array
    {
        $rangeStart = trim((string) ($post['planner_start'] ?? ''));
        $rangeEnd = trim((string) ($post['planner_end'] ?? ''));
        $windowsJson = (string) ($post['planner_windows'] ?? '[]');
        $windows = json_decode($windowsJson, true);

        if (
            $rangeStart === ''
            || $rangeEnd === ''
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $rangeStart) !== 1
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $rangeEnd) !== 1
            || ! is_array($windows)
        ) {
            return $this->error('Dostupnost se nepodařilo uložit.', [
                'status_code' => 422,
            ]);
        }

        $deleteRangeStart = $rangeStart . ' 00:00:00';
        $deleteRangeEnd = date('Y-m-d H:i:s', strtotime($rangeEnd . ' +1 day'));
        $inserted = 0;
        $invalid = 0;
        $deleted = 0;

        try {
            $this->connection->begin_transaction();

            $deleteStatement = $this->connection->prepare(
                'DELETE FROM dostupnost
                 WHERE start_at >= ?
                   AND start_at < ?'
            );

            if (! $deleteStatement) {
                $this->connection->rollback();

                return $this->error('Dostupnost se nepodařilo uložit.', [
                    'status_code' => 500,
                ]);
            }

            $deleteStatement->bind_param('ss', $deleteRangeStart, $deleteRangeEnd);
            $deleteStatement->execute();
            $deleted = $deleteStatement->affected_rows;
            $deleteStatement->close();

            $insertStatement = $this->connection->prepare(
                'INSERT INTO dostupnost (start_at, end_at, poznamka)
                 VALUES (?, ?, ?)'
            );

            if (! $insertStatement) {
                $this->connection->rollback();

                return $this->error('Dostupnost se nepodařilo uložit.', [
                    'status_code' => 500,
                ]);
            }

            $note = 'Dostupnost';

            foreach ($windows as $window) {
                if (! is_array($window)) {
                    $invalid++;
                    continue;
                }

                $startAt = trim((string) ($window['start_at'] ?? ''));
                $endAt = trim((string) ($window['end_at'] ?? ''));
                $isValidDateTime = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $startAt) === 1
                    && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $endAt) === 1;

                if (! $isValidDateTime || $startAt >= $endAt) {
                    $invalid++;
                    continue;
                }

                $insertStatement->bind_param('sss', $startAt, $endAt, $note);
                $insertStatement->execute();
                $inserted++;
            }

            $insertStatement->close();

            $readService = new AdminAvailabilityReadService($this->connection);
            $availabilityRows = $readService->loadAvailabilityRowsForApi();

            $this->connection->commit();

            $message = 'Dostupnost uložena.';
            if ($invalid > 0) {
                $message .= ' Některé položky byly přeskočeny.';
            }

            return $this->success($message, [
                'stats' => [
                    'inserted' => $inserted,
                    'deleted' => $deleted,
                    'invalid' => $invalid,
                ],
                'availability_rows' => $availabilityRows,
            ]);
        } catch (Throwable) {
            $this->connection->rollback();

            return $this->error('Dostupnost se nepodařilo uložit.', [
                'status_code' => 500,
            ]);
        }
    }

    public function deleteWindow(array $post): array
    {
        $result = $this->deleteWindowDetailed($post);

        if (($result['success'] ?? false) === true) {
            return $this->success('Volné okno bylo odstraněno.', (array) ($result['data'] ?? []));
        }

        return $this->error('Okno se nepodařilo odstranit.', (array) ($result['data'] ?? []));
    }

    public function deleteWindowDetailed(array $post): array
    {
        $windowId = (int) ($post['window_id'] ?? 0);
        if ($windowId <= 0) {
            return $this->error('Neplatné okno.', [
                'status_code' => 422,
            ]);
        }

        try {
            $this->connection->begin_transaction();

            $selectStatement = $this->connection->prepare(
                'SELECT id, start_at, end_at, poznamka
                 FROM dostupnost
                 WHERE id = ?
                 LIMIT 1'
            );

            if (! $selectStatement) {
                $this->connection->rollback();

                return $this->error('Okno se nepodařilo odstranit.', [
                    'status_code' => 500,
                ]);
            }

            $selectStatement->bind_param('i', $windowId);
            $selectStatement->execute();
            $result = $selectStatement->get_result();
            $selectedRow = $result ? ($result->fetch_assoc() ?: null) : null;
            if ($result) {
                $result->free();
            }
            $selectStatement->close();

            if (! is_array($selectedRow)) {
                $this->connection->rollback();

                return $this->error('Okno už neexistuje.', [
                    'status_code' => 404,
                ]);
            }

            $deleteStatement = $this->connection->prepare('DELETE FROM dostupnost WHERE id = ? LIMIT 1');
            if (! $deleteStatement) {
                $this->connection->rollback();

                return $this->error('Okno se nepodařilo odstranit.', [
                    'status_code' => 500,
                ]);
            }

            $deleteStatement->bind_param('i', $windowId);
            $deleteStatement->execute();
            $deletedAffected = $deleteStatement->affected_rows;
            $deleteStatement->close();

            if ($deletedAffected < 1) {
                $this->connection->rollback();

                return $this->error('Okno se nepodařilo odstranit.', [
                    'status_code' => 500,
                ]);
            }

            $readService = new AdminAvailabilityReadService($this->connection);
            $availabilityRows = $readService->loadAvailabilityRowsForApi();

            $this->connection->commit();

            return $this->success('Okno odstraněno.', [
                'deleted_window' => [
                    'id' => (int) ($selectedRow['id'] ?? 0),
                    'start_at' => (string) ($selectedRow['start_at'] ?? ''),
                    'end_at' => (string) ($selectedRow['end_at'] ?? ''),
                    'note' => (string) ($selectedRow['poznamka'] ?? ''),
                ],
                'availability_rows' => $availabilityRows,
            ]);
        } catch (Throwable) {
            $this->connection->rollback();

            return $this->error('Okno se nepodařilo odstranit.', [
                'status_code' => 500,
            ]);
        }
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
