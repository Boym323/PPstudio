<?php
declare(strict_types=1);

namespace PPStudio\Support;

use GdImage;
use PPStudio\Service\AvailabilityStoryService;
use Throwable;

final class AvailabilityStoryRenderRegressionTestRunner
{
    public function __construct(
        private readonly string $scriptPrefix = '[availability-story-render-regression-tests]'
    ) {
    }

    /**
     * @param array<int, string> $argv
     */
    public function run(array $argv): int
    {
        $args = $argv;
        array_shift($args);
        $writeFixtures = $this->parseWriteFixturesFlag($args);

        ppstudioCliTestBootstrapBase();

        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagepng')) {
            ppstudioCliTestFail($this->scriptPrefix, 'Na serveru chybi GD extension pro render testy.');
        }

        $fixtureDir = dirname(__DIR__, 2) . '/scripts/fixtures/availability-story';
        if (! is_dir($fixtureDir) && ! mkdir($fixtureDir, 0775, true) && ! is_dir($fixtureDir)) {
            ppstudioCliTestFail($this->scriptPrefix, 'Nepodarilo se vytvorit fixture adresar.');
        }

        $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ppstudio-story-render-tests-' . bin2hex(random_bytes(4));
        if (! mkdir($tempDir, 0770, true) && ! is_dir($tempDir)) {
            ppstudioCliTestFail($this->scriptPrefix, 'Nepodarilo se vytvorit docasny adresar.');
        }

        $backgroundPath = $tempDir . '/background.png';
        $this->createDeterministicBackground($backgroundPath);

        try {
            $storyService = new AvailabilityStoryService();
            $scenarios = $this->storyRenderScenarios($backgroundPath);

            foreach ($scenarios as $scenario) {
                $rendered = $storyService->renderImage(
                    $scenario['title'],
                    $scenario['month'],
                    $scenario['slots'],
                    $scenario['services'],
                    $scenario['style'],
                    $scenario['background']
                );

                $fixturePath = $this->storyFixturePath($scenario['name']);
                $actualPath = $tempDir . '/' . $scenario['name'] . '.png';
                imagepng($rendered, $actualPath);
                imagedestroy($rendered);

                if ($writeFixtures) {
                    if (! copy($actualPath, $fixturePath)) {
                        ppstudioCliTestFail($this->scriptPrefix, 'Nepodarilo se prepsat fixture pro scenar ' . $scenario['name'] . '.');
                    }
                    continue;
                }

                ppstudioCliTestAssertTrue(
                    $this->scriptPrefix,
                    is_file($fixturePath),
                    'Chybi fixture soubor pro scenar ' . $scenario['name'] . '. Spustte skript s --write-fixtures.'
                );

                $actualBytes = file_get_contents($actualPath);
                $expectedBytes = file_get_contents($fixturePath);
                ppstudioCliTestAssertTrue($this->scriptPrefix, is_string($actualBytes), 'Nepodarilo se nacist aktualni render pro scenar ' . $scenario['name'] . '.');
                ppstudioCliTestAssertTrue($this->scriptPrefix, is_string($expectedBytes), 'Nepodarilo se nacist fixture render pro scenar ' . $scenario['name'] . '.');

                $actualHash = hash('sha256', $actualBytes);
                $expectedHash = hash('sha256', $expectedBytes);
                ppstudioCliTestAssertSame(
                    $this->scriptPrefix,
                    $expectedHash,
                    $actualHash,
                    'Render hash mismatch pro scenar ' . $scenario['name'] . '.'
                );
            }

            $invalidBackgroundImage = $storyService->renderImage(
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
                $storyGradientFixture = $this->storyFixturePath('story-gradient');
                $storyGradientBytes = file_get_contents($storyGradientFixture);
                $fallbackBytes = file_get_contents($fallbackPath);
                ppstudioCliTestAssertTrue($this->scriptPrefix, is_string($storyGradientBytes), 'Nepodarilo se nacist fixture story-gradient.');
                ppstudioCliTestAssertTrue($this->scriptPrefix, is_string($fallbackBytes), 'Nepodarilo se nacist invalid-background render.');

                ppstudioCliTestAssertSame(
                    $this->scriptPrefix,
                    hash('sha256', $storyGradientBytes),
                    hash('sha256', $fallbackBytes),
                    'Invalid background musi fallbacknout na gradient variantu story.'
                );
            }

            echo $this->scriptPrefix . ' [OK] Availability story render regression tests passed.' . PHP_EOL;
            return 0;
        } catch (Throwable $exception) {
            ppstudioCliTestFail($this->scriptPrefix, 'Exception: ' . $exception->getMessage());
        } finally {
            if (is_dir($tempDir)) {
                $files = glob($tempDir . '/*') ?: [];
                foreach ($files as $file) {
                    @unlink($file);
                }
                @rmdir($tempDir);
            }
        }
    }

    /**
     * @param array<int, array{
     *   name:string,
     *   style:string,
     *   title:string,
     *   month:string,
     *   slots:array<int, string>,
     *   services:array<int, string>,
     *   background:string
     * }> $backgroundPath
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
    private function storyRenderScenarios(string $backgroundPath): array
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

    private function storyFixturePath(string $name): string
    {
        return dirname(__DIR__, 2) . '/scripts/fixtures/availability-story/' . $name . '.png';
    }

    private function createDeterministicBackground(string $path): void
    {
        $width = 1080;
        $height = 1920;
        $image = imagecreatetruecolor($width, $height);
        if (! $image instanceof GdImage) {
            ppstudioCliTestFail($this->scriptPrefix, 'Nepodarilo se vytvorit canvas pro test background.');
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
            ppstudioCliTestFail($this->scriptPrefix, 'Nepodarilo se zapsat test background.');
        }

        imagedestroy($image);
    }

    /**
     * @param array<int, string> $args
     */
    private function parseWriteFixturesFlag(array $args): bool
    {
        return in_array('--write-fixtures', $args, true);
    }
}
