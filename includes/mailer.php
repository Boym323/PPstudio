<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/phpmailer/PHPMailer-6.10.0/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer-6.10.0/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer-6.10.0/src/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function sendReservationReceivedEmail(array $emailConfig, array $siteSettings, array $reservation): bool
{
    if (! ($emailConfig['enabled'] ?? false) || ($reservation['email'] ?? '') === '') {
        return false;
    }

    $siteName = setting($siteSettings, 'site_name', defaultSiteName());
    $subject = $siteName . ': přijetí rezervace';
    $customerName = (string) ($reservation['jmeno'] ?? '');
    $serviceName = (string) ($reservation['service_name'] ?? 'Vybraná procedura');
    $dateTime = formatCzechDateTime((string) ($reservation['datum_cas'] ?? ''));

    $textBody = "Dobrý den {$customerName},\n\n"
        . "děkujeme za rezervaci ve studiu {$siteName}.\n"
        . "Procedura: {$serviceName}\n"
        . "Termín: {$dateTime}\n\n"
        . "Jakmile rezervaci potvrdíme, pošleme vám další e-mail.\n\n"
        . "{$siteName}";

    $htmlBody = '<p>Dobrý den ' . escape($customerName) . ',</p>'
        . '<p>děkujeme za rezervaci ve studiu <strong>' . escape($siteName) . '</strong>.</p>'
        . '<p><strong>Procedura:</strong> ' . escape($serviceName) . '<br>'
        . '<strong>Termín:</strong> ' . escape($dateTime) . '</p>'
        . '<p>Jakmile rezervaci potvrdíme, pošleme vám další e-mail.</p>'
        . '<p>' . escape($siteName) . '</p>';

    return sendPhpMailerMessage((string) $reservation['email'], $subject, $htmlBody, $textBody, $emailConfig);
}

function sendReservationAdminNotification(array $emailConfig, array $siteSettings, array $reservation): bool
{
    if (! ($emailConfig['enabled'] ?? false)) {
        return false;
    }

    $siteName = setting($siteSettings, 'site_name', defaultSiteName());
    $recipients = getNotificationRecipients($emailConfig, $siteSettings);

    if ($recipients === []) {
        return false;
    }

    $subject = $siteName . ': nová rezervace';
    $confirmUrl = buildReservationActionUrl($emailConfig, $siteSettings, (int) ($reservation['id'] ?? 0), 'confirm');
    $cancelUrl = buildReservationActionUrl($emailConfig, $siteSettings, (int) ($reservation['id'] ?? 0), 'cancel');
    $textBody = "Nová rezervace ve studiu {$siteName}\n\n"
        . "Klientka: " . (string) ($reservation['jmeno'] ?? '') . "\n"
        . "E-mail: " . (string) ($reservation['email'] ?? '') . "\n"
        . "Telefon: " . (string) ($reservation['telefon'] ?? '') . "\n"
        . "Procedura: " . (string) ($reservation['service_name'] ?? '') . "\n"
        . "Termín: " . formatCzechDateTime((string) ($reservation['datum_cas'] ?? '')) . "\n"
        . "Zdroj: " . (string) ($reservation['zdroj'] ?? 'web') . "\n"
        . "Poznámka: " . (string) ($reservation['poznamka_klienta'] ?? '') . "\n\n"
        . "Akci potvrzení nebo zrušení proveďte přes tlačítka v HTML verzi e-mailu.";

    $htmlBody = nl2br(escape($textBody));

    if ($confirmUrl !== '' || $cancelUrl !== '') {
        $actions = '<div style="margin-top:20px;padding-top:16px;border-top:1px solid #e4d6c5;">';
        if ($confirmUrl !== '') {
            $actions .= '<a href="'
                . escape($confirmUrl)
                . '" style="display:inline-block;padding:10px 16px;background:#6f4d34;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;">Potvrdit rezervaci</a>';
        }
        if ($cancelUrl !== '') {
            $actions .= ($confirmUrl !== '' ? '<span style="display:inline-block;width:34px;"></span>' : '')
                . '<a href="'
                . escape($cancelUrl)
                . '" style="display:inline-block;padding:10px 16px;background:#b86a59;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;">Zrušit rezervaci</a>';
        }
        $actions .= '<p style="margin:12px 0 0 0;font-size:12px;color:#6e5f52;">Tlačítko „Zrušit rezervaci“ je záměrně oddělené kvůli bezpečnosti kliknutí.</p></div>';
        $htmlBody .= $actions;
    }

    $sent = true;
    foreach ($recipients as $recipient) {
        $sent = sendPhpMailerMessage($recipient, $subject, $htmlBody, $textBody, $emailConfig) && $sent;
    }

    return $sent;
}

