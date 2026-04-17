<?php

use PPStudio\Http\Controller\Admin\AdminSettingsPostActionHandler;
use PPStudio\Repository\SiteSettingsRepository;
use PPStudio\Service\SiteSettingsService;

$settingsHandler = new AdminSettingsPostActionHandler(
    new SiteSettingsService(
        new SiteSettingsRepository($connection),
        defaultSiteSettings()
    )
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_integrations'])) {
    $integrationKeys = [
        'google_reviews_url',
        'firmy_reviews_url',
        'firmy_reviews_embed',
        'google_place_id',
        'google_reviews_language',
    ];
    $result = $settingsHandler->saveIntegrations($siteSettings, $integrationKeys, $_POST);
    $siteSettings = $result['siteSettings'];
    if ($result['message'] !== '') {
        $message = $result['message'];
    }
    if ($result['error'] !== '') {
        $error = $result['error'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_email_settings'])) {
    $emailSettingKeys = [
        'notification_emails',
    ];
    $result = $settingsHandler->saveEmailSettings($siteSettings, $emailSettingKeys, $_POST);
    $siteSettings = $result['siteSettings'];
    if ($result['message'] !== '') {
        $message = $result['message'];
    }
    if ($result['error'] !== '') {
        $error = $result['error'];
    }
}
