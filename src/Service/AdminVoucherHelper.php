<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use mysqli_result;

final class AdminVoucherHelper
{
    public function isModuleTableReady(mysqli $connection): bool
    {
        $query = $connection->query("SHOW TABLES LIKE 'poukazy'");
        if (! ($query instanceof mysqli_result)) {
            return false;
        }

        $ready = (bool) $query->fetch_row();
        $query->free();

        return $ready;
    }

    public function generateCode(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?? '';

        if ($prefix === '') {
            $prefix = 'PP' . date('y');
        }

        return $prefix . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * @param array<string, mixed> $voucher
     */
    public function effectiveStatus(array $voucher): string
    {
        $storedStatus = (string) ($voucher['status'] ?? 'aktivni');
        if ($storedStatus === 'storno') {
            return 'storno';
        }

        $expiresAt = trim((string) ($voucher['expires_at'] ?? ''));
        if ($expiresAt !== '' && $expiresAt < date('Y-m-d')) {
            return 'expirovan';
        }

        $remaining = (float) ($voucher['zustatek'] ?? 0);
        if ($remaining <= 0.0001) {
            return 'vycerpan';
        }

        return 'aktivni';
    }

    public function normalizeRecipientEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
