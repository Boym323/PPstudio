<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller;

use PPStudio\Http\Request\ReservationSubmitRequest;
use PPStudio\Security\CsrfService;
use PPStudio\Security\PublicSiteLockService;
use PPStudio\Security\ReservationAntispamService;
use PPStudio\Security\RequestSecurityService;
use PPStudio\Service\ReservationSubmitService;

final class ReservationController
{
    private const STATUS_MESSAGES = [
        'success' => 'Rezervace byla odeslaná. Potvrzení vám během chvíle dorazí e-mailem.',
        'csrf' => 'Platnost formuláře vypršela. Obnovte stránku a zkuste to znovu.',
        'missing' => 'Vyplňte prosím všechna povinná pole.',
        'email' => 'Zadejte platný e-mail.',
        'invalid_datetime' => 'Vyberte platný termín rezervace.',
        'slot' => 'Vybraný termín už není volný. Zkuste prosím jiný.',
        'too_fast' => 'Formulář byl odeslán příliš rychle. Zkuste to prosím ještě jednou.',
        'spam' => 'Rezervaci se nepodařilo ověřit. Obnovte stránku a zkuste to znovu.',
        'rate_limit' => 'Odeslání je dočasně omezené. Zkuste to prosím za chvíli.',
        'db' => 'Nepodařilo se navázat spojení. Zkuste to prosím za chvíli znovu.',
        'insert' => 'Rezervaci se nepodařilo uložit. Zkuste to prosím znovu.',
        'locked' => 'Web je dočasně uzamčen heslem.',
    ];

    public function __construct(
        private ReservationSubmitService $submitService,
        private PublicSiteLockService $publicSiteLockService,
        private CsrfService $csrfService,
        private ReservationAntispamService $reservationAntispamService,
        private RequestSecurityService $requestSecurityService
    ) {
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     */
    public function submit(array $server, array $post): never
    {
        $isAjaxRequest = $this->isAjaxRequest($server);

        if ($this->publicSiteLockService->isLockedForCurrentVisitor()) {
            if ($isAjaxRequest) {
                $this->respond('locked', false, 423, [], true);
            }

            http_response_code(423);
            echo 'Web je dočasně uzamčen heslem.';
            exit;
        }

        if (($server['REQUEST_METHOD'] ?? '') !== 'POST') {
            if ($isAjaxRequest) {
                $this->respond('missing', false, 405, [], true);
            }

            http_response_code(405);
            echo 'Method not allowed';
            exit;
        }

        if (! $this->csrfService->isValid((string) ($post['_csrf'] ?? ''))) {
            $this->respond('csrf', false, 419, [], $isAjaxRequest);
        }

        $clientIpAddress = $this->requestSecurityService->clientIpAddress($server);
        $userAgent = $this->requestSecurityService->userAgent($server);
        $antispamResult = $this->reservationAntispamService->validateSubmission(
            $post,
            $clientIpAddress,
            $userAgent
        );
        if (! ($antispamResult['allowed'] ?? false)) {
            $this->respond(
                (string) ($antispamResult['status'] ?? 'spam'),
                false,
                (int) ($antispamResult['http_code'] ?? 422),
                [],
                $isAjaxRequest
            );
        }

        $request = ReservationSubmitRequest::fromPost($post);
        $validationStatus = $request->validationStatus();
        if ($validationStatus !== null) {
            $this->respond($validationStatus, false, 422, [], $isAjaxRequest);
        }

        $result = $this->submitService->submit($request);
        $this->respond(
            (string) ($result['status'] ?? 'insert'),
            (bool) ($result['success'] ?? false),
            (int) ($result['http_code'] ?? 500),
            is_array($result['extra'] ?? null) ? $result['extra'] : [],
            $isAjaxRequest
        );
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function respond(string $status, bool $success, int $httpCode, array $extra, bool $isAjaxRequest): never
    {
        if (! $isAjaxRequest) {
            header('Location: /rezervace.php?reservation=' . rawurlencode($status) . '#contact');
            exit;
        }

        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');

        $payload = array_merge([
            'success' => $success,
            'status' => $status,
            'message' => self::STATUS_MESSAGES[$status] ?? 'Nepodařilo se zpracovat požadavek.',
            'new_token' => $this->reservationAntispamService->issueToken(),
        ], $extra);

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param array<string, mixed> $server
     */
    private function isAjaxRequest(array $server): bool
    {
        return strtolower((string) ($server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch'
            || str_contains(strtolower((string) ($server['HTTP_ACCEPT'] ?? '')), 'application/json');
    }
}
