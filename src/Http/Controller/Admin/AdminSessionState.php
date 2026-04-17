<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

final class AdminSessionState
{
    /**
     * @param array<string, mixed> $session
     */
    public static function isAuthenticated(array $session): bool
    {
        return self::isFullAdmin($session) || (bool) ($session['ppstudio_admin_lite_authenticated'] ?? false);
    }

    /**
     * @param array<string, mixed> $session
     */
    public static function isFullAdmin(array $session): bool
    {
        return (bool) ($session['ppstudio_admin_authenticated'] ?? false);
    }

    /**
     * @param array<string, mixed> $session
     * @return array{cancelled_by:string,cancelled_by_user:string}
     */
    public static function cancelledBy(array $session): array
    {
        $isFullAdmin = self::isFullAdmin($session);

        return [
            'cancelled_by' => $isFullAdmin ? 'admin_full' : 'admin_lite',
            'cancelled_by_user' => $isFullAdmin
                ? trim((string) ($session['ppstudio_admin_username'] ?? 'admin'))
                : trim((string) ($session['ppstudio_admin_lite_username'] ?? 'staff')),
        ];
    }
}
