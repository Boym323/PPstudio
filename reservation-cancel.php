<?php
declare(strict_types=1);

require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/security_events.php';
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/mailer.php';

$dbConfig = require __DIR__ . '/config/database.php';
$emailConfig = require __DIR__ . '/config/email.php';

$reservationId = (int) ($_REQUEST['id'] ?? 0);
$action = trim((string) ($_REQUEST['action'] ?? ''));
$expiresAt = (int) ($_REQUEST['exp'] ?? 0);
$nonce = trim((string) ($_REQUEST['nonce'] ?? ''));
$signature = trim((string) ($_REQUEST['sig'] ?? ''));
$isPost = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
$linkIsValid = $action === 'cancel'
    && isValidReservationActionSignature($emailConfig, $reservationId, $action, $expiresAt, $nonce, $signature);

$pageTitle = defaultSiteName() . ' | Zrušení rezervace';
$message = '';
$messageType = 'info';
$showConfirmForm = false;

if (! $linkIsValid) {
    http_response_code(403);
    $message = 'Odkaz je neplatný nebo expiroval.';
    $messageType = 'error';
    securityEventLog('reservation_customer_cancel_invalid_link', 'reservation_cancel', 'warning', [
        'reservation_id' => $reservationId,
    ]);
} else {
    $connection = @new mysqli(
        $dbConfig['host'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['database']
    );

    if ($connection->connect_errno) {
        http_response_code(500);
        $message = 'Databáze není dostupná.';
        $messageType = 'error';
    } else {
        $connection->set_charset($dbConfig['charset']);
        $siteSettings = loadSiteSettings($connection);
        $reservation = loadReservationDetails($connection, $reservationId);

        if ($reservation === null) {
            http_response_code(404);
            $message = 'Rezervace nebyla nalezena.';
            $messageType = 'error';
        } else {
            $statusBefore = (string) ($reservation['stav'] ?? '');

            if (! $isPost) {
                if ($statusBefore === 'zrusena') {
                    $message = 'Tato rezervace už je zrušena.';
                } else {
                    $message = 'Opravdu chcete zrušit tuto rezervaci?';
                    $showConfirmForm = true;
                }
            } else {
                if (! consumeReservationActionNonce($reservationId, 'cancel', $expiresAt, $nonce)) {
                    http_response_code(403);
                    $message = 'Odkaz už byl použit nebo expiroval.';
                    $messageType = 'error';
                } elseif ($statusBefore === 'zrusena') {
                    $message = 'Rezervace už byla dříve zrušena.';
                } elseif ($statusBefore === 'dokoncena') {
                    $message = 'Rezervace je již dokončená a nelze ji zrušit tímto odkazem.';
                    $messageType = 'error';
                } else {
                    $update = $connection->prepare('UPDATE rezervace SET stav = "zrusena" WHERE id = ? LIMIT 1');
                    if (! $update) {
                        http_response_code(500);
                        $message = 'Rezervaci se nepodařilo zrušit.';
                        $messageType = 'error';
                    } else {
                        $update->bind_param('i', $reservationId);
                        if (! $update->execute()) {
                            http_response_code(500);
                            $message = 'Rezervaci se nepodařilo zrušit.';
                            $messageType = 'error';
                        } else {
                            $reservationAfter = loadReservationDetails($connection, $reservationId);
                            if ($reservationAfter !== null) {
                                sendReservationCancelledEmail($emailConfig, $siteSettings, $reservationAfter);
                            }
                            $message = 'Rezervace byla úspěšně zrušena. Potvrzení jsme poslali i na váš e-mail.';
                            $messageType = 'success';
                            securityEventLog('reservation_customer_cancelled', 'reservation_cancel', 'warning', [
                                'reservation_id' => $reservationId,
                            ]);
                        }
                        $update->close();
                    }
                }
            }
        }

        $connection->close();
    }
}
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
