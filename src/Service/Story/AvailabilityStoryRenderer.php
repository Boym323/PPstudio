<?php
declare(strict_types=1);

namespace PPStudio\Service\Story;

use GdImage;

final class AvailabilityStoryRenderer
{
    public function __construct(
        private StoryStyleFactory $styleFactory = new StoryStyleFactory(),
        private StoryFontResolver $fontResolver = new StoryFontResolver()
    ) {
    }

    /**
     * @param array<int, string> $slotLines
     * @param array<int, string> $serviceLines
     */
    public function render(
        string $title,
        string $monthLabel,
        array $slotLines,
        array $serviceLines,
        string $style = 'story',
        string $backgroundPath = ''
    ): GdImage {
        $config = $this->styleFactory->create($style);
        $width = (int) ($config['width'] ?? 1080);
        $height = (int) ($config['height'] ?? 1920);
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $canvas = new StoryCanvas($image);
        $backgroundApplied = false;
        if ($backgroundPath !== '') {
            $backgroundApplied = $canvas->applyBackgroundImage($backgroundPath);
        }
        if (! $backgroundApplied) {
            $canvas->fillGradient($config['top'], $config['bottom']);
        }
        $canvas->drawDecor((string) ($config['decor'] ?? 'story'));

        $titleHasDecor = $this->styleFactory->hasDecorativeEmoji($title);
        $monthHasDecor = $this->styleFactory->hasDecorativeEmoji($monthLabel);
        $white = $canvas->allocateColor($this->styleFactory->color([255, 255, 255], 18));
        $text = $canvas->allocateColor($this->styleFactory->color([65, 46, 33], 0));
        $muted = $canvas->allocateColor($this->styleFactory->color([132, 111, 93], 0));
        $sparkleColor = $canvas->allocateColor($this->styleFactory->color([200, 166, 95], 36));

        $fontRegular = $this->fontResolver->findFont(false);
        $fontBold = $this->fontResolver->findFont(true) ?? $fontRegular;

        $centerX = (int) ($width / 2);
        $title = $this->styleFactory->normalizeText($title);
        $monthLabel = $this->styleFactory->normalizeText($monthLabel);
        $titleLines = $canvas->wrapText($title, (int) ($config['title_size'] ?? 40), $style === 'feed' ? 740 : 720, $fontBold);
        if ($titleLines === []) {
            $titleLines = ['Volné termíny'];
        }

        $headingTop = $style === 'feed' ? 100 : 170;
        $headingBottom = $canvas->drawLabel($titleLines, $centerX, $headingTop, (int) ($config['title_size'] ?? 40), $text, $white, $fontBold, 40, 22, 22);
        $monthBottom = $canvas->drawLabel([$monthLabel !== '' ? $monthLabel : 'Aktuální období'], $centerX, $headingBottom + 12, (int) ($config['month_size'] ?? 28), $muted, $white, $fontRegular, 28, 18, 18);

        if ($style !== 'feed' || $titleHasDecor || $monthHasDecor) {
            $canvas->drawSparkle($centerX - 270, $headingTop + 36, 10, $sparkleColor);
            $canvas->drawSparkle($centerX + 270, $headingTop + 34, 10, $sparkleColor);
            if ($monthHasDecor || $style !== 'feed') {
                $canvas->drawSparkle($centerX - 92, $monthBottom - 18, 7, $sparkleColor);
                $canvas->drawSparkle($centerX + 92, $monthBottom - 18, 7, $sparkleColor);
            }
        }

        $currentY = $monthBottom + ($style === 'feed' ? 42 : 62);
        foreach ($slotLines as $line) {
            $line = $this->styleFactory->normalizeText($line);
            $wrapped = $canvas->wrapText($line, (int) ($config['slot_size'] ?? 34), $style === 'feed' ? 760 : 660, $fontRegular);
            $boxBottom = $canvas->drawLabel($wrapped, $centerX, $currentY, (int) ($config['slot_size'] ?? 34), $text, $white, $fontRegular, 34, 16, 18);
            $currentY = $boxBottom + ($style === 'feed' ? 16 : 22);
        }

        if ($serviceLines !== []) {
            $currentY += $style === 'feed' ? 24 : 36;
            foreach ($serviceLines as $serviceLine) {
                $serviceLine = $this->styleFactory->normalizeText($serviceLine);
                if ($serviceLine === '') {
                    continue;
                }

                $wrapped = $canvas->wrapText($serviceLine, (int) ($config['service_size'] ?? 32), $style === 'feed' ? 660 : 620, $fontRegular);
                $boxBottom = $canvas->drawLabel($wrapped, $centerX, $currentY, (int) ($config['service_size'] ?? 32), $text, $white, $fontRegular, 30, 15, 18);
                $currentY = $boxBottom + 18;
            }
        }

        return $canvas->image();
    }
}
