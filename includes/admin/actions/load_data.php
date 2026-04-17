<?php
declare(strict_types=1);

$adminRoot = dirname(__DIR__, 3);
$__ppstudioAdminDataLoader = new \PPStudio\Http\Controller\Admin\AdminDataLoader(
    $adminRoot,
    new \PPStudio\Service\MailerIntegrationService(is_array($emailConfig ?? null) ? $emailConfig : []),
    new \PPStudio\Http\Controller\Admin\AdminViewStateFactory(),
    is_array($emailConfig ?? null) ? $emailConfig : []
);
$__ppstudioAdminState = $__ppstudioAdminDataLoader->load(
    $__ppstudioAdminDataLoader->captureDefinedState(get_defined_vars()),
    $connection
);

extract($__ppstudioAdminState, EXTR_OVERWRITE);

unset($__ppstudioAdminDataLoader, $__ppstudioAdminState, $adminRoot);
