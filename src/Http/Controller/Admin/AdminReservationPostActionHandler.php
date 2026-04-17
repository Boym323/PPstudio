<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Service\AdminReservationMutationService;

final class AdminReservationPostActionHandler
{
    public function __construct(private AdminReservationMutationService $mutationService)
    {
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     * @param array<string, mixed> $session
     * @param array<string, mixed> $manualReservationForm
     * @return array{message:string,error:string,manual_reservation_form:array<string, mixed>}
     */
    public function handle(
        array $server,
        array $post,
        array $session,
        array $manualReservationForm
    ): array {
        if (($server['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return [
                'message' => '',
                'error' => '',
                'manual_reservation_form' => $manualReservationForm,
            ];
        }

        if (isset($post['update_reservation'])) {
            return $this->mapResult(
                $this->mutationService->updateReservation($post, $session),
                $manualReservationForm,
                'Rezervace byla upravena.',
                'Rezervaci se nepodařilo upravit.'
            );
        }

        if (isset($post['save_manual_reservation'])) {
            return $this->mapResult(
                $this->mutationService->createManualReservation($post),
                $manualReservationForm,
                'Ruční rezervace byla vložena.',
                'Ruční rezervaci se nepodařilo uložit.'
            );
        }

        if (isset($post['delete_reservation'])) {
            return $this->mapResult(
                $this->mutationService->deleteReservation($post),
                $manualReservationForm,
                'Rezervace byla smazána.',
                'Rezervaci se nepodařilo smazat.'
            );
        }

        return [
            'message' => '',
            'error' => '',
            'manual_reservation_form' => $manualReservationForm,
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $manualReservationForm
     * @return array{message:string,error:string,manual_reservation_form:array<string, mixed>}
     */
    private function mapResult(
        array $result,
        array $manualReservationForm,
        string $successFallback,
        string $errorFallback
    ): array {
        $nextManualReservationForm = is_array($result['data']['manual_reservation_form'] ?? null)
            ? $result['data']['manual_reservation_form']
            : $manualReservationForm;

        if (($result['success'] ?? false) === true) {
            return [
                'message' => (string) ($result['message'] ?? $successFallback),
                'error' => '',
                'manual_reservation_form' => $nextManualReservationForm,
            ];
        }

        return [
            'message' => '',
            'error' => (string) ($result['error'] ?? $errorFallback),
            'manual_reservation_form' => $nextManualReservationForm,
        ];
    }
}
