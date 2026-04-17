<?php

use PPStudio\Service\AdminVoucherModule;

$voucherModule = new AdminVoucherModule($connection, $emailConfig, $siteSettings);
$voucherData = $voucherModule->dataLoader()->load();

$voucherModuleReady = (bool) ($voucherData['voucher_module_ready'] ?? false);
$voucherRows = is_array($voucherData['voucher_rows'] ?? null) ? $voucherData['voucher_rows'] : [];
$voucherTransactionsByVoucher = is_array($voucherData['voucher_transactions_by_voucher'] ?? null) ? $voucherData['voucher_transactions_by_voucher'] : [];
$voucherReservationOptions = is_array($voucherData['voucher_reservation_options'] ?? null) ? $voucherData['voucher_reservation_options'] : [];
$voucherReservationLookup = is_array($voucherData['voucher_reservation_lookup'] ?? null) ? $voucherData['voucher_reservation_lookup'] : [];

$voucherSectionViewData = $voucherModule->catalogService()->buildSectionViewData(
    $voucherRows,
    $voucherTransactionsByVoucher,
    $voucherReservationOptions,
    $voucherReservationLookup
);

$voucherSummary = is_array($voucherSectionViewData['voucher_summary'] ?? null) ? $voucherSectionViewData['voucher_summary'] : [];
$voucherRowsPrepared = is_array($voucherSectionViewData['voucher_rows_prepared'] ?? null) ? $voucherSectionViewData['voucher_rows_prepared'] : $voucherRows;
$voucherReservationOptionsPrepared = is_array($voucherSectionViewData['voucher_reservation_options_prepared'] ?? null)
    ? $voucherSectionViewData['voucher_reservation_options_prepared']
    : $voucherReservationOptions;
