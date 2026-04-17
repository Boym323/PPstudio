<?php

use PPStudio\Http\Controller\Admin\AdminSecurityLogDataLoader;

if (! in_array($reminderLogFilters['severity'] ?? 'all', array_keys($reminderLogSeverityOptions), true)) {
    $reminderLogFilters['severity'] = 'all';
}

if (! in_array($reminderLogFilters['limit'] ?? 100, $reminderLogLimitOptions, true)) {
    $reminderLogFilters['limit'] = 100;
}

if (($reminderLogFilters['page'] ?? 0) < 1) {
    $reminderLogFilters['page'] = 1;
}

$reminderData = (new AdminSecurityLogDataLoader($connection))->loadReminderLogs(
    $reminderLogFilters,
    $reminderLogEventOptions,
    $reminderLogSeverityOptions,
    $reminderLogLimitOptions
);

$reminderLogRows = $reminderData['reminder_log_rows'];
$reminderLogStats = $reminderData['reminder_log_stats'];
$reminderLogEventOptions = $reminderData['reminder_log_event_options'];
$reminderLogSeverityOptions = $reminderData['reminder_log_severity_options'];
$reminderLogLimitOptions = $reminderData['reminder_log_limit_options'];
$reminderLogFilters = $reminderData['reminder_log_filters'];
$reminderLogPagination = $reminderData['reminder_log_pagination'];
