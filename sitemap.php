<?php
declare(strict_types=1);

require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/settings.php';

$dbConfig = require __DIR__ . '/config/database.php';
$emailConfig = require __DIR__ . '/config/email.php';

$siteUrl = rtrim((string) ($emailConfig['site_url'] ?? 'https://www.ppstudio.cz'), '/');
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

$connection = @new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['database']
);

if (! $connection->connect_errno) {
    $connection->set_charset($dbConfig['charset']);
    $siteSettings = loadSiteSettings($connection);
    $siteUrl = rtrim(setting($siteSettings, 'site_url', $siteUrl), '/');
    $connection->close();
}

header('Content-Type: application/xml; charset=utf-8');
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

foreach ($pages as $path) {
    echo "  <url>\n";
    echo '    <loc>' . escape($siteUrl . $path) . "</loc>\n";
    echo "    <lastmod>{$lastMod}</lastmod>\n";
    echo "  </url>\n";
}

echo "</urlset>\n";
