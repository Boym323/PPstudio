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

$reservationId = (int) ($_GET['id'] ?? 0);
$action = trim((string) ($_GET['action'] ?? ''));
$expiresAt = (int) ($_GET['exp'] ?? 0);
$nonce = trim((string) ($_GET['nonce'] ?? ''));
$signature = trim((string) ($_GET['sig'] ?? ''));
$allowedActions = ['confirm', 'cancel'];
$message = 'Požadavek se nepodařilo zpracovat.';

if (
    ! in_array($action, $allowedActions, true)
    || ! isValidReservationActionSignature($emailConfig, $reservationId, $action, $expiresAt, $nonce, $signature)
    || ! consumeReservationActionNonce($reservationId, $action, $expiresAt, $nonce)
) {
    http_response_code(403);
    $message = 'Odkaz je neplatný nebo expiroval.';
    securityEventLog('reservation_action_invalid_link', 'reservation_action', 'warning', [
        'reservation_id' => $reservationId,
        'action' => $action,
        'expires_at' => $expiresAt,
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
    } else {
        $connection->set_charset($dbConfig['charset']);
        $siteSettings = loadSiteSettings($connection);
        $reservationBefore = loadReservationDetails($connection, $reservationId);

        if ($reservationBefore === null) {
            http_response_code(404);
            $message = 'Rezervace nebyla nalezena.';
        } else {
            $newStatus = $action === 'confirm' ? 'potvrzena' : 'zrusena';
            $statement = $connection->prepare('UPDATE rezervace SET stav = ? WHERE id = ?');

            if ($statement) {
                $statement->bind_param('si', $newStatus, $reservationId);
                if ($statement->execute()) {
                    $reservationAfter = loadReservationDetails($connection, $reservationId);
                    if ($reservationAfter !== null) {
                        if ($action === 'confirm' && (string) ($reservationBefore['stav'] ?? '') !== 'potvrzena') {
                            sendReservationConfirmedEmail($emailConfig, $siteSettings, $reservationAfter);
                            $message = 'Rezervace byla potvrzena a klientce odešel potvrzovací e-mail.';
                            securityEventLog('reservation_action_confirmed', 'reservation_action', 'info', [
                                'reservation_id' => $reservationId,
                            ]);
                        } elseif ($action === 'cancel' && (string) ($reservationBefore['stav'] ?? '') !== 'zrusena') {
                            sendReservationCancelledEmail($emailConfig, $siteSettings, $reservationAfter);
                            $message = 'Rezervace byla zrušena a klientce odešlo oznámení.';
                            securityEventLog('reservation_action_cancelled', 'reservation_action', 'warning', [
                                'reservation_id' => $reservationId,
                            ]);
                        } else {
                            $message = 'Rezervace už byla v tomto stavu.';
                        }
                    }
                }
                $statement->close();
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
    <title><?= escape(SITE_NAME) ?> | Rezervace</title>
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