function sendReservationConfirmedEmail(array $emailConfig, array $siteSettings, array $reservation): bool
{
    if (! ($emailConfig['enabled'] ?? false) || ($reservation['email'] ?? '') === '') {
        return false;
    }

    $siteName = setting($siteSettings, 'site_name', defaultSiteName());
    $subject = $siteName . ': potvrzení rezervace';
    $customerName = (string) ($reservation['jmeno'] ?? '');
    $serviceName = (string) ($reservation['service_name'] ?? 'Vybraná procedura');
    $dateTime = formatCzechDateTime((string) ($reservation['datum_cas'] ?? ''));
    $location = setting($siteSettings, 'contact_address', 'Adresa studia');

    $textBody = "Dobrý den {$customerName},\n\n"
        . "vaše rezervace byla potvrzena.\n"
        . "Procedura: {$serviceName}\n"
        . "Termín: {$dateTime}\n"
        . "Místo: {$location}\n\n"
        . "V příloze najdete soubor pro vložení do kalendáře.\n"
        . "\n{$siteName}";

    $htmlBody = '<p>Dobrý den ' . escape($customerName) . ',</p>'
        . '<p>vaše rezervace byla potvrzena.</p>'
        . '<p><strong>Procedura:</strong> ' . escape($serviceName) . '<br>'
        . '<strong>Termín:</strong> ' . escape($dateTime) . '<br>'
        . '<strong>Místo:</strong> ' . escape($location) . '</p>'
        . '<p>V příloze najdete soubor pro vložení do kalendáře.</p>'
        . '<p>' . escape($siteName) . '</p>';

    $icalContent = buildReservationIcal($siteSettings, $reservation);

    return sendPhpMailerMessage(
        (string) $reservation['email'],
        $subject,
        $htmlBody,
        $textBody,
        $emailConfig,
        [
            'filename' => 'rezervace-' . ((string) ($reservation['id'] ?? 'termin')) . '.ics',
            'content' => $icalContent,
            'content_type' => 'text/calendar; method=REQUEST; charset=UTF-8',
        ]
    );
}

function sendReservationCancelledEmail(array $emailConfig, array $siteSettings, array $reservation): bool
{
    if (! ($emailConfig['enabled'] ?? false) || ($reservation['email'] ?? '') === '') {
        return false;
    }

    $siteName = setting($siteSettings, 'site_name', defaultSiteName());
    $subject = $siteName . ': změna rezervace';
    $customerName = (string) ($reservation['jmeno'] ?? '');
    $serviceName = (string) ($reservation['service_name'] ?? 'Vybraná procedura');
    $dateTime = formatCzechDateTime((string) ($reservation['datum_cas'] ?? ''));

    $textBody = "Dobrý den {$customerName},\n\n"
        . "rezervace pro proceduru {$serviceName} v termínu {$dateTime} byla zrušena nebo upravena.\n"
        . "Pokud budete chtít nový termín, stačí si vybrat další datum na webu.\n\n"
        . "{$siteName}";

    return sendPhpMailerMessage(
        (string) $reservation['email'],
        $subject,
        nl2br(escape($textBody)),
        $textBody,
        $emailConfig
    );
}

