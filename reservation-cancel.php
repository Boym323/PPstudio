<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/security_events.php';
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/mailer.php';

$emailConfig = require __DIR__ . '/config/email.php';
$controller = \PPStudio\Http\Controller\ReservationActionController::create($emailConfig);
$state = $controller->customerCancel($_REQUEST, $_SERVER);

$pageTitle = defaultSiteName() . ' | Zrušení rezervace';
$reservationId = (int) ($_REQUEST['id'] ?? 0);
$action = trim((string) ($_REQUEST['action'] ?? ''));
$expiresAt = (int) ($_REQUEST['exp'] ?? 0);
$nonce = trim((string) ($_REQUEST['nonce'] ?? ''));
$signature = trim((string) ($_REQUEST['sig'] ?? ''));
$message = (string) ($state['message'] ?? '');
$messageType = (string) ($state['message_type'] ?? 'info');
$showConfirmForm = (bool) ($state['show_confirm_form'] ?? false);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="page-shell">
        <main class="admin-shell">
            <div class="container admin-login-wrap">
                <div class="admin-card">
                    <p class="eyebrow">Online rezervace</p>
                    <h1><?= escape($message) ?></h1>

                    <?php if ($showConfirmForm): ?>
                        <form method="post" class="admin-form" style="margin-top: 1rem;">
                            <input type="hidden" name="id" value="<?= escape((string) $reservationId) ?>">
                            <input type="hidden" name="action" value="<?= escape($action) ?>">
                            <input type="hidden" name="exp" value="<?= escape((string) $expiresAt) ?>">
                            <input type="hidden" name="nonce" value="<?= escape($nonce) ?>">
                            <input type="hidden" name="sig" value="<?= escape($signature) ?>">
                            <div class="table-actions">
                                <button class="button button-danger button-small" type="submit">Ano, zrušit rezervaci</button>
                                <a class="button button-secondary button-small" href="index.php#rezervace">Ne, ponechat rezervaci</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="table-actions" style="margin-top: 1rem;">
                            <a class="button button-primary button-small" href="index.php#rezervace">Zpět na web</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
