<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/site_lock.php';
require __DIR__ . '/../includes/settings.php';


header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

requirePublicSiteAccessOrJsonError();

$connection = \PPStudio\Database\DatabaseFactory::tryConnect();

if (! $connection instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['error' => 'Databaze neni dostupna.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$siteSettings = loadSiteSettings($connection);
$connection->close();

$apiKey = trim((string) ppstudioEnv('PPSTUDIO_GOOGLE_PLACES_API_KEY', ''));
$placeId = trim(setting($siteSettings, 'google_place_id', ''));
$language = trim(setting($siteSettings, 'google_reviews_language', 'cs'));
if ($language === '') {
    $language = 'cs';
}

if ($apiKey === '' || $placeId === '') {
    http_response_code(422);
    echo json_encode([
        'configured' => false,
        'error' => 'Google recenze nejsou nakonfigurovane (chybi Place ID nebo API key).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$cachePath = dirname(__DIR__) . '/.google-reviews-cache.json';
$cacheTtlSeconds = 6 * 60 * 60;

$loadCache = static function () use ($cachePath): ?array {
    if (! is_file($cachePath) || ! is_readable($cachePath)) {
        return null;
    }

    $raw = @file_get_contents($cachePath);
    if (! is_string($raw) || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
};

$cache = $loadCache();
$cacheFetchedAt = isset($cache['fetched_at']) ? (int) $cache['fetched_at'] : 0;
$cacheIsFresh = $cacheFetchedAt > 0 && (time() - $cacheFetchedAt) < $cacheTtlSeconds;
$cacheMatchesSettings = is_array($cache)
    && (string) ($cache['place_id'] ?? '') === $placeId
    && (string) ($cache['language'] ?? '') === $language
    && is_array($cache['payload'] ?? null);

if ($cacheIsFresh && $cacheMatchesSettings) {
    $payload = $cache['payload'];
    $payload['cached'] = true;
    $payload['stale'] = false;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$url = 'https://maps.googleapis.com/maps/api/place/details/json?place_id='
    . rawurlencode($placeId)
    . '&fields=name,rating,user_ratings_total,reviews,url'
    . '&reviews_sort=newest'
    . '&language=' . rawurlencode($language)
    . '&key=' . rawurlencode($apiKey);

$fetchRemote = static function (string $remoteUrl): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($remoteUrl);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'PPStudio/GoogleReviewsWidget',
            ]);
            $body = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (is_string($body) && $body !== '' && $httpCode >= 200 && $httpCode < 300) {
                return $body;
            }
        }
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'header' => "User-Agent: PPStudio/GoogleReviewsWidget\r\n",
        ],
    ]);
    $body = @file_get_contents($remoteUrl, false, $context);

    return is_string($body) && $body !== '' ? $body : null;
};

$remoteRaw = $fetchRemote($url);
$remoteDecoded = is_string($remoteRaw) ? json_decode($remoteRaw, true) : null;
$googleStatus = is_array($remoteDecoded) ? (string) ($remoteDecoded['status'] ?? '') : '';
$googleResult = is_array($remoteDecoded) && is_array($remoteDecoded['result'] ?? null)
    ? $remoteDecoded['result']
    : null;

if ($googleStatus !== 'OK' || ! is_array($googleResult)) {
    if ($cacheMatchesSettings) {
        $payload = $cache['payload'];
        $payload['cached'] = true;
        $payload['stale'] = true;
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(502);
    echo json_encode([
        'configured' => true,
        'error' => 'Google API nevratilo data recenzi.',
        'details' => $googleStatus !== '' ? $googleStatus : 'fetch_failed',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$reviews = [];
$sourceReviews = $googleResult['reviews'] ?? [];
if (is_array($sourceReviews)) {
    foreach ($sourceReviews as $review) {
        if (! is_array($review)) {
            continue;
        }
        $reviews[] = [
            'author_name' => trim((string) ($review['author_name'] ?? 'Klientka')),
            'rating' => max(1, min(5, (int) ($review['rating'] ?? 0))),
            'text' => trim((string) ($review['text'] ?? '')),
            'relative_time_description' => trim((string) ($review['relative_time_description'] ?? '')),
            'time' => (int) ($review['time'] ?? 0),
            'profile_photo_url' => trim((string) ($review['profile_photo_url'] ?? '')),
        ];
    }
}

usort($reviews, static fn(array $a, array $b): int => (int) ($b['time'] ?? 0) <=> (int) ($a['time'] ?? 0));
$reviews = array_slice($reviews, 0, 8);

$payload = [
    'configured' => true,
    'cached' => false,
    'stale' => false,
    'summary' => [
        'name' => trim((string) ($googleResult['name'] ?? 'PP Studio')),
        'rating' => (float) ($googleResult['rating'] ?? 0),
        'total_ratings' => (int) ($googleResult['user_ratings_total'] ?? 0),
        'url' => trim((string) ($googleResult['url'] ?? setting($siteSettings, 'google_reviews_url', ''))),
    ],
    'reviews' => $reviews,
];

$cacheData = [
    'fetched_at' => time(),
    'place_id' => $placeId,
    'language' => $language,
    'payload' => $payload,
];
@file_put_contents($cachePath, json_encode($cacheData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
