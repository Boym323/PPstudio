<?php
declare(strict_types=1);

namespace PPStudio\Service;

final class SitemapBuilder
{
    private const DEFAULT_PATHS = [
        '/',
        '/sluzby.php',
        '/cenik.php',
        '/o-studiu.php',
        '/galerie.php',
        '/recenze.php',
        '/rezervace.php',
    ];

    /**
     * @return array<int, array{loc: string, lastmod: string}>
     */
    public function build(string $siteUrl, string $lastMod): array
    {
        $entries = [];
        $normalizedSiteUrl = rtrim($siteUrl, '/');

        foreach (self::DEFAULT_PATHS as $path) {
            $entries[] = [
                'loc' => $normalizedSiteUrl . $path,
                'lastmod' => $lastMod,
            ];
        }

        return $entries;
    }
}
