<?php
declare(strict_types=1);

require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/availability.php';
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/admin/availability_story.php';

startSecureSession();

$isAdminAuthenticated = (bool) ($_SESSION['ppstudio_admin_authenticated'] ?? false);
$isAdminLiteAuthenticated = (bool) ($_SESSION['ppstudio_admin_lite_authenticated'] ?? false);

if (! $isAdminAuthenticated && ! $isAdminLiteAuthenticated) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Přístup odepřen.';
    exit;
}

$isPreview = $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['preview']);

if (! $isPreview && ($_SERVER['REQUEST_METHOD'] !== 'POST' || ! isValidCsrfToken((string) ($_POST['_csrf'] ?? '')))) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Platnost formuláře vypršela.';
    exit;
}

$dbConfig = require __DIR__ . '/config/database.php';
$connection = @new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['database']
);

if ($connection->connect_errno) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Nepodařilo se připojit k databázi.';
    exit;
}

$source = $isPreview ? $_GET : $_POST;

$fromRaw = trim((string) ($source['story_from'] ?? ''));
$toRaw = trim((string) ($source['story_to'] ?? ''));
$title = trim((string) ($source['story_title'] ?? ''));
$monthOverride = trim((string) ($source['story_month_label'] ?? ''));
$style = trim((string) ($source['story_style'] ?? 'story'));
if (! in_array($style, ['story', 'minimal', 'feed'], true)) {
    $style = 'story';
}
$maxDays = max(1, min(8, (int) ($source['story_max_days'] ?? 5)));
$maxTimesPerDay = max(1, min(8, (int) ($source['story_max_times_per_day'] ?? 5)));
$servicesRaw = trim((string) ($source['story_services'] ?? ''));

$from = DateTimeImmutable::createFromFormat('Y-m-d', $fromRaw) ?: new DateTimeImmutable('today');
$to = DateTimeImmutable::createFromFormat('Y-m-d', $toRaw) ?: $from->modify('last day of this month');

if ($to < $from) {
    $tmp = $from;
    $from = $to;
    $to = $tmp;
}

$maxTo = $from->modify('+62 days');
if ($to > $maxTo) {
    $to = $maxTo;
}

if (! function_exists('imagecreatetruecolor') || ! function_exists('imagepng')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Na serveru chybí podpora GD pro generování obrázku.';
    exit;
}

$freeSlotsByDay = ppstudioCollectFreeSlotsForStory($connection, $from, $to);
$slotLines = [];
foreach ($freeSlotsByDay as $dateKey => $times) {
    if ($times === []) {
        continue;
    }

    $visibleTimes = array_slice($times, 0, $maxTimesPerDay);
    if (count($times) > $maxTimesPerDay) {
        $hourOnlyTimes = array_values(array_filter(
            $times,
            static fn (string $time): bool => substr($time, 3, 2) === '00'
        ));

        if ($hourOnlyTimes !== []) {
            $visibleTimes = array_slice($hourOnlyTimes, 0, $maxTimesPerDay);
        }
    }

    $line = (new DateTimeImmutable($dateKey))->format('j.n.') . ' ' . implode(', ', $visibleTimes);
    $slotLines[] = $line;

    if (count($slotLines) >= $maxDays) {
        break;
    }
}

if ($slotLines === []) {
    $slotLines[] = 'Momentálně nejsou vypsané volné termíny';
}

$serviceLines = array_values(array_filter(array_map(
    static fn (string $line): string => trim($line),
    preg_split('/\r\n|\r|\n/', $servicesRaw) ?: []
)));
$serviceLines = array_slice($serviceLines, 0, 6);

$monthLabel = $monthOverride !== '' ? $monthOverride : ppstudioAvailabilityStoryMonthLabel($from, $to);
$title = $title !== '' ? $title : 'Zbývají volné termíny';
$backgroundSetting = trim((string) ($source['story_background_path'] ?? ''));
if ($backgroundSetting === '') {
    $settings = loadSiteSettings($connection);
    $backgroundSetting = trim((string) ($settings['availability_story_background'] ?? ''));
}
$backgroundPath = '';
if ($backgroundSetting !== '' && str_starts_with($backgroundSetting, 'uploads/')) {
    $candidate = __DIR__ . '/' . ltrim($backgroundSetting, '/');
    if (is_file($candidate) && is_readable($candidate)) {
        $backgroundPath = $candidate;
    }
}

$image = ppstudioRenderAvailabilityStoryImage($title, $monthLabel, $slotLines, $serviceLines, $style, $backgroundPath);
$fileName = 'ppstudio-volne-terminy-' . $from->format('Y-m-d') . '-' . $style . '.png';

header('Content-Type: image/png');
if ($isPreview) {
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
} else {
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}
imagepng($image);
exit;
