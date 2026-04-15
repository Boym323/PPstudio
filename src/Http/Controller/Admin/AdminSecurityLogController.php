<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

final class AdminSecurityLogController
{
    /**
     * @return list<string>
     */
    public static function dataFiles(string $projectRoot): array
    {
        return [
            $projectRoot . '/includes/admin/actions/load/antispam.php',
            $projectRoot . '/includes/admin/actions/load/reminder_logs.php',
        ];
    }
}
