<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_availability_grid'])) {
    $rangeStart = trim((string) ($_POST['planner_start'] ?? ''));
    $rangeEnd = trim((string) ($_POST['planner_end'] ?? ''));
    $windowsJson = (string) ($_POST['planner_windows'] ?? '[]');
    $windows = json_decode($windowsJson, true);

    if ($rangeStart === '' || $rangeEnd === '' || ! is_array($windows)) {
        $error = 'Kalendář dostupnosti se nepodařilo uložit.';
    } else {
        $deleteStatement = $connection->prepare(
            'DELETE FROM dostupnost
             WHERE start_at >= ?
               AND start_at < ?'
        );

        if ($deleteStatement) {
            $deleteRangeStart = $rangeStart . ' 00:00:00';
            $deleteRangeEnd = date('Y-m-d H:i:s', strtotime($rangeEnd . ' +1 day'));
            $deleteStatement->bind_param('ss', $deleteRangeStart, $deleteRangeEnd);
            $deleteStatement->execute();
            $deleteStatement->close();
        }

        $insertStatement = $connection->prepare(
            'INSERT INTO dostupnost (start_at, end_at, poznamka)
             VALUES (?, ?, ?)'
        );

        if ($insertStatement) {
            foreach ($windows as $window) {
                $startAt = (string) ($window['start_at'] ?? '');
                $endAt = (string) ($window['end_at'] ?? '');
                $note = 'Kalendář dostupnosti';

                if ($startAt !== '' && $endAt !== '' && $startAt < $endAt) {
                    $insertStatement->bind_param('sss', $startAt, $endAt, $note);
                    $insertStatement->execute();
                }
            }

            $insertStatement->close();
        }

        $message = 'Dostupnost v kalendáři byla uložena.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_window'])) {
    $windowId = (int) ($_POST['window_id'] ?? 0);
    if ($windowId > 0) {
        $statement = $connection->prepare('DELETE FROM dostupnost WHERE id = ? LIMIT 1');
        if ($statement) {
            $statement->bind_param('i', $windowId);
            if ($statement->execute()) {
                $message = 'Volné okno bylo odstraněno.';
            } else {
                $error = 'Okno se nepodařilo odstranit.';
            }
            $statement->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_availability_story_background'])) {
    $backgroundError = null;
    $backgroundPath = null;

    if (! isset($_FILES['story_background']) || ! is_array($_FILES['story_background'])) {
        $error = 'Vyberte prosím obrázek pozadí.';
    } else {
        $backgroundPath = storeUploadedImage($_FILES['story_background'], dirname(__DIR__, 4) . '/uploads', $backgroundError);

        if ($backgroundPath === null) {
            $error = $backgroundError !== null && $backgroundError !== ''
                ? 'Pozadí pro story se nepodařilo uložit. ' . $backgroundError
                : 'Pozadí pro story se nepodařilo uložit.';
        } else {
            $previousBackground = trim((string) ($siteSettings['availability_story_background'] ?? ''));
            if ($previousBackground !== '' && str_starts_with($previousBackground, 'uploads/')) {
                $previousPath = dirname(__DIR__, 4) . '/' . ltrim($previousBackground, '/');
                if (is_file($previousPath)) {
                    @unlink($previousPath);
                }
            }

            if (saveSiteSetting($connection, 'availability_story_background', $backgroundPath)) {
                $siteSettings['availability_story_background'] = $backgroundPath;
                $message = 'Pozadí pro Instagram story bylo uloženo.';
            } else {
                $error = 'Pozadí pro story se nepodařilo uložit do nastavení.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_availability_story_background'])) {
    $previousBackground = trim((string) ($siteSettings['availability_story_background'] ?? ''));

    if (saveSiteSetting($connection, 'availability_story_background', '')) {
        $siteSettings['availability_story_background'] = '';
        if ($previousBackground !== '' && str_starts_with($previousBackground, 'uploads/')) {
            $previousPath = dirname(__DIR__, 4) . '/' . ltrim($previousBackground, '/');
            if (is_file($previousPath)) {
                @unlink($previousPath);
            }
        }
        $message = 'Pozadí pro Instagram story bylo odstraněno.';
    } else {
        $error = 'Pozadí pro story se nepodařilo odstranit.';
    }
}
