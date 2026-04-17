<?php
declare(strict_types=1);

namespace PPStudio\Service\Story;

final class StoryFontResolver
{
    public function __construct(
        private ?string $projectRoot = null
    ) {
        $this->projectRoot ??= dirname(__DIR__, 3);
    }

    public function findFont(bool $bold = false): ?string
    {
        $candidates = $bold
            ? [
                $this->projectRoot . '/assets/fonts/ppstudio-story-bold.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
                '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
                '/Library/Fonts/Arial Bold.ttf',
            ]
            : [
                $this->projectRoot . '/assets/fonts/ppstudio-story-regular.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
                '/System/Library/Fonts/Supplemental/Arial.ttf',
                '/Library/Fonts/Arial.ttf',
            ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
