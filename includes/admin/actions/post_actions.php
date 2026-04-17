<?php
declare(strict_types=1);

$adminRoot = dirname(__DIR__, 3);

require $adminRoot . '/includes/admin/actions/post/helpers.php';

foreach (
    array_merge(
        [
            $adminRoot . '/includes/admin/actions/post/vouchers.php',
        ],
        [
            $adminRoot . '/includes/admin/actions/post/services.php',
        ],
        [
            $adminRoot . '/includes/admin/actions/post/availability.php',
        ],
        [
            $adminRoot . '/includes/admin/actions/post/reservations.php',
        ],
        [
            $adminRoot . '/includes/admin/actions/post/media.php',
        ]
    ) as $adminPostActionFile
) {
    require $adminPostActionFile;
}
