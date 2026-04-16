<?php
declare(strict_types=1);

function ppstudioPublicLockEnabled(): bool
{
    return ppstudioPublicSiteLockService()->enabled();
}

function ppstudioPublicLockSessionKey(): string
{
    return ppstudioPublicSiteLockService()->sessionKey();
}

function ppstudioPublicLockHasAccess(): bool
{
    return ppstudioPublicSiteLockService()->hasAccess();
}

function ppstudioPublicLockPasswordMatches(string $password): bool
{
    return ppstudioPublicSiteLockService()->passwordMatches($password);
}

function ppstudioPublicLockCurrentUrl(): string
{
    return ppstudioPublicSiteLockService()->currentUrl();
}

function ppstudioPublicLockRenderPage(string $errorMessage = ''): never
{
    ppstudioPublicSiteLockService()->renderPage($errorMessage);
}

function requirePublicSiteAccessOrPrompt(): void
{
    ppstudioPublicSiteLockService()->requireAccessOrPrompt($_SERVER, $_POST);
}

function requirePublicSiteAccessOrJsonError(): void
{
    ppstudioPublicSiteLockService()->requireAccessOrJsonError();
}
