<?php
declare(strict_types=1);

use PPStudio\Service\AdminAvailabilityStoryService;
use PPStudio\Service\AvailabilityStoryService;
use PPStudio\Service\Story\StoryCanvas;
use PPStudio\Service\Story\StoryFontResolver;
use PPStudio\Service\Story\StoryStyleFactory;

function ppstudioCzechMonthName(int $month): string
{
    $months = [
        1 => 'leden',
        2 => 'únor',
        3 => 'březen',
        4 => 'duben',
        5 => 'květen',
        6 => 'červen',
        7 => 'červenec',
        8 => 'srpen',
        9 => 'září',
        10 => 'říjen',
        11 => 'listopad',
        12 => 'prosinec',
    ];

    return $months[$month] ?? '';
}

function ppstudioAvailabilityStoryMonthLabel(DateTimeImmutable $from, DateTimeImmutable $to): string
{
    return AdminAvailabilityStoryService::buildMonthLabel($from, $to);
}

function ppstudioCollectFreeSlotsForStory(mysqli $connection, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    return (new AdminAvailabilityStoryService($connection))->collectFreeSlots($from, $to);
}

function ppstudioStoryFindFont(bool $bold = false): ?string
{
    return (new StoryFontResolver(dirname(__DIR__, 2)))->findFont($bold);
}

function ppstudioStoryNormalizeText(string $text): string
{
    return (new StoryStyleFactory())->normalizeText($text);
}

function ppstudioStoryHasDecorativeEmoji(string $text): bool
{
    return (new StoryStyleFactory())->hasDecorativeEmoji($text);
}

function ppstudioStoryStyleConfig(string $style): array
{
    return (new StoryStyleFactory())->create($style);
}

function ppstudioStoryColor(array $rgb, int $alpha = 0): array
{
    return (new StoryStyleFactory())->color($rgb, $alpha);
}

function ppstudioStoryAllocateColor(GdImage $image, array $color): int
{
    return (new StoryCanvas($image))->allocateColor($color);
}

function ppstudioStoryFillGradient(GdImage $image, array $top, array $bottom): void
{
    (new StoryCanvas($image))->fillGradient($top, $bottom);
}

function ppstudioStoryLoadImage(string $path): GdImage|false
{
    $canvas = imagecreatetruecolor(1, 1);
    $storyCanvas = new StoryCanvas($canvas);
    $image = $storyCanvas->loadImage($path);
    imagedestroy($canvas);

    return $image;
}

function ppstudioStoryApplyBackgroundImage(GdImage $canvas, string $backgroundPath): bool
{
    return (new StoryCanvas($canvas))->applyBackgroundImage($backgroundPath);
}

function ppstudioStoryRoundedBox(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $fillColor): void
{
    (new StoryCanvas($image))->roundedBox($x1, $y1, $x2, $y2, $radius, $fillColor);
}

function ppstudioStoryMeasureText(string $text, int $fontSize, ?string $fontFile): int
{
    $canvas = imagecreatetruecolor(1, 1);
    $storyCanvas = new StoryCanvas($canvas);
    $width = $storyCanvas->measureText($text, $fontSize, $fontFile);
    imagedestroy($canvas);

    return $width;
}

function ppstudioStoryWrapText(string $text, int $fontSize, int $maxWidth, ?string $fontFile): array
{
    $canvas = imagecreatetruecolor(1, 1);
    $storyCanvas = new StoryCanvas($canvas);
    $lines = $storyCanvas->wrapText($text, $fontSize, $maxWidth, $fontFile);
    imagedestroy($canvas);

    return $lines;
}

function ppstudioStoryDrawCenteredText(GdImage $image, string $text, int $centerX, int $y, int $fontSize, int $color, ?string $fontFile): int
{
    return (new StoryCanvas($image))->drawCenteredText($text, $centerX, $y, $fontSize, $color, $fontFile);
}

function ppstudioStoryDrawLabel(
    GdImage $image,
    array $lines,
    int $centerX,
    int $topY,
    int $fontSize,
    int $textColor,
    int $fillColor,
    ?string $fontFile,
    int $horizontalPadding = 34,
    int $verticalPadding = 22,
    int $radius = 18
): int {
    return (new StoryCanvas($image))->drawLabel(
        $lines,
        $centerX,
        $topY,
        $fontSize,
        $textColor,
        $fillColor,
        $fontFile,
        $horizontalPadding,
        $verticalPadding,
        $radius
    );
}

function ppstudioStoryDrawDecor(GdImage $image, string $variant = 'story'): void
{
    (new StoryCanvas($image))->drawDecor($variant);
}

function ppstudioStoryDrawSparkle(GdImage $image, int $centerX, int $centerY, int $size, int $color): void
{
    (new StoryCanvas($image))->drawSparkle($centerX, $centerY, $size, $color);
}

function ppstudioRenderAvailabilityStoryImage(
    string $title,
    string $monthLabel,
    array $slotLines,
    array $serviceLines,
    string $style = 'story',
    string $backgroundPath = ''
): GdImage {
    return (new AvailabilityStoryService())->renderImage(
        $title,
        $monthLabel,
        $slotLines,
        $serviceLines,
        $style,
        $backgroundPath
    );
}