function buildReservationIcal(array $siteSettings, array $reservation): string
{
    $siteName = setting($siteSettings, 'site_name', defaultSiteName());
    $serviceName = (string) ($reservation['service_name'] ?? 'Rezervace');
    $start = new DateTimeImmutable((string) ($reservation['datum_cas'] ?? 'now'));
    $duration = max(15, (int) ($reservation['service_duration'] ?? 60));
    $end = $start->modify('+' . $duration . ' minutes');
    $location = setting($siteSettings, 'contact_address', '');
    $description = 'Rezervace ve studiu ' . $siteName . ' - ' . $serviceName;
    $uid = 'reservation-' . ((string) ($reservation['id'] ?? uniqid('', true))) . '@ppstudio.cz';
    $nowUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Ymd\THis\Z');

    return "BEGIN:VCALENDAR\r\n"
        . "VERSION:2.0\r\n"
        . "PRODID:-//PPStudio//Reservation//CS\r\n"
        . "CALSCALE:GREGORIAN\r\n"
        . "METHOD:REQUEST\r\n"
        . "BEGIN:VEVENT\r\n"
        . "UID:" . $uid . "\r\n"
        . "DTSTAMP:" . $nowUtc . "\r\n"
        . "DTSTART:" . $start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z') . "\r\n"
        . "DTEND:" . $end->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z') . "\r\n"
        . "SUMMARY:" . escapeIcalText($siteName . ' - ' . $serviceName) . "\r\n"
        . "DESCRIPTION:" . escapeIcalText($description) . "\r\n"
        . "LOCATION:" . escapeIcalText($location) . "\r\n"
        . "STATUS:CONFIRMED\r\n"
        . "END:VEVENT\r\n"
        . "END:VCALENDAR\r\n";
}

function buildReservationsFeedIcal(mysqli $connection, array $siteSettings): string
{
    $siteName = setting($siteSettings, 'site_name', defaultSiteName());
    $nowUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Ymd\THis\Z');
    $ics = "BEGIN:VCALENDAR\r\n"
        . "VERSION:2.0\r\n"
        . "PRODID:-//PPStudio//ReservationsFeed//CS\r\n"
        . "CALSCALE:GREGORIAN\r\n"
        . "X-WR-CALNAME:" . escapeIcalText($siteName . ' rezervace') . "\r\n";

    $query = $connection->query(
        'SELECT r.id, r.jmeno, r.email, r.telefon, r.datum_cas, s.nazev, s.doba_trvani
         FROM rezervace r
         INNER JOIN sluzby s ON s.id = r.sluzba
         WHERE r.stav IN ("nova", "potvrzena")
           AND r.datum_cas >= NOW() - INTERVAL 1 DAY
         ORDER BY r.datum_cas ASC'
    );

    if ($query instanceof mysqli_result) {
        while ($row = $query->fetch_assoc()) {
            $start = new DateTimeImmutable((string) $row['datum_cas']);
            $duration = max(15, (int) ($row['doba_trvani'] ?? 60));
            $end = $start->modify('+' . $duration . ' minutes');
            $serviceName = trim((string) ($row['nazev'] ?? 'Rezervace'));
            $customerName = trim((string) ($row['jmeno'] ?? 'Klientka'));
            $customerEmail = trim((string) ($row['email'] ?? ''));
            $customerPhone = trim((string) ($row['telefon'] ?? ''));
            $summary = $serviceName . ' - ' . $customerName;
            $description = "Klientka: {$customerName}";

            if ($customerEmail !== '') {
                $description .= "\nE-mail: {$customerEmail}";
            }

            if ($customerPhone !== '') {
                $description .= "\nTelefon: {$customerPhone}";
            }

            $ics .= "BEGIN:VEVENT\r\n"
                . "UID:feed-" . (string) ($row['id'] ?? uniqid('', true)) . "@ppstudio.cz\r\n"
                . "DTSTAMP:" . $nowUtc . "\r\n"
                . "DTSTART:" . $start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z') . "\r\n"
                . "DTEND:" . $end->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z') . "\r\n"
                . "SUMMARY:" . escapeIcalText($summary) . "\r\n"
                . "DESCRIPTION:" . escapeIcalText($description) . "\r\n"
                . "STATUS:CONFIRMED\r\n"
                . "END:VEVENT\r\n";
        }
        $query->free();
    }

    return $ics . "END:VCALENDAR\r\n";
}

