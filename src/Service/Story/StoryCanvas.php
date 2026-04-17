<?php
declare(strict_types=1);

namespace PPStudio\Service\Story;

use GdImage;

final class StoryCanvas
{
    public function __construct(
        private GdImage $image
    ) {
    }

    public function image(): GdImage
    {
        return $this->image;
    }

    /**
     * @param array{0:int,1:int,2:int,3:int} $color
     */
    public function allocateColor(array $color): int
    {
        return imagecolorallocatealpha(
            $this->image,
            (int) ($color[0] ?? 0),
            (int) ($color[1] ?? 0),
            (int) ($color[2] ?? 0),
            (int) ($color[3] ?? 0)
        );
    }

    /**
     * @param array{0:int,1:int,2:int} $top
     * @param array{0:int,1:int,2:int} $bottom
     */
    public function fillGradient(array $top, array $bottom): void
    {
        $width = imagesx($this->image);
        $height = imagesy($this->image);

        for ($y = 0; $y < $height; $y++) {
            $ratio = $height > 1 ? $y / ($height - 1) : 0;
            $r = (int) round($top[0] + (($bottom[0] - $top[0]) * $ratio));
            $g = (int) round($top[1] + (($bottom[1] - $top[1]) * $ratio));
            $b = (int) round($top[2] + (($bottom[2] - $top[2]) * $ratio));
            $color = imagecolorallocate($this->image, $r, $g, $b);
            imageline($this->image, 0, $y, $width, $y, $color);
        }
    }

