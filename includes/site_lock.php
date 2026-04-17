<?php
declare(strict_types=1);

function requirePublicSiteAccessOrPrompt(): void
{
    ppstudioPublicSiteLockService()->requireAccessOrPrompt($_SERVER, $_POST);
}

function requirePublicSiteAccessOrJsonError(): void
{
    ppstudioPublicSiteLockService()->requireAccessOrJsonError();
}
