<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';

$siteUrl = rtrim((string) ppstudioEnv('PPSTUDIO_SITE_URL', ''), '/');
$lastMod = gmdate('Y-m-d');
$pages = [
    '/',
    '/sluzby.php',
    '/cenik.php',
    '/o-studiu.php',
    '/galerie.php',
    '/recenze.php',
    '/rezervace.php',
];

$connection = \PPStudio\Database\DatabaseFactory::tryConnect();

if ($connection instanceof mysqli) {
    $siteSettings = (new \PPStudio\Service\SiteSettingsService(new \PPStudio\Repository\SiteSettingsRepository($connection), defaultSiteSettings()))->load();
    $siteUrl = rtrim(\PPStudio\Support\SettingsHelper::setting($siteSettings, 'site_url', $siteUrl), '/');
    $connection->close();
}

if ($siteUrl === '') {
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '') {
        $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $siteUrl = $scheme . '://' . $host;
    }
}

header('Content-Type: application/xml; charset=utf-8');
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

foreach ($pages as $path) {
    echo "  <url>\n";
    echo '    <loc>' . \PPStudio\Support\ViewHelper::escape($siteUrl . $path) . "</loc>\n";
    echo "    <lastmod>{$lastMod}</lastmod>\n";
    echo "  </url>\n";
}

echo "</urlset>\n";
