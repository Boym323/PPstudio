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

function ppstudioSecurityFacade(): \PPStudio\Security\SecurityFacade
{
    static $service = null;

    if (! $service instanceof \PPStudio\Security\SecurityFacade) {
        $service = new \PPStudio\Security\SecurityFacade();
    }

    return $service;
}

function ppstudioAvailabilityFacade(): \PPStudio\Service\AvailabilityFacade
{
    static $service = null;

    if (! $service instanceof \PPStudio\Service\AvailabilityFacade) {
        $service = new \PPStudio\Service\AvailabilityFacade();
    }

    return $service;
}

function requirePublicSiteAccessOrPrompt(): void
{
    ppstudioSecurityFacade()->publicSiteLockService()->requireAccessOrPrompt($_SERVER, $_POST);
}

function requirePublicSiteAccessOrJsonError(): void
{
    ppstudioSecurityFacade()->publicSiteLockService()->requireAccessOrJsonError();
}
