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
$state = $controller->adminAction($_GET);
$message = (string) ($state['message'] ?? 'Požadavek se nepodařilo zpracovat.');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape(defaultSiteName()) ?> | Rezervace</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="page-shell">
        <main class="admin-shell">
            <div class="container admin-login-wrap">
                <div class="admin-card">
                    <p class="eyebrow">Akce rezervace</p>
                    <h1><?= escape($message) ?></h1>
                    <div class="table-actions">
                        <a class="button button-primary button-small" href="admin.php">Přejít do administrace</a>
                        <a class="button button-secondary button-small" href="index.php">Otevřít web</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
