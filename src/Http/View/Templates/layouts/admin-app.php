<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="cs" class="admin-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \PPStudio\Support\ViewHelper::escape($resolvedPageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= \PPStudio\Support\ViewHelper::escape($adminCssVersion) ?>">
</head>
<body>
    <div class="admin-page">
        <?php include $sidebarTemplate; ?>

        <main class="admin-main">
            <div class="container admin-content">
                <?php include $introTemplate; ?>
                <?php include $sectionPath; ?>
            </div>
        </main>
    </div>
    <script src="/assets/js/main.js?v=<?= \PPStudio\Support\ViewHelper::escape($adminJsVersion) ?>"></script>
</body>
</html>
