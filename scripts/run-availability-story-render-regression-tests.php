#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Tento skript lze spoustet jen z CLI.\n";
    exit(1);
}

const SCRIPT_PREFIX = '[availability-story-render-regression-tests]';

require_once __DIR__ . '/_test_helpers.php';

/**
 * @return array<int, array{
 *   name:string,
 *   style:string,
 *   title:string,
 *   month:string,
 *   slots:array<int, string>,
 *   services:array<int, string>,
 *   background:string
 * }>
 */
function storyRenderScenarios(string $backgroundPath): array
{
    return [
        [
            'name' => 'story-gradient',
            'style' => 'story',
            'title' => 'Zbyvaji volne terminy',
            'month' => 'Duben',
            'slots' => ['17.4. 10:00, 10:30, 11:00', '18.4. 12:00, 12:30'],
            'services' => ['Lash lifting', 'Barveni ras'],
            'background' => '',
        ],
        [
            'name' => 'minimal-gradient',
            'style' => 'minimal',
            'title' => 'Zbyvaji volne terminy',
            'month' => 'Duben',
            'slots' => ['17.4. 10:00, 10:30, 11:00', '18.4. 12:00, 12:30'],
            'services' => ['Lash lifting', 'Barveni ras'],
            'background' => '',
        ],
        [
            'name' => 'feed-gradient',
            'style' => 'feed',
            'title' => 'Zbyvaji volne terminy',
            'month' => 'Duben',
            'slots' => ['17.4. 10:00, 10:30, 11:00', '18.4. 12:00, 12:30'],
            'services' => ['Lash lifting', 'Barveni ras'],
            'background' => '',
        ],
        [
            'name' => 'story-background',
            'style' => 'story',
            'title' => 'Zbyvaji volne terminy',
            'month' => 'Duben',
            'slots' => ['17.4. 10:00, 10:30, 11:00', '18.4. 12:00, 12:30'],
            'services' => ['Lash lifting', 'Barveni ras'],
            'background' => $backgroundPath,
        ],
    ];
}

function storyFixturePath(string $name): string
{
    return dirname(__DIR__) . '/scripts/fixtures/availability-story/' . $name . '.png';
}

function createDeterministicBackground(string $path): void
{
    $width = 1080;
    $height = 1920;
    $image = imagecreatetruecolor($width, $height);
    if (! $image instanceof GdImage) {
        ppstudioCliTestFail(SCRIPT_PREFIX, 'Nepodarilo se vytvorit canvas pro test background.');
    }

    for ($y = 0; $y < $height; $y++) {
        $ratio = $height > 1 ? $y / ($height - 1) : 0;
        $r = (int) round(32 + ((88 - 32) * $ratio));
        $g = (int) round(52 + ((140 - 52) * $ratio));
        $b = (int) round(76 + ((190 - 76) * $ratio));
        $lineColor = imagecolorallocate($image, $r, $g, $b);
        imageline($image, 0, $y, $width, $y, $lineColor);
    }

    $stripeColor = imagecolorallocatealpha($image, 238, 226, 198, 90);
    for ($x = -$height; $x < $width + $height; $x += 140) {
        imageline($image, $x, 0, $x + $height, $height, $stripeColor);
    }

    $accentColor = imagecolorallocatealpha($image, 255, 255, 255, 96);
    imagefilledellipse($image, 860, 260, 520, 520, $accentColor);
    imagefilledellipse($image, 260, 1460, 620, 620, $accentColor);

    if (! imagepng($image, $path)) {
        imagedestroy($image);
        ppstudioCliTestFail(SCRIPT_PREFIX, 'Nepodarilo se zapsat test background.');
    }

    imagedestroy($image);
}

function parseWriteFixturesFlag(array $args): bool
{
    return in_array('--write-fixtures', $args, true);
}

$args = $argv;
array_shift($args);
$writeFixtures = parseWriteFixturesFlag($args);

ppstudioCliTestBootstrapBase();
require dirname(__DIR__) . '/includes/admin/availability_story.php';

if (! function_exists('imagecreatetruecolor') || ! function_exists('imagepng')) {
    ppstudioCliTestFail(SCRIPT_PREFIX, 'Na serveru chybi GD extension pro render testy.');
}

