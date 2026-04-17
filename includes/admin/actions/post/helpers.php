<?php

if (! function_exists('syncServicePriceHistory')) {
    function syncServicePriceHistory(mysqli $connection, int $serviceId, ?float $newPrice): void
    {
        (new \PPStudio\Service\ServicePriceHistorySynchronizer())->sync($connection, $serviceId, $newPrice);
    }
}

if (! function_exists('voucherModuleTableReady')) {
    function voucherModuleTableReady(mysqli $connection): bool
    {
        return (new \PPStudio\Service\AdminVoucherHelper())->isModuleTableReady($connection);
    }
}

if (! function_exists('generateVoucherCode')) {
    function generateVoucherCode(string $prefix): string
    {
        return (new \PPStudio\Service\AdminVoucherHelper())->generateCode($prefix);
    }
}

if (! function_exists('voucherEffectiveStatus')) {
    function voucherEffectiveStatus(array $voucher): string
    {
        return (new \PPStudio\Service\AdminVoucherHelper())->effectiveStatus($voucher);
    }
}

if (! function_exists('normalizeVoucherRecipientEmail')) {
    function normalizeVoucherRecipientEmail(string $email): string
    {
        return (new \PPStudio\Service\AdminVoucherHelper())->normalizeRecipientEmail($email);
    }
}
