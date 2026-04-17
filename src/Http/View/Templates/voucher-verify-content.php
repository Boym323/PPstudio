<?php
declare(strict_types=1);
?>
<main class="wrap">
    <section class="card">
        <p class="eyebrow"><?= escape($site_name) ?></p>
        <h1>Ověření dárkového poukazu</h1>
        <span class="status"><?= escape((string) $status_label) ?></span>

        <div class="grid">
            <div class="row">
                <b>Kód</b>
                <span><?= escape((string) ($voucher['kod'] ?? '')) ?></span>
            </div>
            <div class="row">
                <b>Platnost do</b>
                <span><?= escape((string) $expires_label) ?></span>
            </div>
            <div class="row">
                <b>Původní hodnota</b>
                <span><?= escape((string) $original_value_label) ?></span>
            </div>
            <?php if ($is_privileged): ?>
                <div class="row">
                    <b>Aktuální zůstatek</b>
                    <span><?= escape((string) $remaining_value_label) ?></span>
                </div>
                <div class="row">
                    <b>Příjemce</b>
                    <span><?= escape((string) (($voucher['recipient_name'] ?? '') !== '' ? $voucher['recipient_name'] : 'Neuvedeno')) ?></span>
                </div>
                <div class="row">
                    <b>Vydáno</b>
                    <span><?= escape((string) $issued_at_label) ?></span>
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
