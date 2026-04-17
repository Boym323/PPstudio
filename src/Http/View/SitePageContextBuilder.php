<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

final class SitePageContextBuilder
{
    private SiteContactContextBuilder $contactContextBuilder;

    public function __construct(?SiteContactContextBuilder $contactContextBuilder = null)
    {
        $this->contactContextBuilder = $contactContextBuilder ?? new SiteContactContextBuilder();
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function build(array $config): array
    {
        $title = (string) ($config['title'] ?? 'PP Studio');
        $description = (string) ($config['description'] ?? 'PP Studio - kosmetické studio');
        $activeNav = (string) ($config['active_nav'] ?? 'home');
        $template = (string) ($config['template'] ?? '');

        $reservationAlertHtml = $this->reservationAlertMarkupFromQuery();
        $csrfToken = (new \PPStudio\Security\SecurityFacade())->getCsrfToken();
        $siteSettings = \defaultSiteSettings();

        $connection = \PPStudio\Database\DatabaseFactory::tryConnect();
        if ($connection instanceof \mysqli) {
            $siteSettings = (new \PPStudio\Service\SiteSettingsService(new \PPStudio\Repository\SiteSettingsRepository($connection), defaultSiteSettings()))->load();
            $connection->close();
        }

        $fallbackSiteUrl = rtrim((string) \ppstudioEnv('PPSTUDIO_SITE_URL', ''), '/');
        $siteBaseUrl = rtrim((string) \PPStudio\Support\SettingsHelper::setting($siteSettings, 'site_url', $fallbackSiteUrl), '/');
        if ($siteBaseUrl === '') {
            $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
            if ($host !== '') {
                $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $siteBaseUrl = $scheme . '://' . $host;
            }
        }

        $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $requestPath = $requestPath !== '' ? $requestPath : '/';
        $canonicalUrl = $siteBaseUrl . ($requestPath === '/' ? '/' : $requestPath);

        $reservationAntispamToken = '';
        if ($activeNav === 'reservation') {
            $reservationAntispamToken = (new \PPStudio\Security\SecurityFacade())->reservationAntispamService()->issueToken();
        }

        return [
            'title' => $title,
            'description' => $description,
            'active_nav' => $activeNav,
            'template' => $template,
            'reservationAlertHtml' => $reservationAlertHtml,
            'csrfToken' => $csrfToken,
            'siteSettings' => $siteSettings,
            'siteBaseUrl' => $siteBaseUrl,
            'canonicalUrl' => $canonicalUrl,
            'reservationAntispamToken' => $reservationAntispamToken,
        ] + $this->contactContextBuilder->build($siteSettings);
    }

    private function reservationAlertMarkupFromQuery(): string
    {
        $reservationStatus = (string) ($_GET['reservation'] ?? '');
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
