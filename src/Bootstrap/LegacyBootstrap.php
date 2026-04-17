<?php
declare(strict_types=1);

namespace PPStudio\Bootstrap;

use PPStudio\Security\SecurityFacade;

final class LegacyBootstrap
{
    private static ?self $instance = null;

    private ?SecurityFacade $securityFacade = null;

    private bool $legacyFunctionsRegistered = false;

    private function __construct()
    {
    }

    public static function boot(string $projectRoot): void
    {
        $autoloader = new ProjectAutoloader($projectRoot);
        $autoloader->register();

        self::instance()->registerLegacyFunctions($projectRoot);
    }

    public static function instance(): self
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function securityFacade(): SecurityFacade
    {
        if (! $this->securityFacade instanceof SecurityFacade) {
            $this->securityFacade = new SecurityFacade();
        }

        return $this->securityFacade;
    }

    private function registerLegacyFunctions(string $projectRoot): void
    {
        if ($this->legacyFunctionsRegistered) {
            return;
        }

        require_once $projectRoot . '/src/Bootstrap/legacy_functions.php';
        $this->legacyFunctionsRegistered = true;
    }
}