    public function loadImage(string $path): GdImage|false
    {
        if (! is_file($path) || ! is_readable($path) || ! function_exists('getimagesize')) {
            return false;
        }

        $info = @getimagesize($path);
        if (! is_array($info) || count($info) < 3) {
            return false;
        }

        return match ((int) ($info[2] ?? 0)) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            IMAGETYPE_GIF => function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : false,
            default => false,
        };
    }

    public function applyBackgroundImage(string $backgroundPath): bool
    {
        $source = $this->loadImage($backgroundPath);
        if (! $source instanceof GdImage) {
            return false;
        }

        $canvasWidth = imagesx($this->image);
        $canvasHeight = imagesy($this->image);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);
            return false;
        }

        $scale = max($canvasWidth / $sourceWidth, $canvasHeight / $sourceHeight);
        $targetWidth = (int) ceil($sourceWidth * $scale);
        $targetHeight = (int) ceil($sourceHeight * $scale);
        $offsetX = (int) floor(($canvasWidth - $targetWidth) / 2);
        $offsetY = (int) floor(($canvasHeight - $targetHeight) / 2);

        imagecopyresampled(
            $this->image,
            $source,
            $offsetX,
            $offsetY,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        imagedestroy($source);

        return true;
    }

    public function roundedBox(int $x1, int $y1, int $x2, int $y2, int $radius, int $fillColor): void
    {
        imagefilledrectangle($this->image, $x1 + $radius, $y1, $x2 - $radius, $y2, $fillColor);
        imagefilledrectangle($this->image, $x1, $y1 + $radius, $x2, $y2 - $radius, $fillColor);
        imagefilledellipse($this->image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $fillColor);
        imagefilledellipse($this->image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $fillColor);
        imagefilledellipse($this->image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $fillColor);
        imagefilledellipse($this->image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $fillColor);
    }

    public function measureText(string $text, int $fontSize, ?string $fontFile): int
    {
        if ($fontFile !== null && function_exists('imagettfbbox')) {
            $box = imagettfbbox($fontSize, 0, $fontFile, $text);
            if (is_array($box)) {
                return (int) abs(($box[2] ?? 0) - ($box[0] ?? 0));
            }
        }

        return imagefontwidth(5) * strlen($text);
    }

    /**
     * @return array<int, string>
     */
    public function wrapText(string $text, int $fontSize, int $maxWidth, ?string $fontFile): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($this->measureText($candidate, $fontSize, $fontFile) <= $maxWidth || $current === '') {
                $current = $candidate;
                continue;
            }

            $lines[] = $current;
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    public function drawCenteredText(
        string $text,
        int $centerX,
        int $y,
        int $fontSize,
        int $color,
        ?string $fontFile
    ): int {
        if ($fontFile !== null && function_exists('imagettftext')) {
            $box = imagettfbbox($fontSize, 0, $fontFile, $text);
            $width = is_array($box) ? (int) abs(($box[2] ?? 0) - ($box[0] ?? 0)) : 0;
            $x = (int) round($centerX - ($width / 2));
            imagettftext($this->image, $fontSize, 0, $x, $y, $color, $fontFile, $text);

            return $y + (int) round($fontSize * 1.45);
        }

        $font = 5;
        $width = imagefontwidth($font) * strlen($text);
        $x = (int) round($centerX - ($width / 2));
        imagestring($this->image, $font, $x, $y, $text, $color);

        return $y + imagefontheight($font) + 10;
    }

    /**
     * @param array<int, string> $lines
     */
    public function drawLabel(
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
        if ($lines === []) {
            return $topY;
        }

        $maxWidth = 0;
        foreach ($lines as $line) {
            $maxWidth = max($maxWidth, $this->measureText($line, $fontSize, $fontFile));
        }

        $lineStep = $fontFile !== null ? (int) round($fontSize * 1.4) : imagefontheight(5) + 10;
        $boxWidth = $maxWidth + ($horizontalPadding * 2);
        $boxHeight = (count($lines) * $lineStep) + ($verticalPadding * 2) - (int) round($fontSize * 0.2);
        $x1 = (int) round($centerX - ($boxWidth / 2));
        $x2 = $x1 + $boxWidth;
        $y2 = $topY + $boxHeight;

        $this->roundedBox($x1, $topY, $x2, $y2, $radius, $fillColor);

        $baselineY = $topY + $verticalPadding + $fontSize;
        foreach ($lines as $line) {
            if ($fontFile !== null && function_exists('imagettftext')) {
                $box = imagettfbbox($fontSize, 0, $fontFile, $line);
                $width = is_array($box) ? (int) abs(($box[2] ?? 0) - ($box[0] ?? 0)) : 0;
                $x = (int) round($centerX - ($width / 2));
                imagettftext($this->image, $fontSize, 0, $x, $baselineY, $textColor, $fontFile, $line);
            } else {
                $font = 5;
                $width = imagefontwidth($font) * strlen($line);
                $x = (int) round($centerX - ($width / 2));
                imagestring($this->image, $font, $x, $baselineY - imagefontheight($font), $line, $textColor);
            }
            $baselineY += $lineStep;
        }

        return $y2;
    }

    public function drawDecor(string $variant = 'story'): void
    {
        $goldSoft = $this->allocateColor([217, 190, 130, 96]);
        $goldLine = $this->allocateColor([195, 160, 90, 78]);
        $goldDust = $this->allocateColor([222, 198, 142, 92]);
        imagesetthickness($this->image, $variant === 'minimal' ? 1 : 2);

        if ($variant === 'feed') {
            imagearc($this->image, 110, 135, 220, 170, 160, 350, $goldSoft);
            imagearc($this->image, 960, 120, 320, 150, 185, 20, $goldSoft);
            imagearc($this->image, 1020, 1230, 260, 220, 170, 330, $goldLine);
            imagearc($this->image, 50, 1240, 200, 180, 10, 180, $goldSoft);

            return;
        }

        if ($variant === 'minimal') {
            imagearc($this->image, 160, 180, 300, 210, 170, 350, $goldSoft);
            imagearc($this->image, 930, 170, 380, 220, 195, 20, $goldSoft);
            imagearc($this->image, 1000, 1710, 320, 240, 175, 340, $goldLine);
            imagearc($this->image, 80, 1700, 220, 210, 5, 180, $goldLine);

            return;
        }

        imagearc($this->image, 160, 180, 300, 210, 170, 350, $goldSoft);
        imagearc($this->image, 890, 170, 420, 260, 190, 30, $goldSoft);
        imagearc($this->image, 930, 120, 520, 160, 195, 355, $goldLine);
        imagearc($this->image, 130, 1690, 260, 220, 5, 190, $goldLine);
        imagearc($this->image, 1000, 1710, 360, 300, 175, 340, $goldSoft);

        imageline($this->image, 760, 120, 1020, 40, $goldSoft);
        imageline($this->image, 790, 145, 1040, 65, $goldSoft);
        imageline($this->image, 805, 1540, 1045, 1620, $goldSoft);
        imageline($this->image, 30, 1500, 210, 1570, $goldSoft);

        for ($i = 0; $i < 46; $i++) {
            $x = 80 + ($i * 20);
            $y = 102 + (($i % 4) * 7);
            imagefilledellipse($this->image, $x, $y, 3, 3, $goldDust);
        }

        for ($i = 0; $i < 36; $i++) {
            $x = 770 + (($i % 6) * 26);
            $y = 1390 + (int) floor($i / 6) * 16;
            imagefilledellipse($this->image, $x, $y, 3, 3, $goldDust);
        }
    }

    public function drawSparkle(int $centerX, int $centerY, int $size, int $color): void
    {
        imagesetthickness($this->image, 2);
        imageline($this->image, $centerX, $centerY - $size, $centerX, $centerY + $size, $color);
        imageline($this->image, $centerX - $size, $centerY, $centerX + $size, $centerY, $color);
        imageline(
            $this->image,
            $centerX - (int) round($size * 0.7),
            $centerY - (int) round($size * 0.7),
            $centerX + (int) round($size * 0.7),
            $centerY + (int) round($size * 0.7),
            $color
        );
        imageline(
            $this->image,
            $centerX - (int) round($size * 0.7),
            $centerY + (int) round($size * 0.7),
            $centerX + (int) round($size * 0.7),
            $centerY - (int) round($size * 0.7),
            $color
        );
    }
}
