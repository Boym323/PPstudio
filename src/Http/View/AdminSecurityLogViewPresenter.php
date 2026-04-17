<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

final class AdminSecurityLogViewPresenter
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function prepareAntispamRows(array $rows): array
    {
        $prepared = [];

        foreach ($rows as $row) {
            $sourceKey = (string) ($row['source'] ?? '');
            $uaText = trim((string) ($row['ua'] ?? ''));
            $contextText = trim((string) ($row['context'] ?? ''));

            $prepared[] = $row + [
                'time_label' => $this->formatTimeLabel((string) ($row['time'] ?? '')),
                'source_label' => $this->sourceLabel($sourceKey),
                'ua_text' => $uaText !== '' ? $uaText : 'Neuvedeno',
                'context_text' => $contextText !== '' ? $contextText : 'Neuvedeno',
                'context_preview' => $this->preview($contextText, 'Bez doplňujícího kontextu'),
            ];
        }

        return $prepared;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function prepareReminderRows(array $rows): array
    {
        $prepared = [];

        foreach ($rows as $row) {
            $contextText = trim((string) ($row['context'] ?? ''));
            $reservationId = $row['reservation_id'] ?? null;
            $reservationLabel = '—';

            if (is_int($reservationId) && $reservationId > 0) {
                $reservationLabel = '#' . $reservationId;
                $reservationName = trim((string) ($row['reservation_name'] ?? ''));
                $reservationDatetime = trim((string) ($row['reservation_datetime'] ?? ''));

                if ($reservationName !== '') {
                    $reservationLabel .= ' · ' . $reservationName;
                }
                if ($reservationDatetime !== '') {
                    $reservationLabel .= ' · ' . \PPStudio\Support\FormatHelper::formatCzechDateTime($reservationDatetime);
                }
            }

            $prepared[] = $row + [
                'time_label' => $this->formatTimeLabel((string) ($row['time'] ?? '')),
                'context_preview' => $this->preview($contextText, 'Neuvedeno'),
                'reservation_label' => $reservationLabel,
                'severity_label' => strtoupper((string) ($row['severity'] ?? '')),
            ];
        }

        return $prepared;
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $pagination
     * @param array<string, string> $baseParams
     * @return array<string, mixed>
     */
    public function buildPaginationView(
        string $basePath,
        string $anchor,
        string $pageParam,
        array $filters,
        array $pagination,
        array $baseParams
    ): array {
        $currentPage = max(1, (int) ($filters['page'] ?? 1));
        $totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));

        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++) {
            if ($pageNumber === 1 || $pageNumber === $totalPages || abs($pageNumber - $currentPage) <= 1) {
                $pages[] = [
                    'type' => 'page',
                    'number' => $pageNumber,
                    'active' => $pageNumber === $currentPage,
                    'url' => $this->buildPaginationUrl($basePath, $anchor, $baseParams, $pageParam, $pageNumber),
                ];
                continue;
            }

            if ($pageNumber === 2 || $pageNumber === $totalPages - 1) {
                $pages[] = ['type' => 'separator'];
            }
        }

        return [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'prev_url' => $this->buildPaginationUrl($basePath, $anchor, $baseParams, $pageParam, max(1, $currentPage - 1)),
            'next_url' => $this->buildPaginationUrl($basePath, $anchor, $baseParams, $pageParam, min($totalPages, $currentPage + 1)),
            'pages' => $pages,
        ];
    }

    private function formatTimeLabel(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date('d.m.Y H:i:s', $timestamp);
    }

    private function sourceLabel(string $sourceKey): string
    {
        return match ($sourceKey) {
            'reservation_form' => 'Rezervační formulář',
            'admin_login' => 'Admin přihlášení',
            'admin_lite_login' => 'User admin přihlášení',
            'reservation_action' => 'Akce rezervace',
            default => ($sourceKey !== '' ? $sourceKey : 'Neznámá sekce'),
        };
    }

    private function preview(string $value, string $fallback): string
    {
        if ($value === '') {
            return $fallback;
        }

        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($value, 0, 180, '…', 'UTF-8');
        }

        return strlen($value) > 180 ? substr($value, 0, 177) . '...' : $value;
    }

    /**
     * @param array<string, string> $baseParams
     */
    private function buildPaginationUrl(
        string $basePath,
        string $anchor,
        array $baseParams,
        string $pageParam,
        int $pageNumber
    ): string {
        return $basePath . '?' . http_build_query($baseParams + [$pageParam => (string) $pageNumber]) . '#' . $anchor;
    }
}
