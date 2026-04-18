<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use PPStudio\Config\AppConfig;
use mysqli;
use PPStudio\Database\DatabaseFactory;
use PPStudio\Repository\SiteSettingsRepository;
use PPStudio\Http\View\SitemapRenderer;
use PPStudio\Service\SitemapBuilder;
use PPStudio\Service\SiteSettingsService;
use PPStudio\Support\SettingsHelper;

final class SitemapController
{
    public function __construct(
        private readonly SitemapBuilder $builder = new SitemapBuilder(),
        private readonly SitemapRenderer $renderer = new SitemapRenderer()
    ) {
    }

    /**
     * @param array<string, mixed> $server
     */
    public function handle(array $server): never
    {
        $siteUrl = $this->resolveSiteUrl($server);
        $entries = $this->builder->build($siteUrl, gmdate('Y-m-d'));

        $this->renderer->render($entries);
    }

    /**
     * @param array<string, mixed> $server
     */
    private function resolveSiteUrl(array $server): string
    {
        $siteUrl = rtrim((string) AppConfig::instance()->env('PPSTUDIO_SITE_URL', ''), '/');

        $connection = DatabaseFactory::tryConnect();
        if ($connection instanceof mysqli) {
            try {
                $siteSettings = (new SiteSettingsService(
                    new SiteSettingsRepository($connection),
                    AppConfig::instance()->defaultSiteSettings()
                ))->load();
                $siteUrl = rtrim(SettingsHelper::setting($siteSettings, 'site_url', $siteUrl), '/');
            } finally {
                $connection->close();
            }
        }

        if ($siteUrl === '') {
            $host = (string) ($server['HTTP_HOST'] ?? '');
            if ($host !== '') {
                $scheme = (! empty($server['HTTPS']) && $server['HTTPS'] !== 'off') ? 'https' : 'http';
                $siteUrl = $scheme . '://' . $host;
            }
        }

        return $siteUrl;
    }
}
