<?php
declare(strict_types=1);

namespace PPStudio\Config;

final class AppConfig
{
    public const SITE_SETTING_KEYS = [
        'site_name',
        'site_url',
        'contact_address',
        'contact_name',
        'contact_phone',
        'contact_email',
        'contact_instagram_url',
        'contact_ico',
        'contact_opening_hours',
        'google_reviews_url',
        'firmy_reviews_url',
        'firmy_reviews_embed',
        'google_place_id',
        'google_reviews_language',
        'notification_emails',
        'availability_story_background',
    ];

    private static ?self $instance = null;

    public function __construct(
        private readonly EnvLoader $envLoader,
        private readonly SiteDefaultsProvider $siteDefaultsProvider
    ) {
    }

    public static function instance(): self
    {
        if (! self::$instance instanceof self) {
            $envLoader = new EnvLoader();
            self::$instance = new self(
                $envLoader,
                new SiteDefaultsProvider($envLoader)
            );
        }

        return self::$instance;
    }

    public function boot(string $projectRoot): void
    {
        $this->loadEnvironmentFiles([
            $projectRoot . '/.env',
            $projectRoot . '/.env.local',
        ]);
    }

    /**
     * @param array<int, string> $paths
     */
    public function loadEnvironmentFiles(array $paths): void
    {
        $this->envLoader->load($paths);
    }

    public function env(string $name, ?string $default = null): ?string
    {
        return $this->envLoader->get($name, $default);
    }

    /**
     * @return array<string, string>
     */
    public function defaultSiteSettings(): array
    {
        return $this->siteDefaultsProvider->defaultSiteSettings();
    }

    public function defaultSiteName(): string
    {
        return $this->siteDefaultsProvider->defaultSiteName();
    }

    public function defaultContactInstagramUrl(): string
    {
        return $this->siteDefaultsProvider->defaultContactInstagramUrl();
    }

    /**
     * @return array<int, string>
     */
    public function siteSettingKeys(): array
    {
        return self::SITE_SETTING_KEYS;
    }
}
