<?php

use PPStudio\Service\AdminVoucherModule;

$voucherPostResult = (new AdminVoucherModule($connection, $emailConfig, $siteSettings))
    ->postActionHandler()
    ->handle($_SERVER, $_POST, $voucherForm, $voucherBatchForm);

$message = $voucherPostResult['message'] !== '' ? $voucherPostResult['message'] : $message;
$error = $voucherPostResult['error'] !== '' ? $voucherPostResult['error'] : $error;
$voucherForm = $voucherPostResult['voucher_form'];
$voucherBatchForm = $voucherPostResult['voucher_batch_form'];
