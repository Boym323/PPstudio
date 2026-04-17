<?php

use PPStudio\Http\Controller\Admin\AdminServicePostActionHandler;
use PPStudio\Service\AdminServiceMutationService;

$servicePostActionHandler = new AdminServicePostActionHandler(
    new AdminServiceMutationService($connection)
);
$servicePostState = $servicePostActionHandler->handle($_SERVER, $_POST, $serviceForm, $categoryForm);

if ($servicePostState['message'] !== '') {
    $message = $servicePostState['message'];
}

if ($servicePostState['error'] !== '') {
    $error = $servicePostState['error'];
}

if (is_array($servicePostState['service_form'] ?? null)) {
    $serviceForm = $servicePostState['service_form'];
}

if (is_array($servicePostState['category_form'] ?? null)) {
    $categoryForm = $servicePostState['category_form'];
}
