<?php
declare(strict_types=1);

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
    $fromMonth = (int) $from->format('n');
    $toMonth = (int) $to->format('n');
    $fromYear = (int) $from->format('Y');
    $toYear = (int) $to->format('Y');

    if ($fromMonth === $toMonth && $fromYear === $toYear) {
        return mb_convert_case(ppstudioCzechMonthName($fromMonth), MB_CASE_TITLE, 'UTF-8');
    }

    $fromLabel = mb_convert_case(ppstudioCzechMonthName($fromMonth), MB_CASE_TITLE, 'UTF-8');
    $toLabel = mb_convert_case(ppstudioCzechMonthName($toMonth), MB_CASE_TITLE, 'UTF-8');

    if ($fromYear !== $toYear) {
        return $fromLabel . ' ' . $fromYear . ' / ' . $toLabel . ' ' . $toYear;
    }

    return $fromLabel . ' / ' . $toLabel;
}

function ppstudioCollectFreeSlotsForStory(mysqli $connection, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $rangeStart = $from->setTime(0, 0, 0);
    $rangeEnd = $to->setTime(23, 59, 59);
    $rangeStartSql = $rangeStart->format('Y-m-d H:i:s');
    $rangeEndSql = $rangeEnd->format('Y-m-d H:i:s');
    $now = new DateTimeImmutable('now');
    $slotSizeSeconds = 30 * 60;

    $available = [];
    $booked = [];

    $windowStatement = $connection->prepare(
        'SELECT start_at, end_at
         FROM dostupnost
         WHERE start_at <= ?
           AND end_at >= ?
           AND end_at > start_at
         ORDER BY start_at ASC'
    );

    if ($windowStatement) {
        $windowStatement->bind_param('ss', $rangeEndSql, $rangeStartSql);
        $windowStatement->execute();
        $windowStatement->bind_result($windowStartAt, $windowEndAt);

        while ($windowStatement->fetch()) {
            if (! is_string($windowStartAt) || ! is_string($windowEndAt) || $windowStartAt === '' || $windowEndAt === '') {
                continue;
            }

            $startTs = strtotime($windowStartAt);
            $endTs = strtotime($windowEndAt);
            if ($startTs === false || $endTs === false || $endTs <= $startTs) {
                continue;
            }

            $startTs = max($startTs, $rangeStart->getTimestamp(), $now->getTimestamp());
            $endTs = min($endTs, $rangeEnd->getTimestamp());
            if ($endTs <= $startTs) {
                continue;
            }

            $cursorTs = (int) (ceil($startTs / $slotSizeSeconds) * $slotSizeSeconds);
            while ($cursorTs < $endTs) {
                $dateKey = date('Y-m-d', $cursorTs);
                $timeValue = date('H:i', $cursorTs);
                $available[$dateKey][$timeValue] = true;
                $cursorTs += $slotSizeSeconds;
            }
        }

        $windowStatement->close();
    }

    $bookingStatement = $connection->prepare(
        'SELECT r.datum_cas, s.doba_trvani
         FROM rezervace r
         INNER JOIN sluzby s ON s.id = r.sluzba
         WHERE r.datum_cas >= ?
           AND r.datum_cas <= ?
           AND r.stav IN ("nova", "potvrzena", "dokoncena")
         ORDER BY r.datum_cas ASC'
    );

    if ($bookingStatement) {
        $bookingStatement->bind_param('ss', $rangeStartSql, $rangeEndSql);
        $bookingStatement->execute();
        $bookingStatement->bind_result($bookingStartAt, $bookingDuration);

        while ($bookingStatement->fetch()) {
            if (! is_string($bookingStartAt) || $bookingStartAt === '') {
                continue;
            }

            $bookingStartTs = strtotime($bookingStartAt);
            if ($bookingStartTs === false) {
                continue;
            }

            $durationSeconds = max(15, (int) $bookingDuration) * 60;
            $bookingEndTs = $bookingStartTs + $durationSeconds;
            $bookingStartTs = max($bookingStartTs, $rangeStart->getTimestamp(), $now->getTimestamp());
            $bookingEndTs = min($bookingEndTs, $rangeEnd->getTimestamp());

            if ($bookingEndTs <= $bookingStartTs) {
                continue;
            }

            $cursorTs = (int) (floor($bookingStartTs / $slotSizeSeconds) * $slotSizeSeconds);
            while ($cursorTs < $bookingEndTs) {
                $dateKey = date('Y-m-d', $cursorTs);
                $timeValue = date('H:i', $cursorTs);
                $booked[$dateKey][$timeValue] = true;
                $cursorTs += $slotSizeSeconds;
            }
        }

        $bookingStatement->close();
    }

    $result = [];
    foreach ($available as $dateKey => $times) {
        $freeTimes = array_diff_key($times, $booked[$dateKey] ?? []);
        if ($freeTimes === []) {
            continue;
        }

        $timeValues = array_keys($freeTimes);
        sort($timeValues);
        $result[$dateKey] = $timeValues;
    }

    ksort($result);

    return $result;
}

