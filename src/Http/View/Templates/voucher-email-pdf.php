<?php
declare(strict_types=1);

$view = is_array($__view ?? null) ? $__view : [];
$siteName = (string) ($view['site_name'] ?? \defaultSiteName());
$voucherCode = (string) ($view['voucher_code'] ?? '');
$voucherValueLabel = (string) ($view['voucher_value_label'] ?? '');
$expiresLabel = (string) ($view['expires_label'] ?? 'Bez omezení');
$issuedLabel = (string) ($view['issued_label'] ?? '');
$recipient = (string) ($view['recipient'] ?? '');
$note = (string) ($view['note'] ?? '');
$qrUrl = (string) ($view['qr_url'] ?? '');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title><?= \PPStudio\Support\ViewHelper::escape($siteName) ?> | Dárkový poukaz</title>
    <style>
        @page { size: 210mm 99mm; margin: 0; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #2f231c;
            font-size: 13px;
            line-height: 1.25;
        }
        .page {
            padding: 11mm 12mm 8mm;
            background: #fffaf4;
        }
        .qr-wrap {
            float: right;
            width: 40mm;
            text-align: center;
            margin-left: 8mm;
        }
        .qr {
            width: 34mm;
            height: 34mm;
            border: 1px solid #dbc8b5;
            background: #fff;
            margin: 0 auto 2mm;
            display: block;
        }
        .qr-caption {
            font-size: 10px;
            color: #6f5c50;
            line-height: 1.2;
            margin: 0;
        }
        .brand {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #7a6558;
            font-weight: bold;
            margin-bottom: 2mm;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            margin: 0 0 1mm;
        }
        .value {
            font-size: 30px;
            color: #7f593f;
            font-weight: bold;
            margin: 0 0 2mm;
            line-height: 1.05;
        }
        .row { margin: 0 0 1.4mm; }
        .label {
            display: inline-block;
            min-width: 28mm;
            color: #7a6558;
            font-weight: bold;
        }
        .note {
            margin-top: 2mm;
            padding-top: 1.5mm;
            border-top: 1px dashed #dbc8b5;
            font-size: 11px;
            color: #6f5c50;
            max-height: 10mm;
            overflow: hidden;
        }
        .clear { clear: both; }
    </style>
</head>
<body>
    <div class="page">
        <?php if ($qrUrl !== ''): ?>
            <div class="qr-wrap">
                <img class="qr" src="<?= \PPStudio\Support\ViewHelper::escape($qrUrl) ?>" alt="QR kód poukazu">
                <p class="qr-caption">QR vede na ověření poukazu.</p>
            </div>
        <?php endif; ?>

        <div class="brand"><?= \PPStudio\Support\ViewHelper::escape($siteName) ?></div>
        <h1 class="title">Dárkový poukaz</h1>
        <div class="value"><?= \PPStudio\Support\ViewHelper::escape($voucherValueLabel) ?></div>
        <div class="row"><span class="label">Kód:</span><?= \PPStudio\Support\ViewHelper::escape($voucherCode) ?></div>
        <div class="row"><span class="label">Platnost do:</span><?= \PPStudio\Support\ViewHelper::escape($expiresLabel) ?></div>
        <div class="row"><span class="label">Vydáno:</span><?= \PPStudio\Support\ViewHelper::escape($issuedLabel) ?></div>
        <?php if ($recipient !== ''): ?>
            <div class="row"><span class="label">Příjemce:</span><?= \PPStudio\Support\ViewHelper::escape($recipient) ?></div>
        <?php endif; ?>
        <?php if ($note !== ''): ?>
            <div class="note">Poznámka: <?= \PPStudio\Support\ViewHelper::escape($note) ?></div>
        <?php endif; ?>

        <div class="clear"></div>
    </div>
</body>
</html>
