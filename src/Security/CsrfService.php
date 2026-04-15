<?php
declare(strict_types=1);

namespace PPStudio\Security;

final class CsrfService
{
    private const SESSION_KEY = 'ppstudio_csrf_token';

    public function __construct(
        private SessionService $sessionService
    ) {
    }

    public function token(): string
    {
        $this->sessionService->start();

        if (! isset($_SESSION[self::SESSION_KEY]) || ! is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public function inputField(string $fieldName = '_csrf'): string
    {
        $token = htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="' . $name . '" value="' . $token . '">';
    }

    public function isValid(?string $token): bool
    {
        $this->sessionService->start();

        if (! is_string($token) || $token === '') {
            return false;
        }

        $sessionToken = (string) ($_SESSION[self::SESSION_KEY] ?? '');

        return $sessionToken !== '' && hash_equals($sessionToken, $token);
    }
}
