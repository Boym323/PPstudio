<?php
declare(strict_types=1);

require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/mailer.php';

$dbConfig = require __DIR__ . '/config/database.php';
$emailConfig = require __DIR__ . '/config/email.php';

$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '' || $token !== (string) ($emailConfig['calendar_token'] ?? '')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$connection = @new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['database']
);

if ($connection->connect_errno) {
    http_response_code(500);
    echo 'Database unavailable';
    exit;
}

$connection->set_charset($dbConfig['charset']);
$siteSettings = loadSiteSettings($connection);

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="reservations-feed.ics"');

echo buildReservationsFeedIcal($connection, $siteSettings);
$connection->close();
