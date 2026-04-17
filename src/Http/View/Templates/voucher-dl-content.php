<?php
declare(strict_types=1);
?>
<div class="screen-tools">
    <button type="button" onclick="window.print()">Tisk / Uložit jako PDF</button>
    <a href="/admin.php?tab=poukazy#poukazy">Zpět do poukazů</a>
</div>

<article class="voucher-page">
    <section class="voucher-main">
        <div class="brand"><?= escape($site_name) ?></div>
        <h1 class="title">Dárkový poukaz</h1>
        <div class="value"><?= escape((string) $original_value_label) ?></div>
        <div class="meta">
            <div class="meta-row"><b>Kód:</b> <?= escape((string) $code) ?></div>
            <div class="meta-row"><b>Platnost do:</b> <?= escape((string) $expires_label) ?></div>
            <div class="meta-row"><b>Vydáno:</b> <?= escape((string) $issued_label) ?></div>
            <?php if (($recipient ?? '') !== ''): ?>
                <div class="meta-row"><b>Příjemce:</b> <?= escape((string) $recipient) ?></div>
            <?php endif; ?>
        </div>
        <?php if (($note ?? '') !== ''): ?>
            <div class="note">Poznámka: <?= escape((string) $note) ?></div>
        <?php endif; ?>
    </section>
    <aside class="voucher-side">
        <img class="qr" src="<?= escape((string) $qr_url) ?>" alt="QR kód poukazu">
        <div class="qr-caption">
            QR kód obsahuje odkaz na ověření poukazu<br>
            pro rychlou obsluhu ve studiu.
        </div>
    </aside>
</article>
