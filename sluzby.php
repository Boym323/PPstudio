<?php
declare(strict_types=1);

require __DIR__ . '/includes/site/render.php';

renderSitePage([
    'title' => 'Služby | PP Studio',
    'description' => 'Přehled služeb PP Studia a typů ošetření.',
    'active_nav' => 'services',
    'template' => __DIR__ . '/includes/site/pages/services.php',
]);
