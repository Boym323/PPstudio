<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \PPStudio\Support\ViewHelper::escape($page_title) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
    <?php if (($inline_styles ?? '') !== ''): ?>
        <style>
<?= $inline_styles ?>
        </style>
    <?php endif; ?>
</head>
<body>
    <?php require __DIR__ . '/../' . $content_template . '.php'; ?>
</body>
</html>
