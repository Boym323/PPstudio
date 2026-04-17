<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

final class ReservationsFeedResponder
{
    public function respondForbidden(): never
    {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    public function respondDatabaseUnavailable(): never
    {
        http_response_code(500);
        echo 'Database unavailable';
        exit;
    }

    public function respondCalendar(string $ical): never
    {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="reservations-feed.ics"');

        echo $ical;
        exit;
    }
}
