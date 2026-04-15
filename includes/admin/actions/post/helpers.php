<?php

if (! function_exists('syncServicePriceHistory')) {
    function syncServicePriceHistory(mysqli $connection, int $serviceId, ?float $newPrice): void
    {
        if ($serviceId <= 0) {
            return;
        }

        $closeOpenHistory = $connection->prepare(
            'UPDATE historie_cen_sluzeb
             SET platna_do = NOW()
             WHERE sluzba_id = ?
               AND platna_do IS NULL'
        );
        if ($closeOpenHistory) {
            $closeOpenHistory->bind_param('i', $serviceId);
            $closeOpenHistory->execute();
            $closeOpenHistory->close();
        }

        if ($newPrice === null) {
            return;
        }

        $insertHistory = $connection->prepare(
            'INSERT INTO historie_cen_sluzeb (sluzba_id, cena, platna_od, platna_do)
             VALUES (?, ?, NOW(), NULL)'
        );
        if ($insertHistory) {
            $insertHistory->bind_param('id', $serviceId, $newPrice);
            $insertHistory->execute();
            $insertHistory->close();
        }
    }
}

if (! function_exists('voucherModuleTableReady')) {
    function voucherModuleTableReady(mysqli $connection): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        $query = $connection->query("SHOW TABLES LIKE 'poukazy'");
        if (! ($query instanceof mysqli_result)) {
            $ready = false;
            return $ready;
        }

        $ready = (bool) $query->fetch_row();
        $query->free();

        return $ready;
    }
}

if (! function_exists('generateVoucherCode')) {
    function generateVoucherCode(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?? '';
        if ($prefix === '') {
            $prefix = 'PP' . date('y');
        }

        return $prefix . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}

if (! function_exists('voucherEffectiveStatus')) {
    function voucherEffectiveStatus(array $voucher): string
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
}

if (! function_exists('normalizeVoucherRecipientEmail')) {
    function normalizeVoucherRecipientEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