$fixtureDir = dirname(__DIR__) . '/scripts/fixtures/availability-story';
if (! is_dir($fixtureDir) && ! mkdir($fixtureDir, 0775, true) && ! is_dir($fixtureDir)) {
    ppstudioCliTestFail(SCRIPT_PREFIX, 'Nepodarilo se vytvorit fixture adresar.');
}

$tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ppstudio-story-render-tests-' . bin2hex(random_bytes(4));
if (! mkdir($tempDir, 0770, true) && ! is_dir($tempDir)) {
    ppstudioCliTestFail(SCRIPT_PREFIX, 'Nepodarilo se vytvorit docasny adresar.');
}

$backgroundPath = $tempDir . '/background.png';
createDeterministicBackground($backgroundPath);

try {
    $scenarios = storyRenderScenarios($backgroundPath);

    foreach ($scenarios as $scenario) {
        $rendered = ppstudioRenderAvailabilityStoryImage(
            $scenario['title'],
            $scenario['month'],
            $scenario['slots'],
            $scenario['services'],
            $scenario['style'],
            $scenario['background']
        );

        $fixturePath = storyFixturePath($scenario['name']);
        $actualPath = $tempDir . '/' . $scenario['name'] . '.png';
        imagepng($rendered, $actualPath);
        imagedestroy($rendered);

        if ($writeFixtures) {
            if (! copy($actualPath, $fixturePath)) {
                ppstudioCliTestFail(SCRIPT_PREFIX, 'Nepodarilo se prepsat fixture pro scenar ' . $scenario['name'] . '.');
            }
            continue;
        }

        ppstudioCliTestAssertTrue(
            SCRIPT_PREFIX,
            is_file($fixturePath),
            'Chybi fixture soubor pro scenar ' . $scenario['name'] . '. Spustte skript s --write-fixtures.'
        );

        $actualBytes = file_get_contents($actualPath);
        $expectedBytes = file_get_contents($fixturePath);
        ppstudioCliTestAssertTrue(SCRIPT_PREFIX, is_string($actualBytes), 'Nepodarilo se nacist aktualni render pro scenar ' . $scenario['name'] . '.');
        ppstudioCliTestAssertTrue(SCRIPT_PREFIX, is_string($expectedBytes), 'Nepodarilo se nacist fixture render pro scenar ' . $scenario['name'] . '.');

        $actualHash = hash('sha256', $actualBytes);
        $expectedHash = hash('sha256', $expectedBytes);
        ppstudioCliTestAssertSame(
            SCRIPT_PREFIX,
            $expectedHash,
            $actualHash,
            'Render hash mismatch pro scenar ' . $scenario['name'] . '.'
        );
    }

    $invalidBackgroundImage = ppstudioRenderAvailabilityStoryImage(
        'Zbyvaji volne terminy',
        'Duben',
        ['17.4. 10:00, 10:30, 11:00', '18.4. 12:00, 12:30'],
        ['Lash lifting', 'Barveni ras'],
        'story',
        $tempDir . '/missing-background.png'
    );
    $fallbackPath = $tempDir . '/story-invalid-background.png';
    imagepng($invalidBackgroundImage, $fallbackPath);
    imagedestroy($invalidBackgroundImage);

    if (! $writeFixtures) {
        $storyGradientFixture = storyFixturePath('story-gradient');
        $storyGradientBytes = file_get_contents($storyGradientFixture);
        $fallbackBytes = file_get_contents($fallbackPath);
        ppstudioCliTestAssertTrue(SCRIPT_PREFIX, is_string($storyGradientBytes), 'Nepodarilo se nacist fixture story-gradient.');
        ppstudioCliTestAssertTrue(SCRIPT_PREFIX, is_string($fallbackBytes), 'Nepodarilo se nacist invalid-background render.');

        ppstudioCliTestAssertSame(
            SCRIPT_PREFIX,
            hash('sha256', $storyGradientBytes),
            hash('sha256', $fallbackBytes),
            'Invalid background musi fallbacknout na gradient variantu story.'
        );
    }

    echo SCRIPT_PREFIX . ' [OK] Availability story render regression tests passed.' . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    ppstudioCliTestFail(SCRIPT_PREFIX, 'Exception: ' . $exception->getMessage());
} finally {
    if (is_dir($tempDir)) {
        $files = glob($tempDir . '/*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($tempDir);
    }
}
