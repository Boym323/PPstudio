<?php
declare(strict_types=1);

require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/settings.php';

startSecureSession();

$isAdmin = (bool) ($_SESSION['ppstudio_admin_authenticated'] ?? false);
$isAdminLite = (bool) ($_SESSION['ppstudio_admin_lite_authenticated'] ?? false);
if (! $isAdmin && ! $isAdminLite) {
    http_response_code(401);
    echo 'Nejste přihlášeni.';
    exit;
}

$voucherId = (int) ($_GET['id'] ?? 0);
if ($voucherId <= 0) {
    http_response_code(422);
    echo 'Neplatné ID poukazu.';
    exit;
}

$dbConfig = require __DIR__ . '/config/database.php';
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
    'SELECT id, kod, puvodni_hodnota, zustatek, status, issued_at, expires_at, recipient_name, note
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

if (! is_array($voucher)) {
    http_response_code(404);
    echo 'Poukaz nebyl nalezen.';
    exit;
}

$code = (string) ($voucher['kod'] ?? '');
$recipient = trim((string) ($voucher['recipient_name'] ?? ''));
$originalValue = (float) ($voucher['puvodni_hodnota'] ?? 0);
$expiresAt = trim((string) ($voucher['expires_at'] ?? ''));
$expiresLabel = $expiresAt !== '' ? formatCzechDate($expiresAt) : 'Bez omezení';
$issuedLabel = formatCzechDateTime((string) ($voucher['issued_at'] ?? ''));
$note = trim((string) ($voucher['note'] ?? ''));

$verifyUrl = buildVoucherVerifyUrl($siteSettings, $voucherId, $code, ppstudioVoucherVerifySecret());
$qrPayload = $verifyUrl !== '' ? $verifyUrl : implode("\n", [
    'PP Studio - darkovy poukaz',
    'Kod: ' . $code,
    'Hodnota: ' . number_format($originalValue, 0, ',', ' ') . ' Kc',
    'Platnost: ' . $expiresLabel,
]);
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($qrPayload);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($siteName) ?> | DL poukaz <?= escape($code) ?></title>
    <style>
        :root {
            --bg: #f6f1ea;
            --card: #fffaf4;
            --text: #2f231c;
            --muted: #7a6558;
            --accent: #7f593f;
            --line: #dbc8b5;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: var(--bg); color: var(--text); font-family: Georgia, "Times New Roman", serif; }
        .screen-tools {
            max-width: 920px;
            margin: 18px auto 10px;
            padding: 0 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .screen-tools button, .screen-tools a {
            border: 1px solid var(--accent);
            border-radius: 999px;
            padding: 9px 14px;
            background: #fff;
            color: var(--text);
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }
        .voucher-page {
            width: 210mm;
            height: 99mm;
            margin: 8px auto 22px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1.35fr 0.65fr;
        }
        .voucher-main {
            padding: 12mm 13mm 10mm;
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            gap: 4mm;
        }
        .brand {
            font-size: 11pt;
            letter-spacing: .24em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 700;
        }
        .title {
            font-size: 25pt;
            line-height: 1.05;
            margin: 0;
            color: var(--text);
        }
        .value {
            font-size: 34pt;
            color: var(--accent);
            font-weight: 700;
            line-height: 1;
        }
        .meta {
            display: grid;
            gap: 2mm;
            font-size: 12pt;
        }
        .meta-row b {
            display: inline-block;
            min-width: 34mm;
            color: var(--muted);
            font-weight: 700;
        }
        .note {
            font-size: 10.5pt;
            color: var(--muted);
            border-top: 1px dashed var(--line);
            padding-top: 2.6mm;
            margin-top: 1.5mm;
            line-height: 1.35;
            max-height: 24mm;
            overflow: hidden;
        }
        .voucher-side {
            border-left: 1px solid var(--line);
            background: linear-gradient(180deg, #f1e5d8 0%, #fffaf4 100%);
            display: grid;
            place-items: center;
            padding: 8mm;
            gap: 3mm;
            text-align: center;
        }
        .qr {
            width: 44mm;
            height: 44mm;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            object-fit: cover;
        }
        .qr-caption {
            font-size: 9.5pt;
            color: var(--muted);
            line-height: 1.25;
        }

        @page {
            size: 210mm 99mm;
            margin: 0;
        }
        @media print {
            html, body {
                background: #fff;
                width: 210mm;
                height: 99mm;
            }
            .screen-tools { display: none !important; }
            .voucher-page {
                margin: 0;
                border: 0;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="screen-tools">
        <button type="button" onclick="window.print()">Tisk / Uložit jako PDF</button>
        <a href="/admin.php?tab=poukazy#poukazy">Zpět do poukazů</a>
    </div>

    <article class="voucher-page">
        <section class="voucher-main">
            <div class="brand"><?= escape($siteName) ?></div>
            <h1 class="title">Dárkový poukaz</h1>
            <div class="value"><?= escape(formatPrice($originalValue)) ?></div>
            <div class="meta">
                <div class="meta-row"><b>Kód:</b> <?= escape($code) ?></div>
                <div class="meta-row"><b>Platnost do:</b> <?= escape($expiresLabel) ?></div>
                <div class="meta-row"><b>Vydáno:</b> <?= escape($issuedLabel) ?></div>
                <?php if ($recipient !== ''): ?>
                    <div class="meta-row"><b>Příjemce:</b> <?= escape($recipient) ?></div>
                <?php endif; ?>
            </div>
            <?php if ($note !== ''): ?>
                <div class="note">Poznámka: <?= escape($note) ?></div>
            <?php endif; ?>
        </section>
        <aside class="voucher-side">
            <img class="qr" src="<?= escape($qrUrl) ?>" alt="QR kód poukazu">
            <div class="qr-caption">
                QR kód obsahuje identifikaci poukazu<br>
                pro rychlou obsluhu ve studiu.
            </div>
        </aside>
    </article>
</body>
</html>
