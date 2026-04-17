<?php
declare(strict_types=1);

namespace PPStudio\Service\Story;

final class StoryStyleFactory
{
    /**
     * @return array{
     *   width:int,
     *   height:int,
     *   title_size:int,
     *   month_size:int,
     *   slot_size:int,
     *   service_size:int,
     *   top:array{0:int,1:int,2:int},
     *   bottom:array{0:int,1:int,2:int},
     *   decor:string
     * }
     */
    public function create(string $style): array
    {
        return match ($style) {
            'minimal' => [
                'width' => 1080,
                'height' => 1920,
                'title_size' => 38,
                'month_size' => 26,
                'slot_size' => 32,
                'service_size' => 30,
                'top' => [250, 245, 238],
                'bottom' => [242, 235, 226],
                'decor' => 'minimal',
            ],
            'feed' => [
                'width' => 1080,
                'height' => 1350,
                'title_size' => 34,
                'month_size' => 24,
                'slot_size' => 28,
                'service_size' => 26,
                'top' => [249, 244, 236],
                'bottom' => [239, 230, 219],
                'decor' => 'feed',
            ],
            default => [
                'width' => 1080,
                'height' => 1920,
                'title_size' => 40,
                'month_size' => 28,
                'slot_size' => 34,
                'service_size' => 32,
                'top' => [251, 247, 241],
                'bottom' => [242, 232, 219],
                'decor' => 'story',
            ],
        };
    }

    /**
     * @param array{0:int,1:int,2:int} $rgb
     * @return array{0:int,1:int,2:int,3:int}
     */
    public function color(array $rgb, int $alpha = 0): array
    {
        return [$rgb[0], $rgb[1], $rgb[2], $alpha];
    }

    public function normalizeText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    public function hasDecorativeEmoji(string $text): bool
    {
        return preg_match('/[✨⭐🌟💫❇️❈❊]/u', $text) === 1;
    }
}
