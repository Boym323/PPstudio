<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/antispam.php';
require_once __DIR__ . '/includes/security_events.php';
require __DIR__ . '/includes/availability.php';
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/media.php';
require __DIR__ . '/includes/mailer.php';
require __DIR__ . '/includes/admin/availability_story.php';

$adminConfig = require __DIR__ . '/config/admin.php';
$emailConfig = require __DIR__ . '/config/email.php';

(new \PPStudio\Http\Controller\Admin\AdminApplication($adminConfig, $emailConfig))->handle();
