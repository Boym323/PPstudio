<?php
declare(strict_types=1);

require __DIR__ . '/includes/site/render.php';

renderSitePage([
    'title' => 'Recenze klientek | PP Studio',
    'description' => 'Přečtěte si hodnocení klientek, které navštívily PP Studio.',
    'active_nav' => 'reviews',
    'template' => __DIR__ . '/includes/site/pages/reviews.php',
]);
