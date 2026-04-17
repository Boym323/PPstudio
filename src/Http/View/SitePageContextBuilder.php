<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

use PPStudio\Security\SecurityFacade;
use PPStudio\Service\SiteSettingsLoader;

final class SitePageContextBuilder
{
    private SiteContactContextBuilder $contactContextBuilder;
    private SecurityFacade $security;
    private SiteSettingsLoader $siteSettingsLoader;

    public function __construct(
        ?SiteContactContextBuilder $contactContextBuilder = null,
        ?SecurityFacade $security = null,
        ?SiteSettingsLoader $siteSettingsLoader = null
    )
    {
        $this->contactContextBuilder = $contactContextBuilder ?? new SiteContactContextBuilder();
        $this->security = $security ?? new SecurityFacade();
        $this->siteSettingsLoader = $siteSettingsLoader ?? new SiteSettingsLoader();
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function build(array $config, array $server = [], array $query = []): array
    {
        $title = (string) ($config['title'] ?? 'PP Studio');
        $description = (string) ($config['description'] ?? 'PP Studio - kosmetické studio');
        $activeNav = (string) ($config['active_nav'] ?? 'home');
        $template = (string) ($config['template'] ?? '');
        $reservationAlertHtml = $this->reservationAlertMarkupFromQuery($query);
        $csrfToken = $this->security->getCsrfToken();
        $siteSettings = $this->siteSettingsLoader->load();

        $fallbackSiteUrl = rtrim((string) \ppstudioEnv('PPSTUDIO_SITE_URL', ''), '/');
        $siteBaseUrl = rtrim((string) \PPStudio\Support\SettingsHelper::setting($siteSettings, 'site_url', $fallbackSiteUrl), '/');
        if ($siteBaseUrl === '') {
            $host = (string) ($server['HTTP_HOST'] ?? '');
            if ($host !== '') {
                $scheme = (! empty($server['HTTPS']) && $server['HTTPS'] !== 'off') ? 'https' : 'http';
                $siteBaseUrl = $scheme . '://' . $host;
            }
        }

        $requestPath = (string) parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $requestPath = $requestPath !== '' ? $requestPath : '/';
        $canonicalUrl = $siteBaseUrl . ($requestPath === '/' ? '/' : $requestPath);

        $reservationAntispamToken = '';
        if ($activeNav === 'reservation') {
            $reservationAntispamToken = $this->security->reservationAntispamService()->issueToken();
        }

        $context = [
            'title' => $title,
            'description' => $description,
            'active_nav' => $activeNav,
            'activeNav' => $activeNav,
            'template' => $template,
            'reservationAlertHtml' => $reservationAlertHtml,
            'csrfToken' => $csrfToken,
            'siteSettings' => $siteSettings,
            'siteBaseUrl' => $siteBaseUrl,
            'canonicalUrl' => $canonicalUrl,
            'reservationAntispamToken' => $reservationAntispamToken,
        ] + $this->contactContextBuilder->build($siteSettings);

        return match ($activeNav) {
            'about' => $context + (new SiteAboutPageContextBuilder())->build(),
            'reviews' => $context + (new SiteReviewsPageContextBuilder())->build($siteSettings),
            'services' => $context + (new SiteServicesPageContextBuilder())->build(),
            default => $context,
        };
    }

    /**
     * @param array<string, mixed> $query
     */
    private function reservationAlertMarkupFromQuery(array $query): string
    {
        $reservationStatus = (string) ($query['reservation'] ?? '');
        $reservationMessages = [
            'success' => ['type' => 'success', 'text' => 'Rezervace byla odeslána. Děkujeme, brzy se vám ozvu.'],
            'csrf' => ['type' => 'error', 'text' => 'Platnost formuláře vypršela. Obnovte stránku a odešlete rezervaci znovu.'],
            'missing' => ['type' => 'error', 'text' => 'Vyplňte prosím všechny povinné údaje rezervace.'],
            'email' => ['type' => 'error', 'text' => 'Zadaný e-mail není platný.'],
            'invalid_datetime' => ['type' => 'error', 'text' => 'Vybraný termín není platný. Zkuste prosím vybrat jiný.'],
            'slot' => ['type' => 'error', 'text' => 'Vybraný termín už není dostupný. Zvolte prosím jiný den nebo čas.'],
            'too_fast' => ['type' => 'error', 'text' => 'Formulář byl odeslán příliš rychle. Zkuste to prosím znovu.'],
            'spam' => ['type' => 'error', 'text' => 'Odeslání rezervace se nepodařilo ověřit. Obnovte stránku a zkuste to znovu.'],
            'rate_limit' => ['type' => 'error', 'text' => 'Odesíláte příliš mnoho požadavků za krátkou dobu. Počkejte chvíli a zkuste to znovu.'],
            'db' => ['type' => 'error', 'text' => 'Rezervační systém je dočasně nedostupný. Zkuste to za chvíli znovu.'],
            'insert' => ['type' => 'error', 'text' => 'Rezervaci se nepodařilo uložit. Zkuste to prosím znovu.'],
        ];

        if (! isset($reservationMessages[$reservationStatus])) {
            return '';
        }

        $message = $reservationMessages[$reservationStatus];

        return '<div class="reservation-alert reservation-alert-' . \PPStudio\Support\ViewHelper::escape($message['type']) . '">' . \PPStudio\Support\ViewHelper::escape($message['text']) . '</div>';
    }
}
