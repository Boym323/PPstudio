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
            $actions .= '<div style="margin-bottom:18px;">'
                . '<a href="'
                . escape($confirmUrl)
                . '" style="display:inline-block;padding:11px 18px;background:#7a5a43;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:700;box-shadow:0 6px 16px rgba(122,90,67,0.18);">Potvrdit rezervaci</a>'
                . '</div>';
        }
        if ($cancelUrl !== '') {
            $actions .= '<div style="margin-top:20px;padding-top:14px;border-top:1px dashed #e5d9ca;">'
                . '<p style="margin:0 0 10px 0;font-size:12px;color:#7b6959;">Pozor: následující akce rezervaci okamžitě zruší.</p>'
                . '<a href="'
                . escape($cancelUrl)
                . '" style="display:inline-block;padding:10px 16px;background:#fbf5ee;color:#8c4f42;text-decoration:none;border-radius:999px;font-weight:700;border:1px solid #d9c0b5;">Zrušit rezervaci</a>'
                . '</div>';
        }
        $actions .= '</div>';
        $htmlBody .= $actions;
    }

    $sent = true;
    foreach ($recipients as $recipient) {
        $sent = sendPhpMailerMessage($recipient, $subject, $htmlBody, $textBody, $emailConfig) && $sent;
    }

    return $sent;
}

