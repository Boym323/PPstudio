<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

final class SitePageRenderer
{
    private SitePageContextBuilder $contextBuilder;
    private SitePageLayoutRenderer $layoutRenderer;

    public function __construct(
        ?SitePageContextBuilder $contextBuilder = null,
        ?SitePageLayoutRenderer $layoutRenderer = null
    ) {
        $this->contextBuilder = $contextBuilder ?? new SitePageContextBuilder();
        $this->layoutRenderer = $layoutRenderer ?? new SitePageLayoutRenderer();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function render(array $config): never
    {
        \requirePublicSiteAccessOrPrompt();

        $context = $this->contextBuilder->build($config);
        $template = (string) ($context['template'] ?? '');
        if ($template === '' || ! is_file($template)) {
            http_response_code(500);
            echo 'Template not found.';
            exit;
        }

        header('Content-Type: text/html; charset=UTF-8');
        $this->layoutRenderer->render($context);
    }
}
