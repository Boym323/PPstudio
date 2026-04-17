<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

final class AdminPageRenderer
{
    /**
     * @param array<string, mixed> $viewState
     * @param array<string, mixed> $config
     */
    public function render(array $viewState, array $config): void
    {
        $sidebarTemplate = (string) ($config['sidebarTemplate'] ?? '');
        $introTemplate = (string) ($config['introTemplate'] ?? '');
        $defaultSection = (string) ($config['defaultSection'] ?? '');
        $pageTitlePrefix = (string) ($config['pageTitlePrefix'] ?? 'Admin');
        $sectionByTab = is_array($config['sectionByTab'] ?? null) ? $config['sectionByTab'] : [];

        extract($viewState, EXTR_OVERWRITE);

        $adminCssVersion = (string) (@filemtime(__DIR__ . '/../../../assets/css/admin.css') ?: time());
        $adminJsVersion = (string) (@filemtime(__DIR__ . '/../../../assets/js/main.js') ?: time());
        $resolvedPageTitle = $pageTitlePrefix . ' | ' . \PPStudio\Support\SettingsHelper::setting($siteSettings, 'site_name', \defaultSiteName());
        $sectionPath = (string) ($sectionByTab[$adminTab] ?? $defaultSection);

        include __DIR__ . '/Templates/layouts/admin-app.php';
    }
}
