<?php
declare(strict_types=1);

namespace PPStudio\Config;

final class EnvLoader
{
    private static bool $loaded = false;

    /**
     * Load local environment files in the given order.
     *
     * Existing server-provided variables stay untouched.
     *
     * @param array<int, string> $paths
     */
    public function load(array $paths): void
    {
        if (self::$loaded) {
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
                $parsed = $this->parseAssignment((string) $line);
                if ($parsed === null) {
                    continue;
                }

                [$name, $value] = $parsed;

                // Keep web-server injected values as the highest priority.
                if (array_key_exists($name, $_SERVER)) {
                    continue;
                }

                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
            }
        }

        self::$loaded = true;
    }

    public function get(string $name, ?string $default = null): ?string
    {
        $value = getenv($name);
        if ($value === false) {
            return $default;
        }

        return (string) $value;
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function parseAssignment(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            return null;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        $separatorPos = strpos($line, '=');
        if ($separatorPos === false) {
            return null;
        }

        $name = trim(substr($line, 0, $separatorPos));
        $value = trim(substr($line, $separatorPos + 1));

        if ($name === '' || ! preg_match('/^[A-Z0-9_]+$/i', $name)) {
            return null;
        }

        if ($value !== '' && (
            ($value[0] === '"' && str_ends_with($value, '"')) ||
            ($value[0] === "'" && str_ends_with($value, "'"))
        )) {
            $quote = $value[0];
            $value = substr($value, 1, -1);

            if ($quote === '"') {
                $value = stripcslashes($value);
            }
        }

        return [$name, $value];
    }
}
