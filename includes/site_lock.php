<?php
declare(strict_types=1);

function ppstudioPublicLockEnabled(): bool
{
    $value = strtolower(trim((string) ppstudioEnv('PPSTUDIO_PUBLIC_LOCK_ENABLED', '0')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function ppstudioPublicLockSessionKey(): string
{
    return 'ppstudio_public_lock_passed';
}

function ppstudioPublicLockHasAccess(): bool
{
    if (! ppstudioPublicLockEnabled()) {
        return true;
    }

    startSecureSession();
    return (bool) ($_SESSION[ppstudioPublicLockSessionKey()] ?? false);
}

function ppstudioPublicLockPasswordMatches(string $password): bool
{
    $password = trim($password);
    if ($password === '') {
        return false;
    }

    $hash = trim((string) ppstudioEnv('PPSTUDIO_PUBLIC_LOCK_PASSWORD_HASH', ''));
    if ($hash !== '') {
        return password_verify($password, $hash);
    }

    $plain = (string) ppstudioEnv('PPSTUDIO_PUBLIC_LOCK_PASSWORD', '');
    return $plain !== '' && hash_equals($plain, $password);
}

function ppstudioPublicLockCurrentUrl(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    return $uri !== '' ? $uri : '/';
}

function ppstudioPublicLockRenderPage(string $errorMessage = ''): never
{
    $siteName = trim((string) ppstudioEnv('PPSTUDIO_SITE_NAME', 'PP Studio'));
    $csrf = getCsrfToken();
    $currentUrl = ppstudioPublicLockCurrentUrl();

    http_response_code(401);
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= escape($siteName) ?> | Dočasně uzamčeno</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            font-family: Georgia, "Times New Roman", serif;
            background: linear-gradient(180deg, #f8f1e8 0%, #f3e7d9 100%);
            color: #4a3529;
        }
        .lock-card {
            width: min(460px, 100%);
            background: #fffaf4;
            border: 1px solid #dcc4aa;
            border-radius: 16px;
            padding: 22px 20px;
            box-shadow: 0 14px 34px rgba(59, 40, 27, .12);
        }
        h1 { margin: 0 0 8px 0; font-size: 1.7rem; line-height: 1.2; }
        p { margin: 0 0 14px 0; color: #6e5f52; }
        label { display: block; margin: 10px 0 8px 0; font-weight: 700; }
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
    </style>
</head>
<body>
    <main class="lock-card">
        <h1>Web je dočasně uzamčen</h1>
        <p>Stránka je zatím dostupná pouze na heslo.</p>
        <?php if ($errorMessage !== ''): ?>
            <div class="lock-error"><?= escape($errorMessage) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= escape($currentUrl) ?>">
            <input type="hidden" name="_csrf" value="<?= escape($csrf) ?>">
            <label for="public-site-password">Heslo</label>
            <input id="public-site-password" type="password" name="public_site_password" required autocomplete="current-password">
            <button type="submit" name="public_site_unlock" value="1">Odemknout web</button>
        </form>
        <div class="lock-note">Po odemknutí zůstane přístup aktivní jen v tomto prohlížeči.</div>
    </main>
</body>
</html>
    <?php
    exit;
}

function requirePublicSiteAccessOrPrompt(): void
{
    if (! ppstudioPublicLockEnabled()) {
        return;
    }

    startSecureSession();
    $ip = getClientIpAddress();
    $scope = 'public-lock';
    $username = 'site';
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['public_site_unlock'])) {
        if (! isValidCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
            $error = 'Platnost formuláře vypršela. Obnovte stránku a zkuste to znovu.';
        } else {
            $rateState = ppstudioLoginThrottleState($scope, $ip, $username, 8, 900);
            if ((bool) ($rateState['locked'] ?? false)) {
                $minutesLeft = (int) ($rateState['minutes_left'] ?? 15);
                $error = 'Příliš mnoho pokusů. Zkuste to znovu za ' . $minutesLeft . ' min.';
            } else {
                $password = (string) ($_POST['public_site_password'] ?? '');
                if (ppstudioPublicLockPasswordMatches($password)) {
                    $_SESSION[ppstudioPublicLockSessionKey()] = true;
                    ppstudioLoginThrottleReset($scope, $ip, $username);
                    session_regenerate_id(true);
                    header('Location: ' . ppstudioPublicLockCurrentUrl());
                    exit;
                }

                $failureState = ppstudioLoginThrottleRegisterFailure($scope, $ip, $username, 8, 900);
                if ((bool) ($failureState['locked'] ?? false)) {
                    $minutesLeft = (int) ($failureState['minutes_left'] ?? 15);
                    $error = 'Příliš mnoho pokusů. Zkuste to znovu za ' . $minutesLeft . ' min.';
                } else {
                    $error = 'Neplatné heslo.';
                }
            }
        }
    }

    if (! ppstudioPublicLockHasAccess()) {
        ppstudioPublicLockRenderPage($error);
    }
}

function requirePublicSiteAccessOrJsonError(): void
{
    if (! ppstudioPublicLockEnabled() || ppstudioPublicLockHasAccess()) {
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
