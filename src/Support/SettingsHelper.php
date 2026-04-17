<?php
declare(strict_types=1);

namespace PPStudio\Support;

final class SettingsHelper
{
    public static function setting(array $settings, string $key, string $fallback = ''): string
    {
        $value = $settings[$key] ?? $fallback;

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    public static function trustedSettingHtml(array $settings, string $key): string
    {
        $value = $settings[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
