<?php
declare(strict_types=1);

require __DIR__ . '/includes/site/render.php';

renderSitePage([
    'title' => 'Prostory studia | PP Studio',
    'description' => 'Prohlédněte si prostory PP Studia.',
    'active_nav' => 'spaces',
    'template' => __DIR__ . '/includes/site/pages/spaces.php',
]);
