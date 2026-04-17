<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \PPStudio\Support\ViewHelper::escape($page_title) ?></title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <div class="page-shell">
        <main class="admin-shell">
            <div class="container admin-login-wrap">
                <div class="admin-card">
                    <?php require __DIR__ . '/../' . $content_template . '.php'; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
