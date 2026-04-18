<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Config\AppConfig;
use DateTimeImmutable;
use mysqli;
use PPStudio\Repository\SiteSettingsRepository;

final class AdminAvailabilityStoryService
{
    public function __construct(
        private mysqli $connection
    ) {
    }

    /**
     * @param array<string, mixed> $siteSettings
     * @param array<int, array<string, mixed>> $serviceCategoryRows
     * @return array{
     *   storyDefaultFrom:string,
     *   storyDefaultTo:string,
     *   storyDefaultMonth:string,
     *   storyBackground:string,
     *   storyBackgroundUrl:string,
     *   storyDefaultServices:array<int, string>
     * }
     */
    public function buildDefaultViewModel(array $siteSettings, array $serviceCategoryRows): array
    {
        $storyDefaultFrom = (new DateTimeImmutable('today'))->format('Y-m-d');
        $storyDefaultTo = (new DateTimeImmutable('last day of this month'))->format('Y-m-d');
        $storyBackground = trim((string) ($siteSettings['availability_story_background'] ?? ''));
        $storyBackgroundUrl = '';

        if ($storyBackground !== '' && str_starts_with($storyBackground, 'uploads/')) {
            $storyBackgroundUrl = '/' . ltrim($storyBackground, '/');
        }

        $storyDefaultServices = [];
        foreach ($serviceCategoryRows as $categoryRow) {
            if ((int) ($categoryRow['aktivni'] ?? 0) !== 1) {
                continue;
            }

            $storyDefaultServices[] = trim((string) ($categoryRow['nazev'] ?? ''));
            if (count($storyDefaultServices) >= 4) {
                break;
            }
        }

        return [
            'storyDefaultFrom' => $storyDefaultFrom,
            'storyDefaultTo' => $storyDefaultTo,
            'storyDefaultMonth' => self::buildMonthLabel(
                new DateTimeImmutable($storyDefaultFrom),
                new DateTimeImmutable($storyDefaultTo)
            ),
            'storyBackground' => $storyBackground,
            'storyBackgroundUrl' => $storyBackgroundUrl,
            'storyDefaultServices' => $storyDefaultServices,
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @return array{
     *   from:DateTimeImmutable,
     *   to:DateTimeImmutable,
     *   title:string,
     *   month_label:string,
     *   style:string,
     *   max_days:int,
     *   max_times_per_day:int,
     *   service_lines:array<int, string>,
     *   slot_lines:array<int, string>,
     *   background_path:string,
     *   file_name:string
     * }
     */
    public function buildRenderPayload(array $source, bool $loadBackgroundFromSettings = true): array
    {
        $fromRaw = trim((string) ($source['story_from'] ?? ''));
        $toRaw = trim((string) ($source['story_to'] ?? ''));
        $title = trim((string) ($source['story_title'] ?? ''));
        $monthOverride = trim((string) ($source['story_month_label'] ?? ''));
        $style = trim((string) ($source['story_style'] ?? 'story'));

        if (! in_array($style, ['story', 'minimal', 'feed'], true)) {
            $style = 'story';
        }

        $maxDays = max(1, min(8, (int) ($source['story_max_days'] ?? 5)));
        $maxTimesPerDay = max(1, min(8, (int) ($source['story_max_times_per_day'] ?? 5)));
        $servicesRaw = trim((string) ($source['story_services'] ?? ''));

        $from = DateTimeImmutable::createFromFormat('Y-m-d', $fromRaw) ?: new DateTimeImmutable('today');
        $to = DateTimeImmutable::createFromFormat('Y-m-d', $toRaw) ?: $from->modify('last day of this month');

        if ($to < $from) {
            $swap = $from;
            $from = $to;
            $to = $swap;
        }

        $maxTo = $from->modify('+62 days');
        if ($to > $maxTo) {
            $to = $maxTo;
        }

        $slotLines = $this->buildSlotLines($from, $to, $maxDays, $maxTimesPerDay);
        if ($slotLines === []) {
            $slotLines[] = 'Momentálně nejsou vypsané volné termíny';
        }

        $serviceLines = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/\r\n|\r|\n/', $servicesRaw) ?: []
        )));
        $serviceLines = array_slice($serviceLines, 0, 6);

