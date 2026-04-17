<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

use PPStudio\Service\SiteSettingsService;

final class AdminSettingsPostActionHandler
{
    public function __construct(
        private SiteSettingsService $siteSettingsService
    ) {
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     * @param array<string, string> $siteSettings
     * @param array<string, string> $studioSettingFields
     * @param string[] $integrationKeys
     * @param string[] $emailSettingKeys
     * @return array{siteSettings: array<string, string>, message: string, error: string}
     */
    public function handle(
        array $server,
        array $post,
        array $siteSettings,
        array $studioSettingFields,
        array $integrationKeys,
        array $emailSettingKeys
    ): array {
        $state = [
            'siteSettings' => $siteSettings,
            'message' => '',
            'error' => '',
        ];

        if (($server['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return $state;
        }

        if (isset($post['save_settings'])) {
            $state = $this->applyResult(
                $state,
                $this->saveStudioSettings($state['siteSettings'], $studioSettingFields, $post)
            );
        }

        if (isset($post['save_integrations'])) {
            $state = $this->applyResult(
                $state,
                $this->saveIntegrations($state['siteSettings'], $integrationKeys, $post)
            );
        }

        if (isset($post['save_email_settings'])) {
            $state = $this->applyResult(
                $state,
                $this->saveEmailSettings($state['siteSettings'], $emailSettingKeys, $post)
            );
        }

        return $state;
    }

    /**
     * @param array<string, string> $siteSettings
     * @param array<string, string> $studioSettingFields
     * @param array<string, mixed> $post
     * @return array{siteSettings: array<string, string>, message: string, error: string}
     */
    public function saveStudioSettings(array $siteSettings, array $studioSettingFields, array $post): array
    {
        $settingsToSave = [];
        foreach (array_keys($studioSettingFields) as $settingKey) {
            $settingsToSave[$settingKey] = trim((string) ($post[$settingKey] ?? ''));
        }

        if ($this->siteSettingsService->saveMany($settingsToSave)) {
            return [
                'siteSettings' => array_replace($siteSettings, $settingsToSave),
                'message' => 'Nastavení studia bylo uloženo.',
                'error' => '',
            ];
        }

        return [
            'siteSettings' => $siteSettings,
            'message' => '',
            'error' => 'Nastavení studia se nepodařilo uložit.',
        ];
    }

    /**
     * @param array<string, string> $siteSettings
     * @param array<string, string> $integrationKeys
     * @param array<string, mixed> $post
     * @return array{siteSettings: array<string, string>, message: string, error: string}
     */
    public function saveIntegrations(array $siteSettings, array $integrationKeys, array $post): array
    {
        return $this->saveGenericSettings(
            $siteSettings,
            $integrationKeys,
            $post,
            'Napojení recenzí a sociálních odkazů bylo uloženo.',
            'Napojení se nepodařilo uložit.'
        );
    }

    /**
     * @param array<string, string> $siteSettings
     * @param array<string, string> $emailSettingKeys
     * @param array<string, mixed> $post
     * @return array{siteSettings: array<string, string>, message: string, error: string}
     */
    public function saveEmailSettings(array $siteSettings, array $emailSettingKeys, array $post): array
    {
        return $this->saveGenericSettings(
            $siteSettings,
            $emailSettingKeys,
            $post,
            'E-mailové notifikace byly uloženy.',
            'E-mailové notifikace se nepodařilo uložit.'
        );
    }

    /**
     * @param array<string, string> $siteSettings
     * @param string[] $settingKeys
     * @param array<string, mixed> $post
     * @return array{siteSettings: array<string, string>, message: string, error: string}
     */
    private function saveGenericSettings(
        array $siteSettings,
        array $settingKeys,
        array $post,
        string $successMessage,
        string $errorMessage
    ): array {
        $settingsToSave = [];
        foreach ($settingKeys as $settingKey) {
            $settingsToSave[$settingKey] = trim((string) ($post[$settingKey] ?? ''));
        }

        if ($this->siteSettingsService->saveMany($settingsToSave)) {
            return [
                'siteSettings' => array_replace($siteSettings, $settingsToSave),
                'message' => $successMessage,
                'error' => '',
            ];
        }

        return [
            'siteSettings' => $siteSettings,
            'message' => '',
            'error' => $errorMessage,
        ];
    }

    /**
     * @param array{siteSettings: array<string, string>, message: string, error: string} $state
     * @param array{siteSettings: array<string, string>, message: string, error: string} $result
     * @return array{siteSettings: array<string, string>, message: string, error: string}
     */
    private function applyResult(array $state, array $result): array
    {
        return [
            'siteSettings' => $result['siteSettings'],
            'message' => $result['message'] !== '' ? $result['message'] : $state['message'],
            'error' => $result['error'] !== '' ? $result['error'] : $state['error'],
        ];
    }
}
