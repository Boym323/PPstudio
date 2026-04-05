<?php
declare(strict_types=1);

require __DIR__ . '/includes/site/render.php';

renderSitePage([
    'title' => 'PP Studio - Kosmetický salon Zlín',
    'description' => 'PP Studio - moderní kosmetický salon v Interhotelu Zlín. Profesionální péče o pleť a individuální přístup.',
    'active_nav' => 'home',
    'template' => __DIR__ . '/includes/site/pages/home.php',
]);
