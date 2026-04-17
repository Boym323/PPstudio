<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use PPStudio\Database\DatabaseFactory;
use PPStudio\Http\Request\ReservationsFeedRequest;

final class ReservationsFeedApplication
{
    private ReservationsFeedAuthorizationService $authorizationService;

    private ReservationsFeedDataLoader $dataLoader;

    private ReservationsFeedResponder $responder;

    /**
     * @param array<string, mixed> $emailConfig
     */
    public function __construct(
        private array $emailConfig
    ) {
        $this->authorizationService = new ReservationsFeedAuthorizationService();
        $this->dataLoader = new ReservationsFeedDataLoader();
        $this->responder = new ReservationsFeedResponder();
    }

    public function handle(ReservationsFeedRequest $request): never
    {
        if (! $this->authorizationService->isAuthorized($request, $this->emailConfig)) {
            $this->responder->respondForbidden();
        }

        $connection = DatabaseFactory::tryConnect();

        if (! $connection instanceof \mysqli) {
            $this->responder->respondDatabaseUnavailable();
        }

        try {
            $payload = $this->dataLoader->load($connection, $this->emailConfig);
        } finally {
            $connection->close();
        }

        $this->responder->respondCalendar($payload['ical']);
    }
}
