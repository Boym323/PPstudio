<?php
declare(strict_types=1);

require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/site_lock.php';
require __DIR__ . '/includes/antispam.php';
require __DIR__ . '/includes/availability.php';
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/mailer.php';

$dbConfig = require __DIR__ . '/config/database.php';
$emailConfig = require __DIR__ . '/config/email.php';

startSecureSession();

if (ppstudioPublicLockEnabled() && ! ppstudioPublicLockHasAccess()) {
    http_response_code(423);
    echo 'Web je dočasně uzamčen heslem.';
    exit;
}

$redirectWithStatus = static function (string $status): never {
    header('Location: /rezervace.php?reservation=' . rawurlencode($status) . '#contact');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

if (! isValidCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
    $redirectWithStatus('csrf');
}

$honeypot = trim((string) ($_POST['website'] ?? ''));
$reservationToken = trim((string) ($_POST['reservation_token'] ?? ''));
$clientIp = getClientIpAddress();

$rateLimitResult = reservationAntispamRateLimitCheck($clientIp, 8, 600);
if (! ($rateLimitResult['allowed'] ?? true)) {
    reservationAntispamLog('rate_limited', ['retry_after' => (int) ($rateLimitResult['retry_after'] ?? 0)]);
    $redirectWithStatus('rate_limit');
}

if ($honeypot !== '') {
    reservationAntispamLog('honeypot_filled');
    $redirectWithStatus('spam');
}

$issuedAt = reservationAntispamConsumeToken($reservationToken);

if ($issuedAt === null) {
    reservationAntispamLog('missing_or_invalid_token');
    $redirectWithStatus('spam');
}

$elapsed = time() - $issuedAt;
if ($elapsed < 3) {
    reservationAntispamLog('submitted_too_fast', ['elapsed' => $elapsed]);
    $redirectWithStatus('too_fast');
}

if ($elapsed > 2 * 60 * 60) {
    reservationAntispamLog('token_expired', ['elapsed' => $elapsed]);
    $redirectWithStatus('spam');
}

$name = trim((string) ($_POST['jmeno'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['telefon'] ?? ''));
$note = trim((string) ($_POST['poznamka'] ?? ''));
$serviceId = (int) ($_POST['sluzba_id'] ?? 0);
$day = trim((string) ($_POST['rezervacni_datum'] ?? ''));
$time = trim((string) ($_POST['rezervacni_cas'] ?? ''));
$source = 'web';

if ($name === '' || $email === '' || $serviceId <= 0 || $day === '' || $time === '') {
    $redirectWithStatus('missing');
}

if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $redirectWithStatus('email');
}

if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
    $redirectWithStatus('invalid_datetime');
}

$dateTime = $day . ' ' . $time . ':00';

$connection = @new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['database']
);

if ($connection->connect_errno) {
    $redirectWithStatus('db');
}

$connection->set_charset($dbConfig['charset']);
$siteSettings = loadSiteSettings($connection);

if (! isValidReservationSlot($connection, $serviceId, $dateTime)) {
    $connection->close();
    $redirectWithStatus('slot');
}

$service = getServiceById($connection, $serviceId);
$statement = $connection->prepare(
    'INSERT INTO rezervace (jmeno, email, telefon, zdroj, poznamka_klienta, sluzba, cena_v_dobe_rezervace, datum_cas, stav)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, "nova")'
);

if (! $statement) {
    $connection->close();
    $redirectWithStatus('insert');
}

$servicePrice = isset($service['cena']) ? (float) $service['cena'] : null;
$statement->bind_param('sssssids', $name, $email, $phone, $source, $note, $serviceId, $servicePrice, $dateTime);

if (! $statement->execute()) {
    $statement->close();
    $connection->close();
    $redirectWithStatus('insert');
}

$reservation = [
    'id' => $connection->insert_id,
    'jmeno' => $name,
    'email' => $email,
    'telefon' => $phone,
    'zdroj' => $source,
    'poznamka_klienta' => $note,
    'datum_cas' => $dateTime,
    'service_name' => (string) ($service['nazev'] ?? 'Vybraná procedura'),
    'service_price' => $servicePrice,
    'service_duration' => (int) ($service['doba_trvani'] ?? 60),
];

sendReservationReceivedEmail($emailConfig, $siteSettings, $reservation);
sendReservationAdminNotification($emailConfig, $siteSettings, $reservation);

$statement->close();
$connection->close();

$redirectWithStatus('success');
