<?php
declare(strict_types=1);

$view = is_array($__view ?? null) ? $__view : [];
$messageSize = (string) ($view['message_size'] ?? ($message_size ?? ''));
$messageHeading = (string) ($view['message_heading'] ?? ($message_heading ?? 'Dárkový poukaz'));
$messageText = (string) ($view['message'] ?? ($message ?? 'Odkaz je neplatný nebo expirovaný.'));
?>
<main class="wrap<?= $messageSize === 'narrow' ? ' is-narrow' : '' ?>">
    <section class="card">
        <h1><?= \PPStudio\Support\ViewHelper::escape($messageHeading) ?></h1>
        <p><?= \PPStudio\Support\ViewHelper::escape($messageText) ?></p>
    </section>
</main>
