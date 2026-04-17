<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/media.php';

$adminConfig = require __DIR__ . '/config/admin_lite.php';
$emailConfig = require __DIR__ . '/config/email.php';

(new \PPStudio\Http\Controller\Admin\AdminLiteApplication($adminConfig, $emailConfig))->handle();
