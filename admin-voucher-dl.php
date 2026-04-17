<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/settings.php';


\PPStudio\Http\Controller\VoucherAdminDownloadApplication::create()
    ->handle($_GET, $_SESSION);
