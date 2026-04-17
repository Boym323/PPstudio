<?php
declare(strict_types=1);
?>
<main class="wrap<?= ($message_size ?? '') === 'narrow' ? ' is-narrow' : '' ?>">
    <section class="card">
        <h1><?= \PPStudio\Support\ViewHelper::escape((string) ($message_heading ?? 'Dárkový poukaz')) ?></h1>
        <p><?= \PPStudio\Support\ViewHelper::escape((string) ($message ?? 'Odkaz je neplatný nebo expirovaný.')) ?></p>
    </section>
</main>