        $monthLabel = $monthOverride !== '' ? $monthOverride : self::buildMonthLabel($from, $to);
        $title = $title !== '' ? $title : 'Zbývají volné termíny';
        $backgroundPath = $this->resolveBackgroundPath(
            trim((string) ($source['story_background_path'] ?? '')),
            $loadBackgroundFromSettings
        );

        return [
            'from' => $from,
            'to' => $to,
            'title' => $title,
            'month_label' => $monthLabel,
            'style' => $style,
            'max_days' => $maxDays,
            'max_times_per_day' => $maxTimesPerDay,
            'service_lines' => $serviceLines,
            'slot_lines' => $slotLines,
            'background_path' => $backgroundPath,
            'file_name' => 'ppstudio-volne-terminy-' . $from->format('Y-m-d') . '-' . $style . '.png',
        ];
    }

    public static function buildMonthLabel(DateTimeImmutable $from, DateTimeImmutable $to): string
    {
        $fromMonth = (int) $from->format('n');
        $toMonth = (int) $to->format('n');
        $fromYear = (int) $from->format('Y');
        $toYear = (int) $to->format('Y');

        if ($fromMonth === $toMonth && $fromYear === $toYear) {
            return mb_convert_case(self::czechMonthName($fromMonth), MB_CASE_TITLE, 'UTF-8');
        }

        $fromLabel = mb_convert_case(self::czechMonthName($fromMonth), MB_CASE_TITLE, 'UTF-8');
        $toLabel = mb_convert_case(self::czechMonthName($toMonth), MB_CASE_TITLE, 'UTF-8');

        if ($fromYear !== $toYear) {
            return $fromLabel . ' ' . $fromYear . ' / ' . $toLabel . ' ' . $toYear;
        }

        return $fromLabel . ' / ' . $toLabel;
    }

    public function collectFreeSlots(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rangeStart = $from->setTime(0, 0, 0);
        $rangeEnd = $to->setTime(23, 59, 59);
        $rangeStartSql = $rangeStart->format('Y-m-d H:i:s');
        $rangeEndSql = $rangeEnd->format('Y-m-d H:i:s');
        $now = new DateTimeImmutable('now');
        $slotSizeSeconds = 30 * 60;

        $available = [];
        $booked = [];

        $windowStatement = $this->connection->prepare(
            'SELECT start_at, end_at
             FROM dostupnost
             WHERE start_at <= ?
               AND end_at >= ?
               AND end_at > start_at
             ORDER BY start_at ASC'
        );

        if ($windowStatement) {
            $windowStatement->bind_param('ss', $rangeEndSql, $rangeStartSql);
            $windowStatement->execute();
            $windowStatement->bind_result($windowStartAt, $windowEndAt);

            while ($windowStatement->fetch()) {
                if (! is_string($windowStartAt) || ! is_string($windowEndAt) || $windowStartAt === '' || $windowEndAt === '') {
                    continue;
                }

                $startTs = strtotime($windowStartAt);
                $endTs = strtotime($windowEndAt);
                if ($startTs === false || $endTs === false || $endTs <= $startTs) {
                    continue;
                }

                $startTs = max($startTs, $rangeStart->getTimestamp(), $now->getTimestamp());
                $endTs = min($endTs, $rangeEnd->getTimestamp());
                if ($endTs <= $startTs) {
                    continue;
                }

                $cursorTs = (int) (ceil($startTs / $slotSizeSeconds) * $slotSizeSeconds);
                while ($cursorTs < $endTs) {
                    $dateKey = date('Y-m-d', $cursorTs);
                    $timeValue = date('H:i', $cursorTs);
                    $available[$dateKey][$timeValue] = true;
                    $cursorTs += $slotSizeSeconds;
                }
            }

            $windowStatement->close();
        }

        $bookingStatement = $this->connection->prepare(
            'SELECT r.datum_cas, s.doba_trvani
             FROM rezervace r
             INNER JOIN sluzby s ON s.id = r.sluzba
             WHERE r.datum_cas >= ?
               AND r.datum_cas <= ?
               AND r.stav IN ("nova", "potvrzena", "dokoncena")
             ORDER BY r.datum_cas ASC'
        );

        if ($bookingStatement) {
            $bookingStatement->bind_param('ss', $rangeStartSql, $rangeEndSql);
            $bookingStatement->execute();
            $bookingStatement->bind_result($bookingStartAt, $bookingDuration);

            while ($bookingStatement->fetch()) {
                if (! is_string($bookingStartAt) || $bookingStartAt === '') {
                    continue;
                }

                $bookingStartTs = strtotime($bookingStartAt);
                if ($bookingStartTs === false) {
                    continue;
                }

                $durationSeconds = max(15, (int) $bookingDuration) * 60;
                $bookingEndTs = $bookingStartTs + $durationSeconds;
                $bookingStartTs = max($bookingStartTs, $rangeStart->getTimestamp(), $now->getTimestamp());
                $bookingEndTs = min($bookingEndTs, $rangeEnd->getTimestamp());

                if ($bookingEndTs <= $bookingStartTs) {
                    continue;
                }

                $cursorTs = (int) (floor($bookingStartTs / $slotSizeSeconds) * $slotSizeSeconds);
                while ($cursorTs < $bookingEndTs) {
                    $dateKey = date('Y-m-d', $cursorTs);
                    $timeValue = date('H:i', $cursorTs);
                    $booked[$dateKey][$timeValue] = true;
                    $cursorTs += $slotSizeSeconds;
                }
            }

            $bookingStatement->close();
        }

        $result = [];
        foreach ($available as $dateKey => $times) {
            $freeTimes = array_diff_key($times, $booked[$dateKey] ?? []);
            if ($freeTimes === []) {
                continue;
            }

            $timeValues = array_keys($freeTimes);
            sort($timeValues);
            $result[$dateKey] = $timeValues;
        }

        ksort($result);

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function buildSlotLines(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $maxDays,
        int $maxTimesPerDay
    ): array {
        $slotLines = [];

        foreach ($this->collectFreeSlots($from, $to) as $dateKey => $times) {
            if ($times === []) {
                continue;
            }

            $visibleTimes = array_slice($times, 0, $maxTimesPerDay);
            if (count($times) > $maxTimesPerDay) {
                $hourOnlyTimes = array_values(array_filter(
                    $times,
                    static fn (string $time): bool => substr($time, 3, 2) === '00'
                ));

                if ($hourOnlyTimes !== []) {
                    $visibleTimes = array_slice($hourOnlyTimes, 0, $maxTimesPerDay);
                }
            }

            $slotLines[] = (new DateTimeImmutable($dateKey))->format('j.n.') . ' ' . implode(', ', $visibleTimes);

            if (count($slotLines) >= $maxDays) {
                break;
            }
        }

        return $slotLines;
    }

    private function resolveBackgroundPath(string $backgroundSetting, bool $loadBackgroundFromSettings): string
    {
        if ($backgroundSetting === '' && $loadBackgroundFromSettings) {
            $settings = (new SiteSettingsService(
                new SiteSettingsRepository($this->connection),
                AppConfig::instance()->defaultSiteSettings()
            ))->load();
            $backgroundSetting = trim((string) ($settings['availability_story_background'] ?? ''));
        }

        if ($backgroundSetting !== '' && str_starts_with($backgroundSetting, 'uploads/')) {
            $candidate = dirname(__DIR__, 2) . '/' . ltrim($backgroundSetting, '/');
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private static function czechMonthName(int $month): string
    {
        $months = [
            1 => 'leden',
            2 => 'únor',
            3 => 'březen',
            4 => 'duben',
            5 => 'květen',
            6 => 'červen',
            7 => 'červenec',
            8 => 'srpen',
            9 => 'září',
            10 => 'říjen',
            11 => 'listopad',
            12 => 'prosinec',
        ];

        return $months[$month] ?? '';
    }
}
