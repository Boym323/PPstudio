<?php
declare(strict_types=1);

require __DIR__ . '/includes/site/render.php';

renderSitePage([
    'title' => 'Ceník | PP Studio',
    'description' => 'Aktuální ceník ošetření PP Studia přehledně podle kategorií.',
    'active_nav' => 'pricing',
    'template' => __DIR__ . '/includes/site/pages/pricing.php',
]);