function buildSubscriptionCalendarUrl(array $emailConfig, array $siteSettings): string
{
    $siteUrl = rtrim(setting($siteSettings, 'site_url', ''), '/');
    $token = (string) ($emailConfig['calendar_token'] ?? '');

    if ($siteUrl === '' || $token === '') {
        return '';
    }

    return preg_replace('#^https://#', 'webcal://', $siteUrl) . '/reservations-feed.php?token=' . rawurlencode($token);
}

function buildReservationActionUrl(array $emailConfig, array $siteSettings, int $reservationId, string $action): string
{
    $siteUrl = rtrim(setting($siteSettings, 'site_url', ''), '/');
    $secret = (string) ($emailConfig['action_secret'] ?? '');
    $ttl = (int) ($emailConfig['action_ttl_seconds'] ?? 172800);

    if ($siteUrl === '' || $secret === '' || $reservationId <= 0) {
        return '';
    }

    $expiresAt = time() + max(300, $ttl);
    $nonce = bin2hex(random_bytes(16));
    $payload = $action . '|' . $reservationId . '|' . $expiresAt . '|' . $nonce;
    $signature = hash_hmac('sha256', $payload, $secret);

    return $siteUrl . '/reservation-action.php?id=' . $reservationId
        . '&action=' . rawurlencode($action)
        . '&exp=' . $expiresAt
        . '&nonce=' . rawurlencode($nonce)
        . '&sig=' . rawurlencode($signature);
}

function isValidReservationActionSignature(
    array $emailConfig,
    int $reservationId,
    string $action,
    int $expiresAt,
    string $nonce,
    string $signature
): bool
{
    $secret = (string) ($emailConfig['action_secret'] ?? '');

    if ($secret === '' || $reservationId <= 0 || $signature === '' || $expiresAt <= 0 || $nonce === '') {
        return false;
    }

    if ($expiresAt < time()) {
        return false;
    }

    if (! preg_match('/^[a-f0-9]{32}$/i', $nonce)) {
        return false;
    }

    $expected = hash_hmac('sha256', $action . '|' . $reservationId . '|' . $expiresAt . '|' . $nonce, $secret);

    return hash_equals($expected, $signature);
}

function reservationActionNonceStoragePath(): string
{
    if (function_exists('ppstudioSecurityStorageDir')) {
        return ppstudioSecurityStorageDir() . '/reservation-action-nonces.json';
    }

    $fallbackDir = dirname(__DIR__) . '/var/security';
    if (! is_dir($fallbackDir)) {
        @mkdir($fallbackDir, 0770, true);
    }

    return $fallbackDir . '/reservation-action-nonces.json';
}

function consumeReservationActionNonce(int $reservationId, string $action, int $expiresAt, string $nonce): bool
{
    if ($reservationId <= 0 || $action === '' || $expiresAt <= 0 || $nonce === '') {
        return false;
    }

    $path = reservationActionNonceStoragePath();
    $handle = @fopen($path, 'c+');
    if (! $handle) {
        return false;
    }

    if (! @flock($handle, LOCK_EX)) {
        fclose($handle);
        return false;
    }

    $content = stream_get_contents($handle);
    $map = [];
    if (is_string($content) && trim($content) !== '') {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $map = $decoded;
        }
    }

    $now = time();
    foreach ($map as $key => $usedAt) {
        if (! is_int($usedAt) || $usedAt < ($now - 14 * 24 * 60 * 60)) {
            unset($map[$key]);
        }
    }

    $tokenKey = hash('sha256', $reservationId . '|' . $action . '|' . $expiresAt . '|' . $nonce);
    if (isset($map[$tokenKey])) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }

    $map[$tokenKey] = $now;

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return true;
}

