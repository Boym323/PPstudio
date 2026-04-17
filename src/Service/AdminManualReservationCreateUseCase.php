<?php
declare(strict_types=1);

namespace PPStudio\Service;

final class AdminManualReservationCreateUseCase
{
    public function __construct(
        private array $siteSettings,
        private ReservationService $reservationService,
        private ReservationNotificationService $notificationService
    ) {
    }

    public function handle(array $post): array
    {
        $form = [
            'jmeno' => trim((string) ($post['jmeno'] ?? '')),
            'email' => trim((string) ($post['email'] ?? '')),
            'telefon' => trim((string) ($post['telefon'] ?? '')),
            'zdroj' => trim((string) ($post['zdroj'] ?? 'telefon')),
            'sluzba_id' => trim((string) ($post['sluzba_id'] ?? '')),
            'datum_cas' => trim((string) ($post['datum_cas'] ?? '')),
            'poznamka_klienta' => trim((string) ($post['poznamka_klienta'] ?? '')),
        ];

        $serviceId = (int) $form['sluzba_id'];
        $dateTimeForSave = $this->normalizeDateTimeInput($form['datum_cas']);

        if ($form['jmeno'] === '' || $serviceId <= 0 || $dateTimeForSave === '') {
            return $this->error('Pro ruční rezervaci vyplňte jméno, službu a termín.', 422, [
                'manual_reservation_form' => $form,
            ]);
        }

        if ($form['email'] !== '' && ! filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->error('Zadaný e-mail není platný.', 422, [
                'manual_reservation_form' => $form,
            ]);
        }

        $reservationInsert = $this->reservationService->createReservationWithLock(
            $form['jmeno'],
            $form['email'],
            $form['telefon'],
            $form['zdroj'],
            $form['poznamka_klienta'],
            $serviceId,
            $dateTimeForSave,
            'potvrzena'
        );

        if (($reservationInsert['status'] ?? 'error') === 'ok') {
            $service = is_array($reservationInsert['service'] ?? null) ? $reservationInsert['service'] : [];
            $servicePrice = $reservationInsert['service_price'] ?? null;
            $reservation = [
                'id' => (int) ($reservationInsert['reservation_id'] ?? 0),
                'jmeno' => $form['jmeno'],
                'email' => $form['email'],
                'telefon' => $form['telefon'],
                'zdroj' => $form['zdroj'],
                'poznamka_klienta' => $form['poznamka_klienta'],
                'datum_cas' => (string) ($reservationInsert['date_time'] ?? $dateTimeForSave),
                'service_name' => (string) ($service['nazev'] ?? 'Vybraná procedura'),
                'service_price' => $servicePrice,
                'service_duration' => (int) ($service['doba_trvani'] ?? 60),
            ];

            if ($form['email'] !== '') {
                $this->notificationService->sendConfirmedEmail($this->siteSettings, $reservation);
            }

            return $this->success('Ruční rezervace byla vložena.', [
                'reservation_id' => (int) ($reservationInsert['reservation_id'] ?? 0),
                'manual_reservation_form' => $this->emptyManualReservationForm(),
            ]);
        }

        if (in_array($reservationInsert['status'] ?? 'error', ['slot_unavailable', 'service_unavailable'], true)) {
            return $this->error('Vybraný termín už není volný nebo neodpovídá dostupnosti.', 422, [
                'manual_reservation_form' => $form,
            ]);
        }

        return $this->error('Ruční rezervaci se nepodařilo uložit.', 500, [
            'manual_reservation_form' => $form,
        ]);
    }

    private function normalizeDateTimeInput(string $value): string
    {
        $normalized = trim(str_replace('T', ' ', $value));
        if ($normalized !== '' && strlen($normalized) === 16) {
            $normalized .= ':00';
        }

        return $normalized;
    }

    private function emptyManualReservationForm(): array
    {
        return [
            'jmeno' => '',
            'email' => '',
            'telefon' => '',
            'zdroj' => 'telefon',
            'sluzba_id' => '',
            'datum_cas' => '',
            'poznamka_klienta' => '',
        ];
    }

    private function success(string $message, array $data = []): array
    {
        return [
            'success' => true,
            'message' => $message,
            'error' => null,
            'http_code' => 200,
            'data' => $data,
        ];
    }

    private function error(string $message, int $httpCode, array $data = []): array
    {
        return [
            'success' => false,
            'message' => null,
            'error' => $message,
            'http_code' => $httpCode,
            'data' => $data,
        ];
    }
}
