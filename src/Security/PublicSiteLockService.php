<?php
declare(strict_types=1);

namespace PPStudio\Security;

final class PublicSiteLockService
{
    private const SESSION_KEY = 'ppstudio_public_lock_passed';

    public function __construct(
        private SessionService $sessionService,
        private CsrfService $csrfService,
        private RequestSecurityService $requestSecurityService
    ) {
    }

    public function enabled(): bool
    {
        $value = strtolower(trim((string) (\function_exists('ppstudioEnv') ? \ppstudioEnv('PPSTUDIO_PUBLIC_LOCK_ENABLED', '0') : '0')));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public function sessionKey(): string
    {
        return self::SESSION_KEY;
    }

    public function hasAccess(): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        $this->sessionService->start();

        return (bool) ($_SESSION[self::SESSION_KEY] ?? false);
    }

    public function isLockedForCurrentVisitor(): bool
    {
        return $this->enabled() && ! $this->hasAccess();
    }

    public function passwordMatches(string $password): bool
    {
        $password = trim($password);
        if ($password === '') {
            return false;
        }

        $hash = trim((string) (\function_exists('ppstudioEnv') ? \ppstudioEnv('PPSTUDIO_PUBLIC_LOCK_PASSWORD_HASH', '') : ''));
        if ($hash !== '') {
            return password_verify($password, $hash);
        }

        $plain = (string) (\function_exists('ppstudioEnv') ? \ppstudioEnv('PPSTUDIO_PUBLIC_LOCK_PASSWORD', '') : '');

        return $plain !== '' && hash_equals($plain, $password);
    }

    /**
     * @param array<string, mixed>|null $server
     */
    public function currentUrl(?array $server = null): string
    {
        $server ??= $_SERVER;
        $uri = (string) ($server['REQUEST_URI'] ?? '/');

        return $uri !== '' ? $uri : '/';
    }

