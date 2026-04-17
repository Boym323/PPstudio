<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

final class AdminAuthenticationService
{
    /**
     * @param array<string, mixed> $adminConfig
     * @param array{
     *     auth_session_key:string,
     *     username_session_key:string,
     *     throttle_scope:string,
     *     redirect_path:string,
     *     event_source:string,
     *     event_name_prefix:string
     * } $options
     * @return array{is_authenticated:bool,login_error:string,error:string}
     */
    public function handle(array $adminConfig, array $options): array
    {
        $security = \(new \PPStudio\Security\SecurityFacade());
        $security->startSecureSession();

        $isAuthenticated = (bool) ($_SESSION[$options['auth_session_key']] ?? false);
        $loginError = '';
        $error = '';
        $loginIp = $security->getClientIpAddress();
        $loginUsernameInput = trim((string) ($_POST['username'] ?? ''));
        $loginRateState = $security->loginThrottleState(
            $options['throttle_scope'],
            $loginIp,
            $loginUsernameInput
        );
        $isLocked = (bool) ($loginRateState['locked'] ?? false);
        $minutesLeft = (int) ($loginRateState['minutes_left'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! $security->isValidCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
            if (isset($_POST['admin_login'])) {
                $loginError = 'Platnost přihlášení vypršela. Obnovte stránku a zkuste to znovu.';
            } else {
                $error = 'Platnost formuláře vypršela. Obnovte stránku a akci opakujte.';
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login']) && $loginError === '') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $storedUsername = (string) ($adminConfig['username'] ?? '');
            $storedHash = (string) ($adminConfig['password_hash'] ?? '');
            $legacyPassword = (string) ($adminConfig['password'] ?? '');
            $passwordMatches = $storedHash !== ''
                ? password_verify($password, $storedHash)
                : ($legacyPassword !== '' && hash_equals($legacyPassword, $password));

            if ($isLocked) {
                $loginError = 'Příliš mnoho neúspěšných pokusů. Zkuste to znovu za ' . $minutesLeft . ' min.';
                $security->securityEventLogger()->log($options['event_name_prefix'] . '_locked', $options['event_source'], 'warning', [
                    'username' => $username,
                    'minutes_left' => $minutesLeft,
                ]);
            } elseif ($username === $storedUsername && $passwordMatches) {
                $_SESSION[$options['auth_session_key']] = true;
                $_SESSION[$options['username_session_key']] = $username;
                $security->loginThrottleReset($options['throttle_scope'], $loginIp, $username);
                $security->securityEventLogger()->log($options['event_name_prefix'] . '_success', $options['event_source'], 'info', [
                    'username' => $username,
                ]);
                session_regenerate_id(true);
                header('Location: ' . $options['redirect_path']);
                exit;
            }

            if ($loginError === '') {
                $failureState = $security->loginThrottleRegisterFailure($options['throttle_scope'], $loginIp, $username);
                if ((bool) ($failureState['locked'] ?? false)) {
                    $minutesToWait = (int) ($failureState['minutes_left'] ?? 15);
                    $loginError = 'Příliš mnoho neúspěšných pokusů. Zkuste to znovu za 15 min.';
                    if ($minutesToWait > 0) {
                        $loginError = 'Příliš mnoho neúspěšných pokusů. Zkuste to znovu za ' . $minutesToWait . ' min.';
                    }
                    $security->securityEventLogger()->log($options['event_name_prefix'] . '_locked', $options['event_source'], 'warning', [
                        'username' => $username,
                        'minutes_left' => $minutesToWait,
                    ]);
                } else {
                    $loginError = 'Neplatné přihlašovací údaje.';
                    $security->securityEventLogger()->log($options['event_name_prefix'] . '_failed', $options['event_source'], 'warning', [
                        'username' => $username,
                        'remaining' => (int) ($failureState['remaining'] ?? 0),
                    ]);
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['admin_logout'])
            && $security->isValidCsrfToken((string) ($_POST['_csrf'] ?? ''))
        ) {
            unset($_SESSION[$options['auth_session_key']], $_SESSION[$options['username_session_key']]);
            header('Location: ' . $options['redirect_path']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! $security->isValidCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
            $_POST = [];
        }

        if (! $isAuthenticated && isset($_SESSION[$options['auth_session_key']])) {
            $isAuthenticated = true;
        }

        return [
            'is_authenticated' => $isAuthenticated,
            'login_error' => $loginError,
            'error' => $error,
        ];
    }
}
