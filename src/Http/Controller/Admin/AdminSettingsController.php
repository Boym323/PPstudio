<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

final class AdminSettingsController
{
    /**
     * @return list<string>
     */
    public static function postActionFiles(string $projectRoot): array
    {
        return [
            $projectRoot . '/includes/admin/actions/post/settings.php',
        ];
    }

    /**
     * @return list<string>
     */
    public static function integrationPostActionFiles(string $projectRoot): array
    {
        return [
            $projectRoot . '/includes/admin/actions/post/settings_integrations.php',
        ];
    }
}
