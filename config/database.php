<?php
declare(strict_types=1);

return [
    'host' => ppstudioEnv('PPSTUDIO_DB_HOST', '127.0.0.1'),
    'database' => ppstudioEnv('PPSTUDIO_DB_NAME', 'pp_studio'),
    'username' => ppstudioEnv('PPSTUDIO_DB_USER', ''),
    'password' => ppstudioEnv('PPSTUDIO_DB_PASSWORD', ''),
    'charset' => ppstudioEnv('PPSTUDIO_DB_CHARSET', 'utf8mb4'),
];
