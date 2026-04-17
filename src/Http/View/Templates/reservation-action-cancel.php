<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($page_title) ?></title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <div class="page-shell">
        <main class="admin-shell">
            <div class="container admin-login-wrap">
                <div class="admin-card">
                    <p class="eyebrow">Online rezervace</p>
                    <h1><?= escape($message) ?></h1>

                    <?php if ($show_confirm_form): ?>
                        <form method="post" class="admin-form" style="margin-top: 1rem;">
                            <input type="hidden" name="id" value="<?= escape((string) $reservation_id) ?>">
                            <input type="hidden" name="action" value="<?= escape($action) ?>">
                            <input type="hidden" name="exp" value="<?= escape((string) $expires_at) ?>">
                            <input type="hidden" name="nonce" value="<?= escape($nonce) ?>">
                            <input type="hidden" name="sig" value="<?= escape($signature) ?>">
                            <div class="table-actions">
                                <button class="button button-danger button-small" type="submit">Ano, zrušit rezervaci</button>
                                <a class="button button-secondary button-small" href="/index.php#rezervace">Ne, ponechat rezervaci</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="table-actions" style="margin-top: 1rem;">
                            <a class="button button-primary button-small" href="/index.php#rezervace">Zpět na web</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
