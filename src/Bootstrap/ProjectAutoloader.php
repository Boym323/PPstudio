<?php
declare(strict_types=1);

namespace PPStudio\Bootstrap;

final class ProjectAutoloader
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function register(): void
    {
        $composerAutoload = $this->projectRoot . '/vendor/autoload.php';
        if (is_file($composerAutoload)) {
            require_once $composerAutoload;
            return;
        }

        spl_autoload_register(function (string $class): void {
            $prefix = 'PPStudio\\';

            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $relativeClass = substr($class, strlen($prefix));
            $path = $this->projectRoot . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

            if (is_file($path)) {
                require_once $path;
            }
        });
    }
}
