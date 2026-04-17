<?php
declare(strict_types=1);

namespace PPStudio\Service;

final class GoogleReviewsCache
{
    public function __construct(
        private string $cachePath,
        private int $cacheTtlSeconds
    ) {
    }

    public function load(): ?array
    {
        if (! is_file($this->cachePath) || ! is_readable($this->cachePath)) {
            return null;
        }

        $raw = @file_get_contents($this->cachePath);
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function isFresh(array $cache): bool
    {
        $cacheFetchedAt = isset($cache['fetched_at']) ? (int) $cache['fetched_at'] : 0;

        return $cacheFetchedAt > 0 && (time() - $cacheFetchedAt) < $this->cacheTtlSeconds;
    }

    public function matchesSettings(array $cache, string $placeId, string $language): bool
    {
        return (string) ($cache['place_id'] ?? '') === $placeId
            && (string) ($cache['language'] ?? '') === $language
            && is_array($cache['payload'] ?? null);
    }

    public function store(string $placeId, string $language, array $payload): void
    {
        $cacheData = [
            'fetched_at' => time(),
            'place_id' => $placeId,
            'language' => $language,
            'payload' => $payload,
        ];

        @file_put_contents(
            $this->cachePath,
            json_encode($cacheData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }
}
