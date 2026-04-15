<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

final class AdminAvailabilityController
{
    /**
     * @return list<string>
     */
    public static function postActionFiles(string $projectRoot): array
    {
        return [
            $projectRoot . '/includes/admin/actions/post/availability.php',
        ];
    }

    /**
     * @return list<string>
     */
    public static function availabilityWindowDataFiles(string $projectRoot): array
    {
        return [
            $projectRoot . '/includes/admin/actions/load/availability.php',
        ];
    }

    /**
     * @return list<string>
     */
    public static function plannerDataFiles(string $projectRoot): array
    {
        return [
            $projectRoot . '/includes/admin/actions/load/availability_planner.php',
        ];
    }
}