    /**
     * @param array<string, mixed>|null $server
     */
    public function renderPage(string $errorMessage = '', ?array $server = null): never
    {
        $siteName = \defaultSiteName();
        $csrf = $this->csrfService->token();
        $currentUrl = $this->currentUrl($server);
        $instagramUrl = 'https://www.instagram.com/ppstudio.cz/';

        http_response_code(401);
        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= \escape($siteName) ?> | Dočasně uzamčeno</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Georgia, "Times New Roman", serif;
            background:
                radial-gradient(circle at top right, rgba(214, 188, 154, .18), transparent 24rem),
                radial-gradient(circle at bottom left, rgba(214, 188, 154, .12), transparent 22rem),
                linear-gradient(180deg, #faf4ec 0%, #f3e7d9 100%);
            color: #4a3529;
        }
        .lock-card {
            width: min(760px, 100%);
            background: #fffaf4;
            border: 1px solid #dcc4aa;
            border-radius: 24px;
            padding: 34px 34px 28px;
            box-shadow: 0 18px 44px rgba(59, 40, 27, .12);
        }
        .lock-eyebrow {
            margin: 0 0 14px 0;
            font-size: .9rem;
            font-weight: 700;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: #8a6246;
        }
        h1 {
            margin: 0 0 18px 0;
            font-size: clamp(1.8rem, 3.4vw, 2.55rem);
            line-height: 1.12;
            max-width: 14ch;
            letter-spacing: -0.02em;
            color: #4a3529;
        }
        .lock-lead {
            margin: 0 0 12px 0;
            font-size: 1.04rem;
            line-height: 1.78;
            color: #5f4d40;
            max-width: 40rem;
        }
        .lock-copy {
            margin: 0 0 22px 0;
            font-size: .96rem;
            line-height: 1.74;
            color: #746558;
            max-width: 38rem;
        }
        .lock-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 0 0 26px 0;
        }
        .lock-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 54px;
            padding: 0 22px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 700;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }
        .lock-link:hover {
            transform: translateY(-1px);
        }
        .lock-link-primary {
            background: #8a6246;
            color: #fff;
            box-shadow: 0 10px 24px rgba(77, 53, 37, .14);
        }
        .lock-link-primary:hover {
            background: #77523a;
        }
        .lock-link-secondary {
            background: #fff;
            color: #6e5f52;
            border: 1px solid #d6bea4;
        }
        .lock-panel {
            margin-top: 6px;
            border-top: 1px solid #ead9c7;
            padding-top: 22px;
        }
        .lock-panel-title {
            margin: 0 0 8px 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #4a3529;
        }
        .lock-panel-copy {
            margin: 0 0 14px 0;
            color: #8c7d70;
            line-height: 1.6;
            font-size: .94rem;
        }
        .lock-form-wrap {
            background: #fdf8f2;
            border: 1px solid #eee0d1;
            border-radius: 18px;
            padding: 16px;
        }
        label { display: block; margin: 8px 0 8px 0; font-weight: 700; }
        input[type="password"] {
            width: 100%;
            border: 1px solid #d2b79c;
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 1rem;
            font-family: inherit;
            background: #fff;
            color: #3d2c21;
        }
        button {
            margin-top: 12px;
            width: 100%;
            border: 0;
            border-radius: 999px;
            padding: 11px 14px;
            font-size: 1rem;
            font-weight: 700;
            font-family: inherit;
            background: #8a6246;
            color: #fff;
            cursor: pointer;
        }
        .lock-error {
            margin: 8px 0 10px;
            border: 1px solid #d7a89d;
            background: #fff0ed;
            color: #8d3b2a;
            border-radius: 10px;
            padding: 9px 10px;
            font-size: .94rem;
        }
        .lock-note { margin-top: 10px; font-size: .85rem; color: #8a7b6d; }
        .lock-subtle {
            font-size: .9rem;
            color: #8b7d71;
        }
        @media (max-width: 640px) {
            .lock-card {
                padding: 24px 18px 22px;
                border-radius: 20px;
            }
            .lock-actions {
                flex-direction: column;
            }
            .lock-link {
                width: 100%;
            }
            h1 {
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <main class="lock-card">
        <p class="lock-eyebrow">PP Studio</p>
        <h1>Připravujeme pro vás nový web PP Studio.</h1>
        <p class="lock-lead">Na stránce právě pracujeme a brzy ji spustíme v plné podobě.</p>
        <p class="lock-copy">Mezitím můžete sledovat novinky, volné termíny a aktuality studia na Instagramu.</p>
        <div class="lock-actions">
            <a class="lock-link lock-link-primary" href="<?= \escape($instagramUrl) ?>" target="_blank" rel="noopener noreferrer">Sledovat PP Studio na Instagramu</a>
        </div>
        <?php if ($errorMessage !== ''): ?>
            <div class="lock-error"><?= \escape($errorMessage) ?></div>
        <?php endif; ?>
        <section class="lock-panel" aria-labelledby="lock-panel-title">
            <h2 id="lock-panel-title" class="lock-panel-title">Interní vstup</h2>
            <p class="lock-panel-copy">Pokud máte přístupové heslo, můžete si stránku zobrazit už teď.</p>
            <div class="lock-form-wrap">
                <form method="post" action="<?= \escape($currentUrl) ?>">
                    <input type="hidden" name="_csrf" value="<?= \escape($csrf) ?>">
                    <label for="public-site-password">Heslo</label>
                    <input id="public-site-password" type="password" name="public_site_password" required autocomplete="current-password">
                    <button type="submit" name="public_site_unlock" value="1">Vstoupit na web</button>
                </form>
            </div>
            <div class="lock-note lock-subtle">Po odemknutí zůstane přístup aktivní jen v tomto prohlížeči.</div>
        </section>
    </main>
</body>
</html>
        <?php
        exit;
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     */
    public function requireAccessOrPrompt(array $server, array $post): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->sessionService->start();
        $ip = $this->requestSecurityService->clientIpAddress($server);
        $scope = 'public-lock';
        $username = 'site';
        $error = '';

        if (($server['REQUEST_METHOD'] ?? '') === 'POST' && isset($post['public_site_unlock'])) {
            if (! $this->csrfService->isValid((string) ($post['_csrf'] ?? ''))) {
                $error = 'Platnost formuláře vypršela. Obnovte stránku a zkuste to znovu.';
            } else {
                $rateState = $this->requestSecurityService->loginThrottleState($scope, $ip, $username, 8, 900);
                if ((bool) ($rateState['locked'] ?? false)) {
                    $minutesLeft = (int) ($rateState['minutes_left'] ?? 15);
                    $error = 'Příliš mnoho pokusů. Zkuste to znovu za ' . $minutesLeft . ' min.';
                } else {
                    $password = (string) ($post['public_site_password'] ?? '');
                    if ($this->passwordMatches($password)) {
                        $_SESSION[self::SESSION_KEY] = true;
                        $this->requestSecurityService->loginThrottleReset($scope, $ip, $username);
                        session_regenerate_id(true);
                        header('Location: ' . $this->currentUrl($server));
                        exit;
                    }

                    $failureState = $this->requestSecurityService->loginThrottleRegisterFailure($scope, $ip, $username, 8, 900);
                    if ((bool) ($failureState['locked'] ?? false)) {
                        $minutesLeft = (int) ($failureState['minutes_left'] ?? 15);
                        $error = 'Příliš mnoho pokusů. Zkuste to znovu za ' . $minutesLeft . ' min.';
                    } else {
                        $error = 'Neplatné heslo.';
                    }
                }
            }
        }

        if (! $this->hasAccess()) {
            $this->renderPage($error, $server);
        }
    }

    public function requireAccessOrJsonError(): void
    {
        if (! $this->isLockedForCurrentVisitor()) {
            return;
        }

        http_response_code(423);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'Web je dočasně uzamčen heslem.',
            'locked' => true,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
