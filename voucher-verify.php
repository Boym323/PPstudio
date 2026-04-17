<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';

\PPStudio\Http\Controller\VoucherPublicApplication::create(
    ppstudioSecurityFacade()->sessionService(),
    ppstudioSecurityFacade()->requestSecurityService()
)->handleVerify($_GET);
