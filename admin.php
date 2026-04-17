<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';

(new \PPStudio\Http\Controller\Admin\AdminPanelEntryPointApplication(__DIR__))->handleFull();
