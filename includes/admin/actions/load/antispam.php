<?php

use PPStudio\Http\Controller\Admin\AdminSecurityLogDataLoader;

if (! in_array($reservationFilters['status'], array_keys($reservationStatusFilterOptions), true)) {
    $reservationFilters['status'] = 'all';
}

if (! in_array($reservationFilters['period'], array_keys($reservationPeriodFilterOptions), true)) {
    $reservationFilters['period'] = 'all';
}

if (! in_array($reservationFilters['per_page'], $reservationPerPageOptions, true)) {
    $reservationFilters['per_page'] = 25;
}

if (! in_array($antispamFilters['reason'], array_keys($antispamReasonOptions), true)) {
    $antispamFilters['reason'] = 'all';
}

if (! in_array($antispamFilters['limit'], $antispamLimitOptions, true)) {
    $antispamFilters['limit'] = 100;
}

if (($antispamFilters['page'] ?? 0) < 1) {
    $antispamFilters['page'] = 1;
}

$antispamData = (new AdminSecurityLogDataLoader($connection))->loadAntispam(
    $antispamFilters,
    $antispamReasonOptions,
    $antispamLimitOptions
);

$antispamRows = $antispamData['antispam_rows'];
$antispamLogStats = $antispamData['antispam_log_stats'];
$antispamReasonOptions = $antispamData['antispam_reason_options'];
$antispamLimitOptions = $antispamData['antispam_limit_options'];
$antispamFilters = $antispamData['antispam_filters'];
$antispamPagination = $antispamData['antispam_pagination'];
