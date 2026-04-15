<?php

$profileMedia = loadMediaByCategory($connection, 'profile', 1);
$galleryMedia = loadMediaByCategory($connection, 'gallery', 30);
$certificateFiles = loadCertificateUploads(dirname(__DIR__, 4) . '/uploads', '/uploads', 'cert_');
