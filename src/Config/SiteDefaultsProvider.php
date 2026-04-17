<?php
declare(strict_types=1);

namespace PPStudio\Config;

final class SiteDefaultsProvider
{
    public function __construct(private readonly EnvLoader $envLoader)
    {
    }

    /**
     * @return array<string, string>
     */
    public function defaultSiteSettings(): array
    {
        return [
            'site_name' => $this->defaultSiteName(),
            'site_url' => '',
            'contact_address' => '',
            'contact_name' => '',
            'contact_phone' => '',
            'contact_email' => '',
            'contact_instagram_url' => $this->defaultContactInstagramUrl(),
            'contact_ico' => '',
            'contact_opening_hours' => '',
            'google_reviews_url' => '',
            'firmy_reviews_url' => '',
            'firmy_reviews_embed' => '',
            'google_place_id' => '',
            'google_reviews_language' => '',
            'notification_emails' => '',
            'availability_story_background' => '',
        ];
    }

    public function defaultSiteName(): string
    {
        return trim((string) $this->envLoader->get('PPSTUDIO_SITE_NAME', 'PP Studio'));
    }

    public function defaultContactInstagramUrl(): string
    {
        return trim((string) $this->envLoader->get('PPSTUDIO_CONTACT_INSTAGRAM_URL', ''));
    }
}
