<?php
declare(strict_types=1);

return [
    'enabled' => filter_var(ppstudioEnv('PPSTUDIO_EMAIL_ENABLED', '1'), FILTER_VALIDATE_BOOL),
    'mailer' => ppstudioEnv('PPSTUDIO_MAILER', 'smtp'),
    'host' => ppstudioEnv('PPSTUDIO_SMTP_HOST', 'smtp.t-email.cz'),
    'port' => (int) (ppstudioEnv('PPSTUDIO_SMTP_PORT', '25')),
    'encryption' => ppstudioEnv('PPSTUDIO_SMTP_ENCRYPTION', 'tls'),
    'username' => ppstudioEnv('PPSTUDIO_SMTP_USERNAME', 'noreply@ppstudio.cz'),
    'password' => ppstudioEnv('PPSTUDIO_SMTP_PASSWORD', ''),
    'auth' => filter_var(ppstudioEnv('PPSTUDIO_SMTP_AUTH', '0'), FILTER_VALIDATE_BOOL),
    'from_email' => ppstudioEnv('PPSTUDIO_FROM_EMAIL', 'noreply@ppstudio.cz'),
    'from_name' => ppstudioEnv('PPSTUDIO_FROM_NAME', 'PPStudio'),
    'admin_email' => ppstudioEnv('PPSTUDIO_ADMIN_EMAIL', 'info@ppstudio.cz'),
    'reply_to' => ppstudioEnv('PPSTUDIO_REPLY_TO', 'info@ppstudio.cz'),
    'site_url' => ppstudioEnv('PPSTUDIO_SITE_URL', 'https://ppstudio.cz'),
    'calendar_token' => ppstudioEnv('PPSTUDIO_CALENDAR_TOKEN', ''),
    'action_secret' => ppstudioEnv('PPSTUDIO_ACTION_SECRET', ''),
    'action_ttl_seconds' => (int) (ppstudioEnv('PPSTUDIO_ACTION_TTL_SECONDS', '172800')),
];
