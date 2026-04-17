<?php
declare(strict_types=1);

namespace PPStudio\Service;

final class GoogleReviewsService
{
    public function __construct(private GoogleReviewsCache $cache)
    {
    }

    public function loadPayload(array $siteSettings): array
    {
        $apiKey = trim((string) \ppstudioEnv('PPSTUDIO_GOOGLE_PLACES_API_KEY', ''));
        $placeId = trim((string) \PPStudio\Support\SettingsHelper::setting($siteSettings, 'google_place_id', ''));
        $language = trim((string) \PPStudio\Support\SettingsHelper::setting($siteSettings, 'google_reviews_language', 'cs'));
        if ($language === '') {
            $language = 'cs';
        }

        if ($apiKey === '' || $placeId === '') {
            return [
                'http_code' => 422,
                'json_flags' => JSON_UNESCAPED_UNICODE,
                'payload' => [
                    'configured' => false,
                    'error' => 'Google recenze nejsou nakonfigurovane (chybi Place ID nebo API key).',
                ],
            ];
        }

        $cache = $this->cache->load();
        $cacheMatchesSettings = is_array($cache) && $this->cache->matchesSettings($cache, $placeId, $language);
        $cacheIsFresh = is_array($cache) && $this->cache->isFresh($cache);

        if ($cacheIsFresh && $cacheMatchesSettings) {
            $payload = $cache['payload'];
            $payload['cached'] = true;
            $payload['stale'] = false;

            return $this->response(200, $payload);
        }

        $remoteDecoded = $this->fetchGoogleDetails($apiKey, $placeId, $language);
        $googleStatus = is_array($remoteDecoded) ? (string) ($remoteDecoded['status'] ?? '') : '';
        $googleResult = is_array($remoteDecoded) && is_array($remoteDecoded['result'] ?? null)
            ? $remoteDecoded['result']
            : null;

        if ($googleStatus !== 'OK' || ! is_array($googleResult)) {
            if ($cacheMatchesSettings) {
                $payload = $cache['payload'];
                $payload['cached'] = true;
                $payload['stale'] = true;

                return $this->response(200, $payload);
            }

            return $this->response(502, [
                'configured' => true,
                'error' => 'Google API nevratilo data recenzi.',
                'details' => $googleStatus !== '' ? $googleStatus : 'fetch_failed',
            ]);
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
                'url' => trim((string) ($googleResult['url'] ?? \PPStudio\Support\SettingsHelper::setting($siteSettings, 'google_reviews_url', ''))),
            ],
            'reviews' => $reviews,
        ];

        $this->cache->store($placeId, $language, $payload);

        return $this->response(200, $payload);
    }

    private function response(int $httpCode, array $payload): array
    {
        return [
            'http_code' => $httpCode,
            'json_flags' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            'payload' => $payload,
        ];
    }

    private function fetchGoogleDetails(string $apiKey, string $placeId, string $language): ?array
    {
        $url = 'https://maps.googleapis.com/maps/api/place/details/json?place_id='
            . rawurlencode($placeId)
            . '&fields=name,rating,user_ratings_total,reviews,url'
            . '&reviews_sort=newest'
            . '&language=' . rawurlencode($language)
            . '&key=' . rawurlencode($apiKey);

        $raw = $this->fetchRemote($url);

        return is_string($raw) ? json_decode($raw, true) : null;
    }

    private function fetchRemote(string $remoteUrl): ?string
    {
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
    }
}
