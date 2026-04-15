<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_integrations'])) {
    $integrationKeys = [
        'google_reviews_url',
        'firmy_reviews_url',
        'firmy_reviews_embed',
        'google_place_id',
        'google_reviews_language',
    ];
    $settingsToSave = [];
    foreach ($integrationKeys as $settingKey) {
        $settingsToSave[$settingKey] = trim((string) ($_POST[$settingKey] ?? ''));
    }
    $savedAll = ppstudioSiteSettingsService($connection)->saveMany($settingsToSave);

    if ($savedAll) {
        $siteSettings = array_replace($siteSettings, $settingsToSave);
        $message = 'Napojení recenzí a sociálních odkazů bylo uloženo.';
    } else {
        $error = 'Napojení se nepodařilo uložit.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_email_settings'])) {
    $emailSettingKeys = [
        'notification_emails',
    ];
    $settingsToSave = [];
    foreach ($emailSettingKeys as $settingKey) {
        $settingsToSave[$settingKey] = trim((string) ($_POST[$settingKey] ?? ''));
    }
    $savedAll = ppstudioSiteSettingsService($connection)->saveMany($settingsToSave);

    if ($savedAll) {
        $siteSettings = array_replace($siteSettings, $settingsToSave);
        $message = 'E-mailové notifikace byly uloženy.';
    } else {
        $error = 'E-mailové notifikace se nepodařilo uložit.';
    }
}
