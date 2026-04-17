<?php
declare(strict_types=1);

namespace PPStudio\Support;

use DateTimeImmutable;
use DateTimeZone;

final class DateHelper
{
    public static function isWeekendDate(string $date): bool
    {
        $dateObject = self::parseDate($date);

        if (! $dateObject instanceof DateTimeImmutable) {
            return false;
        }

        return (int) $dateObject->format('N') >= 6;
    }

    public static function getCzechHolidayName(string $date): ?string
    {
        $dateObject = self::parseDate($date);
        if (! $dateObject instanceof DateTimeImmutable) {
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

    /**
     * @return array{start: string, end: string}|null
     */
    public static function sqlDayBounds(string $date): ?array
    {
        $dayStart = self::parseDate($date);
        if (! $dayStart instanceof DateTimeImmutable) {
            return null;
        }

        $dayStart = $dayStart->setTime(0, 0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        return [
            'start' => $dayStart->format('Y-m-d H:i:s'),
            'end' => $dayEnd->format('Y-m-d H:i:s'),
        ];
    }

    public static function normalizeSqlDateTime(string $dateTime): ?string
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

    private static function parseDate(string $value): ?DateTimeImmutable
    {
        $dateObject = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $dateObject instanceof DateTimeImmutable ? $dateObject : null;
    }
}
