<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use PPStudio\Http\View\ReservationActionPageRenderer;

final class ReservationActionApplication
{
    public function __construct(
        private ReservationActionController $controller,
        private ReservationActionPageRenderer $renderer
    ) {
    }

    public static function create(array $emailConfig): self
    {
        return new self(
            ReservationActionController::create($emailConfig),
            new ReservationActionPageRenderer()
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    public function handleAdminAction(array $query): never
    {
        $state = $this->controller->adminAction($query);
        $this->renderer->renderAdminAction($state);
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $server
     */
    public function handleCustomerCancel(array $request, array $server): never
    {
        $state = $this->controller->customerCancel($request, $server);
        $this->renderer->renderCustomerCancel($state, $request);
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     */
    public function handleCustomerReschedule(array $request, array $post, array $server): never
    {
        $state = $this->controller->customerReschedule($request, $post, $server);

        if ((bool) ($state['is_ajax_request'] ?? false)) {
            $this->renderer->renderJson($this->controller->rescheduleJsonPayload($state));
        }

        $this->renderer->renderCustomerReschedule($state, $request);
    }
}
