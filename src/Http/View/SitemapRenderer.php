<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

final class SitemapRenderer
{
    /**
     * @param array<int, array{loc: string, lastmod: string}> $entries
     */
    public function render(array $entries): never
    {
        header('Content-Type: application/xml; charset=utf-8');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($entries as $entry) {
            echo "  <url>\n";
            echo '    <loc>' . \PPStudio\Support\ViewHelper::escape($entry['loc']) . "</loc>\n";
            echo '    <lastmod>' . \PPStudio\Support\ViewHelper::escape($entry['lastmod']) . "</lastmod>\n";
            echo "  </url>\n";
        }

        echo "</urlset>\n";
        exit;
    }
}
