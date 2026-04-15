<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Repository\SiteSettingsRepository;

final class SiteSettingsService
{
    public function __construct(
        private SiteSettingsRepository $repository,
        private array $defaultSettings
    ) {
    }

    public function load(): array
    {
        return array_replace($this->defaultSettings, $this->repository->findAll());
    }

    public function save(string $key, string $value): bool
    {
        return $this->repository->save($key, $value);
    }

    public function saveMany(array $settings): bool
    {
        foreach ($settings as $key => $value) {
            if (! is_string($key) || ! $this->save($key, (string) $value)) {
                return false;
            }
        }

        return true;
    }

    public function get(array $settings, string $key, string $fallback = ''): string
    {
        $value = $settings[$key] ?? $fallback;

        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
