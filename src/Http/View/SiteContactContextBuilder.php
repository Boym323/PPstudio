<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

final class SiteContactContextBuilder
{
    /**
     * @param array<string, mixed> $siteSettings
     * @return array<string, mixed>
     */
    public function build(array $siteSettings): array
    {
        $contactName = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'contact_name', 'Pavlína Pomykalová');
        $contactPhone = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'contact_phone', '+420 732 856 036');
        $contactEmail = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'contact_email', 'pavlina@pomykal.cz');
        $contactInstagramUrl = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'contact_instagram_url', '');
        $contactIco = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'contact_ico', '234 275 66');
        $contactOpeningHours = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'contact_opening_hours', 'Po-Pá: Dle objednávek');
        $contactAddress = \PPStudio\Support\SettingsHelper::setting($siteSettings, 'contact_address', '');
        $contactPhoneHref = \PPStudio\Support\ContactHelper::contactPhoneHref($contactPhone);
        $contactEmailHref = \PPStudio\Support\ContactHelper::contactEmailHref($contactEmail);
        $contactInstagramHandle = \PPStudio\Support\ContactHelper::contactInstagramHandle($contactInstagramUrl);
        $contactInstagramDmUrl = \PPStudio\Support\ContactHelper::contactInstagramDmHref($contactInstagramUrl);

        $contactEmailUser = $contactEmail;
        $contactEmailDomain = '';
        if (str_contains($contactEmail, '@')) {
            [$contactEmailUser, $contactEmailDomain] = explode('@', $contactEmail, 2);
        }

        return [
            'contactName' => $contactName,
            'contactPhone' => $contactPhone,
            'contactEmail' => $contactEmail,
            'contactInstagramUrl' => $contactInstagramUrl,
            'contactIco' => $contactIco,
            'contactOpeningHours' => $contactOpeningHours,
            'contactAddress' => $contactAddress,
            'contactPhoneHref' => $contactPhoneHref,
            'contactEmailHref' => $contactEmailHref,
            'contactInstagramHandle' => $contactInstagramHandle,
            'contactInstagramDmUrl' => $contactInstagramDmUrl,
            'contactEmailUser' => $contactEmailUser,
            'contactEmailDomain' => $contactEmailDomain,
        ];
    }
}
