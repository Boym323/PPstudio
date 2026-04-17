<?php

use PPStudio\Http\Controller\Admin\AdminSettingsPostActionHandler;
use PPStudio\Repository\SiteSettingsRepository;
use PPStudio\Service\SiteSettingsService;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $settingsHandler = new AdminSettingsPostActionHandler(
        new SiteSettingsService(
            new SiteSettingsRepository($connection),
            defaultSiteSettings()
        )
    );

    $result = $settingsHandler->saveStudioSettings($siteSettings, $studioSettingFields, $_POST);
    $siteSettings = $result['siteSettings'];
    if ($result['message'] !== '') {
        $message = $result['message'];
    }
    if ($result['error'] !== '') {
        $error = $result['error'];
    }
}
