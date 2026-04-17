<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Bootstrap/ProjectAutoloader.php';
require_once dirname(__DIR__) . '/src/Bootstrap/LegacyBootstrap.php';

\PPStudio\Bootstrap\LegacyBootstrap::boot(dirname(__DIR__));
