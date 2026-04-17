<?php
declare(strict_types=1);

namespace PPStudio\Service;

use DateTimeImmutable;
use DateTimeZone;
use PPStudio\Security\ReservationLinkSigner;

final class ReservationNotificationService
{
    private Mailer $mailer;
    private ReservationLinkSigner $linkSigner;

    public function __construct(private array $emailConfig, ?Mailer $mailer = null, ?ReservationLinkSigner $linkSigner = null)
    {
        $this->mailer = $mailer ?? new Mailer($emailConfig);
        $this->linkSigner = $linkSigner ?? new ReservationLinkSigner($emailConfig);
    }

    public function notifyReservationSubmitted(array $siteSettings, array $reservation): void
    {
        $this->sendReceivedEmail($siteSettings, $reservation);
        $this->sendAdminNotification($siteSettings, $reservation);
    }

    public function sendReceivedEmail(array $siteSettings, array $reservation): bool
    {
        if (! ($this->emailConfig['enabled'] ?? false) || ($reservation['email'] ?? '') === '') {
            return false;
        }

        $siteName = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'site_name', \defaultSiteName());
        $subject = $siteName . ': přijetí rezervace';
        $customerName = (string) ($reservation['jmeno'] ?? '');
        $serviceName = (string) ($reservation['service_name'] ?? 'Vybraná procedura');
        $dateTime = \PPStudio\Support\FormatHelper::formatCzechDateTime((string) ($reservation['datum_cas'] ?? ''));

        $textBody = "Dobrý den {$customerName},\n\n"
            . "děkujeme za rezervaci ve studiu {$siteName}.\n"
            . "Procedura: {$serviceName}\n"
            . "Termín: {$dateTime}\n\n"
            . "Jakmile rezervaci potvrdíme, pošleme vám další e-mail.\n\n"
            . "{$siteName}";

        $htmlBody = '<p>Dobrý den ' . \PPStudio\Support\ViewHelper::escape($customerName) . ',</p>'
            . '<p>děkujeme za rezervaci ve studiu <strong>' . \PPStudio\Support\ViewHelper::escape($siteName) . '</strong>.</p>'
            . '<p><strong>Procedura:</strong> ' . \PPStudio\Support\ViewHelper::escape($serviceName) . '<br>'
            . '<strong>Termín:</strong> ' . \PPStudio\Support\ViewHelper::escape($dateTime) . '</p>'
            . '<p>Jakmile rezervaci potvrdíme, pošleme vám další e-mail.</p>'
            . '<p>' . \PPStudio\Support\ViewHelper::escape($siteName) . '</p>';

