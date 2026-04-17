<?php
declare(strict_types=1);

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'PPStudio\\';

        if (! str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($path)) {
            require_once $path;
        }
    });
}

function (new \PPStudio\Security\SecurityFacade()): \PPStudio\Security\SecurityFacade
{
    static $service = null;

    if (! $service instanceof \PPStudio\Security\SecurityFacade) {
        $service = new \PPStudio\Security\SecurityFacade();
    }

    return $service;
}

function ppstudioSiteSettingsService(?\mysqli $connection = null): \PPStudio\Service\SiteSettingsService
{
    static $services = [];

    if (! $connection instanceof \mysqli) {
        throw new \LogicException('Site settings service requires a database connection.');
    }

    $key = spl_object_id($connection);
    if (! isset($services[$key]) || ! $services[$key] instanceof \PPStudio\Service\SiteSettingsService) {
        $services[$key] = new \PPStudio\Service\SiteSettingsService(
            new \PPStudio\Repository\SiteSettingsRepository($connection),
            defaultSiteSettings()
        );
    }

    return $services[$key];
}

function (new \PPStudio\Service\SiteSettingsService(new \PPStudio\Repository\SiteSettingsRepository(?\mysqli $connection = null), defaultSiteSettings()))->load(): array
{
    if (! $connection instanceof \mysqli) {
        return defaultSiteSettings();
    }

    return ppstudioSiteSettingsService($connection)->load();
}

function (new \PPStudio\Service\SiteSettingsService(new \PPStudio\Repository\SiteSettingsRepository(\mysqli $connection), defaultSiteSettings()))->save(string $key, string $value): bool
{
    return ppstudioSiteSettingsService($connection)->save($key, $value);
}

function requirePublicSiteAccessOrPrompt(): void
{
    (new \PPStudio\Security\SecurityFacade())->publicSiteLockService()->requireAccessOrPrompt($_SERVER, $_POST);
}

function requirePublicSiteAccessOrJsonError(): void
{
    (new \PPStudio\Security\SecurityFacade())->publicSiteLockService()->requireAccessOrJsonError();
}
