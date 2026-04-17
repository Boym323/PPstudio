<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Http\Request\AdminPostActionRequest;
use PPStudio\Service\AdminAvailabilityMutationService;

final class AdminAvailabilityPostActionHandler
{
    public function __construct(
        private AdminAvailabilityMutationService $mutationService
    ) {
    }

    /**
     * @param array<string, string> $siteSettings
     * @return array{site_settings:array<string, string>,message:string,error:string}
     */
    public function handle(AdminPostActionRequest $request, array $siteSettings): array
    {
        $state = [
            'site_settings' => $siteSettings,
            'message' => '',
            'error' => '',
        ];

        if (! $request->isPost()) {
            return $state;
        }

        $post = $request->post();

        if ($request->hasPostKey('save_availability_grid')) {
            $state = $this->applyResult(
                $state,
                $this->mutationService->saveAvailabilityGrid($post),
                'Dostupnost v kalendáři byla uložena.',
                'Kalendář dostupnosti se nepodařilo uložit.'
            );
        }

        if ($request->hasPostKey('delete_window')) {
            $state = $this->applyResult(
                $state,
                $this->mutationService->deleteWindow($post),
                'Volné okno bylo odstraněno.',
                'Okno se nepodařilo odstranit.'
            );
        }

        if ($request->hasPostKey('save_availability_story_background')) {
            $state = $this->applyResult(
                $state,
                $this->mutationService->saveStoryBackground($request->files()),
                'Pozadí pro Instagram story bylo uloženo.',
                'Pozadí pro story se nepodařilo uložit.'
            );
        }

        if ($request->hasPostKey('delete_availability_story_background')) {
            $state = $this->applyResult(
                $state,
                $this->mutationService->deleteStoryBackground(),
                'Pozadí pro Instagram story bylo odstraněno.',
                'Pozadí pro story se nepodařilo odstranit.'
            );
        }

        return $state;
    }

    /**
     * @param array{site_settings:array<string, string>,message:string,error:string} $state
     * @param array<string, mixed> $result
     * @return array{site_settings:array<string, string>,message:string,error:string}
     */
    private function applyResult(
        array $state,
        array $result,
        string $successFallback,
        string $errorFallback
    ): array {
        if (is_array($result['data']['site_settings'] ?? null)) {
            $state['site_settings'] = $result['data']['site_settings'];
        }

        if (($result['success'] ?? false) === true) {
            $state['message'] = (string) ($result['message'] ?? $successFallback);
            $state['error'] = '';

            return $state;
        }

        $state['error'] = (string) ($result['error'] ?? $errorFallback);

        return $state;
    }
}