        return $this->mailer->send((string) $reservation['email'], $subject, $htmlBody, $textBody);
    }

    public function sendAdminNotification(array $siteSettings, array $reservation): bool
    {
        if (! ($this->emailConfig['enabled'] ?? false)) {
            return false;
        }

        $siteName = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'site_name', \defaultSiteName());
        $recipients = $this->notificationRecipients($siteSettings);

        if ($recipients === []) {
            return false;
        }

        $subject = $siteName . ': nová rezervace';
        $confirmUrl = $this->linkSigner->buildAdminActionUrl($siteSettings, (int) ($reservation['id'] ?? 0), 'confirm');
        $cancelUrl = $this->linkSigner->buildAdminActionUrl($siteSettings, (int) ($reservation['id'] ?? 0), 'cancel');
        $textBody = "Nová rezervace ve studiu {$siteName}\n\n"
            . "Klientka: " . (string) ($reservation['jmeno'] ?? '') . "\n"
            . "E-mail: " . (string) ($reservation['email'] ?? '') . "\n"
            . "Telefon: " . (string) ($reservation['telefon'] ?? '') . "\n"
            . "Procedura: " . (string) ($reservation['service_name'] ?? '') . "\n"
            . "Termín: " . \PPStudio\Support\FormatHelper::formatCzechDateTime((string) ($reservation['datum_cas'] ?? '')) . "\n"
            . "Zdroj: " . (string) ($reservation['zdroj'] ?? 'web') . "\n"
            . "Poznámka: " . (string) ($reservation['poznamka_klienta'] ?? '') . "\n\n"
            . "Akci potvrzení nebo zrušení proveďte přes tlačítka v HTML verzi e-mailu.";

        $htmlBody = nl2br(\PPStudio\Support\ViewHelper::escape($textBody));

        if ($confirmUrl !== '' || $cancelUrl !== '') {
            $actions = '<div style="margin-top:20px;padding-top:16px;border-top:1px solid #e4d6c5;">';
            if ($confirmUrl !== '') {
                $actions .= '<div style="margin-bottom:18px;">'
                    . '<a href="'
                    . \PPStudio\Support\ViewHelper::escape($confirmUrl)
                    . '" style="display:inline-block;padding:11px 18px;background:#7a5a43;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:700;box-shadow:0 6px 16px rgba(122,90,67,0.18);">Potvrdit rezervaci</a>'
                    . '</div>';
            }
            if ($cancelUrl !== '') {
                $actions .= '<div style="margin-top:20px;padding-top:14px;border-top:1px dashed #e5d9ca;">'
                    . '<p style="margin:0 0 10px 0;font-size:12px;color:#7b6959;">Pozor: následující akce rezervaci okamžitě zruší.</p>'
                    . '<a href="'
                    . \PPStudio\Support\ViewHelper::escape($cancelUrl)
                    . '" style="display:inline-block;padding:10px 16px;background:#fbf5ee;color:#8c4f42;text-decoration:none;border-radius:999px;font-weight:700;border:1px solid #d9c0b5;">Zrušit rezervaci</a>'
                    . '</div>';
            }
            $actions .= '</div>';
            $htmlBody .= $actions;
        }

        $sent = true;
        foreach ($recipients as $recipient) {
            $sent = $this->mailer->send($recipient, $subject, $htmlBody, $textBody) && $sent;
        }

        return $sent;
    }

    public function sendConfirmedEmail(array $siteSettings, array $reservation, array $context = []): bool
    {
        if (! ($this->emailConfig['enabled'] ?? false) || ($reservation['email'] ?? '') === '') {
            return false;
        }

        $siteName = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'site_name', \defaultSiteName());
        $previousDateTimeRaw = trim((string) ($context['previous_datetime'] ?? ''));
        $isRescheduled = $previousDateTimeRaw !== '';
        $previousDateTime = $previousDateTimeRaw !== '' ? \PPStudio\Support\FormatHelper::formatCzechDateTime($previousDateTimeRaw) : '';

        $subject = $isRescheduled
            ? $siteName . ': potvrzení změny termínu rezervace'
            : $siteName . ': potvrzení rezervace';
        $customerName = (string) ($reservation['jmeno'] ?? '');
        $serviceName = (string) ($reservation['service_name'] ?? 'Vybraná procedura');
        $dateTime = \PPStudio\Support\FormatHelper::formatCzechDateTime((string) ($reservation['datum_cas'] ?? ''));
        $location = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'contact_address', 'Adresa studia');
        $rescheduleUrl = $this->linkSigner->buildCustomerRescheduleUrl($siteSettings, $reservation);
        $cancelUrl = $this->linkSigner->buildCustomerCancelUrl($siteSettings, $reservation);
        $canManageUntil = $this->linkSigner->customerActionDeadline($reservation);
        $canManageLabel = $canManageUntil > 0 ? \PPStudio\Support\FormatHelper::formatCzechDateTime(date('Y-m-d H:i:s', $canManageUntil)) : '';

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

        $htmlBody = '<p>Dobrý den ' . \PPStudio\Support\ViewHelper::escape($customerName) . ',</p>'
            . '<p>' . \PPStudio\Support\ViewHelper::escape($isRescheduled ? 'váš termín rezervace byl změněn.' : 'vaše rezervace byla potvrzena.') . '</p>'
            . '<p><strong>Procedura:</strong> ' . \PPStudio\Support\ViewHelper::escape($serviceName) . '<br>'
            . ($isRescheduled
                ? '<strong>Původní termín:</strong> ' . \PPStudio\Support\ViewHelper::escape($previousDateTime) . '<br><strong>Nový termín:</strong> ' . \PPStudio\Support\ViewHelper::escape($dateTime) . '<br>'
                : '<strong>Termín:</strong> ' . \PPStudio\Support\ViewHelper::escape($dateTime) . '<br>')
            . '<strong>Místo:</strong> ' . \PPStudio\Support\ViewHelper::escape($location) . '</p>'
            . '<p>V příloze najdete soubor pro vložení do kalendáře.</p>';

        if ($cancelUrl !== '' || $rescheduleUrl !== '') {
            $htmlBody .= '<div style="margin:18px 0;padding-top:14px;border-top:1px solid #e4d6c5;">'
                . '<p style="margin:0 0 10px 0;">Potřebujete změnu? Použijte bezpečná tlačítka níže:</p>';
            if ($rescheduleUrl !== '') {
                $htmlBody .= '<a href="'
                    . \PPStudio\Support\ViewHelper::escape($rescheduleUrl)
                    . '" style="display:inline-block;padding:10px 16px;background:#7a5a43;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:700;box-shadow:0 6px 16px rgba(122,90,67,0.18);">Přesunout termín</a>';
            }
            if ($cancelUrl !== '') {
                $htmlBody .= ($rescheduleUrl !== '' ? '<span style="display:inline-block;width:24px;"></span>' : '')
                    . '<a href="'
                    . \PPStudio\Support\ViewHelper::escape($cancelUrl)
                    . '" style="display:inline-block;padding:10px 16px;background:#fbf5ee;color:#8c4f42;text-decoration:none;border-radius:999px;font-weight:700;border:1px solid #d9c0b5;">Zrušit termín</a>';
            }
            $htmlBody .= '<p style="margin:10px 0 0 0;font-size:12px;color:#6e5f52;">Po otevření odkazu se zobrazí potvrzovací krok.'
                . ($canManageLabel !== '' ? ' Změnu nebo zrušení lze provést nejpozději do ' . \PPStudio\Support\ViewHelper::escape($canManageLabel) . '.' : '')
                . '</p>'
                . '</div>';
        }

        $htmlBody .= '<p>' . \PPStudio\Support\ViewHelper::escape($siteName) . '</p>';

        return $this->mailer->send(
            (string) $reservation['email'],
            $subject,
            $htmlBody,
            $textBody,
            [
                'filename' => 'rezervace-' . ((string) ($reservation['id'] ?? 'termin')) . '.ics',
                'content' => $this->buildReservationIcal($siteSettings, $reservation),
                'content_type' => 'text/calendar; method=REQUEST; charset=UTF-8',
            ]
        );
    }

    public function sendCancelledEmail(array $siteSettings, array $reservation): bool
    {
        if (! ($this->emailConfig['enabled'] ?? false) || ($reservation['email'] ?? '') === '') {
            return false;
        }

        $siteName = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'site_name', \defaultSiteName());
        $subject = $siteName . ': rezervace byla zrušena';
        $customerName = (string) ($reservation['jmeno'] ?? '');
        $serviceName = (string) ($reservation['service_name'] ?? 'Vybraná procedura');
        $dateTime = \PPStudio\Support\FormatHelper::formatCzechDateTime((string) ($reservation['datum_cas'] ?? ''));
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

        return $this->mailer->send(
            (string) $reservation['email'],
            $subject,
            nl2br(\PPStudio\Support\ViewHelper::escape($textBody)),
            $textBody
        );
    }

    public function sendReminderEmail(array $siteSettings, array $reservation): bool
    {
        if (! ($this->emailConfig['enabled'] ?? false) || ($reservation['email'] ?? '') === '') {
            return false;
        }

        $siteName = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'site_name', \defaultSiteName());
        $subject = $siteName . ': připomínka rezervace';
        $customerName = trim((string) ($reservation['jmeno'] ?? ''));
        $serviceName = trim((string) ($reservation['service_name'] ?? 'Vybraná procedura'));
        $dateTime = \PPStudio\Support\FormatHelper::formatCzechDateTime((string) ($reservation['datum_cas'] ?? ''));
        $address = trim((string) \PPStudio\Support\SettingsHelper::setting($siteSettings, 'contact_address', ''));
        $rescheduleUrl = $this->linkSigner->buildCustomerRescheduleUrl($siteSettings, $reservation);
        $cancelUrl = $this->linkSigner->buildCustomerCancelUrl($siteSettings, $reservation);

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
            $htmlBody .= ' ' . \PPStudio\Support\ViewHelper::escape($customerName);
        }
        $htmlBody .= ',</p>'
            . '<p>připomínáme vaši rezervaci v <strong>' . \PPStudio\Support\ViewHelper::escape($siteName) . '</strong>.</p>'
            . '<div style="margin:18px 0;padding:16px 18px;border:1px solid #eadccf;border-radius:18px;background:#fffaf4;">'
            . '<p style="margin:0 0 10px;"><strong>Procedura:</strong> ' . \PPStudio\Support\ViewHelper::escape($serviceName) . '</p>'
            . '<p style="margin:0 0 10px;"><strong>Termín:</strong> ' . \PPStudio\Support\ViewHelper::escape($dateTime) . '</p>';

        if ($address !== '') {
            $htmlBody .= '<p style="margin:0;"><strong>Místo:</strong> ' . nl2br(\PPStudio\Support\ViewHelper::escape($address)) . '</p>';
        }

        $htmlBody .= '</div>';

        if ($rescheduleUrl !== '' || $cancelUrl !== '') {
            $htmlBody .= '<p>Pokud potřebujete termín upravit, můžete využít některou z těchto možností:</p>'
                . '<div style="margin:18px 0;">';

            if ($rescheduleUrl !== '') {
                $htmlBody .= '<a href="' . \PPStudio\Support\ViewHelper::escape($rescheduleUrl) . '" style="display:inline-block;margin:0 12px 12px 0;padding:11px 18px;border-radius:999px;background:#7a5a43;color:#ffffff;text-decoration:none;font-weight:700;box-shadow:0 10px 22px rgba(122,90,67,0.18);">Přesunout termín</a>';
            }

            if ($cancelUrl !== '') {
                $htmlBody .= '<a href="' . \PPStudio\Support\ViewHelper::escape($cancelUrl) . '" style="display:inline-block;margin:0 12px 12px 0;padding:11px 18px;border-radius:999px;background:#fff7f0;border:1px solid #d8b2a9;color:#8b5f56;text-decoration:none;font-weight:700;">Zrušit termín</a>';
            }

            $htmlBody .= '</div>';
        }

        $htmlBody .= '<p>Těšíme se na vaši návštěvu.</p>'
            . '<p>' . \PPStudio\Support\ViewHelper::escape($siteName) . '</p>';

        return $this->mailer->send((string) $reservation['email'], $subject, $htmlBody, $textBody);
    }

    public function notificationRecipients(array $siteSettings): array
    {
        $raw = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'notification_emails', '');
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

    public function buildReservationIcal(array $siteSettings, array $reservation): string
    {
        $siteName = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'site_name', \defaultSiteName());
        $serviceName = (string) ($reservation['service_name'] ?? 'Rezervace');
        $start = new DateTimeImmutable((string) ($reservation['datum_cas'] ?? 'now'));
        $duration = max(15, (int) ($reservation['service_duration'] ?? 60));
        $end = $start->modify('+' . $duration . ' minutes');
        $location = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'contact_address', '');
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
            . "SUMMARY:" . $this->escapeIcalText($siteName . ' - ' . $serviceName) . "\r\n"
            . "DESCRIPTION:" . $this->escapeIcalText($description) . "\r\n"
            . "LOCATION:" . $this->escapeIcalText($location) . "\r\n"
            . "STATUS:CONFIRMED\r\n"
            . "END:VEVENT\r\n"
            . "END:VCALENDAR\r\n";
    }

    public function escapeIcalText(string $value): string
    {
        return str_replace(
            ["\\", ";", ",", "\r\n", "\n"],
            ["\\\\", "\;", "\,", "\\n", "\\n"],
            $value
        );
    }
}
