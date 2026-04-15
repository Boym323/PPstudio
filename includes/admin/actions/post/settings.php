<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $settingsToSave = [];
    foreach (array_keys($studioSettingFields) as $settingKey) {
        $settingsToSave[$settingKey] = trim((string) ($_POST[$settingKey] ?? ''));
    }
    $savedAll = ppstudioSiteSettingsService($connection)->saveMany($settingsToSave);

    if ($savedAll) {
        $siteSettings = array_replace($siteSettings, $settingsToSave);
        $message = 'Nastavení studia bylo uloženo.';
    } else {
        $error = 'Nastavení studia se nepodařilo uložit.';
    }
}
