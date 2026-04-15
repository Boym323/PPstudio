<?php
declare(strict_types=1);

namespace PPStudio\Service;

final class ReservationNotificationService
{
    public function __construct(private array $emailConfig)
    {
    }

    public function notifyReservationSubmitted(array $siteSettings, array $reservation): void
    {
        sendReservationReceivedEmail($this->emailConfig, $siteSettings, $reservation);
        sendReservationAdminNotification($this->emailConfig, $siteSettings, $reservation);
    }
}
