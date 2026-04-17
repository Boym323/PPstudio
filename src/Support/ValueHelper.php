<?php
declare(strict_types=1);

namespace PPStudio\Support;

final class ValueHelper
{
    public static function normalizeNullableFloat(string $value): ?float
    {
        if (trim($value) === '') {
            return null;
        }

        return (float) str_replace(',', '.', $value);
    }
}
