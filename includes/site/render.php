<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../settings.php';

function renderSitePage(array $config): never
{
    (new \PPStudio\Http\View\SitePageRenderer())->render($config);
}
