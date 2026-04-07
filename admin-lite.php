<?php
declare(strict_types=1);

require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/antispam.php';
require_once __DIR__ . '/includes/security_events.php';
require __DIR__ . '/includes/availability.php';
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/media.php';
require __DIR__ . '/includes/mailer.php';

startSecureSession();

$dbConfig = require __DIR__ . '/config/database.php';
$adminConfig = require __DIR__ . '/config/admin_lite.php';
$emailConfig = require __DIR__ . '/config/email.php';

function iniSizeToBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $number = (float) $value;
    $unit = strtolower(substr($value, -1));

    return match ($unit) {
        'g' => (int) ($number * 1024 * 1024 * 1024),
        'm' => (int) ($number * 1024 * 1024),
        'k' => (int) ($number * 1024),
        default => (int) $number,
    };
}

$uploadMaxFilesize = (string) ini_get('upload_max_filesize');
$postMaxSize = (string) ini_get('post_max_size');
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

$isAuthenticated = (bool) ($_SESSION['ppstudio_admin_lite_authenticated'] ?? false);
$loginError = '';
$error = '';
$loginIp = getClientIpAddress();
$loginUsernameInput = trim((string) ($_POST['username'] ?? ''));
$loginRateState = ppstudioLoginThrottleState('admin-lite', $loginIp, $loginUsernameInput);
$isLocked = (bool) ($loginRateState['locked'] ?? false);
$minutesLeft = (int) ($loginRateState['minutes_left'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! isValidCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
    if (isset($_POST['admin_login'])) {
        $loginError = 'Platnost přihlášení vypršela. Obnovte stránku a zkuste to znovu.';
    } else {
        $error = 'Platnost formuláře vypršela. Obnovte stránku a akci opakujte.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login']) && $loginError === '') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $storedUsername = (string) ($adminConfig['username'] ?? '');
    $storedHash = (string) ($adminConfig['password_hash'] ?? '');
    $legacyPassword = (string) ($adminConfig['password'] ?? '');
    $passwordMatches = $storedHash !== ''
        ? password_verify($password, $storedHash)
        : ($legacyPassword !== '' && hash_equals($legacyPassword, $password));

    if ($isLocked) {
        $loginError = 'Příliš mnoho neúspěšných pokusů. Zkuste to znovu za ' . $minutesLeft . ' min.';
        securityEventLog('admin_lite_login_locked', 'admin_lite_login', 'warning', [
            'username' => $username,
            'minutes_left' => $minutesLeft,
        ]);
    } elseif ($username === $storedUsername && $passwordMatches) {
        $_SESSION['ppstudio_admin_lite_authenticated'] = true;
        $_SESSION['ppstudio_admin_lite_username'] = $username;
        ppstudioLoginThrottleReset('admin-lite', $loginIp, $username);
        securityEventLog('admin_lite_login_success', 'admin_lite_login', 'info', [
            'username' => $username,
        ]);
        session_regenerate_id(true);
        header('Location: admin-lite.php');
        exit;
    }

    if ($loginError === '') {
        $failureState = ppstudioLoginThrottleRegisterFailure('admin-lite', $loginIp, $username);
        if ((bool) ($failureState['locked'] ?? false)) {
            $minutesToWait = (int) ($failureState['minutes_left'] ?? 15);
            $loginError = 'Příliš mnoho neúspěšných pokusů. Zkuste to znovu za 15 min.';
            if ($minutesToWait > 0) {
                $loginError = 'Příliš mnoho neúspěšných pokusů. Zkuste to znovu za ' . $minutesToWait . ' min.';
            }
            securityEventLog('admin_lite_login_locked', 'admin_lite_login', 'warning', [
                'username' => $username,
                'minutes_left' => $minutesToWait,
            ]);
        } else {
            $loginError = 'Neplatné přihlašovací údaje.';
            securityEventLog('admin_lite_login_failed', 'admin_lite_login', 'warning', [
                'username' => $username,
                'remaining' => (int) ($failureState['remaining'] ?? 0),
            ]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_logout']) && isValidCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
    unset($_SESSION['ppstudio_admin_lite_authenticated']);
    unset($_SESSION['ppstudio_admin_lite_username']);
    header('Location: admin-lite.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! isValidCsrfToken((string) ($_POST['_csrf'] ?? ''))) {
    $_POST = [];
}

if (! $isAuthenticated && isset($_SESSION['ppstudio_admin_lite_authenticated'])) {
    $isAuthenticated = true;
}

if (! $isAuthenticated) {
    include __DIR__ . '/includes/admin/templates/login_lite.php';
    exit;
}

$connection = @new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['database']
);

$message = '';
$error = $error ?? '';
$siteSettings = defaultSiteSettings();
$availabilityRows = [];
$reservationRows = [];
$serviceRows = [];
$serviceCategoryRows = [];
$servicePriceHistoryRows = [];
$dashboardStats = [
    'new_reservations' => 0,
    'upcoming_reservations' => 0,
    'availability_windows' => 0,
    'services_total' => 0,
    'today_reservations' => 0,
    'pending_reservations' => 0,
    'free_slots_today' => 0,
    'avg_ticket_30d' => 0,
    'active_reservations_30d' => 0,
    'active_reservations_prev_30d' => 0,
    'active_reservations_trend_pct' => 0,
];
$dashboardUpcomingReservations = [];
$dashboardTopServices = [];
$dashboardTopCategories = [];
$dashboardStatusBreakdown = [
    'nova' => 0,
    'potvrzena' => 0,
    'dokoncena' => 0,
    'zrusena' => 0,
];
$serviceForm = [
    'id' => 0,
    'nazev' => '',
    'kategorie_id' => '',
    'kategorie' => '',
    'kategorie_poradi' => '',
    'popis' => '',
    'cena' => '',
    'doba_trvani' => '',
];
$categoryForm = [
    'id' => 0,
    'nazev' => '',
    'poradi' => '',
];
$profileMedia = [];
$galleryMedia = [];
$certificateFiles = [];
$subscriptionCalendarUrl = '';
$manualReservationForm = [
    'jmeno' => '',
    'email' => '',
    'telefon' => '',
    'zdroj' => 'telefon',
    'sluzba_id' => '',
    'datum_cas' => '',
    'poznamka_klienta' => '',
];
$reservationSourceOptions = [
    'telefon' => 'Telefon',
    'instagram' => 'Instagram',
    'messenger' => 'Messenger',
    'osobne' => 'Osobně',
    'jine' => 'Jiné',
];
$plannerDays = [];
$plannerSlots = [];
$plannerInitialWindows = [];
$plannerBookedWindows = [];
$plannerDayMeta = [];
$plannerDayRange = 7;
$plannerWeekOffset = isset($_GET['planner_week']) ? (int) $_GET['planner_week'] : 0;
$plannerWeekLabel = '';
$mediaFeedback = '';
$mediaFeedbackType = '';
$reservationPerPageOptions = [25, 50];
$reservationStatusFilterOptions = ['all' => 'Všechny stavy'] + reservationStatusOptions();
$reservationPeriodFilterOptions = [
    'all' => 'Všechna období',
    'today' => 'Dnes',
    'week' => 'Tento týden',
    'month' => 'Tento měsíc',
];
$reservationFilters = [
    'q' => trim((string) ($_GET['reservation_q'] ?? '')),
    'status' => (string) ($_GET['reservation_status'] ?? 'all'),
    'period' => (string) ($_GET['reservation_period'] ?? 'all'),
    'per_page' => (int) ($_GET['reservation_per_page'] ?? 25),
    'page' => max(1, (int) ($_GET['reservation_page'] ?? 1)),
];
$reservationPagination = [
    'total' => 0,
    'total_pages' => 1,
];
$antispamRows = [];
$antispamLogStats = [
    'total' => 0,
    'shown' => 0,
    'file_exists' => false,
    'size_bytes' => 0,
];
$antispamReasonOptions = [
    'all' => 'Všechny důvody',
    'antispam_rate_limited' => 'Rate limit',
    'antispam_honeypot_filled' => 'Honeypot vyplněn',
    'antispam_missing_or_invalid_token' => 'Chybějící/neplatný token',
    'antispam_submitted_too_fast' => 'Odesláno příliš rychle',
    'antispam_token_expired' => 'Expirovaný token',
];
$antispamLimitOptions = [50, 100, 200, 500];
$antispamFilters = [
    'reason' => (string) ($_GET['antispam_reason'] ?? 'all'),
    'q' => trim((string) ($_GET['antispam_q'] ?? '')),
    'limit' => (int) ($_GET['antispam_limit'] ?? 100),
];
$allowedAdminTabs = [
    'dashboard',
    'dostupnost',
    'rezervace-list',
    'sluzby-admin',
];
$adminTab = trim((string) ($_GET['tab'] ?? 'dashboard'));
if (! in_array($adminTab, $allowedAdminTabs, true)) {
    $adminTab = 'dashboard';
}
$adminBasePath = 'admin-lite.php';
$studioSettingFields = [
    'site_name' => 'Název studia',
    'site_url' => 'URL webu',
    'contact_name' => 'Kontaktní osoba',
    'contact_phone' => 'Telefon',
    'contact_email' => 'Kontaktní e-mail',
    'contact_instagram_url' => 'Instagram URL',
    'contact_ico' => 'IČO',
    'contact_opening_hours' => 'Otevírací doba',
    'contact_address' => 'Adresa studia (pro e-maily)',
];

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $contentLength > 0
    && $_POST === []
    && $_FILES === []
    && $contentLength > iniSizeToBytes($postMaxSize)
) {
    $error = 'Odesílaný formulář je příliš velký pro server. Zmenšete prosím obrázek nebo navyšte limit post_max_size v PHP.';
}

if (! $connection->connect_errno) {
    $connection->set_charset($dbConfig['charset']);
    $siteSettings = loadSiteSettings($connection);
    $subscriptionCalendarUrl = buildSubscriptionCalendarUrl($emailConfig, $siteSettings);

    if (isset($_GET['edit_service'])) {
        $editServiceId = (int) $_GET['edit_service'];
        if ($editServiceId > 0) {
            $statement = $connection->prepare(
                'SELECT s.id, s.nazev, s.kategorie_id, c.nazev AS kategorie, c.poradi AS kategorie_poradi, s.popis, s.cena, s.doba_trvani
                 FROM sluzby s
                 LEFT JOIN kategorie c ON c.id = s.kategorie_id
                 WHERE s.id = ?
                 LIMIT 1'
            );
            if ($statement) {
                $statement->bind_param('i', $editServiceId);
                $statement->execute();
                $statement->bind_result($id, $nazev, $kategorieId, $kategorie, $kategoriePoradi, $popis, $cena, $dobaTrvani);
                if ($statement->fetch()) {
                    $serviceForm = [
                        'id' => (int) $id,
                        'nazev' => (string) $nazev,
                        'kategorie_id' => $kategorieId !== null ? (string) $kategorieId : '',
                        'kategorie' => (string) ($kategorie ?? ''),
                        'kategorie_poradi' => $kategoriePoradi !== null ? (string) $kategoriePoradi : '',
                        'popis' => (string) $popis,
                        'cena' => $cena !== null ? number_format((float) $cena, 0, '.', '') : '',
                        'doba_trvani' => $dobaTrvani !== null ? (string) $dobaTrvani : '',
                    ];
                }
                $statement->close();
            }
        }
    }

    if (isset($_GET['edit_category'])) {
        $editCategoryId = (int) $_GET['edit_category'];
        if ($editCategoryId > 0) {
            $statement = $connection->prepare('SELECT id, nazev, poradi FROM kategorie WHERE id = ? LIMIT 1');
            if ($statement) {
                $statement->bind_param('i', $editCategoryId);
                $statement->execute();
                $statement->bind_result($id, $nazev, $poradi);
                if ($statement->fetch()) {
                    $categoryForm = [
                        'id' => (int) $id,
                        'nazev' => (string) $nazev,
                        'poradi' => $poradi !== null ? (string) $poradi : '',
                    ];
                }
                $statement->close();
            }
        }
    }

    $liteDisallowedPostActionKeys = [
        'save_settings',
        'save_integrations',
        'save_email_settings',
        'save_media',
        'delete_media',
        'save_certificate_file',
        'delete_certificate_file',
    ];

    $liteBlockedActionRequested = false;
    foreach ($liteDisallowedPostActionKeys as $disallowedKey) {
        if (isset($_POST[$disallowedKey])) {
            $liteBlockedActionRequested = true;
            break;
        }
    }

    if ($liteBlockedActionRequested) {
        $error = 'Tato akce není v uživatelském rozhraní povolená.';
    } else {
        include __DIR__ . '/includes/admin/actions/post_actions.php';
    }
    include __DIR__ . '/includes/admin/actions/load_data.php';
} else {
    $error = 'Nepodařilo se připojit k databázi. Zkontrolujte `config/database.php`.';
}
include __DIR__ . '/includes/admin/templates/app_lite.php';
if (isset($connection) && $connection instanceof mysqli) {
    $connection->close();
}
