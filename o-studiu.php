<?php
declare(strict_types=1);

require __DIR__ . '/includes/site/render.php';

renderSitePage([
    'title' => 'O mně | PP Studio',
    'description' => 'Poznejte příběh a přístup Pavlíny Pomykalové v PP Studiu.',
    'active_nav' => 'about',
    'template' => __DIR__ . '/includes/site/pages/about.php',
]);
