<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function escape(?string $value): string
{
    return \PPStudio\Support\ViewHelper::escape($value);
}

function formatPrice(mixed $price): string
{
    return \PPStudio\Support\FormatHelper::formatPrice($price);
}

function formatDuration(mixed $duration): string
{
    return \PPStudio\Support\FormatHelper::formatDuration($duration);
}

function oldInput(string $key, array $source): string
{
    return \PPStudio\Support\ViewHelper::oldInput($key, $source);
}

function formatCzechDate(string $date): string
{
    return \PPStudio\Support\FormatHelper::formatCzechDate($date);
}

function formatCzechDateWithWeekday(string $date): string
{
    return \PPStudio\Support\FormatHelper::formatCzechDateWithWeekday($date);
}

function formatCzechDateTime(string $dateTime): string
{
    return \PPStudio\Support\FormatHelper::formatCzechDateTime($dateTime);
}

function isWeekendDate(string $date): bool
{
    return \PPStudio\Support\DateHelper::isWeekendDate($date);
}

function getCzechHolidayName(string $date): ?string
{
    return \PPStudio\Support\DateHelper::getCzechHolidayName($date);
}

function setting(array $settings, string $key, string $fallback = ''): string
{
    return \PPStudio\Support\SettingsHelper::setting($settings, $key, $fallback);
}

function reservationStatusOptions(): array
{
    return \PPStudio\Support\ReservationStatusHelper::options();
}

function reservationStatusLabel(string $status): string
{
    return \PPStudio\Support\ReservationStatusHelper::label($status);
}

function normalizeNullableFloat(string $value): ?float
{
    return \PPStudio\Support\ValueHelper::normalizeNullableFloat($value);
}

function sqlDayBounds(string $date): ?array
{
    return \PPStudio\Support\DateHelper::sqlDayBounds($date);
}

function normalizeSqlDateTime(string $dateTime): ?string
{
    return \PPStudio\Support\DateHelper::normalizeSqlDateTime($dateTime);
}

function summarizeOpeningHours(string $hours): array
{
    return \PPStudio\Support\OpeningHoursHelper::summarizeOpeningHours($hours);
}

function trustedSettingHtml(array $settings, string $key): string
{
    return \PPStudio\Support\SettingsHelper::trustedSettingHtml($settings, $key);
}

function contactPhoneHref(string $phone): string
{
    return \PPStudio\Support\ContactHelper::contactPhoneHref($phone);
}

function contactEmailHref(string $email): string
{
    return \PPStudio\Support\ContactHelper::contactEmailHref($email);
}

function contactInstagramHandle(string $instagramUrl): string
{
    return \PPStudio\Support\ContactHelper::contactInstagramHandle($instagramUrl);
}

function contactInstagramDmHref(string $instagramUrl): string
{
    return \PPStudio\Support\ContactHelper::contactInstagramDmHref($instagramUrl);
}

function webcalToHttps(string $url): string
{
    return \PPStudio\Support\ContactHelper::webcalToHttps($url);
}
