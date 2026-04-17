<?php

use PPStudio\Service\AdminMediaModule;

$mediaData = (new AdminMediaModule($connection, dirname(__DIR__, 4)))
    ->dataLoader()
    ->load($connection);

$profileMedia = is_array($mediaData['profile_media'] ?? null) ? $mediaData['profile_media'] : [];
$galleryMedia = is_array($mediaData['gallery_media'] ?? null) ? $mediaData['gallery_media'] : [];
$certificateFiles = is_array($mediaData['certificate_files'] ?? null) ? $mediaData['certificate_files'] : [];
