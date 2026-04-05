<?php
declare(strict_types=1);

return [
    'username' => ppstudioEnv('PPSTUDIO_ADMIN_USERNAME', 'admin'),
    'password_hash' => ppstudioEnv('PPSTUDIO_ADMIN_PASSWORD_HASH', ''),
];