function sendReservationConfirmedEmail(
    array $emailConfig,
    array $siteSettings,
    array $reservation,
    array $context = []
): bool
{
    if (! ($emailConfig['enabled'] ?? false) || ($reservation['email'] ?? '') === '') {
        return false;
    }

    $siteName = setting($siteSettings, 'site_name', defaultSiteName());
    $previousDateTimeRaw = trim((string) ($context['previous_datetime'] ?? ''));
    $isRescheduled = $previousDateTimeRaw !== '';
    $previousDateTime = $previousDateTimeRaw !== '' ? formatCzechDateTime($previousDateTimeRaw) : '';

    $subject = $isRescheduled
        ? $siteName . ': potvrzení změny termínu rezervace'
        : $siteName . ': potvrzení rezervace';
    $customerName = (string) ($reservation['jmeno'] ?? '');
    $serviceName = (string) ($reservation['service_name'] ?? 'Vybraná procedura');
    $dateTime = formatCzechDateTime((string) ($reservation['datum_cas'] ?? ''));
    $location = setting($siteSettings, 'contact_address', 'Adresa studia');
    $rescheduleUrl = buildReservationCustomerRescheduleUrl($emailConfig, $siteSettings, $reservation);
    $cancelUrl = buildReservationCustomerCancelUrl($emailConfig, $siteSettings, $reservation);
    $canManageUntil = reservationCustomerActionDeadline($emailConfig, $reservation);
    $canManageLabel = $canManageUntil > 0 ? formatCzechDateTime(date('Y-m-d H:i:s', $canManageUntil)) : '';

    $textBody = "Dobrý den {$customerName},\n\n"
        . ($isRescheduled ? "váš termín rezervace byl změněn.\n" : "vaše rezervace byla potvrzena.\n")
        . "Procedura: {$serviceName}\n"
        . ($isRescheduled
            ? "Původní termín: {$previousDateTime}\nNový termín: {$dateTime}\n"
            : "Termín: {$dateTime}\n")
        . "Místo: {$location}\n\n"
        . "V příloze najdete soubor pro vložení do kalendáře.\n";

    if ($cancelUrl !== '') {
        $textBody .= "\nPokud potřebujete termín zrušit, použijte tento odkaz:\n{$cancelUrl}\n";
    }
    if ($rescheduleUrl !== '') {
        $textBody .= "\nPokud potřebujete termín přesunout, použijte tento odkaz:\n{$rescheduleUrl}\n";
    }
    if (($cancelUrl !== '' || $rescheduleUrl !== '') && $canManageLabel !== '') {
        $textBody .= "\nZměnu nebo zrušení termínu je možné provést nejpozději do {$canManageLabel}.\n";
    }

    $textBody .= "\n"
        . "\n{$siteName}";

    $htmlBody = '<p>Dobrý den ' . escape($customerName) . ',</p>'
        . '<p>' . escape($isRescheduled ? 'váš termín rezervace byl změněn.' : 'vaše rezervace byla potvrzena.') . '</p>'
        . '<p><strong>Procedura:</strong> ' . escape($serviceName) . '<br>'
        . ($isRescheduled
            ? '<strong>Původní termín:</strong> ' . escape($previousDateTime) . '<br><strong>Nový termín:</strong> ' . escape($dateTime) . '<br>'
            : '<strong>Termín:</strong> ' . escape($dateTime) . '<br>')
        . '<strong>Místo:</strong> ' . escape($location) . '</p>'
        . '<p>V příloze najdete soubor pro vložení do kalendáře.</p>';

    if ($cancelUrl !== '' || $rescheduleUrl !== '') {
        $htmlBody .= '<div style="margin:18px 0;padding-top:14px;border-top:1px solid #e4d6c5;">'
            . '<p style="margin:0 0 10px 0;">Potřebujete změnu? Použijte bezpečná tlačítka níže:</p>';
        if ($rescheduleUrl !== '') {
            $htmlBody .= '<a href="'
                . escape($rescheduleUrl)
                . '" style="display:inline-block;padding:10px 16px;background:#7a5a43;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:700;box-shadow:0 6px 16px rgba(122,90,67,0.18);">Přesunout termín</a>';
        }
        if ($cancelUrl !== '') {
            $htmlBody .= ($rescheduleUrl !== '' ? '<span style="display:inline-block;width:24px;"></span>' : '')
                . '<a href="'
                . escape($cancelUrl)
                . '" style="display:inline-block;padding:10px 16px;background:#fbf5ee;color:#8c4f42;text-decoration:none;border-radius:999px;font-weight:700;border:1px solid #d9c0b5;">Zrušit rezervaci</a>';
        }
        $htmlBody .= '<p style="margin:10px 0 0 0;font-size:12px;color:#6e5f52;">Po otevření odkazu se zobrazí potvrzovací krok.'
            . ($canManageLabel !== '' ? ' Změnu nebo zrušení lze provést nejpozději do ' . escape($canManageLabel) . '.' : '')
            . '</p>'
            . '</div>';
    }

    $htmlBody .= '<p>' . escape($siteName) . '</p>';

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

function reservationCustomerActionCutoffSeconds(array $emailConfig): int
{
    $cutoff = (int) ($emailConfig['customer_action_cutoff_seconds'] ?? 86400);

    return max(0, $cutoff);
}

function reservationCustomerActionDeadline(array $emailConfig, array $reservation): int
{
    $reservationTs = strtotime((string) ($reservation['datum_cas'] ?? ''));
    if (! $reservationTs) {
        return 0;
    }

    return $reservationTs - reservationCustomerActionCutoffSeconds($emailConfig);
}

function canUseReservationCustomerAction(array $emailConfig, array $reservation): bool
{
    $deadline = reservationCustomerActionDeadline($emailConfig, $reservation);
    if ($deadline <= 0) {
        return false;
    }

    return time() < $deadline;
}

function buildReservationCustomerActionUrl(array $emailConfig, array $siteSettings, array $reservation, string $action, string $path): string
{
    $siteUrl = rtrim(setting($siteSettings, 'site_url', ''), '/');
    $secret = (string) ($emailConfig['action_secret'] ?? '');
    $reservationId = (int) ($reservation['id'] ?? 0);

    if ($siteUrl === '' || $secret === '' || $reservationId <= 0) {
        return '';
    }

    $expiresAt = reservationCustomerActionDeadline($emailConfig, $reservation);
    if ($expiresAt <= time()) {
        return '';
    }

    $nonce = bin2hex(random_bytes(16));
    $payload = $action . '|' . $reservationId . '|' . $expiresAt . '|' . $nonce;
    $signature = hash_hmac('sha256', $payload, $secret);

    return $siteUrl . '/' . ltrim($path, '/')
        . '?id=' . $reservationId
        . '&action=' . rawurlencode($action)
        . '&exp=' . $expiresAt
        . '&nonce=' . rawurlencode($nonce)
        . '&sig=' . rawurlencode($signature);
}

function buildReservationCustomerCancelUrl(array $emailConfig, array $siteSettings, array $reservation): string
{
    return buildReservationCustomerActionUrl($emailConfig, $siteSettings, $reservation, 'cancel', 'reservation-cancel.php');
}

function buildReservationCustomerRescheduleUrl(array $emailConfig, array $siteSettings, array $reservation): string
{
    return buildReservationCustomerActionUrl($emailConfig, $siteSettings, $reservation, 'reschedule', 'reservation-reschedule.php');
}

function sendReservationCancelledEmail(array $emailConfig, array $siteSettings, array $reservation): bool
{
    if (! ($emailConfig['enabled'] ?? false) || ($reservation['email'] ?? '') === '') {
        return false;
    }

    $siteName = setting($siteSettings, 'site_name', defaultSiteName());
    $subject = $siteName . ': rezervace byla zrušena';
    $customerName = (string) ($reservation['jmeno'] ?? '');
    $serviceName = (string) ($reservation['service_name'] ?? 'Vybraná procedura');
    $dateTime = formatCzechDateTime((string) ($reservation['datum_cas'] ?? ''));
    $cancelledBy = trim((string) ($reservation['zruseno_kym'] ?? ''));
    $cancelledByLabel = match ($cancelledBy) {
        'customer_link' => 'zákazníkem přes e-mailový odkaz',
        'admin_full' => 'studiem (admin)',
        'admin_lite' => 'studiem',
        default => '',
    };

    $textBody = "Dobrý den {$customerName},\n\n"
        . "vaše rezervace pro proceduru {$serviceName} v termínu {$dateTime} byla zrušena.\n";

    if ($cancelledByLabel !== '') {
        $textBody .= "Zrušení provedeno: {$cancelledByLabel}.\n";
    }

    $textBody .= "Pokud budete chtít nový termín, stačí si vybrat další datum na webu.\n\n"
        . "{$siteName}";

    return sendPhpMailerMessage(
        (string) $reservation['email'],
        $subject,
        nl2br(escape($textBody)),
        $textBody,
        $emailConfig
    );
}

function sendReservationReminderEmail(array $emailConfig, array $siteSettings, array $reservation): bool
{
    if (! ($emailConfig['enabled'] ?? false) || ($reservation['email'] ?? '') === '') {
        return false;
    }

    $siteName = setting($siteSettings, 'site_name', defaultSiteName());
    $subject = $siteName . ': připomínka rezervace';
    $customerName = trim((string) ($reservation['jmeno'] ?? ''));
    $serviceName = trim((string) ($reservation['service_name'] ?? 'Vybraná procedura'));
    $dateTime = formatCzechDateTime((string) ($reservation['datum_cas'] ?? ''));
    $address = trim((string) setting($siteSettings, 'contact_address', ''));
    $rescheduleUrl = buildReservationCustomerRescheduleUrl($emailConfig, $siteSettings, $reservation);
    $cancelUrl = buildReservationCustomerCancelUrl($emailConfig, $siteSettings, $reservation);

    $textBody = "Dobrý den";
    if ($customerName !== '') {
        $textBody .= ' ' . $customerName;
    }
    $textBody .= ",\n\n"
        . "připomínáme vaši rezervaci v {$siteName}.\n\n"
        . "Procedura: {$serviceName}\n"
        . "Termín: {$dateTime}\n";

    if ($address !== '') {
        $textBody .= "Místo: {$address}\n";
    }

    if ($rescheduleUrl !== '' || $cancelUrl !== '') {
        $textBody .= "\nPokud potřebujete termín upravit, můžete využít odkaz v tomto e-mailu.\n";
    }

    $textBody .= "\nTěšíme se na vaši návštěvu.\n\n{$siteName}";

    $htmlBody = '<p>Dobrý den';
    if ($customerName !== '') {
        $htmlBody .= ' ' . escape($customerName);
    }
    $htmlBody .= ',</p>'
        . '<p>připomínáme vaši rezervaci v <strong>' . escape($siteName) . '</strong>.</p>'
        . '<div style="margin:18px 0;padding:16px 18px;border:1px solid #eadccf;border-radius:18px;background:#fffaf4;">'
        . '<p style="margin:0 0 10px;"><strong>Procedura:</strong> ' . escape($serviceName) . '</p>'
        . '<p style="margin:0 0 10px;"><strong>Termín:</strong> ' . escape($dateTime) . '</p>';

    if ($address !== '') {
        $htmlBody .= '<p style="margin:0;"><strong>Místo:</strong> ' . nl2br(escape($address)) . '</p>';
    }

    $htmlBody .= '</div>';

    if ($rescheduleUrl !== '' || $cancelUrl !== '') {
        $htmlBody .= '<p>Pokud potřebujete termín upravit, můžete využít některou z těchto možností:</p>'
            . '<div style="margin:18px 0;">';

        if ($rescheduleUrl !== '') {
            $htmlBody .= '<a href="' . escape($rescheduleUrl) . '" style="display:inline-block;margin:0 12px 12px 0;padding:11px 18px;border-radius:999px;background:#7a5a43;color:#ffffff;text-decoration:none;font-weight:700;box-shadow:0 10px 22px rgba(122,90,67,0.18);">Přesunout termín</a>';
        }

        if ($cancelUrl !== '') {
            $htmlBody .= '<a href="' . escape($cancelUrl) . '" style="display:inline-block;margin:0 12px 12px 0;padding:11px 18px;border-radius:999px;background:#fff7f0;border:1px solid #d8b2a9;color:#8b5f56;text-decoration:none;font-weight:700;">Zrušit termín</a>';
        }

        $htmlBody .= '</div>';
    }

    $htmlBody .= '<p>Těšíme se na vaši návštěvu.</p>'
        . '<p>' . escape($siteName) . '</p>';

    return sendPhpMailerMessage(
        (string) $reservation['email'],
        $subject,
        $htmlBody,
        $textBody,
        $emailConfig
    );
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

    return sendPhpMailerMessage(
        $recipientEmail,
        $subject,
        $htmlBody,
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
        'SELECT r.id, r.sluzba, r.jmeno, r.email, r.telefon, r.poznamka_klienta, r.poznamka_admina, r.datum_cas, r.stav,
                r.cena_v_dobe_rezervace, r.reminder_sent_at, s.nazev, s.doba_trvani
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
    $statement->bind_result($id, $serviceId, $name, $email, $phone, $clientNote, $adminNote, $dateTime, $status, $servicePrice, $reminderSentAt, $serviceName, $serviceDuration);
    $reservation = null;

    if ($statement->fetch()) {
        $reservation = [
            'id' => $id,
            'service_id' => (int) $serviceId,
            'jmeno' => $name,
            'email' => $email,
            'telefon' => $phone,
            'poznamka_klienta' => $clientNote,
            'poznamka_admina' => $adminNote,
            'datum_cas' => $dateTime,
            'stav' => $status,
            'service_price' => $servicePrice !== null ? (float) $servicePrice : null,
            'reminder_sent_at' => $reminderSentAt,
            'service_name' => $serviceName,
            'service_duration' => $serviceDuration,
        ];
    }

    $statement->close();

    return $reservation;
}
