<?php
declare(strict_types=1);

use PPStudio\Config\AppConfig;

if (! function_exists('ppstudioAppConfig')) {
    function ppstudioAppConfig(): AppConfig
    {
        return AppConfig::instance();
    }
}

if (! function_exists('ppstudioEnv')) {
    function ppstudioEnv(string $name, ?string $default = null): ?string
    {
        return ppstudioAppConfig()->env($name, $default);
    }
}

if (! function_exists('defaultSiteSettings')) {
    /**
     * @return array<string, string>
     */
    function defaultSiteSettings(): array
    {
        return ppstudioAppConfig()->defaultSiteSettings();
    }
}

if (! function_exists('defaultSiteName')) {
    function defaultSiteName(): string
    {
        return ppstudioAppConfig()->defaultSiteName();
    }
}

if (! function_exists('defaultContactInstagramUrl')) {
    function defaultContactInstagramUrl(): string
    {
        return ppstudioAppConfig()->defaultContactInstagramUrl();
    }
}

$appConfig = ppstudioAppConfig();
$appConfig->boot(dirname(__DIR__));

if (! defined('SITE_SETTING_KEYS')) {
    define('SITE_SETTING_KEYS', $appConfig->siteSettingKeys());
}
