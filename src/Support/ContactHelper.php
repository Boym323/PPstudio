<?php
declare(strict_types=1);

namespace PPStudio\Support;

final class ContactHelper
{
    public static function contactPhoneHref(string $phone): string
    {
        $normalized = trim($phone);
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/(?!^\+)[^\d]/', '', $normalized);
        $normalized = preg_replace('/^\+?(\d+)/', '+$1', $normalized);

        return 'tel:' . $normalized;
    }

    public static function contactEmailHref(string $email): string
    {
        $normalized = trim($email);
        if ($normalized === '') {
            return '';
        }

        return 'mailto:' . $normalized;
    }

    public static function contactInstagramHandle(string $instagramUrl): string
    {
        $url = trim($instagramUrl);
        if ($url === '') {
            return '';
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return '';
        }

        $handle = trim(explode('/', $path)[0]);
        $handle = ltrim($handle, '@');

        return $handle;
    }

    public static function contactInstagramDmHref(string $instagramUrl): string
    {
        $handle = self::contactInstagramHandle($instagramUrl);
        if ($handle === '') {
            return '';
        }

        return 'https://ig.me/m/' . rawurlencode($handle);
    }

    public static function webcalToHttps(string $url): string
    {
        if (str_starts_with($url, 'webcal://')) {
            return 'https://' . substr($url, strlen('webcal://'));
        }

        return $url;
    }
}
