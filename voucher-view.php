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
$statement->bind_result($id, $code, $originalValue, $remainingValue, $status, $issuedAt, $expiresAt, $recipientName, $note);
$voucher = null;

if ($statement->fetch()) {
    $voucher = [
        'id' => (int) $id,
        'kod' => (string) $code,
        'puvodni_hodnota' => $originalValue !== null ? (float) $originalValue : null,
        'zustatek' => $remainingValue !== null ? (float) $remainingValue : null,
        'status' => (string) $status,
        'issued_at' => (string) $issuedAt,
        'expires_at' => $expiresAt,
        'recipient_name' => (string) $recipientName,
        'note' => (string) $note,
    ];
}

$statement->close();
$connection->close();

$isValid = false;
if (is_array($voucher)) {
    $secret = ppstudioVoucherVerifySecret();
    $isValid = isValidVoucherVerifySignature($secret, (int) ($voucher['id'] ?? 0), (string) ($voucher['kod'] ?? ''), $signature);
}

if (! $isValid || ! is_array($voucher)) {
    http_response_code(403);
    securityEventLog('voucher_view_invalid', 'voucher_view', 'warning', [
        'voucher_id' => $voucherId,
    ]);
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= escape($siteName) ?> | Dárkový poukaz</title>
        <style>
            body { margin: 0; font-family: Georgia, "Times New Roman", serif; background: #f8f2ea; color: #3a2b20; }
            .wrap { max-width: 760px; margin: 44px auto; padding: 0 16px; }
            .card { background: #fffaf4; border: 1px solid #dcc8b5; border-radius: 20px; padding: 26px; }
        </style>
    </head>
    <body>
        <main class="wrap">
            <section class="card">
                <h1>Dárkový poukaz</h1>
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
$expiresAtRaw = trim((string) ($voucher['expires_at'] ?? ''));
$effectiveStatus = $statusRaw;

if ($statusRaw !== 'storno' && $remaining <= 0.0001) {
    $effectiveStatus = 'vycerpan';
}
if ($statusRaw !== 'storno' && $expiresAtRaw !== '' && strtotime($expiresAtRaw . ' 23:59:59') !== false && strtotime($expiresAtRaw . ' 23:59:59') < time()) {
    $effectiveStatus = 'expirovan';
}

$statusLabel = match ($effectiveStatus) {
    'aktivni' => 'Aktivní',
    'vycerpan' => 'Vyčerpán',
    'expirovan' => 'Expirovaný',
    'storno' => 'Storno',
    default => ucfirst($effectiveStatus),
};

$expiresLabel = $expiresAtRaw !== '' ? formatCzechDate($expiresAtRaw) : 'Bez omezení';
$valueLabel = formatPrice($voucher['puvodni_hodnota'] ?? null);
$voucherUrl = buildVoucherViewUrl($siteSettings, (int) ($voucher['id'] ?? 0), (string) ($voucher['kod'] ?? ''), ppstudioVoucherVerifySecret());
$verifyUrl = buildVoucherVerifyUrl($siteSettings, (int) ($voucher['id'] ?? 0), (string) ($voucher['kod'] ?? ''), ppstudioVoucherVerifySecret());
$qrPayload = $voucherUrl !== '' ? $voucherUrl : implode("\n", [
    'PP Studio - darkovy poukaz',
    'Kod: ' . (string) ($voucher['kod'] ?? ''),
    'Hodnota: ' . $valueLabel,
    'Platnost: ' . $expiresLabel,
]);
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($qrPayload);

securityEventLog('voucher_view_ok', 'voucher_view', 'info', [
    'voucher_id' => (int) ($voucher['id'] ?? 0),
    'status' => $effectiveStatus,
]);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($siteName) ?> | Dárkový poukaz</title>
    <style>
        :root {
            --bg: #f7f0e7;
            --card: rgba(255, 251, 246, 0.95);
            --card-soft: rgba(255, 251, 246, 0.78);
            --line: rgba(219, 200, 181, 0.92);
            --text: #35261d;
            --muted: #7a6659;
            --accent: #7a5a43;
            --accent-soft: #efe3d7;
            --shadow: 0 28px 60px rgba(138, 112, 88, 0.16);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Georgia, "Times New Roman", serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(217, 193, 168, 0.18), transparent 34%),
                radial-gradient(circle at top right, rgba(222, 204, 180, 0.18), transparent 28%),
                linear-gradient(180deg, #fbf6f0 0%, var(--bg) 100%);
        }
        .wrap {
            max-width: 980px;
            margin: 0 auto;
            padding: 28px 18px 42px;
        }
        .tools {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 0 18px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
            border: 1px solid transparent;
            cursor: pointer;
            font-family: inherit;
            font-size: 1rem;
        }
        .btn-primary {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 14px 28px rgba(122, 90, 67, 0.18);
        }
        .btn-secondary {
            background: #fff;
            color: var(--text);
            border-color: var(--line);
        }
        .voucher-shell {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 28px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .voucher-hero {
            padding: 34px 34px 28px;
            background:
                radial-gradient(circle at top right, rgba(236, 223, 210, 0.85), transparent 34%),
                linear-gradient(180deg, rgba(255, 252, 248, 0.98), rgba(250, 243, 234, 0.92));
            border-bottom: 1px solid var(--line);
        }
        .eyebrow {
            margin: 0 0 14px;
            font-size: 0.82rem;
            letter-spacing: 0.28rem;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 700;
        }
        h1 {
            margin: 0;
            font-size: clamp(2.6rem, 6vw, 4.5rem);
            line-height: 0.96;
            max-width: 12ch;
        }
        .hero-copy {
            max-width: 38rem;
            margin-top: 18px;
            font-size: 1.15rem;
            line-height: 1.65;
            color: #56483d;
        }
        .voucher-body {
            padding: 28px 34px 34px;
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.9fr);
            gap: 22px;
        }
        .voucher-panel,
        .voucher-card {
            background: var(--card-soft);
            border: 1px solid var(--line);
            border-radius: 22px;
        }
        .voucher-panel {
            padding: 26px 24px;
            display: grid;
            gap: 18px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 14px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-weight: 700;
            border: 1px solid rgba(184, 157, 135, 0.7);
        }
        .voucher-value {
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 1;
            color: var(--accent);
            font-weight: 700;
        }
        .voucher-grid {
            display: grid;
            gap: 14px;
        }
        .voucher-grid-item {
            padding: 16px 18px;
            border: 1px solid rgba(219, 200, 181, 0.88);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.55);
        }
        .voucher-grid-item strong {
            display: block;
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 0.88rem;
            letter-spacing: 0.04rem;
            text-transform: uppercase;
        }
        .voucher-grid-item span {
            font-size: 1.18rem;
            line-height: 1.45;
        }
        .voucher-card {
            padding: 26px 24px;
            display: grid;
            align-content: start;
            gap: 18px;
        }
        .voucher-card h2 {
            margin: 0;
            font-size: 1.8rem;
        }
        .voucher-card p {
            margin: 0;
            font-size: 1rem;
            line-height: 1.7;
            color: #5d5046;
        }
        .voucher-actions {
            display: grid;
            gap: 10px;
            margin-top: 6px;
        }
        .voucher-footnote {
            font-size: 0.94rem;
            color: var(--muted);
        }
        .print-only {
            display: none;
        }
        .voucher-code-box {
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid rgba(214, 194, 175, 0.9);
            background: #fff;
        }
        .voucher-code-box strong {
            display: block;
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 0.82rem;
            letter-spacing: 0.12rem;
            text-transform: uppercase;
        }
        .voucher-code-box span {
            font-size: 1.55rem;
            font-weight: 700;
            letter-spacing: 0.04rem;
        }
        .voucher-print-page {
            width: 210mm;
            height: 99mm;
            margin: 0 auto;
            background: #fffaf4;
            border: 1px solid #dbc8b5;
            border-radius: 12px;
            overflow: hidden;
            grid-template-columns: 1.35fr 0.65fr;
        }
        .voucher-print-main {
            padding: 12mm 13mm 10mm;
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            gap: 4mm;
        }
        .voucher-print-brand {
            font-size: 11pt;
            letter-spacing: .24em;
            text-transform: uppercase;
            color: #7a6558;
            font-weight: 700;
        }
        .voucher-print-title {
            font-size: 25pt;
            line-height: 1.05;
            margin: 0;
            color: #2f231c;
        }
        .voucher-print-value {
            font-size: 34pt;
            color: #7f593f;
            font-weight: 700;
            line-height: 1;
        }
        .voucher-print-meta {
            display: grid;
            gap: 2mm;
            font-size: 12pt;
        }
        .voucher-print-meta-row b {
            display: inline-block;
            min-width: 34mm;
            color: #7a6558;
            font-weight: 700;
        }
        .voucher-print-side {
            border-left: 1px solid #dbc8b5;
            background: linear-gradient(180deg, #f1e5d8 0%, #fffaf4 100%);
            display: grid;
            place-items: center;
            padding: 8mm;
            gap: 3mm;
            text-align: center;
        }
        .voucher-print-qr {
            width: 44mm;
            height: 44mm;
            background: #fff;
            border: 1px solid #dbc8b5;
            border-radius: 8px;
            object-fit: cover;
        }
        .voucher-print-caption {
            font-size: 9.5pt;
            color: #7a6558;
            line-height: 1.25;
        }
        @media print {
            body {
                background: #fff;
            }
            .wrap {
                padding: 0;
                max-width: none;
            }
            .tools {
                display: none !important;
            }
            .screen-only {
                display: none !important;
            }
            .print-only {
                display: grid !important;
            }
            .voucher-print-page {
                margin: 0;
                border: 0;
                border-radius: 0;
            }
            @page {
                size: 210mm 99mm;
                margin: 0;
            }
        }
        @media (max-width: 820px) {
            .voucher-body {
                grid-template-columns: 1fr;
                padding: 20px 18px 24px;
            }
            .voucher-hero {
                padding: 26px 18px 22px;
            }
            h1 {
                max-width: 10ch;
            }
        }
    </style>
</head>
<body>
    <main class="wrap">
        <div class="tools">
            <button class="btn btn-primary" type="button" onclick="window.print()">Tisk / Uložit jako PDF</button>
            <a class="btn btn-secondary" href="/rezervace.php">Přejít na rezervaci</a>
            <?php if ($verifyUrl !== ''): ?>
                <a class="btn btn-secondary" href="<?= escape($verifyUrl) ?>">Ověření poukazu</a>
            <?php endif; ?>
        </div>

        <article class="voucher-shell screen-only">
            <header class="voucher-hero">
                <p class="eyebrow"><?= escape($siteName) ?></p>
                <h1>Dárkový poukaz</h1>
                <p class="hero-copy">
                    Připravili jsme pro vás dárkový poukaz, který můžete využít při rezervaci ve studiu.
                    Pokud vám vyhovuje tištěná verze, stačí nahoře zvolit tisk nebo uložení do PDF.
                </p>
            </header>

            <section class="voucher-body">
                <div class="voucher-panel">
                    <span class="status-badge"><?= escape($statusLabel) ?></span>
                    <div class="voucher-value"><?= escape($valueLabel) ?></div>
                    <div class="voucher-grid">
                        <div class="voucher-grid-item">
                            <strong>Kód poukazu</strong>
                            <span><?= escape((string) ($voucher['kod'] ?? '')) ?></span>
                        </div>
                        <div class="voucher-grid-item">
                            <strong>Platnost do</strong>
                            <span><?= escape($expiresLabel) ?></span>
                        </div>
                    </div>
                </div>

                <aside class="voucher-card">
                    <h2>Jak poukaz využít</h2>
                    <p>Na návštěvě stačí nahlásit kód poukazu. Pokud si přejete poukaz vytisknout nebo předat dál, použijte tlačítko pro tisk v horní části stránky.</p>
                    <div class="voucher-code-box">
                        <strong>Kód pro uplatnění</strong>
                        <span><?= escape((string) ($voucher['kod'] ?? '')) ?></span>
                    </div>
                    <div class="voucher-actions">
                        <button class="btn btn-primary" type="button" onclick="window.print()">Vytisknout poukaz</button>
                        <a class="btn btn-secondary" href="/rezervace.php">Přejít na rezervaci termínu</a>
                    </div>
                    <p class="voucher-footnote">
                        Pokud si chcete ověřit platnost poukazu, můžete použít i samostatnou stránku ověření.
                    </p>
                </aside>
            </section>
        </article>

        <article class="voucher-print-page print-only">
            <section class="voucher-print-main">
                <div class="voucher-print-brand"><?= escape($siteName) ?></div>
                <h1 class="voucher-print-title">Dárkový poukaz</h1>
                <div class="voucher-print-value"><?= escape($valueLabel) ?></div>
                <div class="voucher-print-meta">
                    <div class="voucher-print-meta-row"><b>Kód:</b> <?= escape((string) ($voucher['kod'] ?? '')) ?></div>
                    <div class="voucher-print-meta-row"><b>Platnost do:</b> <?= escape($expiresLabel) ?></div>
                </div>
            </section>
            <aside class="voucher-print-side">
                <img class="voucher-print-qr" src="<?= escape($qrUrl) ?>" alt="QR kód poukazu">
                <div class="voucher-print-caption">
                    QR kód otevře dárkový poukaz<br>
                    pro tisk nebo uplatnění ve studiu.
                </div>
            </aside>
        </article>
    </main>
</body>
</html>
