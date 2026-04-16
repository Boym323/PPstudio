<?php
declare(strict_types=1);

if (! function_exists('ppstudioEnv')) {
    /**
     * Load local .env files without external dependencies.
     * Variables explicitly provided by the web server remain untouched.
     */
    function ppstudioLoadEnvFiles(array $paths): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '' || ! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (! is_array($lines)) {
                continue;
            }

            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                if (str_starts_with($line, 'export ')) {
                    $line = trim(substr($line, 7));
                }

                $separatorPos = strpos($line, '=');
                if ($separatorPos === false) {
                    continue;
                }

                $name = trim(substr($line, 0, $separatorPos));
                $value = trim(substr($line, $separatorPos + 1));

                if ($name === '' || ! preg_match('/^[A-Z0-9_]+$/i', $name)) {
                    continue;
                }

                if ($value !== '' && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                    $quote = $value[0];
                    $value = substr($value, 1, -1);
                    if ($quote === '"') {
                        $value = stripcslashes($value);
                    }
                }

                // Keep server-injected variables as highest priority, but allow
                // .env updates to override values previously loaded via putenv.
                if (array_key_exists($name, $_SERVER)) {
                    continue;
                }

                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
            }
        }

        $loaded = true;
    }

    function ppstudioEnv(string $name, ?string $default = null): ?string
    {
        $value = getenv($name);
        if ($value === false) {
            return $default;
        }

        return (string) $value;
    }
}

ppstudioLoadEnvFiles([
    dirname(__DIR__) . '/.env',
    dirname(__DIR__) . '/.env.local',
]);

const SITE_SETTING_KEYS = [
    'site_name',
    'site_url',
    'contact_address',
    'contact_name',
    'contact_phone',
    'contact_email',
    'contact_instagram_url',
    'contact_ico',
    'contact_opening_hours',
    'google_reviews_url',
    'firmy_reviews_url',
    'firmy_reviews_embed',
    'google_place_id',
    'google_reviews_language',
    'notification_emails',
    'availability_story_background',
];

if (! function_exists('defaultSiteSettings')) {
    function defaultSiteSettings(): array
    {
        return [
            'site_name' => defaultSiteName(),
            'site_url' => '',
            'contact_address' => '',
            'contact_name' => '',
            'contact_phone' => '',
            'contact_email' => '',
            'contact_instagram_url' => defaultContactInstagramUrl(),
            'contact_ico' => '',
            'contact_opening_hours' => '',
            'google_reviews_url' => '',
            'firmy_reviews_url' => '',
            'firmy_reviews_embed' => '',
            'google_place_id' => '',
            'google_reviews_language' => '',
            'notification_emails' => '',
            'availability_story_background' => '',
        ];
    }
}

if (! function_exists('defaultSiteName')) {
    function defaultSiteName(): string
    {
        return trim((string) ppstudioEnv('PPSTUDIO_SITE_NAME', 'PP Studio'));
    }
}

if (! function_exists('defaultContactInstagramUrl')) {
    function defaultContactInstagramUrl(): string
    {
        return trim((string) ppstudioEnv('PPSTUDIO_CONTACT_INSTAGRAM_URL', ''));
    }
}
