<?php
declare(strict_types=1);

namespace PPStudio\Security;

final class ReservationLinkSigner
{
    public function __construct(private array $emailConfig)
    {
    }

    public function buildAdminActionUrl(array $siteSettings, int $reservationId, string $action): string
    {
        $siteUrl = rtrim(\PPStudio\Support\SettingsHelper::setting($siteSettings, 'site_url', ''), '/');
        $secret = (string) ($this->emailConfig['action_secret'] ?? '');
        $ttl = (int) ($this->emailConfig['action_ttl_seconds'] ?? 172800);

        if ($siteUrl === '' || $secret === '' || $reservationId <= 0) {
            return '';
        }

        $expiresAt = time() + max(300, $ttl);

        return $this->buildUrl($siteUrl . '/reservation-action.php', $reservationId, $action, $expiresAt);
    }

    public function buildCustomerActionUrl(array $siteSettings, array $reservation, string $action, string $path): string
    {
        $siteUrl = rtrim(\PPStudio\Support\SettingsHelper::setting($siteSettings, 'site_url', ''), '/');
        $secret = (string) ($this->emailConfig['action_secret'] ?? '');
        $reservationId = (int) ($reservation['id'] ?? 0);

        if ($siteUrl === '' || $secret === '' || $reservationId <= 0) {
            return '';
        }

        $expiresAt = $this->customerActionDeadline($reservation);
        if ($expiresAt <= time()) {
            return '';
        }

        return $this->buildUrl($siteUrl . '/' . ltrim($path, '/'), $reservationId, $action, $expiresAt);
    }

    public function buildCustomerCancelUrl(array $siteSettings, array $reservation): string
    {
        return $this->buildCustomerActionUrl($siteSettings, $reservation, 'cancel', 'reservation-cancel.php');
    }

    public function buildCustomerRescheduleUrl(array $siteSettings, array $reservation): string
    {
        return $this->buildCustomerActionUrl($siteSettings, $reservation, 'reschedule', 'reservation-reschedule.php');
    }

    public function isValidActionSignature(
        int $reservationId,
        string $action,
        int $expiresAt,
        string $nonce,
        string $signature
    ): bool {
        $secret = (string) ($this->emailConfig['action_secret'] ?? '');

        if ($secret === '' || $reservationId <= 0 || $signature === '' || $expiresAt <= 0 || $nonce === '') {
            return false;
        }

        if ($expiresAt < time()) {
            return false;
        }

        if (! preg_match('/^[a-f0-9]{32}$/i', $nonce)) {
            return false;
        }

        return hash_equals($this->signature($action, $reservationId, $expiresAt, $nonce), $signature);
    }

    public function customerActionCutoffSeconds(): int
    {
        $cutoff = (int) ($this->emailConfig['customer_action_cutoff_seconds'] ?? 86400);

        return max(0, $cutoff);
    }

    public function customerActionDeadline(array $reservation): int
    {
        $reservationTs = strtotime((string) ($reservation['datum_cas'] ?? ''));
        if (! $reservationTs) {
            return 0;
        }

        return $reservationTs - $this->customerActionCutoffSeconds();
    }

    public function canUseCustomerAction(array $reservation): bool
    {
        $deadline = $this->customerActionDeadline($reservation);
        if ($deadline <= 0) {
            return false;
        }

        return time() < $deadline;
    }

    public function nonceStoragePath(): string
    {
        if (function_exists('ppstudioSecurityStorageDir')) {
            return \ppstudioSecurityStorageDir() . '/reservation-action-nonces.json';
        }

        $fallbackDir = dirname(__DIR__, 2) . '/var/security';
        if (! is_dir($fallbackDir)) {
            @mkdir($fallbackDir, 0770, true);
        }

        return $fallbackDir . '/reservation-action-nonces.json';
    }

    public function consumeNonce(int $reservationId, string $action, int $expiresAt, string $nonce): bool
    {
        if ($reservationId <= 0 || $action === '' || $expiresAt <= 0 || $nonce === '') {
            return false;
        }

        $handle = @fopen($this->nonceStoragePath(), 'c+');
        if (! $handle) {
            return false;
        }

        if (! @flock($handle, LOCK_EX)) {
            fclose($handle);
            return false;
        }

        $content = stream_get_contents($handle);
        $map = [];
        if (is_string($content) && trim($content) !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $map = $decoded;
            }
        }

        $now = time();
        foreach ($map as $key => $usedAt) {
            if (! is_int($usedAt) || $usedAt < ($now - 14 * 24 * 60 * 60)) {
                unset($map[$key]);
            }
        }

        $tokenKey = hash('sha256', $reservationId . '|' . $action . '|' . $expiresAt . '|' . $nonce);
        if (isset($map[$tokenKey])) {
            $this->writeNonceMap($handle, $map);
            return false;
        }

        $map[$tokenKey] = $now;
        $this->writeNonceMap($handle, $map);

        return true;
    }

    private function buildUrl(string $baseUrl, int $reservationId, string $action, int $expiresAt): string
    {
        $nonce = bin2hex(random_bytes(16));
        $signature = $this->signature($action, $reservationId, $expiresAt, $nonce);

        return $baseUrl . '?id=' . $reservationId
            . '&action=' . rawurlencode($action)
            . '&exp=' . $expiresAt
            . '&nonce=' . rawurlencode($nonce)
            . '&sig=' . rawurlencode($signature);
    }

    private function signature(string $action, int $reservationId, int $expiresAt, string $nonce): string
    {
        return hash_hmac(
            'sha256',
            $action . '|' . $reservationId . '|' . $expiresAt . '|' . $nonce,
            (string) ($this->emailConfig['action_secret'] ?? '')
        );
    }

    /**
     * @param resource $handle
     */
    private function writeNonceMap($handle, array $map): void
    {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
