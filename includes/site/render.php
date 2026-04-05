<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../site_lock.php';
require_once __DIR__ . '/../antispam.php';
require_once __DIR__ . '/../settings.php';

function trustedSingleIframeEmbed(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    if (! preg_match('/<iframe\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>\s*<\/iframe>/is', $html, $matches)) {
        return '';
    }

    $src = trim((string) ($matches[1] ?? ''));
    if ($src === '' || ! preg_match('#^https?://#i', $src)) {
        return '';
    }

    $safeSrc = escape($src);

    return '<iframe src="' . $safeSrc . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>';
}

function reservationAlertMarkupFromQuery(): string
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

    return '<div class="reservation-alert reservation-alert-' . escape($message['type']) . '">' . escape($message['text']) . '</div>';
}

function renderSitePage(array $config): never
{
    requirePublicSiteAccessOrPrompt();

    $title = (string) ($config['title'] ?? 'PP Studio');
    $description = (string) ($config['description'] ?? 'PP Studio - kosmetické studio');
    $activeNav = (string) ($config['active_nav'] ?? 'home');
    $template = (string) ($config['template'] ?? '');

    if ($template === '' || ! is_file($template)) {
        http_response_code(500);
        echo 'Template not found.';
        exit;
    }

    $reservationAlertHtml = reservationAlertMarkupFromQuery();
    $csrfToken = getCsrfToken();
    $siteSettings = DEFAULT_SETTINGS;

    $dbConfig = require __DIR__ . '/../../config/database.php';
    $connection = @new mysqli(
        $dbConfig['host'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['database']
    );

    if (! $connection->connect_errno) {
        $connection->set_charset($dbConfig['charset']);
        $siteSettings = loadSiteSettings($connection);
        $connection->close();
    }

    $reservationAntispamToken = '';
    if ($activeNav === 'reservation') {
        $reservationAntispamToken = reservationAntispamIssueToken();
    }

    header('Content-Type: text/html; charset=UTF-8');
    include __DIR__ . '/layout.php';
    exit;
}
