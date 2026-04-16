<?php
declare(strict_types=1);

use PPStudio\Service\AdminAvailabilityReadService;
use PPStudio\Service\AdminAvailabilityStoryService;

$availabilityReadService = new AdminAvailabilityReadService($connection);
$availabilityRows = $availabilityReadService->loadAvailabilityRows();
$storyViewModel = (new AdminAvailabilityStoryService($connection))->buildDefaultViewModel(
    is_array($siteSettings ?? null) ? $siteSettings : [],
    is_array($serviceCategoryRows ?? null) ? $serviceCategoryRows : []
);
$storyDefaultFrom = (string) ($storyViewModel['storyDefaultFrom'] ?? '');
$storyDefaultTo = (string) ($storyViewModel['storyDefaultTo'] ?? '');
$storyDefaultMonth = (string) ($storyViewModel['storyDefaultMonth'] ?? '');
$storyBackground = (string) ($storyViewModel['storyBackground'] ?? '');
$storyBackgroundUrl = (string) ($storyViewModel['storyBackgroundUrl'] ?? '');
$storyDefaultServices = is_array($storyViewModel['storyDefaultServices'] ?? null) ? $storyViewModel['storyDefaultServices'] : [];
