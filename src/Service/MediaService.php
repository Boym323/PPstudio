<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use PPStudio\Repository\MediaRepository;

final class MediaService
{
    public function loadByCategory(mysqli $connection, string $category, int $limit = 50): array
    {
        return (new MediaRepository($connection))->findByCategory($category, $limit);
    }
}