function ppstudioStoryFindFont(bool $bold = false): ?string
{
    $candidates = $bold
        ? [
            __DIR__ . '/../../assets/fonts/ppstudio-story-bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            '/Library/Fonts/Arial Bold.ttf',
        ]
        : [
            __DIR__ . '/../../assets/fonts/ppstudio-story-regular.ttf',
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

function ppstudioStoryNormalizeText(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $text = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

function ppstudioStoryHasDecorativeEmoji(string $text): bool
{
    return preg_match('/[✨⭐🌟💫❇️❈❊]/u', $text) === 1;
}

function ppstudioStoryStyleConfig(string $style): array
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

function ppstudioStoryColor(array $rgb, int $alpha = 0): array
{
    return [$rgb[0], $rgb[1], $rgb[2], $alpha];
}

function ppstudioStoryAllocateColor(GdImage $image, array $color): int
{
    return imagecolorallocatealpha(
        $image,
        (int) ($color[0] ?? 0),
        (int) ($color[1] ?? 0),
        (int) ($color[2] ?? 0),
        (int) ($color[3] ?? 0)
    );
}

function ppstudioStoryFillGradient(GdImage $image, array $top, array $bottom): void
{
    $width = imagesx($image);
    $height = imagesy($image);

    for ($y = 0; $y < $height; $y++) {
        $ratio = $height > 1 ? $y / ($height - 1) : 0;
        $r = (int) round($top[0] + (($bottom[0] - $top[0]) * $ratio));
        $g = (int) round($top[1] + (($bottom[1] - $top[1]) * $ratio));
        $b = (int) round($top[2] + (($bottom[2] - $top[2]) * $ratio));
        $color = imagecolorallocate($image, $r, $g, $b);
        imageline($image, 0, $y, $width, $y, $color);
    }
}

function ppstudioStoryLoadImage(string $path): GdImage|false
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

function ppstudioStoryApplyBackgroundImage(GdImage $canvas, string $backgroundPath): bool
{
    $source = ppstudioStoryLoadImage($backgroundPath);
    if (! $source instanceof GdImage) {
        return false;
    }

    $canvasWidth = imagesx($canvas);
    $canvasHeight = imagesy($canvas);
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);

    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
        return false;
    }

    $scale = max($canvasWidth / $sourceWidth, $canvasHeight / $sourceHeight);
    $targetWidth = (int) ceil($sourceWidth * $scale);
    $targetHeight = (int) ceil($sourceHeight * $scale);
    $offsetX = (int) floor(($canvasWidth - $targetWidth) / 2);
    $offsetY = (int) floor(($canvasHeight - $targetHeight) / 2);

    imagecopyresampled(
        $canvas,
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

    return true;
}

function ppstudioStoryRoundedBox(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $fillColor): void
{
    imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $fillColor);
    imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $fillColor);
    imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $fillColor);
    imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $fillColor);
    imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $fillColor);
    imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $fillColor);
}

function ppstudioStoryMeasureText(string $text, int $fontSize, ?string $fontFile): int
{
    if ($fontFile !== null && function_exists('imagettfbbox')) {
        $box = imagettfbbox($fontSize, 0, $fontFile, $text);
        if (is_array($box)) {
            return (int) abs(($box[2] ?? 0) - ($box[0] ?? 0));
        }
    }

    return imagefontwidth(5) * strlen($text);
}

