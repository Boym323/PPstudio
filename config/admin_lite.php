<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$appConfig = ppstudioAppConfig();

return [
    'username' => $appConfig->env('PPSTUDIO_STAFF_USERNAME')
        ?? $appConfig->env('PPSTUDIO_ADMIN_USERNAME', 'admin'),
    'password_hash' => $appConfig->env('PPSTUDIO_STAFF_PASSWORD_HASH')
        ?? $appConfig->env('PPSTUDIO_ADMIN_PASSWORD_HASH', ''),
];
