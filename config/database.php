<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$appConfig = ppstudioAppConfig();

return [
    'host' => $appConfig->env('PPSTUDIO_DB_HOST', '127.0.0.1'),
    'database' => $appConfig->env('PPSTUDIO_DB_NAME', 'pp_studio'),
    'username' => $appConfig->env('PPSTUDIO_DB_USER', ''),
    'password' => $appConfig->env('PPSTUDIO_DB_PASSWORD', ''),
    'charset' => $appConfig->env('PPSTUDIO_DB_CHARSET', 'utf8mb4'),
];
