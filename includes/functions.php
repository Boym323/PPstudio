<?php
declare(strict_types=1);

function escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatPrice(mixed $price): string
{
    if ($price === null || $price === '') {
        return 'Cena na dotaz';
    }

    return number_format((float) $price, 0, ',', ' ') . ' Kč';
}

function formatDuration(mixed $duration): string
{
    if ($duration === null || $duration === '') {
        return 'Dle vybrané procedury';
    }

    return (int) $duration . ' min';
}

function oldInput(string $key, array $source): string
{
    return escape($source[$key] ?? '');
}

function formatCzechDate(string $date): string
{
    $dateObject = DateTimeImmutable::createFromFormat('Y-m-d', $date);

    if (! $dateObject) {
        return $date;
    }

    return $dateObject->format('d.m.Y');
}

function formatCzechDateWithWeekday(string $date): string
{
    $dateObject = DateTimeImmutable::createFromFormat('Y-m-d', $date);

    if (! $dateObject) {
        return $date;
    }

    $weekdays = [
        1 => 'Po',
        2 => 'Út',
        3 => 'St',
        4 => 'Čt',
        5 => 'Pá',
        6 => 'So',
        7 => 'Ne',
    ];

    return ($weekdays[(int) $dateObject->format('N')] ?? '') . ' ' . $dateObject->format('d.m.');
}

function formatCzechDateTime(string $dateTime): string
{
    $dateObject = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTime)
        ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $dateTime);

    if (! $dateObject) {
        return $dateTime;
    }

    return $dateObject->format('d.m.Y H:i');
}

function isWeekendDate(string $date): bool
{
    $dateObject = DateTimeImmutable::createFromFormat('Y-m-d', $date);

    if (! $dateObject) {
        return false;
    }

    $weekday = (int) $dateObject->format('N');

    return $weekday >= 6;
}

function getCzechHolidayName(string $date): ?string
{
    $dateObject = DateTimeImmutable::createFromFormat('Y-m-d', $date);

    if (! $dateObject) {
        return null;
    }

    $year = (int) $dateObject->format('Y');
    $fixed = [
        $year . '-01-01' => 'Nový rok / Den obnovy samostatného českého státu',
        $year . '-05-01' => 'Svátek práce',
        $year . '-05-08' => 'Den vítězství',
        $year . '-07-05' => 'Den slovanských věrozvěstů Cyrila a Metoděje',
        $year . '-07-06' => 'Den upálení mistra Jana Husa',
        $year . '-09-28' => 'Den české státnosti',
        $year . '-10-28' => 'Den vzniku samostatného československého státu',
        $year . '-11-17' => 'Den boje za svobodu a demokracii',
        $year . '-12-24' => 'Štědrý den',
        $year . '-12-25' => '1. svátek vánoční',
        $year . '-12-26' => '2. svátek vánoční',
    ];

    if (isset($fixed[$date])) {
        return $fixed[$date];
    }

    $easterTimestamp = easter_date($year);
    $easterSunday = (new DateTimeImmutable('@' . $easterTimestamp))
        ->setTimezone(new DateTimeZone(date_default_timezone_get() ?: 'Europe/Prague'))
        ->setTime(0, 0);
    $goodFriday = $easterSunday->modify('-2 days')->format('Y-m-d');
    $easterMonday = $easterSunday->modify('+1 day')->format('Y-m-d');

    if ($date === $goodFriday) {
        return 'Velký pátek';
    }

    if ($date === $easterMonday) {
        return 'Velikonoční pondělí';
    }

    return null;
}

function setting(array $settings, string $key, string $fallback = ''): string
{
    $value = $settings[$key] ?? $fallback;

    return is_string($value) && $value !== '' ? $value : $fallback;
}

function reservationStatusOptions(): array
{
    return [
        'nova' => 'Nová',
        'potvrzena' => 'Potvrzená',
        'dokoncena' => 'Dokončená',
        'zrusena' => 'Zrušená',
    ];
}

function reservationStatusLabel(string $status): string
{
    $options = reservationStatusOptions();

    return $options[$status] ?? 'Neznámá';
}

function normalizeNullableFloat(string $value): ?float
{
    if (trim($value) === '') {
        return null;
    }

    return (float) str_replace(',', '.', $value);
}

function sqlDayBounds(string $date): ?array
{
    $dayStart = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (! $dayStart) {
        return null;
    }

    $dayStart = $dayStart->setTime(0, 0, 0);
    $dayEnd = $dayStart->modify('+1 day');

    return [
        'start' => $dayStart->format('Y-m-d H:i:s'),
        'end' => $dayEnd->format('Y-m-d H:i:s'),
    ];
}

function normalizeSqlDateTime(string $dateTime): ?string
{
    $value = trim(str_replace('T', ' ', $dateTime));
    if ($value === '') {
        return null;
    }

    foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
        $parsed = DateTimeImmutable::createFromFormat($format, $value);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed->format('Y-m-d H:i:s');
        }
    }

    return null;
}

function summarizeOpeningHours(string $hours): array
{
    $parts = array_filter(array_map('trim', explode('|', $hours)));

    return $parts === [] ? [$hours] : array_values($parts);
}

function trustedSettingHtml(array $settings, string $key): string
{
    $value = $settings[$key] ?? '';

    return is_string($value) ? trim($value) : '';
}

function webcalToHttps(string $url): string
{
    if (str_starts_with($url, 'webcal://')) {
        return 'https://' . substr($url, strlen('webcal://'));
    }

    return $url;
}
