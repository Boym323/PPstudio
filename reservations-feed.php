<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/mailer.php';

$emailConfig = require __DIR__ . '/config/email.php';

$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '' || $token !== (string) ($emailConfig['calendar_token'] ?? '')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$connection = \PPStudio\Database\DatabaseFactory::tryConnect();

if (! $connection instanceof mysqli) {
    http_response_code(500);
    echo 'Database unavailable';
    exit;
}
$siteSettings = loadSiteSettings($connection);

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="reservations-feed.ics"');

echo buildReservationsFeedIcal($connection, $siteSettings);
$connection->close();
