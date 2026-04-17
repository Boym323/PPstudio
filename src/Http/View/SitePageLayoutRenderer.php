<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

final class SitePageLayoutRenderer
{
    private SitePageLayoutContextBuilder $contextBuilder;

    public function __construct(?SitePageLayoutContextBuilder $contextBuilder = null)
    {
        $this->contextBuilder = $contextBuilder ?? new SitePageLayoutContextBuilder();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(array $context): never
    {
        $layoutContext = $this->contextBuilder->build($context);
        extract(array_merge($context, $layoutContext), EXTR_SKIP);
        include __DIR__ . '/../../../includes/site/layout.php';
        exit;
    }
}
