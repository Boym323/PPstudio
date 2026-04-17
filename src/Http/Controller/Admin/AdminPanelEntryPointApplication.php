<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

final class AdminPanelEntryPointApplication
{
    public function __construct(private string $projectRoot)
    {
    }

    public static function create(string $projectRoot): self
    {
        return new self($projectRoot);
    }

    public function handleFull(): never
    {
        $this->handle(
            new AdminApplication(
                $this->loadConfig('config/admin.php'),
                $this->loadConfig('config/email.php')
            )
        );
    }

    public function handleLite(): never
    {
        $this->handle(
            new AdminLiteApplication(
                $this->loadConfig('config/admin_lite.php'),
                $this->loadConfig('config/email.php')
            )
        );
    }

    private function handle(AdminApplication|AdminLiteApplication $application): never
    {
        $application->handle();
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(string $relativePath): array
    {
        $path = $this->projectRoot . '/' . ltrim($relativePath, '/');

        $config = require $path;

        return is_array($config) ? $config : [];
    }
}
