<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

final class SiteReviewsPageContextBuilder
{
    private SiteTrustedSingleIframeEmbedRenderer $trustedSingleIframeEmbedRenderer;

    public function __construct(?SiteTrustedSingleIframeEmbedRenderer $trustedSingleIframeEmbedRenderer = null)
    {
        $this->trustedSingleIframeEmbedRenderer = $trustedSingleIframeEmbedRenderer ?? new SiteTrustedSingleIframeEmbedRenderer();
    }

    /**
     * @param array<string, mixed> $siteSettings
     * @return array<string, mixed>
     */
    public function build(array $siteSettings): array
    {
        $googleReviewsUrl = trim(\setting($siteSettings, 'google_reviews_url', ''));
        $firmyReviewsUrl = trim(\setting($siteSettings, 'firmy_reviews_url', ''));
        $firmyEmbed = $this->trustedSingleIframeEmbedRenderer->render(\setting($siteSettings, 'firmy_reviews_embed', ''));

        return [
            'googleReviewsUrl' => $googleReviewsUrl,
            'firmyReviewsUrl' => $firmyReviewsUrl,
            'firmyEmbed' => $firmyEmbed,
        ];
    }
}
