<?php
declare(strict_types=1);

namespace PPStudio\Support;

final class OpeningHoursHelper
{
    /**
     * @return array<int, string>
     */
    public static function summarizeOpeningHours(string $hours): array
    {
        $parts = array_filter(array_map('trim', explode('|', $hours)));

        return $parts === [] ? [$hours] : array_values($parts);
    }
}
