<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$appConfig = ppstudioAppConfig();

return [
    'enabled' => filter_var($appConfig->env('PPSTUDIO_EMAIL_ENABLED', '1'), FILTER_VALIDATE_BOOL),
    'mailer' => $appConfig->env('PPSTUDIO_MAILER', 'smtp'),
    'host' => $appConfig->env('PPSTUDIO_SMTP_HOST', 'smtp.t-email.cz'),
    'port' => (int) ($appConfig->env('PPSTUDIO_SMTP_PORT', '25')),
    'encryption' => $appConfig->env('PPSTUDIO_SMTP_ENCRYPTION', 'tls'),
    'username' => $appConfig->env('PPSTUDIO_SMTP_USERNAME', 'noreply@ppstudio.cz'),
    'password' => $appConfig->env('PPSTUDIO_SMTP_PASSWORD', ''),
    'auth' => filter_var($appConfig->env('PPSTUDIO_SMTP_AUTH', '0'), FILTER_VALIDATE_BOOL),
    'from_email' => $appConfig->env('PPSTUDIO_FROM_EMAIL', 'noreply@ppstudio.cz'),
    'from_name' => $appConfig->env('PPSTUDIO_FROM_NAME', 'PPStudio'),
    'reply_to' => $appConfig->env('PPSTUDIO_REPLY_TO', 'info@ppstudio.cz'),
    'calendar_token' => $appConfig->env('PPSTUDIO_CALENDAR_TOKEN', ''),
    'action_secret' => $appConfig->env('PPSTUDIO_ACTION_SECRET', ''),
    'action_ttl_seconds' => (int) ($appConfig->env('PPSTUDIO_ACTION_TTL_SECONDS', '172800')),
    'customer_action_cutoff_seconds' => (int) ($appConfig->env('PPSTUDIO_CUSTOMER_ACTION_CUTOFF_SECONDS', '86400')),
    'reservation_reminder_lead_seconds' => (int) ($appConfig->env('PPSTUDIO_RESERVATION_REMINDER_LEAD_SECONDS', '93600')),
    'reservation_reminder_window_seconds' => (int) ($appConfig->env('PPSTUDIO_RESERVATION_REMINDER_WINDOW_SECONDS', '3600')),
];
