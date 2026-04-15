<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

final class AdminServiceController
{
    /**
     * @return list<string>
     */
    public static function postActionFiles(string $projectRoot): array
    {
        return [
            $projectRoot . '/includes/admin/actions/post/services.php',
        ];
    }

    /**
     * @return list<string>
     */
    public static function formDataFiles(string $projectRoot): array
    {
        return [
            $projectRoot . '/includes/admin/actions/load/service_forms.php',
        ];
    }

    /**
     * @return list<string>
     */
    public static function dataFiles(string $projectRoot): array
    {
        return [
            $projectRoot . '/includes/admin/actions/load/services.php',
        ];
    }
}
