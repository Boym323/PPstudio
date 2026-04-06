<!DOCTYPE html>
<html lang="cs" class="admin-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přihlášení uživatele | <?= escape(defaultSiteName()) ?></title>
    <?php $adminCssVersion = (string) (@filemtime(__DIR__ . '/../../../assets/css/admin.css') ?: time()); ?>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?= escape($adminCssVersion) ?>">
</head>
<body>
    <div class="page-shell">
        <main class="admin-shell">
            <div class="container admin-login-wrap">
                <div class="admin-card">
                    <p class="eyebrow">Uživatelské rozhraní</p>
                    <h1>Přihlášení do provozní správy</h1>
                    <?php if ($loginError !== ''): ?>
                        <div class="alert alert-error"><?= escape($loginError) ?></div>
                    <?php endif; ?>
                    <form method="post" class="admin-form">
                        <?= csrfInputField() ?>
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
