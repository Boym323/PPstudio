<?php
declare(strict_types=1);

namespace PPStudio\Support;

use DateTimeImmutable;

final class FormatHelper
{
    public static function formatPrice(mixed $price): string
    {
        if ($price === null || $price === '') {
            return 'Cena na dotaz';
        }

        return number_format((float) $price, 0, ',', ' ') . ' Kč';
    }

    public static function formatDuration(mixed $duration): string
    {
        if ($duration === null || $duration === '') {
            return 'Dle vybrané procedury';
        }

        return (int) $duration . ' min';
    }

    public static function formatCzechDate(string $date): string
    {
        $dateObject = self::parseDate($date, 'Y-m-d');

        if (! $dateObject instanceof DateTimeImmutable) {
            return $date;
        }

        return $dateObject->format('d.m.Y');
    }

    public static function formatCzechDateWithWeekday(string $date): string
    {
        $dateObject = self::parseDate($date, 'Y-m-d');

        if (! $dateObject instanceof DateTimeImmutable) {
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

    public static function formatCzechDateTime(string $dateTime): string
    {
        $dateObject = self::parseDateTime($dateTime);

        if (! $dateObject instanceof DateTimeImmutable) {
            return $dateTime;
        }

        return $dateObject->format('d.m.Y H:i');
    }

    private static function parseDate(string $value, string $format): ?DateTimeImmutable
    {
        $dateObject = DateTimeImmutable::createFromFormat($format, $value);

        return $dateObject instanceof DateTimeImmutable ? $dateObject : null;
    }

    private static function parseDateTime(string $value): ?DateTimeImmutable
    {
        $dateObject = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $value);

        return $dateObject instanceof DateTimeImmutable ? $dateObject : null;
    }
}
