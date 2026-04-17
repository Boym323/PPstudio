<?php
declare(strict_types=1);

$adminRoot = dirname(__DIR__, 3);

foreach (
    array_merge(
        [
            $adminRoot . '/includes/admin/actions/load/services.php',
        ],
        [
            $adminRoot . '/includes/admin/actions/load/availability.php',
        ],
        [
            $adminRoot . '/includes/admin/actions/load/reservations.php',
        ],
        [
            $adminRoot . '/includes/admin/actions/load/dashboard.php',
        ],
        [
            $adminRoot . '/includes/admin/actions/load/media.php',
        ],
        [
            $adminRoot . '/includes/admin/actions/load/availability_planner.php',
        ],
        [
            $adminRoot . '/includes/admin/actions/load/vouchers.php',
        ]
    ) as $adminDataFile
) {
    require $adminDataFile;
}
