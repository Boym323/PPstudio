<!DOCTYPE html>
<html lang="cs" class="admin-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | <?= escape(setting($siteSettings, 'site_name', SITE_NAME)) ?></title>
    <?php
    $adminCssVersion = (string) (@filemtime(__DIR__ . '/../../../assets/css/admin.css') ?: time());
    $adminJsVersion = (string) (@filemtime(__DIR__ . '/../../../assets/js/main.js') ?: time());
    ?>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?= escape($adminCssVersion) ?>">
</head>
<body>
    <div class="admin-page">
        <?php include __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="admin-main">
            <div class="container admin-content">
                <?php include __DIR__ . '/../partials/intro.php'; ?>

                <?php
                $sectionByTab = [
                    'dashboard' => __DIR__ . '/../sections/dashboard.php',
                    'kalendar' => __DIR__ . '/../sections/calendar.php',
                    'emaily' => __DIR__ . '/../sections/email.php',
                    'antispam-log' => __DIR__ . '/../sections/antispam.php',
                    'dostupnost' => __DIR__ . '/../sections/availability.php',
                    'rezervace-list' => __DIR__ . '/../sections/reservations.php',
                    'sluzby-admin' => __DIR__ . '/../sections/services.php',
                    'media' => __DIR__ . '/../sections/media.php',
                    'recenze-napojeni' => __DIR__ . '/../sections/integrations.php',
                    'nastaveni' => __DIR__ . '/../sections/settings.php',
                ];
                $sectionPath = $sectionByTab[$adminTab] ?? $sectionByTab['dashboard'];
                include $sectionPath;
                ?>
            </div>
        </main>
    </div>
    <script src="assets/js/main.js?v=<?= escape($adminJsVersion) ?>"></script>
</body>
</html>
