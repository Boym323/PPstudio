<?php
declare(strict_types=1);

namespace PPStudio\Service;

use GdImage;
use PPStudio\Service\Story\AvailabilityStoryRenderer;
use PPStudio\Service\Story\StoryFontResolver;
use PPStudio\Service\Story\StoryStyleFactory;

final class AvailabilityStoryService
{
    private ?AvailabilityStoryRenderer $renderer = null;

    public function __construct(
        private ?AvailabilityStoryRenderer $availabilityStoryRenderer = null,
        private ?string $projectRoot = null
    ) {
        $this->projectRoot ??= dirname(__DIR__, 2);
    }

    /**
     * @param array<int, string> $slotLines
     * @param array<int, string> $serviceLines
     */
    public function renderImage(
        string $title,
        string $monthLabel,
        array $slotLines,
        array $serviceLines,
        string $style = 'story',
        string $backgroundPath = ''
    ): GdImage {
        return $this->renderer()->render($title, $monthLabel, $slotLines, $serviceLines, $style, $backgroundPath);
    }

    public function renderer(): AvailabilityStoryRenderer
    {
        if ($this->availabilityStoryRenderer instanceof AvailabilityStoryRenderer) {
            return $this->availabilityStoryRenderer;
        }

        if ($this->renderer instanceof AvailabilityStoryRenderer) {
            return $this->renderer;
        }

        $this->renderer = new AvailabilityStoryRenderer(
            new StoryStyleFactory(),
            new StoryFontResolver($this->projectRoot)
        );

        return $this->renderer;
    }
}
