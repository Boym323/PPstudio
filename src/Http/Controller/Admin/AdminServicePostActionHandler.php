<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Service\AdminServiceMutationService;

final class AdminServicePostActionHandler
{
    public function __construct(
        private AdminServiceMutationService $mutationService
    ) {
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     * @param array<string, mixed> $serviceForm
     * @param array<string, mixed> $categoryForm
     * @return array{message:string,error:string,service_form:array<string, mixed>,category_form:array<string, mixed>}
     */
    public function handle(
        array $server,
        array $post,
        array $serviceForm,
        array $categoryForm
    ): array {
        $state = [
            'message' => '',
            'error' => '',
            'service_form' => $serviceForm,
            'category_form' => $categoryForm,
        ];

        if (($server['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return $state;
        }

        $applyResult = function (array $result) use (&$state): void {
            if (is_array($result['data']['service_form'] ?? null)) {
                $state['service_form'] = $result['data']['service_form'];
            }

            if (is_array($result['data']['category_form'] ?? null)) {
                $state['category_form'] = $result['data']['category_form'];
            }

            if (is_string($result['message'] ?? null) && ($result['message'] ?? '') !== '') {
                $state['message'] = (string) $result['message'];
            }

            if (is_string($result['error'] ?? null) && ($result['error'] ?? '') !== '') {
                $state['error'] = (string) $result['error'];
            }
        };

        if (isset($post['save_category'])) {
            $applyResult($this->mutationService->saveCategory($post));
        }

        if (isset($post['toggle_category_active'])) {
            $applyResult($this->mutationService->toggleCategoryActive($post));
        }

        if (isset($post['save_category_order'])) {
            $applyResult($this->mutationService->saveCategoryOrder($post));
        }

        if (isset($post['save_service'])) {
            $applyResult($this->mutationService->saveService($post));
        }

        if (isset($post['toggle_service_active'])) {
            $applyResult($this->mutationService->toggleServiceActive($post));
        }

        return $state;
    }
}
