<?php
declare(strict_types=1);

use PPStudio\Service\Mailer;
use PPStudio\Service\ReservationNotificationService;
use PPStudio\Repository\ReservationRepository;

function sendReservationConfirmedEmail(
    array $emailConfig,
    array $siteSettings,
    array $reservation,
    array $context = []
): bool
{
    return (new ReservationNotificationService($emailConfig))->sendConfirmedEmail($siteSettings, $reservation, $context);
}

function sendReservationCancelledEmail(array $emailConfig, array $siteSettings, array $reservation): bool
{
    return (new ReservationNotificationService($emailConfig))->sendCancelledEmail($siteSettings, $reservation);
}

function sendReservationReminderEmail(array $emailConfig, array $siteSettings, array $reservation): bool
{
    return (new ReservationNotificationService($emailConfig))->sendReminderEmail($siteSettings, $reservation);
}

function sendVoucherEmail(
    array $emailConfig,
    array $siteSettings,
    array $voucher,
    string $recipientEmail
): bool
{
    $recipientEmail = trim($recipientEmail);
    if (! ($emailConfig['enabled'] ?? false) || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $siteName = setting($siteSettings, 'site_name', defaultSiteName());
    $recipientName = trim((string) ($voucher['recipient_name'] ?? ''));
    $voucherCode = trim((string) ($voucher['kod'] ?? ''));
    $voucherValue = formatPrice($voucher['puvodni_hodnota'] ?? null);
    $expiresAtRaw = trim((string) ($voucher['expires_at'] ?? ''));
    $expiresAt = $expiresAtRaw !== '' ? formatCzechDate($expiresAtRaw) : 'Bez omezení';
    $voucherUrl = buildVoucherViewUrl(
        $siteSettings,
        (int) ($voucher['id'] ?? 0),
        $voucherCode,
        isset($siteSettings['voucher_verify_secret']) ? (string) $siteSettings['voucher_verify_secret'] : null
    );

    $subject = $siteName . ': dárkový poukaz';

    $textBody = "Dobrý den";
    if ($recipientName !== '') {
        $textBody .= ' ' . $recipientName;
    }
    $textBody .= ",\n\n"
        . "posíláme vám dárkový poukaz do {$siteName}.\n\n"
        . "Kód poukazu: {$voucherCode}\n"
        . "Hodnota: {$voucherValue}\n"
        . "Platnost do: {$expiresAt}\n";

    if ($voucherUrl !== '') {
        $textBody .= "\nOtevření dárkového poukazu:\n{$voucherUrl}\n";
    }

    $textBody .= "\nPři návštěvě studia stačí nahlásit kód poukazu.\n\n{$siteName}";

    $htmlBody = '<p>Dobrý den';
    if ($recipientName !== '') {
        $htmlBody .= ' ' . escape($recipientName);
    }
    $htmlBody .= ',</p>'
        . '<p>posíláme vám dárkový poukaz do <strong>' . escape($siteName) . '</strong>.</p>'
        . '<div style="margin:18px 0;padding:18px 20px;border:1px solid #eadccf;border-radius:20px;background:#fffaf4;">'
        . '<p style="margin:0 0 10px;"><strong>Kód poukazu:</strong> ' . escape($voucherCode) . '</p>'
        . '<p style="margin:0 0 10px;"><strong>Hodnota:</strong> ' . escape($voucherValue) . '</p>'
        . '<p style="margin:0;"><strong>Platnost do:</strong> ' . escape($expiresAt) . '</p>';

    $htmlBody .= '</div>';

    if ($voucherUrl !== '') {
        $htmlBody .= '<p>Poukaz si můžete kdykoli otevřít, vytisknout nebo uložit jako PDF přes tlačítko níže:</p>'
            . '<p style="margin:18px 0;">'
            . '<a href="' . escape($voucherUrl) . '" style="display:inline-block;padding:11px 18px;border-radius:999px;background:#7a5a43;color:#ffffff;text-decoration:none;font-weight:700;box-shadow:0 10px 22px rgba(122,90,67,0.18);">Otevřít dárkový poukaz</a>'
            . '</p>';
    }

    $htmlBody .= '<p>Při návštěvě studia stačí nahlásit kód poukazu.</p>'
        . '<p>' . escape($siteName) . '</p>';

    return (new Mailer($emailConfig))->send(
        $recipientEmail,
        $subject,
        $htmlBody,
        $textBody
    );
}

function buildReservationsFeedIcal(mysqli $connection, array $siteSettings): string
{
    $siteName = setting($siteSettings, 'site_name', defaultSiteName());
    $escapeIcalText = static function (string $value): string {
        return str_replace(
            ["\\", ";", ",", "\r\n", "\n"],
            ["\\\\", "\;", "\,", "\\n", "\\n"],
            $value
        );
    };
    $nowUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Ymd\THis\Z');
    $ics = "BEGIN:VCALENDAR\r\n"
        . "VERSION:2.0\r\n"
        . "PRODID:-//PPStudio//ReservationsFeed//CS\r\n"
        . "CALSCALE:GREGORIAN\r\n"
        . "X-WR-CALNAME:" . $escapeIcalText($siteName . ' rezervace') . "\r\n";

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
                . "SUMMARY:" . $escapeIcalText($summary) . "\r\n"
                . "DESCRIPTION:" . $escapeIcalText($description) . "\r\n"
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

function loadReservationDetails(mysqli $connection, int $reservationId): ?array
{
    return (new ReservationRepository($connection))->findDetailsById($reservationId);
}
