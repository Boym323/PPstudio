<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use PPStudio\Database\DatabaseFactory;
use PPStudio\Http\Request\ReservationSubmitRequest;
use PPStudio\Repository\AvailabilityRepository;
use PPStudio\Repository\ReservationRepository;
use PPStudio\Repository\SiteSettingsRepository;
use PPStudio\Repository\ServiceRepository;

final class ReservationSubmitService
{
    public function __construct(private ReservationNotificationService $notificationService)
    {
    }

    public function submit(ReservationSubmitRequest $request): array
    {
        $connection = DatabaseFactory::tryConnect();

        if (! $connection instanceof mysqli) {
            return [
                'status' => 'db',
                'success' => false,
                'http_code' => 500,
            ];
        }

        $dateTime = $request->dateTime();
        $siteSettings = new SiteSettingsService(new SiteSettingsRepository($connection), \defaultSiteSettings())->load();
        $serviceRepository = new ServiceRepository($connection);
        $availabilityRepository = new AvailabilityRepository($connection);
        $reservationRepository = new ReservationRepository($connection);
        $availabilityService = new AvailabilityService($serviceRepository, $availabilityRepository, $reservationRepository);
        $reservationService = new ReservationService(
            $connection,
            $serviceRepository,
            $availabilityRepository,
            $reservationRepository,
            $availabilityService
        );
        $reservationInsert = $reservationService->createReservationWithLock(
            $request->name,
            $request->email,
            $request->phone,
            $request->source,
            $request->note,
            $request->serviceId,
            $dateTime,
            'nova'
        );

        if (in_array($reservationInsert['status'] ?? 'error', ['slot_unavailable', 'service_unavailable'], true)) {
            $connection->close();

            return [
                'status' => 'slot',
                'success' => false,
                'http_code' => 409,
            ];
        }

        if (($reservationInsert['status'] ?? 'error') !== 'ok') {
            $connection->close();

            return [
                'status' => 'insert',
                'success' => false,
                'http_code' => 500,
            ];
        }

        $service = is_array($reservationInsert['service'] ?? null) ? $reservationInsert['service'] : [];
        $reservation = [
            'id' => (int) ($reservationInsert['reservation_id'] ?? 0),
            'jmeno' => $request->name,
            'email' => $request->email,
            'telefon' => $request->phone,
            'zdroj' => $request->source,
            'poznamka_klienta' => $request->note,
            'datum_cas' => (string) ($reservationInsert['date_time'] ?? $dateTime),
            'service_name' => (string) ($service['nazev'] ?? 'Vybraná procedura'),
            'service_price' => $reservationInsert['service_price'] ?? null,
            'service_duration' => (int) ($service['doba_trvani'] ?? 60),
        ];

        $this->notificationService->notifyReservationSubmitted($siteSettings, $reservation);
        $connection->close();

        return [
            'status' => 'success',
            'success' => true,
            'http_code' => 200,
            'extra' => [
                'reservation' => [
                    'service' => (string) $reservation['service_name'],
                    'slot' => date('d.m.Y', strtotime($dateTime)) . ' v ' . substr($request->time, 0, 5),
                    'contact' => implode(' • ', array_filter([$request->name, $request->email, $request->phone])),
                ],
            ],
        ];
    }
}
