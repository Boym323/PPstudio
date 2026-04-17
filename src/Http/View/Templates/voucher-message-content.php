<?php
declare(strict_types=1);
?>
<main class="wrap<?= ($message_size ?? '') === 'narrow' ? ' is-narrow' : '' ?>">
    <section class="card">
        <h1><?= escape((string) ($message_heading ?? 'Dárkový poukaz')) ?></h1>
        <p><?= escape((string) ($message ?? 'Odkaz je neplatný nebo expirovaný.')) ?></p>
    </section>
</main>
