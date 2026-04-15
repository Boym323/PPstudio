<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

final class AdminDashboardController
{
    /**
     * @return list<string>
     */
    public static function dataFiles(string $projectRoot): array
    {
        return [
            $projectRoot . '/includes/admin/actions/load/dashboard.php',
        ];
    }
}
