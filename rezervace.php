<?php
declare(strict_types=1);

require __DIR__ . '/includes/site/render.php';

renderSitePage([
    'title' => 'Rezervace a kontakt | PP Studio',
    'description' => 'Objednejte si termín online a najděte všechny kontaktní informace na jednom místě.',
    'active_nav' => 'reservation',
    'template' => __DIR__ . '/includes/site/pages/reservation.php',
]);
