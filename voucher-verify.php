<?php
declare(strict_types=1);

require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/security_events.php';
require __DIR__ . '/includes/settings.php';

startSecureSession();

$dbConfig = require __DIR__ . '/config/database.php';
$voucherId = (int) ($_GET['v'] ?? 0);
$signature = trim((string) ($_GET['sig'] ?? ''));

$connection = @new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['database']
);

if ($connection->connect_errno) {
    http_response_code(500);
    echo 'Databáze není dostupná.';
    exit;
}

$connection->set_charset($dbConfig['charset']);
$siteSettings = loadSiteSettings($connection);
$siteName = setting($siteSettings, 'site_name', defaultSiteName());

$statement = $connection->prepare(
    'SELECT id, kod, puvodni_hodnota, zustatek, status, issued_at, expires_at, recipient_name
     FROM poukazy
     WHERE id = ?
     LIMIT 1'
);

if (! $statement) {
    $connection->close();
    http_response_code(500);
    echo 'Poukaz se nepodařilo načíst.';
    exit;
}

$statement->bind_param('i', $voucherId);
$statement->execute();
$result = $statement->get_result();
$voucher = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
$statement->close();
$connection->close();

$isValid = false;
$isAdmin = (bool) ($_SESSION['ppstudio_admin_authenticated'] ?? false);
$isAdminLite = (bool) ($_SESSION['ppstudio_admin_lite_authenticated'] ?? false);
$isPrivileged = $isAdmin || $isAdminLite;

if (is_array($voucher)) {
    $secret = ppstudioVoucherVerifySecret();
    $isValid = isValidVoucherVerifySignature($secret, (int) ($voucher['id'] ?? 0), (string) ($voucher['kod'] ?? ''), $signature);
}

if (! $isValid || ! is_array($voucher)) {
    http_response_code(403);
    securityEventLog('voucher_verify_invalid', 'voucher_verify', 'warning', [
        'voucher_id' => $voucherId,
    ]);
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= escape($siteName) ?> | Ověření poukazu</title>
        <style>
            body { margin: 0; font-family: Georgia, "Times New Roman", serif; background: #f8f2ea; color: #3a2b20; }
            .wrap { max-width: 700px; margin: 44px auto; padding: 0 14px; }
            .card { background: #fffaf4; border: 1px solid #dcc8b5; border-radius: 14px; padding: 22px; }
        </style>
    </head>
    <body>
    <main class="wrap">
        <section class="card">
            <h1>Ověření poukazu</h1>
            <p>Odkaz je neplatný nebo expirovaný.</p>
        </section>
    </main>
    </body>
    </html>
    <?php
    exit;
}

$statusRaw = (string) ($voucher['status'] ?? 'aktivni');
$remaining = (float) ($voucher['zustatek'] ?? 0);
$expiresAt = trim((string) ($voucher['expires_at'] ?? ''));
$effectiveStatus = $statusRaw;

if ($statusRaw !== 'storno' && $remaining <= 0.0001) {
    $effectiveStatus = 'vycerpan';
}
if ($statusRaw !== 'storno' && $expiresAt !== '' && strtotime($expiresAt . ' 23:59:59') !== false && strtotime($expiresAt . ' 23:59:59') < time()) {
    $effectiveStatus = 'expirovan';
}

$statusLabel = match ($effectiveStatus) {
    'aktivni' => 'Aktivní',
    'vycerpan' => 'Vyčerpán',
    'expirovan' => 'Expirovaný',
    'storno' => 'Storno',
    default => ucfirst($effectiveStatus),
};

securityEventLog('voucher_verify_ok', 'voucher_verify', 'info', [
    'voucher_id' => (int) ($voucher['id'] ?? 0),
    'status' => $effectiveStatus,
    'privileged_view' => $isPrivileged ? 1 : 0,
]);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($siteName) ?> | Ověření poukazu</title>
    <style>
        body { margin: 0; font-family: Georgia, "Times New Roman", serif; background: #f8f2ea; color: #3a2b20; }
        .wrap { max-width: 860px; margin: 34px auto; padding: 0 14px; }
        .card { background: #fffaf4; border: 1px solid #dcc8b5; border-radius: 14px; padding: 20px; }
        .eyebrow { margin: 0; font-size: 12px; letter-spacing: .18em; text-transform: uppercase; color: #7a6558; }
        .status { display: inline-block; padding: 6px 11px; border-radius: 999px; border: 1px solid #c8b29f; background: #efe4d8; font-weight: 700; margin-top: 6px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 16px; margin-top: 14px; }
        .row b { display: block; color: #7a6558; font-size: 13px; margin-bottom: 2px; }
        .tools { margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { border: 1px solid #7f593f; border-radius: 999px; padding: 9px 14px; text-decoration: none; color: #2f231c; font-weight: 700; background: #fff; }
        @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main class="wrap">
    <section class="card">
        <p class="eyebrow"><?= escape($siteName) ?></p>
        <h1>Ověření dárkového poukazu</h1>
        <span class="status"><?= escape($statusLabel) ?></span>

        <div class="grid">
            <div class="row">
                <b>Kód</b>
                <span><?= escape((string) ($voucher['kod'] ?? '')) ?></span>
            </div>
            <div class="row">
                <b>Platnost do</b>
                <span><?= escape($expiresAt !== '' ? formatCzechDate($expiresAt) : 'Bez omezení') ?></span>
            </div>
            <div class="row">
                <b>Původní hodnota</b>
                <span><?= escape(formatPrice($voucher['puvodni_hodnota'] ?? null)) ?></span>
            </div>
            <?php if ($isPrivileged): ?>
                <div class="row">
                    <b>Aktuální zůstatek</b>
                    <span><?= escape(formatPrice($voucher['zustatek'] ?? null)) ?></span>
                </div>
                <div class="row">
                    <b>Příjemce</b>
                    <span><?= escape((string) (($voucher['recipient_name'] ?? '') !== '' ? $voucher['recipient_name'] : 'Neuvedeno')) ?></span>
                </div>
                <div class="row">
                    <b>Vydáno</b>
                    <span><?= escape(formatCzechDateTime((string) ($voucher['issued_at'] ?? ''))) ?></span>
                </div>
            <?php else: ?>
                <div class="row">
                    <b>Informace</b>
                    <span>Pro plný detail poukazu se přihlaste do administrace.</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="tools">
            <a class="btn" href="/rezervace.php">Přejít na rezervaci termínu</a>
            <a class="btn" href="/index.php">Přejít na hlavní stránku</a>
        </div>
    </section>
</main>
</body>
</html>
