<?php
declare(strict_types=1);

$view = is_array($__view ?? null) ? $__view : [];
$siteName = (string) ($view['site_name'] ?? ($site_name ?? \defaultSiteName()));
$originalValueLabel = (string) ($view['original_value_label'] ?? ($original_value_label ?? ''));
$code = (string) ($view['code'] ?? ($code ?? ''));
$expiresLabel = (string) ($view['expires_label'] ?? ($expires_label ?? ''));
$issuedLabel = (string) ($view['issued_label'] ?? ($issued_label ?? ''));
$recipient = (string) ($view['recipient'] ?? ($recipient ?? ''));
$note = (string) ($view['note'] ?? ($note ?? ''));
$qrUrl = (string) ($view['qr_url'] ?? ($qr_url ?? ''));
?>
<div class="screen-tools">
    <button type="button" onclick="window.print()">Tisk / Uložit jako PDF</button>
    <a href="/admin.php?tab=poukazy#poukazy">Zpět do poukazů</a>
</div>

<article class="voucher-page">
    <section class="voucher-main">
        <div class="brand"><?= \PPStudio\Support\ViewHelper::escape($siteName) ?></div>
        <h1 class="title">Dárkový poukaz</h1>
        <div class="value"><?= \PPStudio\Support\ViewHelper::escape($originalValueLabel) ?></div>
        <div class="meta">
            <div class="meta-row"><b>Kód:</b> <?= \PPStudio\Support\ViewHelper::escape($code) ?></div>
            <div class="meta-row"><b>Platnost do:</b> <?= \PPStudio\Support\ViewHelper::escape($expiresLabel) ?></div>
            <div class="meta-row"><b>Vydáno:</b> <?= \PPStudio\Support\ViewHelper::escape($issuedLabel) ?></div>
            <?php if ($recipient !== ''): ?>
                <div class="meta-row"><b>Příjemce:</b> <?= \PPStudio\Support\ViewHelper::escape($recipient) ?></div>
            <?php endif; ?>
        </div>
        <?php if ($note !== ''): ?>
            <div class="note">Poznámka: <?= \PPStudio\Support\ViewHelper::escape($note) ?></div>
        <?php endif; ?>
    </section>
    <aside class="voucher-side">
        <img class="qr" src="<?= \PPStudio\Support\ViewHelper::escape($qrUrl) ?>" alt="QR kód poukazu">
        <div class="qr-caption">
            QR kód obsahuje odkaz na ověření poukazu<br>
            pro rychlou obsluhu ve studiu.
        </div>
    </aside>
</article>
