<?php
declare(strict_types=1);

namespace PPStudio\Support;

final class ViewHelper
{
    public static function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function oldInput(string $key, array $source): string
    {
        return self::escape($source[$key] ?? '');
    }
}
