<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use PPStudio\Http\View\SitePageRenderer;

final class SitePageApplication
{
    private SitePageRenderer $renderer;

    public function __construct(?SitePageRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? new SitePageRenderer();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function handle(array $config): never
    {
        $this->renderer->render($config);
    }
}
