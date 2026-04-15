<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

final class AdminReservationController
{
    /**
     * @return list<string>
     */
    public static function postActionFiles(string $projectRoot): array
    {
        return [
            $projectRoot . '/includes/admin/actions/post/reservations.php',
        ];
    }

    /**
     * @return list<string>
     */
    public static function dataFiles(string $projectRoot): array
    {
        return [
            $projectRoot . '/includes/admin/actions/load/reservations.php',
        ];
    }
}
