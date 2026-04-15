<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/site_lock.php';
require __DIR__ . '/includes/antispam.php';
require __DIR__ . '/includes/availability.php';
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/mailer.php';

$emailConfig = require __DIR__ . '/config/email.php';

startSecureSession();

$redirectWithStatus = static function (string $status): never {
    header('Location: /rezervace.php?reservation=' . rawurlencode($status) . '#contact');
    exit;
};

$isAjaxRequest = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch'
    || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');

$statusMessages = [
    'success' => 'Rezervace byla odeslaná. Potvrzení vám během chvíle dorazí e-mailem.',
    'csrf' => 'Platnost formuláře vypršela. Obnovte stránku a zkuste to znovu.',
    'missing' => 'Vyplňte prosím všechna povinná pole.',
    'email' => 'Zadejte platný e-mail.',
    'invalid_datetime' => 'Vyberte platný termín rezervace.',
    'slot' => 'Vybraný termín už není volný. Zkuste prosím jiný.',
    'too_fast' => 'Formulář byl odeslán příliš rychle. Zkuste to prosím ještě jednou.',
    'spam' => 'Rezervaci se nepodařilo ověřit. Obnovte stránku a zkuste to znovu.',
    'rate_limit' => 'Odeslání je dočasně omezené. Zkuste to prosím za chvíli.',
    'db' => 'Nepodařilo se navázat spojení. Zkuste to prosím za chvíli znovu.',
    'insert' => 'Rezervaci se nepodařilo uložit. Zkuste to prosím znovu.',
    'locked' => 'Web je dočasně uzamčen heslem.',
];

$respond = static function (string $status, bool $success = false, int $httpCode = 200, array $extra = []) use ($redirectWithStatus, $isAjaxRequest, $statusMessages): never {
    if (! $isAjaxRequest) {
        $redirectWithStatus($status);
    }

    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');

    $payload = array_merge([
        'success' => $success,
        'status' => $status,
        'message' => $statusMessages[$status] ?? 'Nepodařilo se zpracovat požadavek.',
        'new_token' => reservationAntispamIssueToken(),
    ], $extra);

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

if (ppstudioPublicLockEnabled() && ! ppstudioPublicLockHasAccess()) {
    if ($isAjaxRequest) {
        $respond('locked', false, 423);
    }

    http_response_code(423);
    echo 'Web je dočasně uzamčen heslem.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjaxRequest) {
        $respond('missing', false, 405);
    }
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

if (! isValidCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
    $respond('csrf', false, 419);
}

$honeypot = trim((string) ($_POST['website'] ?? ''));
$reservationToken = trim((string) ($_POST['reservation_token'] ?? ''));
$clientIp = getClientIpAddress();

$rateLimitResult = reservationAntispamRateLimitCheck($clientIp, 8, 600);
if (! ($rateLimitResult['allowed'] ?? true)) {
    reservationAntispamLog('rate_limited', ['retry_after' => (int) ($rateLimitResult['retry_after'] ?? 0)]);
    $respond('rate_limit', false, 429);
}

if ($honeypot !== '') {
    reservationAntispamLog('honeypot_filled');
    $respond('spam', false, 422);
}

$issuedAt = reservationAntispamConsumeToken($reservationToken);

if ($issuedAt === null) {
    reservationAntispamLog('missing_or_invalid_token');
    $respond('spam', false, 422);
}

$elapsed = time() - $issuedAt;
if ($elapsed < 3) {
    reservationAntispamLog('submitted_too_fast', ['elapsed' => $elapsed]);
    $respond('too_fast', false, 422);
}

if ($elapsed > 2 * 60 * 60) {
    reservationAntispamLog('token_expired', ['elapsed' => $elapsed]);
    $respond('spam', false, 422);
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
    $respond('missing', false, 422);
}

if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $respond('email', false, 422);
}

if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
    $respond('invalid_datetime', false, 422);
}

$dateTime = $day . ' ' . $time . ':00';

$connection = \PPStudio\Database\DatabaseFactory::tryConnect();

if (! $connection instanceof mysqli) {
    $respond('db', false, 500);
}
$siteSettings = loadSiteSettings($connection);
$reservationInsert = createReservationWithLock($connection, $name, $email, $phone, $source, $note, $serviceId, $dateTime, 'nova');

if (in_array($reservationInsert['status'] ?? 'error', ['slot_unavailable', 'service_unavailable'], true)) {
    $connection->close();
    $respond('slot', false, 409);
}

if (($reservationInsert['status'] ?? 'error') !== 'ok') {
    $connection->close();
    $respond('insert', false, 500);
}

$service = is_array($reservationInsert['service'] ?? null) ? $reservationInsert['service'] : [];
$servicePrice = $reservationInsert['service_price'] ?? null;

$reservation = [
    'id' => (int) ($reservationInsert['reservation_id'] ?? 0),
    'jmeno' => $name,
    'email' => $email,
    'telefon' => $phone,
    'zdroj' => $source,
    'poznamka_klienta' => $note,
    'datum_cas' => (string) ($reservationInsert['date_time'] ?? $dateTime),
    'service_name' => (string) ($service['nazev'] ?? 'Vybraná procedura'),
    'service_price' => $servicePrice,
    'service_duration' => (int) ($service['doba_trvani'] ?? 60),
];

sendReservationReceivedEmail($emailConfig, $siteSettings, $reservation);
sendReservationAdminNotification($emailConfig, $siteSettings, $reservation);
$connection->close();

$respond('success', true, 200, [
    'reservation' => [
        'service' => (string) $reservation['service_name'],
        'slot' => date('d.m.Y', strtotime($dateTime)) . ' v ' . substr($time, 0, 5),
        'contact' => implode(' • ', array_filter([$name, $email, $phone])),
    ],
]);
