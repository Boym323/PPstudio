<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="cs" class="admin-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \PPStudio\Support\ViewHelper::escape($pageTitle) ?> | <?= \PPStudio\Support\ViewHelper::escape(\defaultSiteName()) ?></title>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= \PPStudio\Support\ViewHelper::escape($adminCssVersion) ?>">
</head>
<body>
    <div class="page-shell">
        <main class="admin-shell">
            <div class="container admin-login-wrap">
                <div class="admin-card">
                    <p class="eyebrow"><?= \PPStudio\Support\ViewHelper::escape($eyebrow) ?></p>
                    <h1><?= \PPStudio\Support\ViewHelper::escape($heading) ?></h1>
                    <?php if ($loginError !== ''): ?>
                        <div class="alert alert-error"><?= \PPStudio\Support\ViewHelper::escape($loginError) ?></div>
                    <?php endif; ?>
                    <form method="post" class="admin-form">
                        <?= \csrfInputField() ?>
                        <label>
                            <span>Uživatelské jméno</span>
                            <input type="text" name="username" required>
                        </label>
                        <label>
                            <span>Heslo</span>
                            <input type="password" name="password" required>
                        </label>
                        <button class="button button-primary" type="submit" name="admin_login" value="1">Přihlásit se</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
