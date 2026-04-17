<?php
declare(strict_types=1);

namespace PPStudio\Service;

use PPStudio\Http\Request\ReservationSubmitRequest;

final class ReservationSubmitService
{
    public function __construct(
        private ReservationSubmitContextFactory $contextFactory,
        private ReservationNotificationService $notificationService
    )
    {
    }

    public function submit(ReservationSubmitRequest $request): array
    {
        $context = $this->contextFactory->create();
        if (! $context instanceof ReservationSubmitContext) {
            return [
                'status' => 'db',
                'success' => false,
                'http_code' => 500,
            ];
        }

        try {
            $dateTime = $request->dateTime();
            $reservationInsert = $context->reservationService->createReservationWithLock(
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
                return [
                    'status' => 'slot',
                    'success' => false,
                    'http_code' => 409,
                ];
            }

            if (($reservationInsert['status'] ?? 'error') !== 'ok') {
                return [
                    'status' => 'insert',
                    'success' => false,
                    'http_code' => 500,
                ];
            }

            $serviceData = is_array($reservationInsert['service'] ?? null) ? $reservationInsert['service'] : [];
            $reservation = [
                'id' => (int) ($reservationInsert['reservation_id'] ?? 0),
                'jmeno' => $request->name,
                'email' => $request->email,
                'telefon' => $request->phone,
                'zdroj' => $request->source,
                'poznamka_klienta' => $request->note,
                'datum_cas' => (string) ($reservationInsert['date_time'] ?? $dateTime),
                'service_name' => (string) ($serviceData['nazev'] ?? 'Vybraná procedura'),
                'service_price' => $reservationInsert['service_price'] ?? null,
                'service_duration' => (int) ($serviceData['doba_trvani'] ?? 60),
            ];

            $this->notificationService->notifyReservationSubmitted($context->siteSettings, $reservation);

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
        } finally {
            $context->connection->close();
        }
    }
}