function getNotificationRecipients(array $emailConfig, array $siteSettings): array
{
    $raw = setting($siteSettings, 'notification_emails', '');
    $parts = preg_split('/[,;\s]+/', $raw) ?: [];
    $emails = [];

    foreach ($parts as $part) {
        $email = trim($part);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $email;
        }
    }

    return array_values(array_unique($emails));
}

function escapeIcalText(string $value): string
{
    return str_replace(
        ["\\", ";", ",", "\r\n", "\n"],
        ["\\\\", "\;", "\,", "\\n", "\\n"],
        $value
    );
}

function sendPhpMailerMessage(
    string $to,
    string $subject,
    string $htmlBody,
    string $textBody,
    array $emailConfig,
    ?array $attachment = null
): bool {
    try {
        $mail = buildConfiguredMailer($emailConfig);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;

        if ($attachment !== null) {
            $mail->addStringAttachment(
                (string) $attachment['content'],
                (string) $attachment['filename'],
                PHPMailer::ENCODING_BASE64,
                (string) $attachment['content_type']
            );
        }

        return $mail->send();
    } catch (Exception $exception) {
        return false;
    }
}

function buildConfiguredMailer(array $emailConfig): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    $fromEmail = (string) ($emailConfig['from_email'] ?? 'noreply@example.com');
    $fromName = (string) ($emailConfig['from_name'] ?? defaultSiteName());
    $replyTo = (string) ($emailConfig['reply_to'] ?? $fromEmail);
    $mailerType = (string) ($emailConfig['mailer'] ?? 'mail');

    if ($mailerType === 'smtp') {
        $mail->isSMTP();
        $mail->Host = (string) ($emailConfig['host'] ?? '');
        $mail->Port = (int) ($emailConfig['port'] ?? 587);
        $mail->SMTPAuth = (bool) ($emailConfig['auth'] ?? true);
        $mail->Username = (string) ($emailConfig['username'] ?? '');
        $mail->Password = (string) ($emailConfig['password'] ?? '');
        $encryption = (string) ($emailConfig['encryption'] ?? 'tls');

        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
    }

    $mail->setFrom($fromEmail, $fromName);
    $mail->addReplyTo($replyTo);

    return $mail;
}

function loadReservationDetails(mysqli $connection, int $reservationId): ?array
{
    $statement = $connection->prepare(
        'SELECT r.id, r.jmeno, r.email, r.telefon, r.poznamka_klienta, r.poznamka_admina, r.datum_cas, r.stav,
                r.cena_v_dobe_rezervace, s.nazev, s.doba_trvani
         FROM rezervace r
         INNER JOIN sluzby s ON s.id = r.sluzba
         WHERE r.id = ?
         LIMIT 1'
    );

    if (! $statement) {
        return null;
    }

    $statement->bind_param('i', $reservationId);
    $statement->execute();
    $statement->bind_result($id, $name, $email, $phone, $clientNote, $adminNote, $dateTime, $status, $servicePrice, $serviceName, $serviceDuration);
    $reservation = null;

    if ($statement->fetch()) {
        $reservation = [
            'id' => $id,
            'jmeno' => $name,
            'email' => $email,
            'telefon' => $phone,
            'poznamka_klienta' => $clientNote,
            'poznamka_admina' => $adminNote,
            'datum_cas' => $dateTime,
            'stav' => $status,
            'service_price' => $servicePrice !== null ? (float) $servicePrice : null,
            'service_name' => $serviceName,
            'service_duration' => $serviceDuration,
        ];
    }

    $statement->close();

    return $reservation;
}
