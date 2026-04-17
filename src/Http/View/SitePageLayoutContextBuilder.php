<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

final class SitePageLayoutContextBuilder
{
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function build(array $context): array
    {
        $siteSettings = is_array($context['siteSettings'] ?? null) ? $context['siteSettings'] : [];
        $resolvedSiteBaseUrl = (string) ($context['siteBaseUrl'] ?? rtrim((string) \ppstudioEnv('PPSTUDIO_SITE_URL', ''), '/'));
        $canonicalUrl = (string) ($context['canonicalUrl'] ?? ($resolvedSiteBaseUrl !== '' ? $resolvedSiteBaseUrl : '') . '/');

        $schemaAddress = trim(\PPStudio\Support\SettingsHelper::setting($siteSettings, 'contact_address', ''));
        $schemaInstagramUrl = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'contact_instagram_url', '');
        $schemaPhone = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'contact_phone', '+420732856036');
        $siteName = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'site_name', \defaultSiteName());
        $schemaData = [
            '@context' => 'https://schema.org',
            '@type' => 'BeautySalon',
            'name' => $siteName,
            'url' => $resolvedSiteBaseUrl,
            'telephone' => $schemaPhone,
            'image' => ($resolvedSiteBaseUrl !== '' ? $resolvedSiteBaseUrl : '') . '/assets/images/Paji.jpeg',
            'sameAs' => $schemaInstagramUrl !== '' ? [$schemaInstagramUrl] : [],
        ];

        if ($schemaAddress !== '') {
            $schemaData['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $schemaAddress,
                'postalCode' => '760 01',
                'addressCountry' => 'CZ',
            ];
        }

        return [
            'cssVersion' => (string) (@filemtime(__DIR__ . '/../../../assets/css/site.css') ?: time()),
            'jsVersion' => (string) (@filemtime(__DIR__ . '/../../../assets/js/site.js') ?: time()),
            'resolvedSiteBaseUrl' => $resolvedSiteBaseUrl,
            'canonicalUrl' => $canonicalUrl,
            'siteName' => $siteName,
            'ogImageUrl' => ($resolvedSiteBaseUrl !== '' ? $resolvedSiteBaseUrl : '') . '/assets/images/Paji.jpeg',
            'schemaData' => $schemaData,
            'footerYearLabel' => $this->buildFooterYearLabel(),
        ];
    }

    private function buildFooterYearLabel(): string
    {
        $startYear = 2025;
        $currentYear = (int) date('Y');

        return $currentYear > $startYear ? $startYear . '–' . $currentYear : (string) $startYear;
    }
}