function ppstudioStoryWrapText(string $text, int $fontSize, int $maxWidth, ?string $fontFile): array
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
        if (ppstudioStoryMeasureText($candidate, $fontSize, $fontFile) <= $maxWidth || $current === '') {
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

function ppstudioStoryDrawCenteredText(GdImage $image, string $text, int $centerX, int $y, int $fontSize, int $color, ?string $fontFile): int
{
    if ($fontFile !== null && function_exists('imagettftext')) {
        $box = imagettfbbox($fontSize, 0, $fontFile, $text);
        $width = is_array($box) ? (int) abs(($box[2] ?? 0) - ($box[0] ?? 0)) : 0;
        $x = (int) round($centerX - ($width / 2));
        imagettftext($image, $fontSize, 0, $x, $y, $color, $fontFile, $text);
        return $y + (int) round($fontSize * 1.45);
    }

    $font = 5;
    $width = imagefontwidth($font) * strlen($text);
    $x = (int) round($centerX - ($width / 2));
    imagestring($image, $font, $x, $y, $text, $color);
    return $y + imagefontheight($font) + 10;
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
    if ($lines === []) {
        return $topY;
    }

    $maxWidth = 0;
    foreach ($lines as $line) {
        $maxWidth = max($maxWidth, ppstudioStoryMeasureText($line, $fontSize, $fontFile));
    }

    $lineStep = $fontFile !== null ? (int) round($fontSize * 1.4) : imagefontheight(5) + 10;
    $boxWidth = $maxWidth + ($horizontalPadding * 2);
    $boxHeight = (count($lines) * $lineStep) + ($verticalPadding * 2) - (int) round($fontSize * 0.2);
    $x1 = (int) round($centerX - ($boxWidth / 2));
    $x2 = $x1 + $boxWidth;
    $y2 = $topY + $boxHeight;

    ppstudioStoryRoundedBox($image, $x1, $topY, $x2, $y2, $radius, $fillColor);

    $baselineY = $topY + $verticalPadding + $fontSize;
    foreach ($lines as $line) {
        if ($fontFile !== null && function_exists('imagettftext')) {
            $box = imagettfbbox($fontSize, 0, $fontFile, $line);
            $width = is_array($box) ? (int) abs(($box[2] ?? 0) - ($box[0] ?? 0)) : 0;
            $x = (int) round($centerX - ($width / 2));
            imagettftext($image, $fontSize, 0, $x, $baselineY, $textColor, $fontFile, $line);
        } else {
            $font = 5;
            $width = imagefontwidth($font) * strlen($line);
            $x = (int) round($centerX - ($width / 2));
            imagestring($image, $font, $x, $baselineY - imagefontheight($font), $line, $textColor);
        }
        $baselineY += $lineStep;
    }

    return $y2;
}

function ppstudioStoryDrawDecor(GdImage $image, string $variant = 'story'): void
{
    $goldSoft = ppstudioStoryAllocateColor($image, ppstudioStoryColor([217, 190, 130], 96));
    $goldLine = ppstudioStoryAllocateColor($image, ppstudioStoryColor([195, 160, 90], 78));
    $goldDust = ppstudioStoryAllocateColor($image, ppstudioStoryColor([222, 198, 142], 92));
    imagesetthickness($image, $variant === 'minimal' ? 1 : 2);

    if ($variant === 'feed') {
        imagearc($image, 110, 135, 220, 170, 160, 350, $goldSoft);
        imagearc($image, 960, 120, 320, 150, 185, 20, $goldSoft);
        imagearc($image, 1020, 1230, 260, 220, 170, 330, $goldLine);
        imagearc($image, 50, 1240, 200, 180, 10, 180, $goldSoft);
        return;
    }

    if ($variant === 'minimal') {
        imagearc($image, 160, 180, 300, 210, 170, 350, $goldSoft);
        imagearc($image, 930, 170, 380, 220, 195, 20, $goldSoft);
        imagearc($image, 1000, 1710, 320, 240, 175, 340, $goldLine);
        imagearc($image, 80, 1700, 220, 210, 5, 180, $goldLine);
        return;
    }

    imagearc($image, 160, 180, 300, 210, 170, 350, $goldSoft);
    imagearc($image, 890, 170, 420, 260, 190, 30, $goldSoft);
    imagearc($image, 930, 120, 520, 160, 195, 355, $goldLine);
    imagearc($image, 130, 1690, 260, 220, 5, 190, $goldLine);
    imagearc($image, 1000, 1710, 360, 300, 175, 340, $goldSoft);

    imageline($image, 760, 120, 1020, 40, $goldSoft);
    imageline($image, 790, 145, 1040, 65, $goldSoft);
    imageline($image, 805, 1540, 1045, 1620, $goldSoft);
    imageline($image, 30, 1500, 210, 1570, $goldSoft);

    for ($i = 0; $i < 46; $i++) {
        $x = 80 + ($i * 20);
        $y = 102 + (($i % 4) * 7);
        imagefilledellipse($image, $x, $y, 3, 3, $goldDust);
    }

    for ($i = 0; $i < 36; $i++) {
        $x = 770 + (($i % 6) * 26);
        $y = 1390 + (int) floor($i / 6) * 16;
        imagefilledellipse($image, $x, $y, 3, 3, $goldDust);
    }
}

function ppstudioStoryDrawSparkle(GdImage $image, int $centerX, int $centerY, int $size, int $color): void
{
    imagesetthickness($image, 2);
    imageline($image, $centerX, $centerY - $size, $centerX, $centerY + $size, $color);
    imageline($image, $centerX - $size, $centerY, $centerX + $size, $centerY, $color);
    imageline($image, $centerX - (int) round($size * 0.7), $centerY - (int) round($size * 0.7), $centerX + (int) round($size * 0.7), $centerY + (int) round($size * 0.7), $color);
    imageline($image, $centerX - (int) round($size * 0.7), $centerY + (int) round($size * 0.7), $centerX + (int) round($size * 0.7), $centerY - (int) round($size * 0.7), $color);
}

function ppstudioRenderAvailabilityStoryImage(
    string $title,
    string $monthLabel,
    array $slotLines,
    array $serviceLines,
    string $style = 'story',
    string $backgroundPath = ''
): GdImage
{
    $config = ppstudioStoryStyleConfig($style);
    $width = (int) ($config['width'] ?? 1080);
    $height = (int) ($config['height'] ?? 1920);
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, true);
    imagesavealpha($image, true);

    $backgroundApplied = false;
    if ($backgroundPath !== '') {
        $backgroundApplied = ppstudioStoryApplyBackgroundImage($image, $backgroundPath);
    }
    if (! $backgroundApplied) {
        ppstudioStoryFillGradient($image, $config['top'], $config['bottom']);
    }
    ppstudioStoryDrawDecor($image, (string) ($config['decor'] ?? 'story'));

    $titleHasDecor = ppstudioStoryHasDecorativeEmoji($title);
    $monthHasDecor = ppstudioStoryHasDecorativeEmoji($monthLabel);

    $white = ppstudioStoryAllocateColor($image, ppstudioStoryColor([255, 255, 255], 18));
    $text = ppstudioStoryAllocateColor($image, ppstudioStoryColor([65, 46, 33], 0));
    $muted = ppstudioStoryAllocateColor($image, ppstudioStoryColor([132, 111, 93], 0));
    $sparkleColor = ppstudioStoryAllocateColor($image, ppstudioStoryColor([200, 166, 95], 36));

    $fontRegular = ppstudioStoryFindFont(false);
    $fontBold = ppstudioStoryFindFont(true) ?? $fontRegular;

    $centerX = (int) ($width / 2);
    $title = ppstudioStoryNormalizeText($title);
    $monthLabel = ppstudioStoryNormalizeText($monthLabel);
    $titleLines = ppstudioStoryWrapText($title, (int) ($config['title_size'] ?? 40), $style === 'feed' ? 740 : 720, $fontBold);
    if ($titleLines === []) {
        $titleLines = ['Volné termíny'];
    }
    $headingTop = $style === 'feed' ? 100 : 170;
    $headingBottom = ppstudioStoryDrawLabel($image, $titleLines, $centerX, $headingTop, (int) ($config['title_size'] ?? 40), $text, $white, $fontBold, 40, 22, 22);
    $monthBottom = ppstudioStoryDrawLabel($image, [$monthLabel !== '' ? $monthLabel : 'Aktuální období'], $centerX, $headingBottom + 12, (int) ($config['month_size'] ?? 28), $muted, $white, $fontRegular, 28, 18, 18);

    if ($style !== 'feed' || $titleHasDecor || $monthHasDecor) {
        ppstudioStoryDrawSparkle($image, $centerX - 270, $headingTop + 36, 10, $sparkleColor);
        ppstudioStoryDrawSparkle($image, $centerX + 270, $headingTop + 34, 10, $sparkleColor);
        if ($monthHasDecor || $style !== 'feed') {
            ppstudioStoryDrawSparkle($image, $centerX - 92, $monthBottom - 18, 7, $sparkleColor);
            ppstudioStoryDrawSparkle($image, $centerX + 92, $monthBottom - 18, 7, $sparkleColor);
        }
    }

    $currentY = $monthBottom + ($style === 'feed' ? 42 : 62);
    foreach ($slotLines as $line) {
        $line = ppstudioStoryNormalizeText($line);
        $wrapped = ppstudioStoryWrapText($line, (int) ($config['slot_size'] ?? 34), $style === 'feed' ? 760 : 660, $fontRegular);
        $boxBottom = ppstudioStoryDrawLabel($image, $wrapped, $centerX, $currentY, (int) ($config['slot_size'] ?? 34), $text, $white, $fontRegular, 34, 16, 18);
        $currentY = $boxBottom + ($style === 'feed' ? 16 : 22);
    }

    if ($serviceLines !== []) {
        $currentY += $style === 'feed' ? 24 : 36;
        foreach ($serviceLines as $serviceLine) {
            $serviceLine = ppstudioStoryNormalizeText($serviceLine);
            if ($serviceLine === '') {
                continue;
            }
            $wrapped = ppstudioStoryWrapText($serviceLine, (int) ($config['service_size'] ?? 32), $style === 'feed' ? 660 : 620, $fontRegular);
            $boxBottom = ppstudioStoryDrawLabel($image, $wrapped, $centerX, $currentY, (int) ($config['service_size'] ?? 32), $text, $white, $fontRegular, 30, 15, 18);
            $currentY = $boxBottom + 18;
        }
    }

    return $image;
}
