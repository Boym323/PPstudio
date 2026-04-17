<?php
declare(strict_types=1);
?>
<main class="wrap">
    <div class="tools">
        <button class="btn btn-primary" type="button" onclick="window.print()">Tisk / Uložit jako PDF</button>
        <a class="btn btn-secondary" href="/rezervace.php">Přejít na rezervaci</a>
        <?php if (($verify_url ?? '') !== ''): ?>
            <a class="btn btn-secondary" href="<?= \PPStudio\Support\ViewHelper::escape((string) $verify_url) ?>">Ověření poukazu</a>
        <?php endif; ?>
    </div>

    <article class="voucher-shell screen-only">
        <header class="voucher-hero">
            <p class="eyebrow"><?= \PPStudio\Support\ViewHelper::escape($site_name) ?></p>
            <h1>Dárkový poukaz</h1>
            <p class="hero-copy">
                Připravili jsme pro vás dárkový poukaz, který můžete využít při rezervaci ve studiu.
                Pokud vám vyhovuje tištěná verze, stačí nahoře zvolit tisk nebo uložení do PDF.
            </p>
        </header>

        <section class="voucher-body">
            <div class="voucher-panel">
                <span class="status-badge"><?= \PPStudio\Support\ViewHelper::escape((string) $status_label) ?></span>
                <div class="voucher-value"><?= \PPStudio\Support\ViewHelper::escape((string) $value_label) ?></div>
                <div class="voucher-grid">
                    <div class="voucher-grid-item">
                        <strong>Kód poukazu</strong>
                        <span><?= \PPStudio\Support\ViewHelper::escape((string) ($voucher['kod'] ?? '')) ?></span>
                    </div>
                    <div class="voucher-grid-item">
                        <strong>Platnost do</strong>
                        <span><?= \PPStudio\Support\ViewHelper::escape((string) $expires_label) ?></span>
                    </div>
                </div>
            </div>

            <aside class="voucher-card">
                <h2>Jak poukaz využít</h2>
                <p>Na návštěvě stačí nahlásit kód poukazu. Pokud si přejete poukaz vytisknout nebo předat dál, použijte tlačítko pro tisk v horní části stránky.</p>
                <div class="voucher-code-box">
                    <strong>Kód pro uplatnění</strong>
                    <span><?= \PPStudio\Support\ViewHelper::escape((string) ($voucher['kod'] ?? '')) ?></span>
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
            <div class="voucher-print-brand"><?= \PPStudio\Support\ViewHelper::escape($site_name) ?></div>
            <h1 class="voucher-print-title">Dárkový poukaz</h1>
            <div class="voucher-print-value"><?= \PPStudio\Support\ViewHelper::escape((string) $value_label) ?></div>
            <div class="voucher-print-meta">
                <div class="voucher-print-meta-row"><b>Kód:</b> <?= \PPStudio\Support\ViewHelper::escape((string) ($voucher['kod'] ?? '')) ?></div>
                <div class="voucher-print-meta-row"><b>Platnost do:</b> <?= \PPStudio\Support\ViewHelper::escape((string) $expires_label) ?></div>
            </div>
        </section>
        <aside class="voucher-print-side">
            <img class="voucher-print-qr" src="<?= \PPStudio\Support\ViewHelper::escape((string) $qr_url) ?>" alt="QR kód poukazu">
            <div class="voucher-print-caption">
                QR kód otevře dárkový poukaz<br>
                pro tisk nebo uplatnění ve studiu.
            </div>
        </aside>
    </article>
</main>
