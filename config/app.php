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

const SITE_NAME = 'PPStudio';
const SITE_TAGLINE = 'Kosmetické studio s jemnou péčí a profesionálním přístupem';

const DEFAULT_SETTINGS = [
    'site_name' => 'PPStudio',
    'site_url' => 'https://www.ppstudio.cz',
    'site_tagline' => 'Jemná kosmetická péče v klidném, elegantním prostředí',
    'contact_address' => 'Adresa studia',
    'contact_email' => 'info@ppstudio.cz',
    'contact_phone' => '+420 777 000 000',
    'contact_instagram' => '@ppstudio.cz',
    'opening_hours' => 'Po - Pá: 8:00 - 18:00 | So: dle objednání',
    'instagram_url' => 'https://www.instagram.com/beauty_touch_by_vp/',
    'instagram_feed_embed' => '',
    'google_reviews_url' => '',
    'firmy_reviews_url' => '',
    'google_reviews_embed' => '',
    'firmy_reviews_embed' => '',
    'google_place_id' => '',
    'google_reviews_language' => 'cs',
    'notification_emails' => 'info@ppstudio.cz',
];
