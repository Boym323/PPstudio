<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Security\SessionService;

final class AdminSessionBootstrap
{
    public static function start(): void
    {
        (new SessionService())->start();
    }
}
