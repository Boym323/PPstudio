<?php
declare(strict_types=1);

$adminRoot = dirname(__DIR__, 3);
$__ppstudioAdminPostHandler = new \PPStudio\Http\Controller\Admin\AdminPostActionHandler(
    $adminRoot,
    new \PPStudio\Http\Controller\Admin\AdminViewStateFactory(),
    is_array($emailConfig ?? null) ? $emailConfig : []
);
$__ppstudioAdminState = $__ppstudioAdminPostHandler->handle(
    $__ppstudioAdminPostHandler->captureDefinedState(get_defined_vars()),
    $connection
);

extract($__ppstudioAdminState, EXTR_OVERWRITE);

unset($__ppstudioAdminPostHandler, $__ppstudioAdminState, $adminRoot);
